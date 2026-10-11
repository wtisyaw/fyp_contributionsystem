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

$user_id = (int)$_SESSION["user_id"];
/* Get current student profile */
$profileQuery = "
    SELECT
        u.full_name,
        u.nickname,
        u.user_code,
        u.email,
        u.programme_code,
        u.semester,
        u.section
    FROM Users u
    WHERE u.user_id = ?
    LIMIT 1
";

$profileStmt = $conn->prepare($profileQuery);
$profileStmt->bind_param("i", $user_id);
$profileStmt->execute();

$profileResult = $profileStmt->get_result();
$profileUser = $profileResult->fetch_assoc();

$profileStmt->close();

$hasGroup = false;
$reviewItems = [];
$feedbackItems = [];
$groupQuery = "
    SELECT
        sg.group_id,
        sg.group_name,
        sg.assignment_id,
        sg.created_by,
        a.title AS assignment_title,
        leader.full_name AS leader_full_name,
        leader.nickname AS leader_nickname
    FROM student_groups sg
    INNER JOIN group_members gm
        ON sg.group_id = gm.group_id
    INNER JOIN assignments a
        ON sg.assignment_id = a.assignment_id
    INNER JOIN Users leader
        ON sg.created_by = leader.user_id
    WHERE gm.user_id = ?
    ORDER BY a.due_date ASC
";

$groupStmt = $conn->prepare($groupQuery);
$groupStmt->bind_param("i", $user_id);
$groupStmt->execute();
$groupResult = $groupStmt->get_result();

$userGroups = [];

while ($group = $groupResult->fetch_assoc()) {
    $hasGroup = true;
    $userGroups[] = $group;
}

$groupStmt->close();

foreach ($userGroups as $group) {
    $group_id = (int)$group["group_id"];
    $leader_id = (int)$group["created_by"];

    $reviewerCheckQuery = "
        SELECT giver_id
        FROM feedback_assignments
        WHERE group_id = ?
        AND receiver_id = ?
        AND giver_id != ?
        ORDER BY assignment_id ASC
        LIMIT 1
    ";

    $reviewerCheckStmt = $conn->prepare($reviewerCheckQuery);
    $reviewerCheckStmt->bind_param(
        "iii",
        $group_id,
        $leader_id,
        $leader_id
    );
    $reviewerCheckStmt->execute();

    $reviewerCheckResult = $reviewerCheckStmt->get_result();
    $leaderReviewer = $reviewerCheckResult->fetch_assoc();
    $reviewerCheckStmt->close();

    if (!$leaderReviewer) {
        $findReviewerQuery = "
            SELECT gm.user_id
            FROM group_members gm
            WHERE gm.group_id = ?
            AND gm.user_id != ?
            ORDER BY gm.user_id ASC
            LIMIT 1
        ";

        $findReviewerStmt = $conn->prepare($findReviewerQuery);
        $findReviewerStmt->bind_param(
            "ii",
            $group_id,
            $leader_id
        );
        $findReviewerStmt->execute();

        $findReviewerResult = $findReviewerStmt->get_result();
        $reviewer = $findReviewerResult->fetch_assoc();
        $findReviewerStmt->close();

        if ($reviewer) {
            $designatedReviewer = (int)$reviewer["user_id"];

            $insertReviewerQuery = "
                INSERT INTO feedback_assignments
                (
                    group_id,
                    giver_id,
                    receiver_id,
                    submission_id,
                    status
                )
                VALUES (?, ?, ?, NULL, 'pending')
            ";

            $insertReviewerStmt = $conn->prepare($insertReviewerQuery);
            $insertReviewerStmt->bind_param(
                "iii",
                $group_id,
                $designatedReviewer,
                $leader_id
            );
            $insertReviewerStmt->execute();
            $insertReviewerStmt->close();
        }
    }
}

