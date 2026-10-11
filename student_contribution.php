<?php

session_start();

require_once "db.php";

if (!isset($_SESSION["user_id"])) {
    header("Location: index.php");
    exit;
}

if (!isset($_SESSION["user_role"]) || $_SESSION["user_role"] !== "Student") {
    header("Location: index.php?error=role");
    exit;
}

$user_id = $_SESSION["user_id"];

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

//count completed tasks
$taskSql = "
    SELECT COUNT(*) AS completed
    FROM group_tasks
    WHERE assigned_to = ?
    AND status = 'Completed'
";

$taskStmt = $conn->prepare($taskSql);
$taskStmt->bind_param("i", $user_id);
$taskStmt->execute();

$taskResult = $taskStmt->get_result();
$taskData = $taskResult->fetch_assoc();

$completedTasks = $taskData["completed"];


//count total tasks
$totalTaskSql = "
    SELECT COUNT(*) AS total
    FROM group_tasks
    WHERE assigned_to = ?
";

$totalTaskStmt = $conn->prepare($totalTaskSql);
$totalTaskStmt->bind_param("i", $user_id);
$totalTaskStmt->execute();

$totalTaskResult = $totalTaskStmt->get_result();
$totalTaskData = $totalTaskResult->fetch_assoc();

$totalTasks = $totalTaskData["total"];


//count feedback received
$feedbackSql = "
    SELECT COUNT(*) AS total
    FROM feedback
    WHERE receiver_id = ?
";

$feedbackStmt = $conn->prepare($feedbackSql);
$feedbackStmt->bind_param("i", $user_id);
$feedbackStmt->execute();

$feedbackResult = $feedbackStmt->get_result();
$feedbackData = $feedbackResult->fetch_assoc();

$totalFeedback = $feedbackData["total"];


//count contribution updates
$contributionSql = "
    SELECT COUNT(*) AS total
    FROM contributions
    WHERE user_id = ?
";

$contributionStmt = $conn->prepare($contributionSql);
$contributionStmt->bind_param("i", $user_id);
$contributionStmt->execute();

$contributionResult = $contributionStmt->get_result();
$contributionData = $contributionResult->fetch_assoc();

$totalContribution = $contributionData["total"];

/* GET ALL TASK PROGRESS FOR GROUPS THE USER BELONGS TO */

$groupTaskSql = "
    SELECT
        gt.task_id,
        gt.task_title,
        gt.task_description,
        gt.status,
        gt.created_at,
        gt.completed_at,

        gt.assigned_to,

        u.full_name,
        u.nickname,

        sg.group_id,
        sg.group_name,
        sg.created_by,

        leader.full_name AS leader_full_name,
        leader.nickname AS leader_nickname,

        a.assignment_id,
        a.title AS assignment_title,
        a.assignment_id,
        a.title AS assignment_title,

sub.subject_code,
sub.subject_name,

        (
    SELECT COUNT(*)
    FROM submissions s
    WHERE s.task_id = gt.task_id
) AS submission_count,

(
    SELECT s.file_name
    FROM submissions s
    WHERE s.task_id = gt.task_id
    ORDER BY s.uploaded_at DESC
    LIMIT 1
) AS latest_file_name,

(
    SELECT s.file_path
    FROM submissions s
    WHERE s.task_id = gt.task_id
    ORDER BY s.uploaded_at DESC
    LIMIT 1
) AS latest_file_path,

(
    SELECT s.version
    FROM submissions s
    WHERE s.task_id = gt.task_id
    ORDER BY s.uploaded_at DESC
    LIMIT 1
) AS latest_version,

