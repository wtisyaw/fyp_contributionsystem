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

/* get assignments for logged in student */
$assignmentQuery = "SELECT
    a.assignment_id,
    a.title,
    a.description,
    a.due_date,
    a.status,
    sg.group_id,
    sg.group_name,
    sg.submission_deadline,
    s.subject_code,
    s.subject_name,
    fs.final_submission_id,
    fs.submitted_at AS final_submitted_at
FROM assignments a
INNER JOIN student_subjects sts
    ON a.subject_section_id = sts.subject_section_id
INNER JOIN subject_sections sec
    ON a.subject_section_id = sec.subject_section_id
INNER JOIN subjects s
    ON sec.subject_id = s.subject_id
LEFT JOIN student_groups sg
    ON a.assignment_id = sg.assignment_id
LEFT JOIN group_members gm
    ON sg.group_id = gm.group_id
    AND gm.user_id = ?
LEFT JOIN final_submissions fs
    ON fs.assignment_id = a.assignment_id
    AND fs.group_id = sg.group_id
WHERE sts.student_id = ?
AND (
    sg.group_id IS NULL
    OR gm.user_id IS NOT NULL
)
ORDER BY a.due_date ASC";
$assignmentStmt=$conn->prepare($assignmentQuery);
$assignmentStmt->bind_param("ii",$user_id,$user_id);
$assignmentStmt->execute();
$assignments=$assignmentStmt->get_result();
?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>My Assignments | FairPeer</title>
    <link rel="stylesheet" href="css/style.css">
    <link rel="stylesheet" href="css/dashboard.css">
    <link rel="stylesheet" href="css/assignments.css">
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

        <a href="student_assignments.php" class="menu-item active">
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
      <header class="dashboard-header">
    <div class="welcome-area">
        <h1>My Assignments</h1>
        <p>View your assigned work and upcoming deadlines.</p>
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
    
     <section class="student-assignments-page">
        <div class="page-section-header">
            <div>
                <p class="block-label">YOUR WORK</p>
                <h2 class="my-assignments-title">All Assignments</h2>
            </div>

            <div class="group-deadline-reminder">
                <i class="fa-solid fa-circle-info"></i>
                <div>
                    <strong>Group Submission Reminder</strong>
                    <p>Please submit your work before the <b>Group Deadline</b> to 
                       allow time for feedback and final compilation.
                    </p>
                </div>
        </div>

        <!--assignment list--> 
            <?php if ($assignments && $assignments->num_rows > 0): ?>
                <div class="student-assignment-list">
<?php while ($assignment = $assignments->fetch_assoc()): ?>
<?php

$isCompleted = !empty($assignment["final_submission_id"]);

$today = new DateTime();
$today->setTime(0, 0, 0);

if (!empty($assignment["submission_deadline"])) {
    $countdownDate = new DateTime($assignment["submission_deadline"]);
} else {
    $countdownDate = new DateTime($assignment["due_date"]);
}

$countdownDate->setTime(0, 0, 0);

$daysDifference = (int)$today->diff($countdownDate)->format("%r%a");

if ($daysDifference > 1) {
    $dueText = $daysDifference . " days to go";
    $dueClass = "upcoming";
} elseif ($daysDifference === 1) {
    $dueText = "1 day to go";
    $dueClass = "upcoming";
} elseif ($daysDifference === 0) {
    $dueText = "Due today";
    $dueClass = "today";
} elseif ($daysDifference === -1) {
    $dueText = "1 day overdue";
    $dueClass = "overdue";
} else {
    $dueText = abs($daysDifference) . " days overdue";
    $dueClass = "overdue";
}

$officialDueDate = new DateTime($assignment["due_date"]);
$officialDueDate->setTime(0, 0, 0);

$officialDaysDifference = (int)$today
    ->diff($officialDueDate)
    ->format("%r%a");

