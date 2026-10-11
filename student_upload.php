<?php

session_start();
require_once "db.php";

if (!isset($_SESSION["user_id"])) {
    header("Location: index.php");
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

$message = "";


$assignmentQuery = "
    SELECT DISTINCT
        a.assignment_id,
        a.title
    FROM assignments a
    INNER JOIN student_groups sg
        ON sg.assignment_id = a.assignment_id
    INNER JOIN group_members gm
        ON gm.group_id = sg.group_id
    WHERE gm.user_id = ?
    ORDER BY a.title ASC
";

$assignmentStmt = $conn->prepare($assignmentQuery);
$assignmentStmt->bind_param("i", $user_id);
$assignmentStmt->execute();
$assignmentResult = $assignmentStmt->get_result();

$assignments = [];

while ($row = $assignmentResult->fetch_assoc()) {
    $assignments[] = $row;
}

$selectedAssignmentId = 0;

if (isset($_GET["assignment_id"])) {
    $selectedAssignmentId =
        (int) $_GET["assignment_id"];

} elseif (isset($_POST["assignment_id"])) {
    $selectedAssignmentId =
        (int) $_POST["assignment_id"];

} elseif (!empty($assignments)) {
    $selectedAssignmentId =
        (int) $assignments[0]["assignment_id"];
}

$selectedAssignment = null;
foreach ($assignments as $assignment) {
    if (
        (int)$assignment["assignment_id"]
        === $selectedAssignmentId
    ) {
        $selectedAssignment = $assignment;
        break;
    }
}

$latestSubmission = null;
$reviewStatus = "No Submission";
$reviewDecision = null;
$canUpload = false;
$nextVersion = "V1";

$task_id = null;

if ($selectedAssignment) {

    $taskQuery = "
        SELECT gt.task_id
        FROM group_tasks gt
        INNER JOIN student_groups sg
            ON gt.group_id = sg.group_id
        INNER JOIN group_members gm
            ON sg.group_id = gm.group_id
        WHERE gt.assigned_to = ?
        AND gm.user_id = ?
        AND sg.assignment_id = ?
        LIMIT 1
    ";

    $taskStmt = $conn->prepare($taskQuery);

    $taskStmt->bind_param(
        "iii",
        $user_id,
        $user_id,
        $selectedAssignmentId
    );

    $taskStmt->execute();

    $taskResult = $taskStmt->get_result();

    if ($taskResult->num_rows > 0) {
        $taskRow = $taskResult->fetch_assoc();
        $task_id = (int)$taskRow["task_id"];
    }

    $taskStmt->close();
}

if ($selectedAssignment) {

    $latestQuery = "
        SELECT
            submission_id,
            title,
            file_name,
            file_path,
            version,
            uploaded_at
        FROM submissions
        WHERE user_id = ?
        AND assignment_id = ?
        ORDER BY submission_id DESC
        LIMIT 1
    ";

    $latestStmt = $conn->prepare($latestQuery);

    $latestStmt->bind_param(
        "ii",
        $user_id,
        $selectedAssignmentId
    );

    $latestStmt->execute();
    $latestResult = $latestStmt->get_result();
    $latestSubmission = $latestResult->fetch_assoc();

    if (!$latestSubmission) {
        $canUpload = true;
        $nextVersion = "V1";
        $reviewStatus = "No Submission";

    } else {
        $submissionId =
            (int)$latestSubmission["submission_id"];

        $feedbackQuery = "
            SELECT review_decision
            FROM feedback
            WHERE submission_id = ?
            AND receiver_id = ?
            ORDER BY feedback_id DESC
            LIMIT 1
        ";

        $feedbackStmt =
            $conn->prepare($feedbackQuery);

        $feedbackStmt->bind_param(
            "ii",
            $submissionId,
            $user_id
        );

        $feedbackStmt->execute();

        $feedbackResult =
            $feedbackStmt->get_result();

        if ($feedbackResult->num_rows === 0) {
            $reviewStatus = "Pending Review";
            $canUpload = false;

        } else {
            $feedbackRow =
                $feedbackResult->fetch_assoc();
            $reviewDecision =
                $feedbackRow["review_decision"];

            if (
                $reviewDecision ===
                "revision_required"
            ) {
                $reviewStatus =
                    "Revision Required";

                $canUpload = true;
                $currentVersion =
                    (int) substr(
                        $latestSubmission["version"],
                        1
                    );
                $nextVersion =
                    "V" . ($currentVersion + 1);

            } else {
                $reviewStatus =
                    "Satisfactory";
                $canUpload = false;
            }
        }
    }
}

if (
    $_SERVER["REQUEST_METHOD"] === "POST"
    && isset($_POST["upload_submission"])
) {
if (!$selectedAssignment) {

    $message = "Please select a valid assignment.";

} elseif (!$task_id) {

    $message =
        "You do not have a task assigned for this assignment yet.";

} elseif (!$canUpload) {

    $message =
        "You cannot upload a new version yet.";

} elseif (
    !isset($_FILES["file"])
    || $_FILES["file"]["error"] !== 0
) {

    $message =
        "Please choose a file to upload.";

} else {

    $file = $_FILES["file"];

    $fileName =
        basename($file["name"]);

    $extension =
        strtolower(
            pathinfo(
                $fileName,
                PATHINFO_EXTENSION
            )
        );

        if ($extension !== "pdf") {
            $message =
                "Only PDF files are allowed.";

        } else {
            $targetFolder = "uploads/";
            $storedFileName =
                time() . "_" . $fileName;
            $filePath =
                $targetFolder . $storedFileName;

            if (
                move_uploaded_file(
                    $file["tmp_name"],
                    $filePath
                )
            ) {
                $title =
                    $selectedAssignment["title"];

                $insertQuery = "
    INSERT INTO submissions (
        user_id,
        assignment_id,
        task_id,
        title,
        file_name,
        file_path,
        version
    )
    VALUES (?, ?, ?, ?, ?, ?, ?)
";

                $insertStmt =
                    $conn->prepare($insertQuery);

$insertStmt->bind_param(
    "iiissss",
    $user_id,
    $selectedAssignmentId,
    $task_id,
    $title,
    $fileName,
    $filePath,
    $nextVersion
);
                if ($insertStmt->execute()) {
                    $newSubmissionId = $conn->insert_id;

                    $updateTaskQuery = "
    UPDATE group_tasks
    SET status = 'Under Review'
    WHERE task_id = ?
    AND assigned_to = ?
";

$updateTaskStmt =
    $conn->prepare($updateTaskQuery);

$updateTaskStmt->bind_param(
    "ii",
    $task_id,
    $user_id
);

$updateTaskStmt->execute();
$updateTaskStmt->close();

                    header(
                        "Location: student_upload.php"
                        . "?assignment_id="
                        . $selectedAssignmentId
                        . "&uploaded=1"
                    );

                    exit;

                } else {
                    $message =
                        "Unable to save submission.";
                }

            } else {
                $message =
                    "Unable to upload the file.";
            }
        }
    }
}

if (isset($_GET["uploaded"])) {
    $message =
        '<span class="upload-success-text">File uploaded successfully!</span> ';
}

$historyResult = null;
if ($selectedAssignment) {

    $historyQuery = "
        SELECT
            title,
            file_name,
            file_path,
            version,
            uploaded_at
        FROM submissions
        WHERE user_id = ?
        AND assignment_id = ?
        ORDER BY submission_id DESC
    ";

    $historyStmt =
        $conn->prepare($historyQuery);

    $historyStmt->bind_param(
        "ii",
        $user_id,
        $selectedAssignmentId
    );

    $historyStmt->execute();
    $historyResult =
        $historyStmt->get_result();
}

?>

<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Upload File | FairPeer</title>
<link rel="stylesheet" href="css/style.css">
<link rel="stylesheet" href="css/dashboard.css">
<link rel="stylesheet" href="css/upload.css">
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
            
            <a href="student_feedback.php" class="menu-item">
                <i class="fa-solid fa-comment"></i>
                <span>Feedback</span>
            </a>
            <a href="student_upload.php" class="menu-item active">
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

    if (sidebarToggle && sidebar) {
        sidebarToggle.addEventListener("click", function () {
            sidebar.classList.toggle("closed");
        });
    }
});
</script>

