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

$user_id = $_SESSION["user_id"];

$taskSuccess = "";
$taskError = "";

$finalSuccess = "";
$finalError = "";

if ($_SERVER["REQUEST_METHOD"] === "POST" && isset($_POST["submit_final_assignment"])) {
    $final_group_id = (int)($_POST["group_id"] ?? 0);

    $finalGroupQuery = "SELECT group_id, assignment_id
                        FROM student_groups
                        WHERE group_id = ?
                        AND created_by = ?
                        LIMIT 1";

    $finalGroupStmt = $conn->prepare($finalGroupQuery);
    $finalGroupStmt->bind_param("ii", $final_group_id, $user_id);
    $finalGroupStmt->execute();
    $finalGroupResult = $finalGroupStmt->get_result();
    $finalGroup = $finalGroupResult->fetch_assoc();
    $finalGroupStmt->close();

    if (!$finalGroup) {
        $finalError = "Only the team leader can submit the final assignment.";
    } elseif (!isset($_FILES["final_file"]) || $_FILES["final_file"]["error"] !== UPLOAD_ERR_OK) {
        $finalError = "Please select a file.";
    } else {
        $file = $_FILES["final_file"];
        $file_name = basename($file["name"]);
        $file_extension = strtolower(pathinfo($file_name, PATHINFO_EXTENSION));
        $allowed_extensions = ["pdf", "doc", "docx"];

        if (!in_array($file_extension, $allowed_extensions)) {
            $finalError = "Only PDF, DOC and DOCX files are allowed.";
        } else {
            $upload_directory = "uploads/final_submissions/";

            if (!is_dir($upload_directory)) {
                mkdir($upload_directory, 0777, true);
            }

            $stored_name = time() . "_" . $final_group_id . "_" . $file_name;
            $file_path = $upload_directory . $stored_name;

            if (move_uploaded_file($file["tmp_name"], $file_path)) {
                $assignment_id = (int)$finalGroup["assignment_id"];

                $existingQuery = "SELECT final_submission_id
                                  FROM final_submissions
                                  WHERE group_id = ?
                                  AND assignment_id = ?
                                  LIMIT 1";

                $existingStmt = $conn->prepare($existingQuery);
                $existingStmt->bind_param("ii", $final_group_id, $assignment_id);
                $existingStmt->execute();
                $existingResult = $existingStmt->get_result();
                $existingSubmission = $existingResult->fetch_assoc();
                $existingStmt->close();

                if ($existingSubmission) {
                    $updateFinalQuery = "UPDATE final_submissions
                                         SET submitted_by = ?,
                                             file_name = ?,
                                             file_path = ?,
                                             submitted_at = NOW()
                                         WHERE final_submission_id = ?";

                    $updateFinalStmt = $conn->prepare($updateFinalQuery);
                    $updateFinalStmt->bind_param(
                        "issi",
                        $user_id,
                        $file_name,
                        $file_path,
                        $existingSubmission["final_submission_id"]
                    );
                    $updateFinalStmt->execute();
                    $updateFinalStmt->close();
                } else {
                    $insertFinalQuery = "INSERT INTO final_submissions
                                         (group_id, assignment_id, submitted_by, file_name, file_path)
                                         VALUES (?, ?, ?, ?, ?)";

                    $insertFinalStmt = $conn->prepare($insertFinalQuery);
                    $insertFinalStmt->bind_param(
                        "iiiss",
                        $final_group_id,
                        $assignment_id,
                        $user_id,
                        $file_name,
                        $file_path
                    );
                    $insertFinalStmt->execute();
                    $insertFinalStmt->close();
                }

                header("Location: student_groups.php?final=success");
                exit;
            } else {
                $finalError = "Unable to upload the file.";
            }
        }
    }
}