$reviewQuery = "
    SELECT
        s.submission_id,
        s.task_id,
        s.user_id AS owner_id,
        s.file_name,
        s.file_path,
        s.version,
        s.uploaded_at,
        gt.task_title,
        gt.status AS task_status,
        owner.full_name AS owner_full_name,
        owner.nickname AS owner_nickname,
        sg.group_id,
        sg.group_name,
        sg.created_by AS leader_id,
        a.title AS assignment_title,
        sub.subject_code,
        sub.subject_name,
        (
            SELECT COUNT(*)
            FROM feedback f
            WHERE f.submission_id = s.submission_id
        ) AS feedback_count
    FROM submissions s
    INNER JOIN group_tasks gt
        ON s.task_id = gt.task_id
    INNER JOIN student_groups sg
        ON gt.group_id = sg.group_id
    INNER JOIN assignments a
        ON sg.assignment_id = a.assignment_id
    INNER JOIN subject_sections sec
        ON a.subject_section_id = sec.subject_section_id
    INNER JOIN subjects sub
        ON sec.subject_id = sub.subject_id
    INNER JOIN Users owner
        ON s.user_id = owner.user_id
    INNER JOIN group_members current_member
        ON current_member.group_id = sg.group_id
        AND current_member.user_id = ?
    WHERE s.user_id != ?
    AND (
        (
            s.user_id != sg.created_by
            AND sg.created_by = ?
        )
        OR
        (
            s.user_id = sg.created_by
            AND EXISTS (
                SELECT 1
                FROM feedback_assignments fa
                WHERE fa.group_id = sg.group_id
                AND fa.receiver_id = sg.created_by
                AND fa.giver_id = ?
            )
        )
    )
    ORDER BY s.uploaded_at DESC, s.submission_id DESC
";

$reviewStmt = $conn->prepare($reviewQuery);
$reviewStmt->bind_param(
    "iiii",
    $user_id,
    $user_id,
    $user_id,
    $user_id
);
$reviewStmt->execute();
$reviewResult = $reviewStmt->get_result();

while ($review = $reviewResult->fetch_assoc()) {
    if ((int)$review["feedback_count"] === 0) {
        $reviewItems[] = $review;
    }
}

$reviewStmt->close();

$feedbackQuery = "
    SELECT
        f.feedback_id,
        f.sender_id,
        f.receiver_id,
        f.feedback_text,
        f.review_decision,
        f.created_at,
        sender.full_name AS sender_full_name,
        sender.nickname AS sender_nickname,
        s.submission_id,
        s.file_name,
        s.file_path,
        s.title,
        s.version,
        s.uploaded_at,
        gt.task_title,
        sg.group_name,
        sg.created_by AS leader_id,
        a.title AS assignment_title,
        sub.subject_code,
        sub.subject_name
    FROM feedback f
    INNER JOIN Users sender
        ON f.sender_id = sender.user_id
    INNER JOIN submissions s
        ON f.submission_id = s.submission_id
    LEFT JOIN group_tasks gt
        ON s.task_id = gt.task_id
    LEFT JOIN student_groups sg
        ON gt.group_id = sg.group_id
    LEFT JOIN assignments a
        ON s.assignment_id = a.assignment_id
    LEFT JOIN subject_sections sec
        ON a.subject_section_id = sec.subject_section_id
    LEFT JOIN subjects sub
        ON sec.subject_id = sub.subject_id
    WHERE f.receiver_id = ?
    ORDER BY f.created_at DESC, f.feedback_id DESC
";

$feedbackStmt = $conn->prepare($feedbackQuery);
$feedbackStmt->bind_param("i", $user_id);
$feedbackStmt->execute();
$feedbackResult = $feedbackStmt->get_result();

while ($feedback = $feedbackResult->fetch_assoc()) {
    $categoryQuery = "
        SELECT category
        FROM feedback_categories
        WHERE feedback_id = ?
        ORDER BY category ASC
    ";

    $categoryStmt = $conn->prepare($categoryQuery);
    $categoryStmt->bind_param(
        "i",
        $feedback["feedback_id"]
    );
    $categoryStmt->execute();

    $categoryResult = $categoryStmt->get_result();
    $categories = [];

    while ($category = $categoryResult->fetch_assoc()) {
        $categories[] = $category["category"];
    }

    $categoryStmt->close();
    $feedback["categories"] = $categories;
    $feedbackItems[] = $feedback;
}

