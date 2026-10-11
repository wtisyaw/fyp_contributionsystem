<?php
session_start();
require_once "db.php";

header("Cache-Control: no-store, no-cache, must-revalidate, max-age=0");
header("Pragma: no-cache");
header("Expires: 0");

if (!isset($_SESSION["user_id"])) {
    header("Location: index.php");
    exit;
}
if (!isset($_SESSION["user_role"]) || $_SESSION["user_role"] !== "Student") {
    header("Location: index.php?error=role");
    exit;
}
$sender_id = (int)$_SESSION["user_id"];
if ($_SERVER["REQUEST_METHOD"] !== "POST") {
    header("Location: student_feedback.php");
    exit;
}
$submission_id = (int)($_POST["submission_id"] ?? 0);
$feedback_text = trim($_POST["feedback_text"] ?? "");
$categories = $_POST["categories"] ?? [];
$review_decision = $_POST["review_decision"] ?? "";
if ($submission_id <= 0) {
    die("Invalid submission.");
}
if ($feedback_text === "") {
    die("Please provide feedback.");
}
if (!in_array($review_decision, ["satisfactory", "revision_required"], true)) {
    die("Invalid review decision.");
}
$query = "
    SELECT
        s.submission_id,
        s.user_id AS receiver_id,
        s.task_id,
        gt.group_id,
        sg.created_by AS leader_id
    FROM submissions s
    INNER JOIN group_tasks gt
        ON s.task_id = gt.task_id
    INNER JOIN student_groups sg
        ON gt.group_id = sg.group_id
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
$task_id = (int)$submission["task_id"];
$group_id = (int)$submission["group_id"];
$leader_id = (int)$submission["leader_id"];
if ($sender_id === $receiver_id) {
    die("You cannot review your own submission.");
}
$canReview = false;
if ($receiver_id !== $leader_id) {
    if ($sender_id === $leader_id) {
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
        $sender_id,
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
$checkQuery = "
    SELECT feedback_id
    FROM feedback
    WHERE submission_id = ?
    LIMIT 1
";
$checkStmt = $conn->prepare($checkQuery);
$checkStmt->bind_param("i", $submission_id);
$checkStmt->execute();
$checkResult = $checkStmt->get_result();
if ($checkResult->num_rows > 0) {
    $checkStmt->close();
    die("This submission has already been reviewed.");
}
$checkStmt->close();
$conn->begin_transaction();
try {
    $feedbackQuery = "
        INSERT INTO feedback (
            group_id,
            submission_id,
            sender_id,
            receiver_id,
            feedback_text,
            review_decision
        )
        VALUES (?, ?, ?, ?, ?, ?)
    ";
    $stmt = $conn->prepare($feedbackQuery);
    $stmt->bind_param(
        "iiiiss",
        $group_id,
        $submission_id,
        $sender_id,
        $receiver_id,
        $feedback_text,
        $review_decision
    );
    if (!$stmt->execute()) {
        throw new Exception("Unable to save feedback.");
    }
    $feedback_id = $conn->insert_id;
    $stmt->close();
    foreach ($categories as $category) {
        $category = trim($category);
        if ($category === "") {
            continue;
        }
        $categoryQuery = "
            INSERT INTO feedback_categories (
                feedback_id,
                category
            )
            VALUES (?, ?)
        ";
        $categoryStmt = $conn->prepare($categoryQuery);
        $categoryStmt->bind_param(
            "is",
            $feedback_id,
            $category
        );
        if (!$categoryStmt->execute()) {
            throw new Exception("Unable to save feedback category.");
        }
        $categoryStmt->close();
    }
    if ($review_decision === "satisfactory") {
        $newStatus = "Completed";
    } else {
        $newStatus = "Revision Required";
    }
    $taskQuery = "
        UPDATE group_tasks
        SET status = ?
        WHERE task_id = ?
    ";
    $taskStmt = $conn->prepare($taskQuery);
    $taskStmt->bind_param(
        "si",
        $newStatus,
        $task_id
    );
    if (!$taskStmt->execute()) {
        throw new Exception("Unable to update task status.");
    }
    $taskStmt->close();
    $assignmentQuery = "
        UPDATE feedback_assignments
        SET status = 'completed'
        WHERE group_id = ?
        AND giver_id = ?
        AND receiver_id = ?
        AND submission_id = ?
    ";
    $assignmentStmt = $conn->prepare($assignmentQuery);
    $assignmentStmt->bind_param(
        "iiii",
        $group_id,
        $sender_id,
        $receiver_id,
        $submission_id
    );
    $assignmentStmt->execute();
    $assignmentStmt->close();
    $conn->commit();
    header("Location: student_feedback.php?review=success");
    exit;
} catch (Exception $e) {
    $conn->rollback();
    die("Unable to submit review. Please try again.");
}
?>