if (
    $_SERVER["REQUEST_METHOD"] === "POST" &&
    isset($_POST["assign_task"])
) {
    $task_group_id = (int) ($_POST["group_id"] ?? 0);
    $assigned_to = (int) ($_POST["assigned_to"] ?? 0);
    $task_title = trim($_POST["task_title"] ?? "");
    $task_description = trim($_POST["task_description"] ?? "");

    if (
        $task_group_id === 0 ||
        $assigned_to === 0 ||
        $task_title === ""
    ) {
        $taskError = "Please fill in all required fields.";
    } else {

        /* Check logged-in student is the group leader */
        $leaderQuery = "
            SELECT assignment_id
            FROM student_groups
            WHERE group_id = ?
            AND created_by = ?
            LIMIT 1
        ";

        $leaderStmt = $conn->prepare($leaderQuery);
        $leaderStmt->bind_param(
            "ii",
            $task_group_id,
            $user_id
        );
        $leaderStmt->execute();

        $leaderResult = $leaderStmt->get_result();
        $leaderGroup = $leaderResult->fetch_assoc();

        $leaderStmt->close();

        if (!$leaderGroup) {
            $taskError = "Only the group leader can assign tasks.";

        } else {

            /* Check selected student belongs to the group */
            $memberCheckQuery = "
                SELECT user_id
                FROM group_members
                WHERE group_id = ?
                AND user_id = ?
                LIMIT 1
            ";

            $memberCheckStmt = $conn->prepare($memberCheckQuery);

            $memberCheckStmt->bind_param(
                "ii",
                $task_group_id,
                $assigned_to
            );

            $memberCheckStmt->execute();

            $memberCheckResult =
                $memberCheckStmt->get_result();

            $validMember =
                $memberCheckResult->fetch_assoc();

            $memberCheckStmt->close();

            if (!$validMember) {
                $taskError = "Selected student is not a member of this group.";

            } else {

                $assignment_id =
                    (int) $leaderGroup["assignment_id"];

                $insertTaskQuery = "
                    INSERT INTO group_tasks
                    (
                        group_id,
                        assignment_id,
                        assigned_to,
                        task_title,
                        task_description,
                        status
                    )
                    VALUES (?, ?, ?, ?, ?, 'Pending')
                ";

                $insertTaskStmt =
                    $conn->prepare($insertTaskQuery);

                $insertTaskStmt->bind_param(
                    "iiiss",
                    $task_group_id,
                    $assignment_id,
                    $assigned_to,
                    $task_title,
                    $task_description
                );

                if ($insertTaskStmt->execute()) {

                    $insertTaskStmt->close();

                    header(
                        "Location: student_groups.php?task=success"
                    );
                    exit;

                } else {
                    $taskError =
                        "Unable to assign task. Please try again.";
                }

                $insertTaskStmt->close();
            }
        }
    }
}

$profileQuery = "SELECT
                    user_code,
                    full_name,
                    nickname,
                    email,
                    user_role,
                    programme_code,
                    semester,
                    section
                 FROM Users
                 WHERE user_id = ?";

$profileStmt = $conn->prepare($profileQuery);
$profileStmt->bind_param("i", $user_id);
$profileStmt->execute();

$profileResult = $profileStmt->get_result();
$profileUser = $profileResult->fetch_assoc();

$profileStmt->close();

$displayName = !empty($profileUser["nickname"])
    ? $profileUser["nickname"]
    : $profileUser["full_name"];

$profileInitial = strtoupper(substr($displayName, 0, 1));

$selected_group_id = isset($_GET["group_id"])
    ? (int) $_GET["group_id"]
    : 0;

$selected_assignment_id = isset($_GET["assignment_id"])
    ? (int) $_GET["assignment_id"]
    : 0;

$groupQuery = "SELECT
                sg.group_id,
                sg.group_name,
                sg.join_code,
                sg.num_members,
                sg.created_by,
                sg.submission_deadline,
                a.assignment_id,
                a.title AS assignment_title,
                a.description AS assignment_description,
                a.due_date,
                s.subject_code,
                s.subject_name
                FROM student_groups sg
                INNER JOIN group_members gm
                ON sg.group_id = gm.group_id
                LEFT JOIN assignments a
                ON sg.assignment_id = a.assignment_id
                LEFT JOIN subject_sections ss
                ON a.subject_section_id = ss.subject_section_id
                LEFT JOIN subjects s
                ON ss.subject_id = s.subject_id
                WHERE gm.user_id = ? ";

if ($selected_group_id > 0) {
    $groupQuery .= " AND sg.group_id = ?";
}

$groupQuery .= " ORDER BY sg.group_id ASC";

$stmt = $conn->prepare($groupQuery);

if ($selected_group_id > 0) {

    $stmt->bind_param("ii", $user_id, $selected_group_id);

} else {
    $stmt->bind_param("i", $user_id);
}

$stmt->execute();
$groupsResult = $stmt->get_result();
$groups = [];

while ($group = $groupsResult->fetch_assoc()) {
    $groups[] = $group;
}

$stmt->close();