(
    SELECT s.uploaded_at
    FROM submissions s
    WHERE s.task_id = gt.task_id
    ORDER BY s.uploaded_at DESC
    LIMIT 1
) AS latest_upload

    FROM group_tasks gt

    INNER JOIN student_groups sg
        ON gt.group_id = sg.group_id

    INNER JOIN group_members my_membership
        ON sg.group_id = my_membership.group_id

    INNER JOIN Users u
        ON gt.assigned_to = u.user_id

    INNER JOIN Users leader
        ON sg.created_by = leader.user_id

    INNER JOIN assignments a
        ON gt.assignment_id = a.assignment_id

    INNER JOIN subjects sub
        ON a.subject_id = sub.subject_id

    WHERE my_membership.user_id = ?

    ORDER BY
        a.title ASC,
        sg.group_name ASC,
        gt.created_at ASC
";

$groupTaskStmt = $conn->prepare($groupTaskSql);
$groupTaskStmt->bind_param("i", $user_id);
$groupTaskStmt->execute();

$groupTaskResult =
    $groupTaskStmt->get_result();


/* ORGANISE TASKS BY GROUP */

$groupProgress = [];

while ($task = $groupTaskResult->fetch_assoc()) {

    $groupId = $task["group_id"];

    if ($task["status"] === "Completed") {

    $task["progress_percentage"] = 100;
    $task["display_status"] = "Completed";
    $task["status_class"] = "completed";

} elseif ($task["status"] === "Revision Required") {

    $task["progress_percentage"] = 25;
    $task["display_status"] = "Revision Required";
    $task["status_class"] = "revision";

} elseif ($task["status"] === "Under Review") {

    $task["progress_percentage"] = 50;
    $task["display_status"] = "Under Review";
    $task["status_class"] = "review";

} else {

    $task["progress_percentage"] = 0;
    $task["display_status"] = "Pending";
    $task["status_class"] = "pending";
}


    if (!isset($groupProgress[$groupId])) {

        $leaderName =
            !empty($task["leader_nickname"])
            ? $task["leader_nickname"]
            : $task["leader_full_name"];

        $groupProgress[$groupId] = [
    "assignment_title" =>
        $task["assignment_title"],

    "subject_code" =>
        $task["subject_code"],

    "subject_name" =>
        $task["subject_name"],

    "group_name" =>
        $task["group_name"],

    "leader_name" =>
        $leaderName,

    "tasks" => []
];
    }

    $groupProgress[$groupId]["tasks"][] = $task;
}

//get feedback received
$feedbackListSql = "
    SELECT 
        feedback.feedback_text,
        feedback.created_at,
        users.full_name
    FROM feedback
    JOIN users 
    ON feedback.sender_id = users.user_id
    WHERE feedback.receiver_id = ?
    ORDER BY feedback.created_at DESC
";

$feedbackListStmt = $conn->prepare($feedbackListSql);

$feedbackListStmt->bind_param(
    "i",
    $user_id
);

$feedbackListStmt->execute();

$feedbackListResult = $feedbackListStmt->get_result();

//get recent contribution activity
$activitySql = "
    SELECT
        'task' COLLATE utf8mb4_unicode_ci AS activity_type,

        gt.task_title COLLATE utf8mb4_unicode_ci AS activity_title,

        CONCAT(
            'Completed task: ',
            gt.task_title
        ) COLLATE utf8mb4_unicode_ci AS activity_description,

        gt.completed_at AS activity_date

    FROM group_tasks gt

    WHERE gt.assigned_to = ?
    AND gt.status = 'Completed'
    AND gt.completed_at IS NOT NULL


    UNION ALL


    SELECT
        'submission' COLLATE utf8mb4_unicode_ci AS activity_type,

        CONCAT(
            'Submitted ',
            s.version
        ) COLLATE utf8mb4_unicode_ci AS activity_title,

        CONCAT(
            'Uploaded ',
            s.file_name,
            ' for review'
        ) COLLATE utf8mb4_unicode_ci AS activity_description,

        s.uploaded_at AS activity_date

    FROM submissions s

    INNER JOIN group_tasks gt
        ON s.task_id = gt.task_id

    WHERE gt.assigned_to = ?


    UNION ALL


    SELECT
        'feedback' COLLATE utf8mb4_unicode_ci AS activity_type,

        CONCAT(
            'Feedback for ',
            COALESCE(
                NULLIF(u.nickname, ''),
                u.full_name
            )
        ) COLLATE utf8mb4_unicode_ci AS activity_title,

        CONCAT(
            'You submitted peer feedback to ',
            COALESCE(
                NULLIF(u.nickname, ''),
                u.full_name
            )
        ) COLLATE utf8mb4_unicode_ci AS activity_description,

        f.created_at AS activity_date

    FROM feedback f

    INNER JOIN Users u
        ON f.receiver_id = u.user_id

    WHERE f.sender_id = ?


    ORDER BY activity_date DESC