<div class="upload-page">
    <header class="dashboard-header">
        <div class="welcome-area">
            <h1>Upload File</h1>
            <p>Upload and manage your assignment submissions.</p>
        </div>
        <button
            type="button"
            class="header-profile"
            id="openProfileModal">
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

    <div class="upload-container">
        <div class="left-column">
            <div class="upload-heading">
                <h1>Submit Your Work</h1>
                <p>Select an assignment and upload your PDF file.</p>
            </div>

            <div class="upload-card">
                <form method="GET">
                    <label>Select Assignment</label>
                    <br>
                    <select
                        name="assignment_id"
                        onchange="this.form.submit()"
                        required>
                        <option value="">
                            -- Select Assignment --
                        </option>

                        <?php foreach ($assignments as $assignment): ?>
                            <option
                                value="<?php echo $assignment["assignment_id"]; ?>"
                                <?php
                                if (
                                    $selectedAssignmentId ==
                                    $assignment["assignment_id"]
                                ) {
                                    echo "selected";
                                }
                                ?>>

                                <?php
                                echo htmlspecialchars($assignment["title"]);
                                ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </form>

                <?php if ($selectedAssignment): ?>
                    <br>

                    <?php if ($message !== ""): ?>
                        <p>
                            <?php echo $message; ?>
                        </p>
                    <?php endif; ?>

                    <?php if ($canUpload): ?>
                        <form
                            method="POST"
                            enctype="multipart/form-data">
                            <input
                                type="hidden"
                                name="assignment_id"
                                value="<?php echo $selectedAssignmentId; ?>">

                            <label>Choose PDF File</label>
                            <br>
                            <input
                                type="file"
                                name="file"
                                accept=".pdf"
                                required>

                            <br><br>
                            <button
                                type="submit"
                                name="upload_submission">
                                Upload
                                <?php echo htmlspecialchars($nextVersion); ?>
                            </button>
                        </form>

                    <?php elseif ($reviewStatus === "Pending Review"): ?>
                        <p>
                            Your latest submission is waiting for team leader feedback.
                        </p>
                        <button class="waiting-feedback-btn" disabled>
                           Waiting for Feedback
                        </button>

                    <?php elseif ($reviewStatus === "Satisfactory"): ?>
                        <p>
                            ✓ Your latest submission received satisfactory feedback.
                            No further revision is required.
                        </p>
                    <?php endif; ?>
                <?php endif; ?>
            </div>

            <div class="submission-status">
                <h3>
                    <i class="fa-solid fa-chart-line"></i>
                    Submission Status
                </h3>

                <?php if (!$selectedAssignment): ?>
                    <p>
                        Select an assignment to view its submission status.
                    </p>

                <?php elseif (!$latestSubmission): ?>
                    <p>No submission yet.</p>

                <?php else: ?>