foreach ($groups as &$group) {

    $memberQuery = "SELECT
                    u.user_id,
                    u.full_name,
                    u.email
                    FROM group_members gm
                    INNER JOIN users u
                    ON gm.user_id = u.user_id
                    WHERE gm.group_id = ?
                    ORDER BY
                        CASE
                            WHEN u.user_id = ? THEN 0
                            ELSE 1
                        END,
                        u.full_name ASC";

    $memberStmt = $conn->prepare($memberQuery);

    $memberStmt->bind_param(
        "ii",
        $group["group_id"],
        $group["created_by"]
    );

    $memberStmt->execute();
    $membersResult = $memberStmt->get_result();

    $group["members"] = [];

    while ($member = $membersResult->fetch_assoc()) {
        $group["members"][] = $member;
    }

    $memberStmt->close();
}

unset($group);

?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>My Groups | FairPeer</title>
    <link rel="stylesheet" href="css/style.css">
    <link rel="stylesheet" href="css/dashboard.css">
    <link rel="stylesheet" href="css/groups.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
</head>

<body class="dashboard-body">
    <aside class="sidebar">
    <div class="sidebar-top">
        <div class="sidebar-logo">
            <div class="logo-icon">
                <i class="fa-solid fa-users"></i>
            </div>
            <div class="logo-text">
                <h2>FairPeer</h2>
            </div>
        </div>

        <button class="sidebar-toggle" id="sidebarToggle" type="button">
            <i class="fa-solid fa-bars"></i>
        </button>
    </div>

     <nav class="sidebar-menu">
        <a href="student_dashboard.php" class="menu-item">
            <i class="fa-solid fa-house"></i>
            <span>Dashboard</span>
        </a>

        <a href="student_subjects.php" class="menu-item">
            <i class="fa-solid fa-book-open"></i>
            <span>My Subjects</span>
        </a>
        
        <a href="student_assignments.php" class="menu-item">
            <i class="fa-solid fa-book"></i>
            <span>My Assignments</span>
        </a>
        
         <a href="student_groups.php" class="menu-item active">
            <i class="fa-solid fa-users"></i>
            <span>My Groups</span>
        </a>
        
        <a href="student_contribution.php" class="menu-item">
            <i class="fa-solid fa-chart-pie"></i>
            <span>My Contribution</span>
        </a>

        <a href="student_feedback.php" class="menu-item">
            <i class="fa-solid fa-comment"></i>
            <span>Feedback</span>
        </a>
        <a href="student_upload.php" class="menu-item">
                <i class="fa-solid fa-upload"></i>
                <span>Upload File</span>
        </a>
    </nav>
    
    <div class="sidebar-bottom">
         <a href="logout.php" class="menu-item logout">
            <i class="fa-solid fa-right-from-bracket"></i>
            <span>Logout</span>
        </a>
    </div>
</aside>

<main class="dashboard-main">
     <header class="dashboard-header">
        <div class="welcome-area">
            <h1>My Groups</h1>
            <p>View your groups and the members you work with.</p>
        </div>
        
        <button type="button" class="header-profile" id="openProfileModal">
    <div class="profile-avatar">
        <?php echo htmlspecialchars($profileInitial); ?>
    </div>

    <div class="profile-info">
        <strong><?php echo htmlspecialchars($displayName); ?></strong>
        <span>Student</span>
    </div>
</button>
    </header>

<?php if (isset($_GET["final"]) && $_GET["final"] === "success"): ?>
    <div class="final-submission-success" id="finalSubmissionSuccess">
        <i class="fa-solid fa-circle-check"></i>
        Final assignment submitted successfully.
    </div>