";

$activityStmt = $conn->prepare($activitySql);

$activityStmt->bind_param(
    "iii",
    $user_id,
    $user_id,
    $user_id
);

$activityStmt->execute();

$activityResult = $activityStmt->get_result();

//get latest contribution percentage
$contributionSql = "
    SELECT contribution_percentage
    FROM contributions
    WHERE user_id = ?
    ORDER BY updated_at DESC
    LIMIT 1
";

$contributionStmt = $conn->prepare($contributionSql);

$contributionStmt->bind_param(
    "i",
    $user_id
);

$contributionStmt->execute();

$contributionResult = $contributionStmt->get_result();

$contributionData = $contributionResult->fetch_assoc();

$myContribution = $contributionData 
    ? $contributionData["contribution_percentage"] 
    : 0;

?>

<!DOCTYPE html>
<html lang="en">

<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>My Contribution | FairPeer</title>
<link rel="stylesheet" href="css/style.css">
<link rel="stylesheet" href="css/dashboard.css">
<link rel="stylesheet" href="css/contribution.css">
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

            <a href="student_groups.php" class="menu-item">
                <i class="fa-solid fa-users"></i>
                <span>My Groups</span>
            </a>

            <a href="student_contribution.php" class="menu-item active">
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

<script>
document.addEventListener("DOMContentLoaded", function(){
    const sidebar = document.querySelector(".sidebar");
    const sidebarToggle = document.getElementById("sidebarToggle");

    sidebarToggle.addEventListener("click", function(){
        sidebar.classList.toggle("closed");
    });
});
</script>

<main class="dashboard-main">

<header class="dashboard-header">
    <div class="welcome-area">
        <h1>My Contribution</h1>
        <p>Track your activity, feedback, and contribution progress across your groups.</p>
    </div>

    <button type="button" class="header-profile" id="openProfileModal">
        <div class="profile-avatar">
            <?php echo htmlspecialchars($profileInitial); ?>
        </div>

        <div class="profile-info">
            <strong>
                <?php echo htmlspecialchars($displayName); ?>
            </strong>
            <span>Student</span>
        </div>
    </button>
</header>

<section class="contribution-section">

    <div class="section-header">
        <div>
            <p class="section-label">
                Task Contribution
            </p>

            <h2>Group Task Progress</h2>

            <p class="group-progress-subtitle">
                Track task responsibilities and progress across your group.
            </p>
        </div>
    </div>


    <?php if (!empty($groupProgress)): ?>

        <div class="group-progress-list">

            <?php foreach ($groupProgress as $group): ?>

                <?php
                $totalProgress = 0;
                $numberOfTasks =
                    count($group["tasks"]);

                foreach ($group["tasks"] as $progressTask) {
                    $totalProgress +=
                        $progressTask["progress_percentage"];
                }

                $overallProgress =
                    $numberOfTasks > 0
                    ? round(
                        $totalProgress / $numberOfTasks
                    )
                    : 0;
                ?>


                <div class="group-progress-card">

                    <div class="group-progress-header">

<div>
    <p class="group-subject-label">
        Assignment | Subject
    </p>

    <h2>
        <?php echo htmlspecialchars($group["assignment_title"]); ?>
        |
        <?php
        echo htmlspecialchars(
            $group["subject_code"] . " - " . $group["subject_name"]
        );
        ?>
    </h2>
