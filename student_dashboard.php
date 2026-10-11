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

/* active assignments */
$assignmentQuery = "
    SELECT COUNT(DISTINCT a.assignment_id) AS total
    FROM assignments a
    INNER JOIN student_groups sg
        ON a.assignment_id = sg.assignment_id
    INNER JOIN group_members gm
        ON sg.group_id = gm.group_id
    LEFT JOIN final_submissions fs
        ON fs.assignment_id = a.assignment_id
        AND fs.group_id = sg.group_id
    WHERE gm.user_id = $user_id
        AND a.status = 'active'
        AND fs.final_submission_id IS NULL
";

$assignmentResult = $conn->query($assignmentQuery);
$activeAssignments = $assignmentResult->fetch_assoc()["total"];

/*current groups*/ 
$groupQuery = "
    SELECT COUNT(*) AS total
    FROM group_members
    WHERE user_id = $user_id
";

$groupResult = $conn->query($groupQuery);
$currentGroups = $groupResult->fetch_assoc()["total"];

/* contribution based on assigned task progress */
$contributionQuery = "
    SELECT
        AVG(
            CASE
                WHEN gt.status = 'Completed' THEN 100
                WHEN gt.status = 'Under Review' THEN 50
                WHEN gt.status = 'Revision Required' THEN 25
                ELSE 0
            END
        ) AS average_contribution
    FROM group_tasks gt
    WHERE gt.assigned_to = ?
";

$contributionStmt = $conn->prepare($contributionQuery);
$contributionStmt->bind_param("i", $user_id);
$contributionStmt->execute();

$contributionResult = $contributionStmt->get_result();
$contributionData = $contributionResult->fetch_assoc();

$contributionPercentage =
    $contributionData["average_contribution"] !== null
    ? round($contributionData["average_contribution"])
    : 0;

$contributionStmt->close();
/* upcoming assignments */
$userAssignmentsQuery = "
    SELECT
        a.assignment_id,
        a.title,
        a.due_date,
        a.status,
        sg.group_name,
        sg.submission_deadline
    FROM assignments a
    INNER JOIN student_groups sg
        ON a.assignment_id = sg.assignment_id
    INNER JOIN group_members gm
        ON sg.group_id = gm.group_id
    LEFT JOIN final_submissions fs
        ON fs.assignment_id = a.assignment_id
        AND fs.group_id = sg.group_id
    WHERE gm.user_id = $user_id
        AND a.status = 'active'
        AND fs.final_submission_id IS NULL
    ORDER BY a.due_date ASC
";

$userAssignments = $conn->query($userAssignmentsQuery);

/*recent feedback*/ 
$feedbackQuery = "
    SELECT feedback_text, created_at
    FROM feedback
    WHERE receiver_id = $user_id
    ORDER BY created_at DESC
    LIMIT 2
";

$feedbackResult = $conn->query($feedbackQuery);

/*recent activity*/
$activityQuery = "

SELECT 
    'Contribution Updated' AS activity_type,
    contribution_details AS activity_text,
    updated_at AS activity_date
FROM contributions
WHERE user_id = $user_id

UNION ALL

SELECT 
    'Feedback Received' AS activity_type,
    feedback_text AS activity_text,
    created_at AS activity_date
FROM feedback
WHERE receiver_id = $user_id

ORDER BY activity_date DESC
LIMIT 3

";

$activityResult = $conn->query($activityQuery);

$profileQuery = "
    SELECT
        user_code,
        full_name,
        nickname,
        email,
        user_role,
        programme_code,
        semester,
        section
    FROM Users
    WHERE user_id = ?
";

$profileStmt = $conn->prepare($profileQuery);
$profileStmt->bind_param("i", $user_id);
$profileStmt->execute();

$profileResult = $profileStmt->get_result();
$profileUser = $profileResult->fetch_assoc();

?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Student Dashboard | FairPeer</title>
    <link rel="stylesheet" href="css/style.css">
    <link rel="stylesheet" href="css/dashboard.css">
    <!--Font-->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
</head>