<?php endif; ?>

      <section class="student-assignments-page student-groups-page">
        <div class="page-section-header">
            <div>
                <p class="block-label">YOUR TEAMS</p>
                <h2 class="my-groups-title">My Groups</h2>
            </div>

            <div class="group-action">
                <button 
                type="button"
                class="create-new-group-button"
                onclick="openCreateModal()">
                <i class="fa-solid fa-plus"></i>
                    Create Group
                </button>
                <button
                type="button"
                class="join-new-group-button"
                onclick="openJoinModal()">
                <i class="fa-solid fa-right-to-bracket"></i>
                    Join Group
                </button>
            </div>
        </div>

        <?php if (count($groups) > 0): ?>
            <div class="student-groups-list">
                <?php foreach ($groups as $group): ?>
                    <div class="student-group-card">
                        <div class="group-card-title">
                            <div class="group-icon">
                                <i class="fa-solid fa-users"></i>
                            </div>
                            
                            <div class="group-info">
                                <div class="group-name-row">
                                <div class="group-name-display">
                                    <span>Group:</span>
                                    <h3><?php echo htmlspecialchars($group["group_name"]); ?></h3>
                                </div>
                            <?php if ((int)$group["created_by"] === (int)$user_id): ?>
                                <button type="button" class="group-edit-button" onclick="openEditModal(<?php echo (int)$group["group_id"]; ?>)">
                                <i class="fa-solid fa-pen"></i>
                                </button>
                            <?php endif; ?>
                            </div>
                                
                               <?php if (!empty($group["subject_code"])): ?>
                                    <p class="group-subject">
                                    <?php echo htmlspecialchars($group["subject_code"]." - ".$group["subject_name"]); ?>
                                    </p>
                                <?php endif; ?>
                                <?php if (!empty($group["assignment_title"])): ?>
                                    <p class="group-assignment">
                                    <span>Assignment:</span>
                                    <strong><?php echo htmlspecialchars($group["assignment_title"]); ?></strong>
                                    </p>
                                <?php endif; ?>
                                
                                <div class="group-code">
                                    <span>Join Code:</span>
                                    <strong><?php echo htmlspecialchars($group["join_code"]); ?></strong>
                                </div>
                            </div>
                        </div>
                        
    <div class="group-deadline">
    <?php if (!empty($group["submission_deadline"])): ?>
        <div class="group-deadline-item">
            <span>Group Deadline</span>
            <strong>
                <?php echo date("d M Y", strtotime($group["submission_deadline"])); ?>
            </strong>
        </div>
    <?php endif; ?>

    <div class="group-deadline-item">
        <span>Assignment Due</span>
        <strong>
            <?php echo date("d M Y", strtotime($group["due_date"])); ?>
        </strong>
    </div>
</div>
                
<div class="group-members-section">
    <div class="group-members-title">
        <h4>
            <i class="fa-solid fa-user-group"></i>
            Group Members
        </h4>
        <span>
            <?php echo count($group["members"]); ?>
            /
            <?php echo $group["num_members"]; ?>
            Members
        </span>
    </div>

<div class="group-members-list">
    <?php foreach ($group["members"] as $member): ?>
        <div class="group-member">
            <span class="member-point"></span>

            <strong>
                <?php echo htmlspecialchars($member["full_name"]); ?>

                <?php if ($member["user_id"] == $group["created_by"]): ?>
                    (Team Leader)
                <?php endif; ?>
            </strong>
        </div>
    <?php endforeach; ?>
</div>

    <?php if ((int)$group["created_by"] === (int)$user_id): ?>
        <div class="group-card-actions">

        <button
    type="button"
    class="final-submission-button"
    title="Final Submission"
    onclick="openFinalSubmissionModal(
        <?php echo (int)$group["group_id"]; ?>,
        <?php echo htmlspecialchars(json_encode($group["assignment_title"]), ENT_QUOTES, "UTF-8"); ?>
    )">
    <i class="fa-solid fa-file-arrow-up"></i>
</button>

            <button type="button" class="assign-task-button" title="Assign Task" onclick='openAssignTaskModal(<?php echo (int)$group["group_id"]; ?>,<?php echo json_encode($group["members"]); ?>)'>
                <i class="fa-solid fa-list-check"></i>
            </button>
            <button type="button" class="delete-group-button" title="Delete Group" onclick="openDeleteModal(<?php echo (int)$group['group_id']; ?>)">
                <i class="fa-solid fa-trash"></i>
            </button>
        </div>
    <?php endif; ?>
</div>
</div>
    
<?php endforeach; ?>
</div>

    <div class="delete-modal" id="deleteModal">
        <div class="delete-box">
            <div class="delete-modal-icon">
                <i class="fa-solid fa-trash"></i>
            </div>
            <h2>Delete Group?</h2>
            <p>Are you sure you want to delete this group?</p>
            <p class="delete-warning">
                This action cannot be undone.
            </p>
            <form
                action="delete_group.php"
                method="POST"
                id="deleteGroupForm">
                <input
                    type="hidden"
                    name="group_id"
                    id="deleteGroupId">
                    
                <div class="delete-modal-actions">
                    <button
                        type="button"
                        class="cancel-delete-button"
                        onclick="closeDeleteModal()">
                        Cancel
                    </button>

                    <button
                        type="submit"
                        class="confirm-delete-button">
                        Delete Group
                    </button>
                </div>
            </form>
        </div>
    </div>

