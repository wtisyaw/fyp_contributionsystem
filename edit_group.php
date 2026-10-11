<?php
session_start();
require_once "db.php";
if (!isset($_SESSION["user_id"])) {
    header("Location: index.php");
    exit;
}
$user_id = (int)$_SESSION["user_id"];
$group_id = isset($_GET["group_id"]) ? (int)$_GET["group_id"] : 0;
if ($group_id <= 0) {
    header("Location: student_groups.php");
    exit;
}
$error = "";
$sql = "SELECT
            sg.group_id,
            sg.group_name,
            sg.join_code,
            sg.num_members,
            sg.submission_deadline,
            sg.assignment_id,
            sg.created_by,
            a.title AS assignment_title,
            a.due_date,
            s.subject_code,
            s.subject_name
        FROM student_groups sg
        INNER JOIN assignments a
            ON sg.assignment_id = a.assignment_id
        INNER JOIN subject_sections sec
            ON a.subject_section_id = sec.subject_section_id
        INNER JOIN subjects s
            ON sec.subject_id = s.subject_id
        WHERE sg.group_id = ?
        LIMIT 1";
$stmt = $conn->prepare($sql);
$stmt->bind_param("i",$group_id);
$stmt->execute();
$result = $stmt->get_result();
if ($result->num_rows === 0) {
    header("Location: student_groups.php");
    exit;
}
$group = $result->fetch_assoc();
$stmt->close();
if ((int)$group["created_by"] !== $user_id) {
    header("Location: student_groups.php");
    exit;
}
if ($_SERVER["REQUEST_METHOD"] === "POST") {
    $group_name = trim($_POST["group_name"] ?? "");
    $num_members = isset($_POST["num_members"]) ? (int)$_POST["num_members"] : 0;
    $submission_deadline = trim($_POST["submission_deadline"] ?? "");
    if ($group_name === "") {
    $error = "Please enter group name.";
    } elseif ($num_members < 1) {
    $error = "Please enter valid number of members.";
    } elseif ($submission_deadline === "") {
    $error = "Please select a group submission deadline.";
    } elseif ($submission_deadline >= $group["due_date"]) {
    $error = "Group submission deadline must be before the assignment due date.";
    } else {
        $memberSql = "SELECT COUNT(*) AS total_members
                      FROM group_members
                      WHERE group_id=?";
        $memberStmt = $conn->prepare($memberSql);
        $memberStmt->bind_param("i",$group_id);
        $memberStmt->execute();
        $memberResult = $memberStmt->get_result();
        $memberData = $memberResult->fetch_assoc();
        $currentMembers = (int)$memberData["total_members"];
        $memberStmt->close();
        if ($num_members < $currentMembers) {
            $error = "Number of members cannot be less than the current group members.";
        } else {
            $updateSql = "UPDATE student_groups
              SET group_name = ?,
                  num_members = ?,
                  submission_deadline = ?
              WHERE group_id = ?
              AND created_by = ?";
              
            $updateStmt = $conn->prepare($updateSql);

$updateStmt->bind_param(
    "sisii",
    $group_name,
    $num_members,
    $submission_deadline,
    $group_id,
    $user_id
);

if ($updateStmt->execute()) {
    $updateStmt->close();

    echo "<script>window.parent.location.reload();</script>";
    exit;
} else {
    $error = "Unable to update group.";
}

$updateStmt->close();
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width,initial-scale=1.0">
<title>Edit Group | FairPeer</title>
<link rel="stylesheet" href="css/style.css">
<link rel="stylesheet" href="css/groups.css">
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
</head>
<body class="modal-edit-page">
<div class="create-group-page">
    <div class="create-group-box">
        <button type="button" class="create-group-close" onclick="closeEditModal()">
            <i class="fa-solid fa-xmark"></i>
        </button>
        <h2>Edit Group</h2>
        <p class="create-group-subtitle">Update your group information.</p>
        <?php if ($error !== ""): ?>
        <div class="join-group-error">
            <?php echo htmlspecialchars($error); ?>
        </div>
        <?php endif; ?>
        <form method="POST">
<div class="edit-group-grid">

    <div class="edit-readonly-item edit-subject">
        <span>Subject</span>
        <strong>
            <?php echo htmlspecialchars($group["subject_code"]." - ".$group["subject_name"]); ?>
        </strong>
    </div>

    <div class="form-group edit-group-name">
        <label>Group Name</label>
        <input
            type="text"
            name="group_name"
            value="<?php echo htmlspecialchars($group["group_name"]); ?>"
            required>
    </div>

    <div class="edit-readonly-item edit-assignment">
        <span>Assignment</span>
        <strong>
            <?php echo htmlspecialchars($group["assignment_title"]); ?>
        </strong>
    </div>

    <div class="form-group edit-members">
        <label>Number of Members</label>
        <input
            type="number"
            name="num_members"
            min="1"
            value="<?php echo (int)$group["num_members"]; ?>"
            required>
    </div>

    <div class="edit-readonly-item edit-due-date">
        <span>Official Due Date</span>
        <strong>
            <?php echo date("d M Y", strtotime($group["due_date"])); ?>
        </strong>
    </div>

    <div class="form-group edit-deadline">
        <label>Group Submission Deadline</label>

        <input
            type="date"
            name="submission_deadline"
            value="<?php echo htmlspecialchars($group["submission_deadline"] ?? ""); ?>"
            max="<?php echo date("Y-m-d", strtotime($group["due_date"] . " -1 day")); ?>"
            required>

        <small>
            Members should submit their work before the official assignment due date.
        </small>
    </div>
</div>
            <div class="group-code-preview">
                <label>Join Code</label>
                <div class="join-code-display">
                    <?php echo htmlspecialchars($group["join_code"]); ?>
                </div>
            </div>
            <div class="modal-button-group">
                <button type="submit" class="create-group-button">
                    <i class="fa-solid fa-floppy-disk"></i>
                    Save Changes
                </button>
            </div>
        </form>
    </div>
</div>
<script>
function closeEditModal(){
    window.parent.document.getElementById("editModal").classList.remove("show");
}
</script>
</body>
</html>