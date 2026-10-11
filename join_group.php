<?php

session_start();

require_once "db.php";

if (!isset($_SESSION["user_id"])) {
    header("Location: index.php");
    exit;
}

$user_id = $_SESSION["user_id"];

$error = "";
$success = false;


if ($_SERVER["REQUEST_METHOD"] === "POST") {
    $join_code = strtoupper(trim($_POST["join_code"] ?? ""));

    if ($join_code === "") {
        $error = "Please enter a join code.";

    } else {
        $groupSql = "SELECT
                        sg.group_id,
                        sg.group_name,
                        sg.num_members,
                        (
                            SELECT COUNT(*)
                            FROM group_members gm
                            WHERE gm.group_id = sg.group_id
                        ) AS current_members
                     FROM student_groups sg
                     WHERE sg.join_code = ?";
        $groupStmt = $conn->prepare($groupSql);
        $groupStmt->bind_param(
            "s",
            $join_code
        );
        $groupStmt->execute();
        $groupResult = $groupStmt->get_result();

        if ($groupResult->num_rows === 0) {
            $error = "Invalid join code.";

        } else {
            $group = $groupResult->fetch_assoc();
            $group_id = $group["group_id"];
            $current_members = $group["current_members"];
            $max_members = $group["num_members"];

            $checkMemberSql = "SELECT *
                               FROM group_members
                               WHERE group_id = ?
                               AND user_id = ?";

            $checkMemberStmt = $conn->prepare(
                $checkMemberSql
            );
            $checkMemberStmt->bind_param(
                "ii",
                $group_id,
                $user_id
            );
            $checkMemberStmt->execute();
            $checkMemberResult =
                $checkMemberStmt->get_result();

            if ($checkMemberResult->num_rows > 0) {
                $error = "You are already a member of this group.";
            }

            elseif ($current_members >= $max_members) {
                $error = "This group is already full.";
            }

            else {
                $joinSql = "INSERT INTO group_members
                            (group_id, user_id)
                            VALUES (?, ?)";
                $joinStmt = $conn->prepare(
                    $joinSql
                );
                $joinStmt->bind_param(
                    "ii",
                    $group_id,
                    $user_id
                );

                if ($joinStmt->execute()) {
                    $success = true;

                } else {
                    $error = "Unable to join the group. Please try again.";
                }
            }
        }
    }
}

?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Join Group | FairPeer</title>
    <link rel="stylesheet" href="css/style.css">
    <link rel="stylesheet" href="css/groups.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
</head>

<body class="modal-join-page">
<div class="create-group-page">
<?php if (!$success): ?>
    <div class="create-group-box">
        <button type="button"
        class="join-group-close"
        onclick="window.parent.closeJoinModal()">
        <i class="fa-solid fa-xmark"></i>
        </button>
        <h2>Join Group</h2>
        <p class="create-group-subtitle">
            Enter the join code shared by your group leader.
        </p>

        <?php if ($error !== ""): ?>
            <div class="join-group-error">
                <?php echo htmlspecialchars($error); ?>
            </div>
        <?php endif; ?>

        <form method="POST">
            <div class="form-group">
                <label>Join Code</label>
                <input
                    type="text"
                    name="join_code"
                    placeholder="Enter join code"
                    maxlength="10"
                    required>
            </div>

            
        <div class="modal-button-group">
            <button
            type="submit" 
            class="confirm-delete-button create-confirm-button">
                <i class="fa-solid fa-right-to-bracket"></i>
                Join Group
        </button>
    </div>
    </div>
    </form>
</div>
<?php endif; ?>

<?php if ($success): ?>
<div class="group-success-modal">
    <div class="group-success-box">
        <div class="group-success-icon">
            <i class="fa-solid fa-check"></i>
        </div>
        <h2>Successfully Joined!</h2>
        <p>
            You have successfully joined
            <strong>
                <?php echo htmlspecialchars($group["group_name"]); ?>
            </strong>.
        </p>

        <button
    type="button"
    class="group-success-button"
    onclick="continueToGroups()">
    Continue
</button>
    </div>
</div>
<?php endif; ?>

<script>
function closeJoinModal() {
    window.parent.document
        .getElementById("joinModal")
        .classList.remove("show");
}

function continueToGroups() {
    window.parent.location.href = "student_groups.php";
}
</script>

</body>
</html>