<?php else: ?>
    <div class="groups-empty-state">
        <i class="fa-solid fa-users-slash"></i>
        <h2>No groups found</h2>
        <p>You haven't been assigned to any group yet.</p>
    </div>
<?php endif; ?>
    </section>

    <?php if (isset($_GET["password"])): ?>
    <?php if ($_GET["password"] === "success"): ?>
        <div class="profile-update-message success">
            <i class="fa-solid fa-circle-check"></i>
            Password changed successfully.
        </div>

    <?php elseif ($_GET["password"] === "current"): ?>
        <div class="profile-update-message error">
            <i class="fa-solid fa-circle-exclamation"></i>
            Your current password is incorrect.
        </div>

    <?php elseif ($_GET["password"] === "invalid"): ?>
        <div class="profile-update-message error">
            <i class="fa-solid fa-circle-exclamation"></i>
            Password must be at least 8 characters and include an uppercase letter, a number and a special character.
        </div>

    <?php elseif ($_GET["password"] === "match"): ?>
        <div class="profile-update-message error">
            <i class="fa-solid fa-circle-exclamation"></i>
            New password and confirm password do not match.
        </div>

    <?php elseif ($_GET["password"] === "same"): ?>
        <div class="profile-update-message error">
            <i class="fa-solid fa-circle-exclamation"></i>
            New password cannot be the same as your current password.
        </div>

    <?php else: ?>
        <div class="profile-update-message error">
            <i class="fa-solid fa-circle-exclamation"></i>
            Unable to change your password. Please try again.
        </div>
    <?php endif; ?>
<?php endif; ?>


</main>

<div class="assign-task-modal" id="assignTaskModal">

    <div class="assign-task-modal-box">

        <button
            type="button"
            class="assign-task-modal-close"
            onclick="closeAssignTaskModal()">

            <i class="fa-solid fa-xmark"></i>

        </button>

        <div class="assign-task-modal-header">
            <h2>Assign Task</h2>

            <p>
                Assign a task to one of your group members.
            </p>
        </div>

        <form method="POST">

            <input
                type="hidden"
                name="group_id"
                id="assignTaskGroupId">

            <div class="form-group">
                <label>Task Title</label>

                <input
                    type="text"
                    name="task_title"
                    placeholder="Enter task title"
                    required>
            </div>

            <div class="form-group">
                <label>Assign To</label>

                <select
                    name="assigned_to"
                    id="assignTaskMember"
                    required>

                    <option value="" disabled selected>
                        Select group member
                    </option>

                </select>
            </div>

            <div class="form-group">
                <label>Task Description</label>

                <textarea
                    name="task_description"
                    placeholder="Enter task description"></textarea>
            </div>

            <button
                type="submit"
                name="assign_task"
                class="assign-task-submit-button">

                <i class="fa-solid fa-check"></i>
                Assign Task

            </button>

        </form>

    </div>

</div>

<div class="final-submission-modal" id="finalSubmissionModal">
    <div class="final-submission-modal-box">
        <button
            type="button"
            class="final-submission-modal-close"
            onclick="closeFinalSubmissionModal()">
            <i class="fa-solid fa-xmark"></i>
        </button>

        <div class="final-submission-modal-header">
            <h2>Final Assignment Submission</h2>
            <p>Submit the completed assignment for your group.</p>
        </div>

        <div class="final-submission-assignment">
            <span>Assignment</span>
            <strong id="finalSubmissionAssignment"></strong>
        </div>

        <form method="POST" enctype="multipart/form-data">
            <input
                type="hidden"
                name="group_id"
                id="finalSubmissionGroupId">

            <div class="form-group">
                <label>Final Assignment File</label>
                <input
                    type="file"
                    name="final_file"
                    accept=".pdf,.doc,.docx"
                    required>
            </div>

            <button
                type="submit"
                name="submit_final_assignment"
                class="final-submission-submit-button">
                <i class="fa-solid fa-upload"></i>
                Submit Final Assignment
            </button>
        </form>
    </div>
</div>