$feedbackStmt->close();

$user_id = (int)$_SESSION["user_id"];

?>

<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Feedback | FairPeer</title>
<link rel="stylesheet" href="css/style.css">
<link rel="stylesheet" href="css/dashboard.css">
<link rel="stylesheet" href="css/profile.css">
<link rel="stylesheet" href="css/feedback.css">
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

        <a href="student_contribution.php" class="menu-item">
            <i class="fa-solid fa-chart-pie"></i>
            <span>My Contribution</span>
        </a>

        <a href="student_feedback.php" class="menu-item active">
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

<?php if (isset($_GET["review"]) && $_GET["review"] === "success"): ?>
<div class="success-message" id="reviewSuccessMessage">
    <i class="fa-solid fa-circle-check"></i>
    <div>
        <strong>Review Submitted!</strong>
        <p>Your review has been submitted successfully.</p>
    </div>
</div>
<?php endif; ?>

<header class="dashboard-header">
    <div class="welcome-area">
        <h1>Feedback</h1>
        <p>Review your peer assignments and view feedback received.</p>
    </div>

    <button type="button"
            class="header-profile"
            id="openProfileModal">

        <div class="profile-avatar">
            <?php
            echo strtoupper(
                substr($profileUser["nickname"], 0, 1)
            );
            ?>
        </div>

        <div class="profile-info">
            <strong>
                <?php echo htmlspecialchars($profileUser["nickname"]); ?>
            </strong>
            <span>Student</span>
        </div>
    </button>
</header>

<?php if (!$hasGroup): ?>

<section class="no-group-card">
    <div class="no-group-icon">
        <i class="fa-solid fa-user-group"></i>
    </div>
    <h2>No Group Yet</h2>
    <p>You haven't joined a group yet. Join a group to start working with your teammates.</p>
    <a href="student_groups.php" class="join-group-button">
        Go to My Group
    </a>
</section>

<?php else: ?>

<section class="feedback-page">

<div class="received-feedback-heading">
    <h2>
        <i class="fa-solid fa-clipboard-check"></i>
        Submissions to Review
    </h2>
    <p>Task submissions that are currently waiting for your review.</p>
</div>

<div class="peer-review-list">

<?php if (count($reviewItems) > 0): ?>

<?php foreach ($reviewItems as $review): ?>

<?php
$ownerName = !empty($review["owner_nickname"])
    ? $review["owner_nickname"]
    : $review["owner_full_name"];

$isOwnerLeader =
    (int)$review["owner_id"] ===
    (int)$review["leader_id"];
?>

<div class="peer-review-card">
    <div class="peer-review-info">
        <h3>
            <?php echo htmlspecialchars($ownerName); ?>

            <?php if ($isOwnerLeader): ?>
                <span class="leader-badge">Team Leader</span>
            <?php endif; ?>
        </h3>

<p>
    <strong>Subject:</strong>
    <?php echo htmlspecialchars($review["subject_code"]." - ".$review["subject_name"]); ?>
</p>

<p>
    <strong>Assignment:</strong>
    <?php echo htmlspecialchars($review["assignment_title"]); ?>
</p>

<p>
    <strong>Group:</strong>
    <?php echo htmlspecialchars($review["group_name"]); ?>
</p>

<p>
    <strong>Task Assigned:</strong>
    <?php echo htmlspecialchars($review["task_title"]); ?>
</p>

        <p>
            <strong>Version:</strong>
            <?php echo htmlspecialchars($review["version"]); ?>
        </p>

        <p>
            <strong>File:</strong>
            <a href="<?php echo htmlspecialchars($review["file_path"]); ?>" target="_blank">
                <?php echo htmlspecialchars($review["file_name"]); ?>
            </a>
        </p>

        <p>
            <strong>Status:</strong>
            <span class="review-pending">
                Pending Review
            </span>
        </p>
    </div>

    <div class="peer-review-action">
        <a
            href="submit_review.php?submission_id=<?php echo (int)$review["submission_id"]; ?>"
            class="review-now-button">
            Review <?php echo htmlspecialchars($review["version"]); ?>
        </a>
    </div>
