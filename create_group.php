<?php
session_start();
require_once "db.php";
if (!isset($_SESSION["user_id"])) {
    header("Location: index.php");
    exit;
}
if (!isset($_SESSION["user_role"]) || strtolower($_SESSION["user_role"]) !== "student") {
    header("Location: index.php?error=role");
    exit;
}
$user_id = (int)$_SESSION["user_id"];
$groupCreated = false;
$errorMessage = "";
$join_code = "";
$subjectQuery = "SELECT DISTINCT ss.subject_section_id,s.subject_code,s.subject_name
FROM student_subjects sts
INNER JOIN subject_sections ss ON sts.subject_section_id=ss.subject_section_id
INNER JOIN subjects s ON ss.subject_id=s.subject_id
WHERE sts.student_id=?
ORDER BY s.subject_code ASC";
$subjectStmt = $conn->prepare($subjectQuery);
$subjectStmt->bind_param("i",$user_id);
$subjectStmt->execute();
$subjectResult = $subjectStmt->get_result();
$availableSubjects = [];
while ($row = $subjectResult->fetch_assoc()) {
    $availableSubjects[] = $row;
}
$subjectStmt->close();
$assignmentQuery = "SELECT DISTINCT a.assignment_id,a.title,a.due_date,a.subject_section_id
FROM assignments a
INNER JOIN student_subjects sts ON a.subject_section_id=sts.subject_section_id
WHERE sts.student_id=?
AND NOT EXISTS (
    SELECT 1
    FROM student_groups sg
    INNER JOIN group_members gm ON sg.group_id=gm.group_id
    WHERE sg.assignment_id=a.assignment_id
    AND gm.user_id=?
)
ORDER BY a.due_date ASC";
$assignmentStmt = $conn->prepare($assignmentQuery);
$assignmentStmt->bind_param("ii",$user_id,$user_id);
$assignmentStmt->execute();
$assignmentResult = $assignmentStmt->get_result();
$availableAssignments = [];
while ($row = $assignmentResult->fetch_assoc()) {
    $availableAssignments[] = $row;
}
$assignmentStmt->close();
if ($_SERVER["REQUEST_METHOD"] === "POST") {
    $group_name = trim($_POST["group_name"] ?? "");
    $subject_section_id = isset($_POST["subject_section_id"]) ? (int)$_POST["subject_section_id"] : 0;
    $assignment_id = isset($_POST["assignment_id"]) ? (int)$_POST["assignment_id"] : 0;
    $num_members = isset($_POST["num_members"]) ? (int)$_POST["num_members"] : 0;
    if ($group_name === "" || $subject_section_id <= 0 || $assignment_id <= 0 || $num_members <= 0) {
        $errorMessage = "Please fill in all fields.";
    } else {
        $checkAssignment = $conn->prepare("SELECT a.assignment_id
        FROM assignments a
        INNER JOIN student_subjects ss ON a.subject_section_id=ss.subject_section_id
        WHERE a.assignment_id=?
        AND a.subject_section_id=?
        AND ss.student_id=?
        LIMIT 1");
        $checkAssignment->bind_param("iii",$assignment_id,$subject_section_id,$user_id);
        $checkAssignment->execute();
        $checkResult = $checkAssignment->get_result();
        if ($checkResult->num_rows === 0) {
            $errorMessage = "Invalid assignment selected.";
        } else {
            $checkGroup = $conn->prepare("SELECT sg.group_id
            FROM student_groups sg
            INNER JOIN group_members gm ON sg.group_id=gm.group_id
            WHERE sg.assignment_id=?
            AND gm.user_id=?
            LIMIT 1");
            $checkGroup->bind_param("ii",$assignment_id,$user_id);
            $checkGroup->execute();
            $existingGroupResult = $checkGroup->get_result();
            if ($existingGroupResult->num_rows > 0) {
                $errorMessage = "You already have a group for this assignment.";
            } else {
                $join_code = strtoupper(substr(md5(uniqid((string)mt_rand(),true)),0,6));
                $sql = "INSERT INTO student_groups (group_name,join_code,num_members,assignment_id,created_by) VALUES (?,?,?,?,?)";
                $stmt = $conn->prepare($sql);
                $stmt->bind_param("ssiii",$group_name,$join_code,$num_members,$assignment_id,$user_id);
                if ($stmt->execute()) {
                    $group_id = $conn->insert_id;
                    $member = $conn->prepare("INSERT INTO group_members (group_id,user_id) VALUES (?,?)");
                    $member->bind_param("ii",$group_id,$user_id);
                    if ($member->execute()) {
                        $groupCreated = true;
                    } else {
                        $errorMessage = "Group was created, but unable to add you as a member.";
                    }
                    $member->close();
                } else {
                    $errorMessage = "Unable to create group. Please try again.";
                }
                $stmt->close();
            }
            $checkGroup->close();
        }
        $checkAssignment->close();
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width,initial-scale=1.0">
<title>Create Group | FairPeer</title>
<link rel="stylesheet" href="css/style.css">
<link rel="stylesheet" href="css/groups.css">
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
</head>
<body class="modal-create-page">
<div class="create-group-page">
    <div class="create-group-box">
        <button type="button" class="create-group-close" onclick="window.parent.closeCreateModal()">
            <i class="fa-solid fa-xmark"></i>
        </button>
        <h2>Create Group</h2>
        <p class="create-group-subtitle">Create a group and invite your teammates using a join code.</p>
        <?php if ($errorMessage !== ""): ?>
        <div class="group-form-error">
            <i class="fa-solid fa-circle-exclamation"></i>
            <?php echo htmlspecialchars($errorMessage); ?>
        </div>
        <?php endif; ?>
        <?php if (!$groupCreated): ?>
        <form method="POST">
            <div class="form-group">
                <label>Subject</label>
                <?php if (count($availableSubjects) > 0): ?>
                <select name="subject_section_id" id="subjectSection" required>
                    <option value="" disabled <?php echo !isset($_POST["subject_section_id"]) ? "selected" : ""; ?>>Select a subject</option>
                    <?php foreach ($availableSubjects as $subject): ?>
                    <option value="<?php echo (int)$subject["subject_section_id"]; ?>" <?php echo isset($_POST["subject_section_id"]) && (int)$_POST["subject_section_id"] === (int)$subject["subject_section_id"] ? "selected" : ""; ?>>
                        <?php echo htmlspecialchars($subject["subject_code"]." - ".$subject["subject_name"]); ?>
                    </option>
                    <?php endforeach; ?>
                </select>
                <?php else: ?>
                <select disabled>
                    <option selected>No subjects available</option>
                </select>
                <?php endif; ?>
            </div>
            <div class="form-group">
                <label>Assignment</label>
                <?php if (count($availableAssignments) > 0): ?>
                <select name="assignment_id" id="assignmentSelect" required disabled>
                    <option value="" disabled <?php echo !isset($_POST["assignment_id"]) ? "selected" : ""; ?>>Select a subject first</option>
                    <?php foreach ($availableAssignments as $assignment): ?>
                    <option value="<?php echo (int)$assignment["assignment_id"]; ?>" data-subject="<?php echo (int)$assignment["subject_section_id"]; ?>" <?php echo isset($_POST["assignment_id"]) && (int)$_POST["assignment_id"] === (int)$assignment["assignment_id"] ? "selected" : ""; ?>>
                        <?php echo htmlspecialchars($assignment["title"]); ?>
                    </option>
                    <?php endforeach; ?>
                </select>
                <?php else: ?>
                <select disabled>
                    <option selected>No assignments available</option>
                </select>
                <?php endif; ?>
            </div>
            <div class="form-group">
                <label>Group Name</label>
                <input type="text" name="group_name" placeholder="Enter your group name" value="<?php echo htmlspecialchars($_POST["group_name"] ?? ""); ?>" required>
            </div>
            <div class="form-group">
                <label>Number of Members</label>
                <input type="number" name="num_members" min="1" placeholder="Enter number of members" value="<?php echo htmlspecialchars($_POST["num_members"] ?? ""); ?>" required>
            </div>
            <div class="modal-button-group">
                <button type="submit" class="create-group-button" <?php echo count($availableAssignments) === 0 ? "disabled" : ""; ?>>
                    <i class="fa-solid fa-plus"></i>
                    Create Group
                </button>
            </div>
        </form>
        <?php endif; ?>
    </div>
</div>
<?php if ($groupCreated): ?>
<div class="group-success-modal">
    <div class="group-success-box">
        <div class="group-success-icon">
            <i class="fa-solid fa-check"></i>
        </div>
        <h2>Group Created!</h2>
        <p>Your group has been created successfully.</p>
        <p class="join-code-label">Join Code</p>
        <div class="join-code-display"><?php echo htmlspecialchars($join_code); ?></div>
        <p class="join-code-note">Share this code with your group members so they can join your group.</p>
        <button type="button" class="group-success-button" onclick="continueToGroups()">Continue</button>
    </div>
</div>
<?php endif; ?>
<script>
function closeCreateModal() {
    window.parent.document.getElementById("createModal").classList.remove("show");
}
function continueToGroups() {
    window.parent.location.href="student_groups.php";
}
const subjectSelect=document.getElementById("subjectSection");
const assignmentSelect=document.getElementById("assignmentSelect");
if(subjectSelect&&assignmentSelect){
    function filterAssignments(){
        const subjectId=subjectSelect.value;
        const options=assignmentSelect.querySelectorAll("option[data-subject]");
        let selectedStillVisible=false;
        options.forEach(function(option){
            const visible=option.dataset.subject===subjectId;
            option.hidden=!visible;
            option.disabled=!visible;
            if(visible&&option.selected){
                selectedStillVisible=true;
            }
        });
        assignmentSelect.disabled=subjectId==="";
        if(!selectedStillVisible){
            assignmentSelect.value="";
        }
        assignmentSelect.options[0].textContent=subjectId===""?"Select a subject first":"Select an assignment";
    }
    subjectSelect.addEventListener("change",filterAssignments);
    filterAssignments();
}
</script>
</body>
</html>