<div class="profile-modal-overlay" id="profileModal">
    <div class="profile-modal">
        <div class="profile-modal-header">
            <div>
                <h2>My Profile</h2>
                <p>Manage your personal and academic information.</p>
            </div>
            <button
                type="button"
                class="profile-modal-close"
                id="closeProfileModal">
                <i class="fa-solid fa-xmark"></i>
            </button>
        </div>

        <div class="profile-modal-summary">
            <div class="profile-modal-avatar">
                <?php echo strtoupper(substr($profileUser["nickname"], 0, 1));
                ?>
            </div>
            <div>
                <h3>
                    <?php echo htmlspecialchars($profileUser["nickname"]);
                    ?>
                </h3>
                <p>Student</p>
            </div>
        </div>

        <div class="profile-modal-divider"></div>
        <form
            action="update_profile.php"
            method="POST"
            class="profile-modal-form">

            <div class="profile-modal-grid">
                <div class="profile-modal-field">
                    <label>Full Name</label>
                    <input
                        type="text"
                        name="full_name"
                        value="<?php
                        echo htmlspecialchars($profileUser["full_name"]); ?>"
                        required>
                </div>

                <div class="profile-modal-field">
                    <label>Nickname</label>
                    <input
                        type="text"
                        name="nickname"
                        value="<?php
                        echo htmlspecialchars($profileUser["nickname"]); ?>"
                        required>
                </div>

                <div class="profile-modal-field">
                    <label>Student ID</label>
                    <input
                        type="text"
                        value="<?php
                        echo htmlspecialchars($profileUser["user_code"]); ?>"
                        disabled>
                    <small>Student ID cannot be changed.</small>
                </div>

                <div class="profile-modal-field">
                    <label>Email Address</label>
                    <input
                        type="email"
                        name="email"
                        value="<?php
                        echo htmlspecialchars($profileUser["email"]); ?>"
                        required>
                </div>

                <div class="profile-modal-field">
                    <label>Programme</label>
                    <input
                        type="text"
                        value="<?php
                        echo htmlspecialchars($profileUser["programme_code"]) ?>"
                        disabled>
                    <small>Programme cannot be changed.</small>
                </div>

                <div class="profile-modal-field">
                    <label>Semester</label>
                    <select name="semester" required>
                        <?php for ($i = 1; $i <= 7; $i++): ?>
                            <option
                                value="<?php echo $i; ?>"
                                <?php
                                if (
                                    (string)$profileUser["semester"] === (string)$i
                                ) {
                                    echo "selected";
                                }
                                ?>>
                                Semester <?php echo $i; ?>
                            </option>
                        <?php endfor; ?>
                    </select>
                </div>

                <div class="profile-modal-field">
                    <label>Section</label>
                    <select name="section" required>
                        <?php for ($i = 1; $i <= 6; $i++): ?>
                            <option
                                value="<?php echo $i; ?>"
                                <?php
                                if (
                                    (string)$profileUser["section"] === (string)$i
                                ) {
                                    echo "selected";
                                }
                                ?>>
                                Section <?php echo $i; ?>
                            </option>
                        <?php endfor; ?>
                    </select>
                </div>

                <div class="profile-modal-field">
                    <label>Account Role</label>
                    <input
                        type="text"
                        value="Student"
                        disabled>
                    <small>Account role cannot be changed.</small>
                </div>
            </div>

            <div class="profile-modal-actions">
                <button
                    type="button"
                    class="change-password-modal-button"
                    id="openPasswordModal">
                    <i class="fa-solid fa-lock"></i>
                    Change Password
                </button>
                <button
                    type="submit"
                    class="save-profile-modal-button">
                    <i class="fa-solid fa-floppy-disk"></i>
                    Save Changes
                </button>
            </div>
        </form>
    </div>