</div>

<?php endforeach; ?>

<?php else: ?>

<div class="peer-review-empty">
    <i class="fa-solid fa-circle-check"></i>
    <h3>You're all caught up!</h3>
    <p>There are currently no task submissions waiting for your review.</p>
</div>

<?php endif; ?>

</div>

<div class="received-feedback-heading">
    <h2>
        <i class="fa-solid fa-comments"></i>
        Feedback Received
    </h2>
    <p>View feedback provided on each version of your task submission.</p>
</div>

<div class="feedback-list">

<?php if (count($feedbackItems) > 0): ?>

<?php foreach ($feedbackItems as $feedback): ?>

<?php
$senderName = !empty($feedback["sender_nickname"])
    ? $feedback["sender_nickname"]
    : $feedback["sender_full_name"];

$isLeaderFeedback =
    (int)$feedback["sender_id"] ===
    (int)$feedback["leader_id"];

$isSatisfactory =
    strtolower((string)$feedback["review_decision"])
    === "satisfactory";
?>

<div class="feedback-card">
    <div class="feedback-content">

<div class="feedback-card-top">
    <div class="feedback-context">
        <span>Task Assigned:</span>
        <strong>
            <?php
            if (!empty($feedback["task_title"])) {
                echo htmlspecialchars($feedback["task_title"]);
            } else {
                echo htmlspecialchars($feedback["title"]);
            }
            ?>
        </strong>
        <span class="context-divider">|</span>
        <span>Assignment:</span>
        <strong><?php echo htmlspecialchars($feedback["assignment_title"]); ?></strong>
        <span class="context-divider">|</span>
        <strong><?php echo htmlspecialchars($feedback["subject_code"]." - ".$feedback["subject_name"]); ?></strong>
    </div>

    <span class="<?php echo $isSatisfactory ? "review-completed" : "review-pending"; ?>">
        <?php echo $isSatisfactory ? "Satisfactory" : "Revision Required"; ?>
    </span>
</div>

        <div class="reviewed-file">
            <p>
                <i class="fa-solid fa-file-lines"></i>
                <strong>Reviewed File:</strong>
            </p>

            <div class="file-name">
                <a
                    href="<?php echo htmlspecialchars($feedback["file_path"]); ?>"
                    target="_blank">
                    <?php echo htmlspecialchars($feedback["file_name"]); ?>
                </a>

                <span>
                    <?php echo htmlspecialchars($feedback["version"]); ?>
                </span>
            </div>
        </div>

        <?php if (count($feedback["categories"]) > 0): ?>

        <h4 class="area-title">
            Areas to Improve:
        </h4>

        <div class="feedback-tags">
            <?php foreach ($feedback["categories"] as $category): ?>
                <span class="feedback-tag">
                    <?php echo htmlspecialchars($category); ?>
                </span>
            <?php endforeach; ?>
        </div>

        <?php endif; ?>

        <div class="additional-feedback">
            <h4>Additional Feedback:</h4>
            <p>
                <?php echo nl2br(htmlspecialchars($feedback["feedback_text"])); ?>
            </p>
        </div>

<div class="feedback-footer">
    <div class="feedback-reviewer">
        Reviewed by:
        <strong><?php echo htmlspecialchars($senderName); ?></strong>
    </div>

    <div class="feedback-date">
        <?php echo date("d M Y", strtotime($feedback["created_at"])); ?>
    </div>
</div>

    </div>
</div>

<?php endforeach; ?>

<?php else: ?>

<div class="feedback-empty">
    <i class="fa-solid fa-comment-slash"></i>
    <h3>No feedback yet</h3>
    <p>You haven't received any feedback on your task submissions yet.</p>
</div>

<?php endif; ?>

</div>

</section>

<?php endif; ?>

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