</div>


                        <div class="group-progress-information">

                            <div>
                                <span>Group Name</span>

                                <strong>
                                    <?php
                                    echo htmlspecialchars(
                                        $group["group_name"]
                                    );
                                    ?>
                                </strong>
                            </div>


                            <div>
                                <span>Team Leader</span>

                                <strong>
                                    <?php
                                    echo htmlspecialchars(
                                        $group["leader_name"]
                                    );
                                    ?>
                                </strong>
                            </div>

                        </div>

                    </div>


                    <div class="overall-progress">

                        <div class="overall-progress-top">

                            <div>
                                <span>Overall Team Progress</span>

                                <small>
                                    Based on all assigned tasks
                                </small>
                            </div>

                            <strong>
                                <?php echo $overallProgress; ?>%
                            </strong>

                        </div>


                        <div class="progress-bar">
                            <div
                                class="progress-bar-fill"
                                style="width:
                                <?php echo $overallProgress; ?>%;">
                            </div>
                        </div>

                    </div>


                    <div class="member-task-list">

                        <?php foreach ($group["tasks"] as $task): ?>

                            <?php
                            $memberName =
                                !empty($task["nickname"])
                                ? $task["nickname"]
                                : $task["full_name"];

                            $initial =
                                strtoupper(
                                    substr($memberName, 0, 1)
                                );
                            ?>


                            <div class="member-task-row">

                                <div class="member-task-person">

                                    <div class="member-task-avatar">
                                        <?php
                                        echo htmlspecialchars(
                                            $initial
                                        );
                                        ?>
                                    </div>


                                    <div>
                                        <strong>
                                            <?php
                                            echo htmlspecialchars(
                                                $memberName
                                            );
                                            ?>
                                        </strong>

                                        <span>
                                            <?php
                                            if (
                                                $task["assigned_to"]
                                                == $user_id
                                            ) {
                                                echo "You";
                                            } else {
                                                echo "Group Member";
                                            }
                                            ?>
                                        </span>
                                    </div>

                                </div>


                                <div class="member-task-details">

                                    <h3> Task Assigned:
                                        <?php
                                        echo htmlspecialchars(
                                            $task["task_title"]
                                        );
                                        ?>
                                    </h3>


                                    <?php if (
                                        !empty(
                                            $task["task_description"]
                                        )
                                    ): ?>

                                        <p>
                                            <strong>Task Description:</strong>
                                            <?php
                                            echo htmlspecialchars(
                                                $task[
                                                    "task_description"
                                                ]
                                            );
                                            ?>
                                        </p>

                                    <?php endif; ?>

                                    <?php if (!empty($task["latest_file_name"])): ?>

    <div class="task-uploaded-file">

        <div class="task-file-icon">
            <i class="fa-solid fa-file-lines"></i>
        </div>

        <div class="task-file-info">

            <span class="task-file-label">
                Latest Submission
            </span>

            <a
                href="<?php
                echo htmlspecialchars(
                    $task["latest_file_path"]
                );
                ?>"
                target="_blank"
                class="task-file-name">

                <?php
                echo htmlspecialchars(
                    $task["latest_file_name"]
                );
                ?>

            </a>

            <small>
                <?php
                echo htmlspecialchars(
                    $task["latest_version"]
                );
                ?>

                <?php if (!empty($task["latest_upload"])): ?>
                    · Uploaded
                    <?php
                    echo date(
                        "d M Y",
                        strtotime($task["latest_upload"])
                    );
                    ?>
                <?php endif; ?>
            </small>

        </div>

    </div>

<?php else: ?>

    <div class="task-no-file">
        <i class="fa-solid fa-file-circle-xmark"></i>
        No file uploaded yet
    </div>