</div>
            
            <div class="password-modal-overlay" id="passwordModal">
                <div class="password-modal">
                    <div class="password-modal-header">
                        <div>
                            <h2>Change Password</h2>
                            <p>Enter your current password and choose a new password.</p>
                        </div>
                        
                        <button
                        type="button"
                        class="password-modal-close"
                        id="closePasswordModal"
                        aria-label="Close">
                        <i class="fa-solid fa-xmark"></i>
                    </button>
                </div>
                
                <form
                action="change_password.php"
                method="POST"
                class="password-form">
                <div class="password-field">
                    <label for="currentPassword">
                        Current Password
                    </label>
                    <div class="password-input-wrapper">
                        <input
                        type="password"
                        id="currentPassword"
                        name="current_password"
                        required>
                    <button
                        type="button"
                        class="password-eye"
                        data-target="currentPassword"
                        aria-label="Show password">
                        <i class="fa-solid fa-eye"></i>
                    </button>
                </div>
            </div>

            <div class="password-field">
                <label for="newPassword">
                    New Password
                </label>
                <div class="password-input-wrapper">
                    <input
                        type="password"
                        id="newPassword"
                        name="new_password"
                        required>
                    <button
                        type="button"
                        class="password-eye"
                        data-target="newPassword"
                        aria-label="Show password">
                        <i class="fa-solid fa-eye"></i>
                    </button>
                </div>

                <small>
                    Minimum 8 characters, including an uppercase letter, a number and a special character.
                </small>
            </div>

            <div class="password-field">
                <label for="confirmPassword">
                    Confirm New Password
                </label>
                <div class="password-input-wrapper">
                    <input
                        type="password"
                        id="confirmPassword"
                        name="confirm_password"
                        required>
                    <button
                        type="button"
                        class="password-eye"
                        data-target="confirmPassword"
                        aria-label="Show password">
                        <i class="fa-solid fa-eye"></i>
                    </button>
                </div>
            </div>

            <div class="password-modal-actions">
                <button
                    type="button"
                    class="back-profile-button"
                    id="backToProfile">
                    <i class="fa-solid fa-arrow-left"></i>
                    Back to Profile
                </button>
                <button
                    type="submit"
                    class="update-password-button">
                    <i class="fa-solid fa-lock"></i>
                    Update Password
                </button>
            </div>
        </form>
    </div>
</div>

<!-- CREATE GROUP MODAL -->
<div class="create-modal" id="createModal">
    <div class="create-modal-box">
        <iframe
            src="create_group.php"
            frameborder="0">
        </iframe>
    </div>
</div>


<!-- JOIN GROUP MODAL -->
<div class="create-modal" id="joinModal">
    <div class="create-modal-box">
        <iframe
            src="join_group.php"
            frameborder="0">
        </iframe>
    </div>
</div>


<!-- EDIT GROUP MODAL -->
<div class="create-modal" id="editModal">
    <div class="create-modal-box edit-modal-box">
        <iframe
            id="editFrame"
            frameborder="0"
            scrolling="no">
        </iframe>
    </div>
</div>

<script>
document.addEventListener("DOMContentLoaded", function () {
        const sidebar = document.querySelector(".sidebar");
        const sidebarToggle = document.getElementById("sidebarToggle");

        sidebarToggle.addEventListener("click", function () {
            sidebar.classList.toggle("closed");
        });
    });
</script>

<script>
function openDeleteModal(groupId) {
    document.getElementById("deleteGroupId").value = groupId;
    document.getElementById("deleteModal").classList.add("show");
}

function closeDeleteModal() {
    document.getElementById("deleteModal").classList.remove("show");
}
function closeCreateModal(){
    document.getElementById("createModal").classList.remove("show");
}

document.addEventListener("DOMContentLoaded", function () {
    const deleteModal = document.getElementById("deleteModal");

    if (deleteModal) {
        deleteModal.addEventListener("click", function (event) {

            if (event.target === deleteModal) {
                closeDeleteModal();
            }
        });
    }
});
</script>

<script>
function openCreateModal(){
    document.getElementById("createModal").classList.add("show");
}

function openJoinModal(){
    document.getElementById("joinModal").classList.add("show");
}
function closeJoinModal(){
    document.getElementById("joinModal").classList.remove("show");
}
function openEditModal(groupId){
    document.getElementById("editFrame").src = "edit_group.php?group_id=" + groupId;
    document.getElementById("editModal").classList.add("show");
}
function closeEditModal(){
    document.getElementById("editModal").classList.remove("show");
}

<?php if ($selected_assignment_id > 0): ?>

document.addEventListener("DOMContentLoaded", function () {
    openCreateModal();
});

<?php endif; ?>
</script>