<body class="dashboard-body">
    <!--sidebar-->
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

        <!--upper menu-->
        <nav class="sidebar-menu">
            <a href="student_dashboard.php" class="menu-item active">
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

        <!--bottom menu-->
        <div class="sidebar-bottom">
            <a href="logout.php" class="menu-item logout">
                <i class="fa-solid fa-right-from-bracket"></i>
                <span>Logout</span>
            </a>
        </div>
    </aside>

    <script>
    document.addEventListener("DOMContentLoaded", function () {

        const sidebar = document.querySelector(".sidebar");
        const sidebarToggle = document.getElementById("sidebarToggle");

        sidebarToggle.addEventListener("click", function () {
            sidebar.classList.toggle("closed");
        });
    });
    </script>

    <main class="dashboard-main">
    <?php if (isset($_GET["profile"])): ?>
        <?php if ($_GET["profile"] === "success"): ?>
            <div class="profile-update-message success">
                <i class="fa-solid fa-circle-check"></i>
                Profile updated successfully.
            </div>

        <?php elseif ($_GET["profile"] === "email_exists"): ?>
            <div class="profile-update-message error">
                <i class="fa-solid fa-circle-exclamation"></i>
                This email address is already being used.
            </div>

        <?php elseif ($_GET["profile"] === "email"): ?>
            <div class="profile-update-message error">
                <i class="fa-solid fa-circle-exclamation"></i>
                Please enter a valid email address.
            </div>

        <?php elseif ($_GET["profile"] !== "success"): ?>
            <div class="profile-update-message error">
                <i class="fa-solid fa-circle-exclamation"></i>
                Unable to update your profile. Please check your information.
            </div>
        <?php endif; ?>
    <?php endif; ?>

        <header class="dashboard-header">
            <div class="welcome-area">
                <h1>Welcome back, <?php echo htmlspecialchars($_SESSION["nickname"]); ?></h1>
                <p>Here's an overview of your assignments and contributions.</p>
            </div>

            <button type="button" class="header-profile" id="openProfileModal">
                <div class="profile-avatar">
                    <?php echo strtoupper(substr($_SESSION["nickname"], 0, 1)); ?>
                </div>
                <div class="profile-info">
                    <strong>
                        <?php echo htmlspecialchars($_SESSION["nickname"]); ?>
                    </strong>
                    <span>Student</span>
                </div>
            </button>
        </header>

        <!--overview-->
        <div class="dashboard-overview-layout">
            <div class="dashboard-left">
                <section class="overview-grid">
                    <div class="overview-card">
                        <div class="section-label">
                        <span><i class="fa-solid fa-layer-group"></i></span>
                        <p>AT A GLANCE</p>
                        </div>
                        
                        <div class="glance-stats">
                            <div class="glance-item">
                                <h2><?php echo str_pad($activeAssignments, 2, "0", STR_PAD_LEFT); ?></h2>
                                <p>Active<br>Assignments</p>
                            </div>

                            <div class="glance-divider"></div>
                            <div class="glance-item">
                            <h2><?php echo str_pad($currentGroups, 2, "0", STR_PAD_LEFT); ?></h2>
                            <p>Current<br>Groups</p>
                            </div>
                        </div>
                    </div>
                </section>

        <!--contribution-->
        <section class="contribution-overview">
                <div class="contribution-top">
                    <div>
                    <p class="contribution-label">Your Contribution</p>
                    <h2><?php echo round($contributionPercentage); ?>%</h2>
                </div>
                <i class="fa-solid fa-chart-line"></i>
                </div>
            <div class="progress-bar">
                <div class="progress-fill" style="width: <?php echo $contributionPercentage; ?>%;"></div>
            </div>

            <div class="contribution-footer">
                <span>Based on your group activity</span>
                <span class="contribution-status">
                    <?php
                    if ($contributionPercentage == 0) {
                        echo "No Activity Yet";
                        } elseif ($contributionPercentage < 50) {
                            echo "Getting Started";
                            } elseif ($contributionPercentage < 80) {
                                echo "Good Progress";
                                } else {
                                    echo "Excellent Progress";
                                    }
                                    ?>
                </span>
            </div>
        </section>
        </div>

        <!--recent activity-->
        <div class="recent-activity-card">
            <p class="activity-title">Recent Activity</p>
            <h2>What's new</h2>
        <div class="activity-list">
                <?php if ($activityResult->num_rows > 0): ?>
                    <?php while ($activity = $activityResult->fetch_assoc()): ?>
                        <div class="activity-item">
                            <div class="activity-icon">
                                <i class="fa-solid <?php echo $activity["activity_type"] == "Feedback Received"
                                ? "fa-comment"
                                : "fa-chart-line";
                                ?>">
                                </i>
                            </div>
                            
                            <div class="activity-content">
                                <h4><?php echo $activity["activity_type"]; ?></h4>
                                <p><?php echo htmlspecialchars($activity["activity_text"]); ?></p>
                            </div>
                            <span class="activity-time">
                                <?php echo date("d M Y",strtotime($activity["activity_date"])); ?>
                            </span>
                        </div>
                    <?php endwhile; ?>
                    
                <?php else: ?>
                    <div class="dashboard-assignment-empty">
                        <i class="fa-solid fa-clock-rotate-left"></i>
                        <h2>No recent activity</h2>
                        <p>Your contribution updates will appear here.</p>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

        <section class="dashboard-block assignment-block">
    <div class="block-header">
        <div>
            <p class="block-label">UPCOMING WORK</p>
            <h2>Your Assignments</h2>
        </div>
        <a href="student_assignments.php" class="simple-link">
            View All
            <i class="fa-solid fa-arrow-right"></i>
        </a>
    </div>
    <?php if ($userAssignments && $userAssignments->num_rows > 0): ?>
    <div class="assignment-list">
        <?php while ($assignment=$userAssignments->fetch_assoc()): ?>
        <?php
        $status=strtolower(trim($assignment["status"]));
        $today=new DateTime();
        $today->setTime(0,0,0);
        $dueDate=new DateTime($assignment["due_date"]);
        $dueDate->setTime(0,0,0);
        $daysDifference=(int)$today->diff($dueDate)->format("%r%a");
        if($daysDifference>1){
            $dueText=$daysDifference." days to go";
            $dueClass="upcoming";
        }elseif($daysDifference===1){
            $dueText="1 day to go";
            $dueClass="upcoming";
        }elseif($daysDifference===0){
            $dueText="Due today";
            $dueClass="today";
        }elseif($daysDifference===-1){
            $dueText="1 day overdue";
            $dueClass="overdue";
        }else{
            $dueText=abs($daysDifference)." days overdue";
            $dueClass="overdue";
        }
        $groupDueText = "";