<?php endif; ?>


                                    <div class="member-progress-bar">

                                        <div
                                            class="member-progress-fill"
                                            style="width:
                                            <?php
                                            echo $task[
                                                "progress_percentage"
                                            ];
                                            ?>%;">
                                        </div>

                                    </div>

                                </div>


                                <div class="member-task-progress">

                                    <strong>
                                        <?php
                                        echo $task[
                                            "progress_percentage"
                                        ];
                                        ?>%
                                    </strong>

                                    <span class="
                                        task-progress-badge
                                        <?php
                                        echo $task[
                                            "status_class"
                                        ];
                                        ?>
                                    ">
                                        <?php
                                        echo htmlspecialchars(
                                            $task[
                                                "display_status"
                                            ]
                                        );
                                        ?>
                                    </span>


                                    <?php if (
                                        (int)$task[
                                            "submission_count"
                                        ] > 0
                                    ): ?>

                                        <small>
                                            <?php
                                            echo
                                                (int)$task[
                                                    "submission_count"
                                                ];
                                            ?>
                                            file version(s)
                                        </small>

                                    <?php endif; ?>

                                </div>

                            </div>

                        <?php endforeach; ?>

                    </div>

                </div>

            <?php endforeach; ?>

        </div>


    <?php else: ?>

        <div class="contribution-task-empty">

            <i class="fa-solid fa-list-check"></i>

            <h2>No group tasks yet</h2>

            <p>
                Tasks assigned to your group members
                will appear here.
            </p>

        </div>

    <?php endif; ?>

</section>

<section class="contribution-section">
    <div class="section-header">
        <div>
            <p class="section-label">Peer Feedback</p>
            <h2>What Your Teammates Say</h2>
        </div>
    </div>

    <?php if ($feedbackListResult->num_rows > 0): ?>
    <div class="contribution-feedback-card">
        <div class="feedback-summary">
            <i class="fa-solid fa-comment-dots"></i>
            <h1><?php echo $totalFeedback; ?></h1>
            <p>Feedback Received</p>
        </div>

        <div class="contribution-feedback-list">
            <?php while ($feedback = $feedbackListResult->fetch_assoc()): ?>
                <div class="feedback-item">
                    <p>
                        "<?php echo htmlspecialchars($feedback["feedback_text"]); ?>"
                    </p>
                    <span>
                        — <?php echo htmlspecialchars($feedback["full_name"]); ?>
                    </span>
                </div>
            <?php endwhile; ?>

        </div>
    </div>
<?php else: ?>

    <div class="contribution-feedback-empty">
        <i class="fa-solid fa-comment-dots"></i>
        <h2>No Feedback Yet</h2>
        <p>
            Feedback from your teammates will appear here.
        </p>
    </div>
<?php endif; ?>
</section>

<section class="contribution-section">

    <div class="section-header">
        <div>
            <p class="section-label">Recent Activity</p>
            <h2>Your Contribution History</h2>
        </div>
    </div>

    <?php if ($activityResult->num_rows > 0): ?>

        <div class="activity-history-card">

            <?php while ($activity = $activityResult->fetch_assoc()): ?>

                <div class="history-item">

                    <div class="history-icon">

<?php if ($activity["activity_type"] === "task"): ?>

    <i class="fa-solid fa-circle-check"></i>

<?php elseif ($activity["activity_type"] === "submission"): ?>

    <i class="fa-solid fa-file-arrow-up"></i>

<?php else: ?>

    <i class="fa-solid fa-comment-dots"></i>

<?php endif; ?>

                    </div>

                    <div class="history-content">

                        <h4>
                            <?php
                            echo htmlspecialchars(
                                $activity["activity_title"]
                            );
                            ?>
                        </h4>

                        <p>
                            <?php
                            echo htmlspecialchars(
                                $activity["activity_description"]
                            );
                            ?>
                        </p>

                        <span>
                            <?php
                            echo date(
                                "d M Y",
                                strtotime(
                                    $activity["activity_date"]
                                )
                            );
                            ?>
                        </span>

                    </div>

                </div>

            <?php endwhile; ?>

        </div>

    <?php else: ?>

        <div class="contribution-history-empty">

            <i class="fa-solid fa-clock-rotate-left"></i>

            <h2>No recent activity</h2>

            <p>
                Your completed tasks and peer feedback
                activity will appear here.
            </p>

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

</body>
</html>