<script>
document.addEventListener("DOMContentLoaded", function () {
    const profileModal =
        document.getElementById("profileModal");
    const openProfileModal =
        document.getElementById("openProfileModal");
    const closeProfileModal =
        document.getElementById("closeProfileModal");

    openProfileModal.addEventListener("click", function () {
        profileModal.classList.add("show");
        document.body.classList.add("modal-open");
    });

    closeProfileModal.addEventListener("click", function () {
        profileModal.classList.remove("show");
        document.body.classList.remove("modal-open");
    });

    profileModal.addEventListener("click", function (event) {
        if (event.target === profileModal) {
            profileModal.classList.remove("show");
            document.body.classList.remove("modal-open");
        }
    });

    document.addEventListener("keydown", function (event) {
        if (event.key === "Escape") {
            profileModal.classList.remove("show");
            document.body.classList.remove("modal-open");
        }
    });

    const passwordModal =
    document.getElementById("passwordModal");

const openPasswordModal =
    document.getElementById("openPasswordModal");

const closePasswordModal =
    document.getElementById("closePasswordModal");

const backToProfile =
    document.getElementById("backToProfile");

openPasswordModal.addEventListener("click", function () {
    profileModal.classList.remove("show");
    passwordModal.classList.add("show");

});

closePasswordModal.addEventListener("click", function () {
    passwordModal.classList.remove("show");
    document.body.classList.remove("modal-open");

});

backToProfile.addEventListener("click", function () {
    passwordModal.classList.remove("show");
    profileModal.classList.add("show");

});

passwordModal.addEventListener("click", function (event) {
    if (event.target === passwordModal) {
        passwordModal.classList.remove("show");
        document.body.classList.remove("modal-open");
    }
});

const passwordEyes =
    document.querySelectorAll(".password-eye");

passwordEyes.forEach(function (button) {
    button.addEventListener("click", function () {
        const targetId =
            button.getAttribute("data-target");
        const input =
            document.getElementById(targetId);
        const icon =
            button.querySelector("i");

        if (input.type === "password") {
            input.type = "text";
            icon.classList.remove("fa-eye");
            icon.classList.add("fa-eye-slash");
            button.setAttribute(
                "aria-label",
                "Hide password"
            );

        } else {
            input.type = "password";
            icon.classList.remove("fa-eye-slash");
            icon.classList.add("fa-eye");
            button.setAttribute(
                "aria-label",
                "Show password"
            );
        }
    });
});

const profileMessages =
    document.querySelectorAll(".profile-update-message");

profileMessages.forEach(function (message) {
    setTimeout(function () {
        message.style.opacity = "0";
        setTimeout(function () {
            message.remove();
        }, 400);
    }, 4000);
});

if (
    window.location.search.includes("profile=") ||
    window.location.search.includes("password=")
) {
    window.history.replaceState(
        {},
        document.title,
        window.location.pathname
    );
}
});
</script>

<script>
function openAssignTaskModal(groupId, members) {

    const modal =
        document.getElementById("assignTaskModal");

    const groupInput =
        document.getElementById("assignTaskGroupId");

    const memberSelect =
        document.getElementById("assignTaskMember");

    groupInput.value = groupId;

    memberSelect.innerHTML = `
        <option value="" disabled selected>
            Select group member
        </option>
    `;

    members.forEach(function (member) {

        const option =
            document.createElement("option");

        option.value =
            member.user_id;

        option.textContent =
            member.full_name;

        memberSelect.appendChild(option);
    });

    modal.classList.add("show");
}


function closeAssignTaskModal() {

    document
        .getElementById("assignTaskModal")
        .classList.remove("show");
}


document.addEventListener(
    "DOMContentLoaded",
    function () {

        const modal =
            document.getElementById("assignTaskModal");

        if (modal) {

            modal.addEventListener(
                "click",
                function (event) {

                    if (event.target === modal) {
                        closeAssignTaskModal();
                    }

                }
            );
        }

    }
);

function openFinalSubmissionModal(groupId, assignmentTitle) {
    document.getElementById("finalSubmissionGroupId").value = groupId;
    document.getElementById("finalSubmissionAssignment").textContent = assignmentTitle;
    document.getElementById("finalSubmissionModal").classList.add("show");
}

function closeFinalSubmissionModal() {
    document.getElementById("finalSubmissionModal").classList.remove("show");
}

document.addEventListener("DOMContentLoaded", function () {
    const modal = document.getElementById("finalSubmissionModal");

    if (modal) {
        modal.addEventListener("click", function (event) {
            if (event.target === modal) {
                closeFinalSubmissionModal();
            }
        });
    }
});
</script>

<script>
document.addEventListener("DOMContentLoaded", function () {

    const finalSuccess =
        document.getElementById("finalSubmissionSuccess");

    if (finalSuccess) {
        setTimeout(function () {

            finalSuccess.style.opacity = "0";

            setTimeout(function () {
                finalSuccess.remove();
            }, 400);

        }, 4000);
    }

});
</script>

</body>
</html>