$groupDueClass = "";

if (!empty($assignment["submission_deadline"])) {
    $groupDueDate = new DateTime($assignment["submission_deadline"]);
    $groupDueDate->setTime(0, 0, 0);
    $groupDaysDifference = (int)$today->diff($groupDueDate)->format("%r%a");

    if ($groupDaysDifference > 1) {
        $groupDueText = $groupDaysDifference . " days to go";
        $groupDueClass = "upcoming";

    } elseif ($groupDaysDifference === 1) {
        $groupDueText = "1 day to go";
        $groupDueClass = "upcoming";

    } elseif ($groupDaysDifference === 0) {
        $groupDueText = "Due today";
        $groupDueClass = "today";

    } elseif ($groupDaysDifference === -1) {
        $groupDueText = "1 day overdue";
        $groupDueClass = "overdue";

    } else {
        $groupDueText = abs($groupDaysDifference) . " days overdue";
        $groupDueClass = "overdue";
    }
}
        ?>
        <div class="assignment-row">
            <div class="assignment-left">
                <div class="assignment-dot <?php echo $status==="active" ? "active-dot" : "pending-dot"; ?>"></div>
                <div class="dashboard-assignment-info">
                    <h3><?php echo htmlspecialchars($assignment["title"]); ?></h3>
                    <p>
                        <span>Group:</span>
                        <strong><?php echo htmlspecialchars($assignment["group_name"]); ?></strong>
                        <span class="assignment-status-text"><?php echo ucfirst(htmlspecialchars($status)); ?></span>
                    </p>
                </div>
            </div>
<div class="assignment-right">

    <?php if (!empty($assignment["submission_deadline"])): ?>
        <div class="deadline">
            <span>Group Deadline</span>

            <strong>
                <?php echo date("d M Y", strtotime($assignment["submission_deadline"])); ?>
            </strong>

            <small class="dashboard-countdown <?php echo $groupDueClass; ?>">
                <i class="fa-regular fa-clock"></i>
                <?php echo htmlspecialchars($groupDueText); ?>
            </small>
        </div>
    <?php endif; ?>

    <div class="deadline">
        <span>Assignment Due</span>

        <strong>
            <?php echo date("d M Y", strtotime($assignment["due_date"])); ?>
        </strong>

        <small class="dashboard-countdown <?php echo $dueClass; ?>">
            <i class="fa-regular fa-clock"></i>
            <?php echo htmlspecialchars($dueText); ?>
        </small>
    </div>

</div>
        </div>
        <?php endwhile; ?>
    </div>
    <?php else: ?>
    <div class="dashboard-assignment-empty">
        <i class="fa-solid fa-clipboard-list"></i>
        <h2>No assignments found.</h2>
        <p>You don't have any assignments assigned to your group yet.</p>
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