<?php
session_start();
require_once "db.php";

if (!isset($_SESSION["user_id"])) {
    header("Location: index.php");
    exit;
}

$userId = $_SESSION["user_id"];
$userRole = $_SESSION["user_role"] ?? "";

if ($userRole === "Lecturer") {
    $redirectPage = "lecturer_dashboard.php";
} else {
    $redirectPage = "student_dashboard.php";
}

if ($_SERVER["REQUEST_METHOD"] !== "POST") {
    header("Location: " . $redirectPage);
    exit;
}

$currentPassword = $_POST["current_password"] ?? "";
$newPassword = $_POST["new_password"] ?? "";
$confirmPassword = $_POST["confirm_password"] ?? "";

if ($currentPassword === "" || $newPassword === "" || $confirmPassword === "") {
    header("Location: " . $redirectPage . "?password=empty");
    exit;
}

if (
    strlen($newPassword) < 8 ||
    !preg_match('/[A-Z]/', $newPassword) ||
    !preg_match('/[0-9]/', $newPassword) ||
    !preg_match('/[^A-Za-z0-9]/', $newPassword)
) {
    header("Location: " . $redirectPage . "?password=invalid");
    exit;
}

if ($newPassword !== $confirmPassword) {
    header("Location: " . $redirectPage . "?password=match");
    exit;
}

$query = "SELECT password FROM Users WHERE user_id = ?";
$stmt = $conn->prepare($query);
$stmt->bind_param("i", $userId);
$stmt->execute();
$result = $stmt->get_result();
$user = $result->fetch_assoc();
$stmt->close();

if (!$user) {
    header("Location: " . $redirectPage . "?password=error");
    exit;
}

if (!password_verify($currentPassword, $user["password"])) {
    header("Location: " . $redirectPage . "?password=current");
    exit;
}

if (password_verify($newPassword, $user["password"])) {
    header("Location: " . $redirectPage . "?password=same");
    exit;
}

$newPasswordHash = password_hash($newPassword, PASSWORD_DEFAULT);

$updateQuery = "UPDATE Users SET password = ? WHERE user_id = ?";
$updateStmt = $conn->prepare($updateQuery);
$updateStmt->bind_param("si", $newPasswordHash, $userId);

if ($updateStmt->execute()) {
    $updateStmt->close();
    header("Location: " . $redirectPage . "?password=success");
    exit;
}

$updateStmt->close();
header("Location: " . $redirectPage . "?password=error");
exit;
?>