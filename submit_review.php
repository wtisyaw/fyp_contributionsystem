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

if (!isset($_GET["submission_id"])) {
    die("No submission selected.");
}

$submission_id = (int)$_GET["submission_id"];

if ($submission_id <= 0) {
    die("Invalid submission.");
}

$query = "
    SELECT
        s.submission_id,
        s.user_id AS receiver_id,
        s.task_id,
        s.assignment_id,
        s.title,
        s.file_name,
        s.file_path,
        s.version,
        s.uploaded_at,
        gt.task_title,
        gt.group_id,
        gt.status AS task_status,
        sg.group_name,
        sg.created_by AS leader_id,
        a.title AS assignment_title,
        u.full_name,
        u.nickname
    FROM submissions s
    INNER JOIN group_tasks gt
        ON s.task_id = gt.task_id
    INNER JOIN student_groups sg
        ON gt.group_id = sg.group_id
    INNER JOIN assignments a
        ON s.assignment_id = a.assignment_id
    INNER JOIN Users u
        ON s.user_id = u.user_id
    WHERE s.submission_id = ?
    LIMIT 1
";

$stmt = $conn->prepare($query);
$stmt->bind_param("i", $submission_id);
$stmt->execute();
$result = $stmt->get_result();
$submission = $result->fetch_assoc();
$stmt->close();

if (!$submission) {
    die("Submission not found.");
}

$receiver_id = (int)$submission["receiver_id"];
$leader_id = (int)$submission["leader_id"];
$group_id = (int)$submission["group_id"];
$canReview = false;

if ($receiver_id !== $leader_id) {
    if ($user_id === $leader_id) {
        $canReview = true;
    }
} else {
    $reviewerQuery = "
        SELECT assignment_id
        FROM feedback_assignments
        WHERE group_id = ?
        AND giver_id = ?
        AND receiver_id = ?
        LIMIT 1
    ";

    $reviewerStmt = $conn->prepare($reviewerQuery);
    $reviewerStmt->bind_param(
        "iii",
        $group_id,
        $user_id,
        $leader_id
    );
    $reviewerStmt->execute();
    $reviewerResult = $reviewerStmt->get_result();

    if ($reviewerResult->num_rows > 0) {
        $canReview = true;
    }

    $reviewerStmt->close();
}

if (!$canReview) {
    die("You are not allowed to review this submission.");
}

if ($receiver_id === $user_id) {
    die("You cannot review your own submission.");
}

$feedbackCheckQuery = "
    SELECT feedback_id
    FROM feedback
    WHERE submission_id = ?
    LIMIT 1
";

$feedbackCheckStmt = $conn->prepare($feedbackCheckQuery);
$feedbackCheckStmt->bind_param(
    "i",
    $submission_id
);
$feedbackCheckStmt->execute();
$feedbackCheckResult = $feedbackCheckStmt->get_result();

if ($feedbackCheckResult->num_rows > 0) {
    die("This submission has already been reviewed.");
}

$feedbackCheckStmt->close();

$displayName = !empty($submission["nickname"])
    ? $submission["nickname"]
    : $submission["full_name"];

$categories = [
    "Content Completeness",
    "Justification & Accuracy",
    "Alignment with Requirements",
    "Organization & Structure",
    "Language & Writing Quality",
    "None"
];
?>

<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Task Review | FairPeer</title>
<link rel="stylesheet" href="css/style.css">
<link rel="stylesheet" href="css/feedback.css">
<link rel="stylesheet" href="css/review.css">
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
</head>

<body class="dashboard-body">

<main class="review-page">

<div class="review-header">
    <h1>Task Review</h1>
    <p>Review the submitted task and provide constructive feedback.</p>
</div>

<div class="review-card">

<div class="review-student-header">
    <div>
        <span class="review-label">REVIEWING</span>
        <h2>
            <i class="fa-solid fa-user"></i>
            <?php echo htmlspecialchars($displayName); ?>
        </h2>
    </div>

    <div class="review-project-info">
        <span>
            <?php echo htmlspecialchars($submission["assignment_title"]); ?>
        </span>
        <strong>
            <?php echo htmlspecialchars($submission["version"]); ?>
        </strong>
    </div>
</div>

<div class="review-task-info">
    <span>Task Assigned:</span>
    <strong>
        <?php echo htmlspecialchars($submission["task_title"]); ?>
    </strong>
    <small>
        <?php echo htmlspecialchars($submission["group_name"]); ?>
    </small>
</div>

<div class="submission-file">
    <div class="submission-file-info">
        <i class="fa-solid fa-file-lines"></i>
        <div>
            <small>SUBMISSION FILE</small>
            <p>
                <?php echo htmlspecialchars($submission["file_name"]); ?>
            </p>
        </div>
    </div>

    <a
        href="<?php echo htmlspecialchars($submission["file_path"]); ?>"
        target="_blank"
        class="view-file-button">
        View File
        <i class="fa-solid fa-arrow-up-right-from-square"></i>
    </a>
</div>

<form action="save_review.php" method="POST">

<input
    type="hidden"
    name="submission_id"
    value="<?php echo $submission_id; ?>">

<div class="review-form-section">
    <h3>Select Feedback Category</h3>
    <p class="section-description">
        Select the areas that need improvement.
    </p>

    <?php foreach ($categories as $category): ?>

    <label>
        <input
            type="checkbox"
            name="categories[]"
            value="<?php echo htmlspecialchars($category); ?>">
        <?php echo htmlspecialchars($category); ?>
    </label>

    <?php endforeach; ?>
</div>

<div class="review-form-section">
    <h3>Additional Feedback</h3>
    <p class="section-description">
        Provide additional comments or suggestions for this task.
    </p>

    <textarea
        name="feedback_text"
        rows="5"
        required
        placeholder="Write your feedback here..."></textarea>
</div>

<div class="review-form-section">
    <h3>Overall Decision</h3>
    <p class="section-description">
        Does this submission need another revision?
    </p>

    <label>
        <input
            type="radio"
            name="review_decision"
            value="satisfactory"
            required>
        Satisfactory - No revision needed
    </label>

    <br>

    <label>
        <input
            type="radio"
            name="review_decision"
            value="revision_required"
            required>
        Revision Required
    </label>
</div>

<div class="review-actions">
    <a href="student_feedback.php" class="back-feedback-button">
        <i class="fa-solid fa-arrow-left"></i>
        Back to Feedback
    </a>

    <button type="submit" class="submit-review-button">
        <i class="fa-solid fa-paper-plane"></i>
        Submit Review
    </button>
</div>

</form>

</div>

</main>

</body>
</html>