<p class="uploaded-version-line">
    Version:
    <span class="uploaded-version-badge">
        <?php echo htmlspecialchars($latestSubmission["version"]); ?>
    </span>
</p>

                    <div class="status-item">
                        <span>Review Status:</span>
                        <strong>
                            <?php
                            echo htmlspecialchars($reviewStatus);
                            ?>
                        </strong>
                    </div>

                    <div class="status-item">
                        <span>Last Upload:</span>
                        <strong>
                            <?php
                            echo date("d M Y", strtotime($latestSubmission["uploaded_at"]));
                            ?>
                        </strong>
                    </div>

                    <?php if ($reviewStatus === "Revision Required"): ?>
                        <p class="revision-message">
                            Your leader requested changes.
                            Please review the feedback and upload
                            <?php echo htmlspecialchars($nextVersion); ?>.
                        </p>

                    <?php elseif ($reviewStatus === "Satisfactory"): ?>
                        <p>
                            ✓ No further revision is required.
                        </p>
                    <?php endif; ?>
                <?php endif; ?>
            </div>
        </div>

        <div class="version-heading">
            <h2>Your Uploaded Versions</h2>
            <p>
                View your previous assignment submissions.
            </p>

            <?php
            if (
                $historyResult &&
                $historyResult->num_rows > 0
            ):
            ?>

                <div class="version-list">
                    <?php
                    while (
                        $file =
                        $historyResult->fetch_assoc()):
                    ?>

                        <div class="version-card">
                            <h3>
                                📄
                                <?php echo htmlspecialchars($file["title"]);
                                ?>
                            </h3>
<p class="version-info">
    Version:
    <strong class="version-number"><?php echo htmlspecialchars($file["version"]); ?></strong>
</p>
<p class="file-info">
    File:
    <a href="<?php echo htmlspecialchars($file["file_path"]); ?>" target="_blank" class="file-name-link">
        <?php echo htmlspecialchars($file["file_name"]); ?>
    </a>
</p>                          <small>
                                Uploaded:
                                <?php
                                echo date("d M Y", strtotime( $file["uploaded_at"]));
                                ?>
                            </small>
                        </div>
                    <?php endwhile; ?>
                </div>

            <?php else: ?>
                <div class="upload-version-empty">
                    <i class="fa-solid fa-file-circle-xmark"></i>
                    <h2>
                        No Uploaded Files Yet
                    </h2>
                    <p>
                        Your uploaded assignment versions will appear here.
                    </p>
                </div>
            <?php endif; ?>
        </div>
    </div>

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