if ($officialDaysDifference > 1) {
    $officialDueText = $officialDaysDifference . " days to go";
    $officialDueClass = "upcoming";
} elseif ($officialDaysDifference === 1) {
    $officialDueText = "1 day to go";
    $officialDueClass = "upcoming";
} elseif ($officialDaysDifference === 0) {
    $officialDueText = "Due today";
    $officialDueClass = "today";
} elseif ($officialDaysDifference === -1) {
    $officialDueText = "1 day overdue";
    $officialDueClass = "overdue";
} else {
    $officialDueText = abs($officialDaysDifference) . " days overdue";
    $officialDueClass = "overdue";
}
?>
                    <div class="student-assignment-card">
                        <div class="student-assignment-main">
                            <div class="assignment-dot <?php echo $isCompleted ? "completed-dot" : (strtolower(trim($assignment["status"])) === "active" ? "active-dot" : "pending-dot"); ?>"></div>
                             <div class="student-assignment-info">
                                <p class="assignment-subject">
                                <?php echo htmlspecialchars($assignment["subject_code"]." - ".$assignment["subject_name"]); ?>
                                </p>
                                <h3><?php echo htmlspecialchars($assignment["title"]); ?></h3>
                                <?php if (!empty(trim($assignment["description"]))): ?>
    <p class="assignment-description">
        <?php echo htmlspecialchars($assignment["description"]); ?>
    </p>
<?php endif; ?>
                                <?php if (!empty($assignment["group_id"])): ?>

<div class="assignment-group">
    <i class="fa-solid fa-users"></i>
    <span>Group:</span>
    <strong><?php echo htmlspecialchars($assignment["group_name"]); ?></strong>
</div>

<?php else: ?>

    <span class="assignment-group">
        <i class="fa-solid fa-users"></i>
        No group yet
    </span>

<?php endif; ?>
                                </p>
                            </div>
                        </div>

                         <div class="student-assignment-meta">
     <div class="assignment-deadline">

    <?php if ($isCompleted): ?>

        <span>Submitted</span>

        <strong>
            <?php echo date("d M Y", strtotime($assignment["final_submitted_at"])); ?>
        </strong>

        <small class="assignment-countdown completed">
            <i class="fa-solid fa-circle-check"></i>
            Final assignment submitted
        </small>
    </div>
<?php else: ?>



<div class="assignment-deadlines">
<?php if (!empty($assignment["submission_deadline"])): ?>
        <div class="assignment-deadline-item">
            <span>Group Deadline</span>

            <strong>
                <?php echo date("d M Y", strtotime($assignment["submission_deadline"])); ?>
            </strong>

            <small class="assignment-countdown <?php echo $dueClass; ?>">
                <i class="fa-regular fa-clock"></i>
                <?php echo htmlspecialchars($dueText); ?>
            </small>
        </div>
    <?php endif; ?>

    <div class="assignment-deadline-item">
        <span>Assignment Due</span>

        <strong>
            <?php echo date("d M Y", strtotime($assignment["due_date"])); ?>
        </strong>

        <small class="assignment-countdown <?php echo $officialDueClass; ?>">
            <i class="fa-regular fa-clock"></i>
            <?php echo htmlspecialchars($officialDueText); ?>
        </small>
    </div>
</div>
</div>
<?php endif; ?>

<span class="assignment-status <?php echo $isCompleted ? "completed" : ""; ?>">
    <?php echo $isCompleted ? "Completed" : ucfirst($assignment["status"]); ?>
</span>
                            <a href="student_assignment_detail.php?id=<?php echo $assignment["assignment_id"]; ?>"  
                            class="assignment-view">
                                View
                            <i class="fa-solid fa-arrow-right"></i>
                            </a>
                        </div>
                    </div>
                    <?php endwhile; ?>

            <?php else: ?>
                 <div class="assignments-empty-state">
                    <i class="fa-solid fa-clipboard-list"></i>
                    <h2>No assignments found</h2>
                    <p>You don't have any assignments yet.</p>
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