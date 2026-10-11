<?php
session_start();
require_once "db.php";

if (!isset($_SESSION["user_id"]) || $_SESSION["user_role"] !== "Lecturer") {
    header("Location: index.php");
    exit;
}

if ($_SERVER["REQUEST_METHOD"] !== "POST") {
    header("Location: lecturer_subjects.php");
    exit;
}

$lecturer_id = $_SESSION["user_id"];
$subject_code = strtoupper(trim($_POST["subject_code"] ?? ""));
$subject_name = trim($_POST["subject_name"] ?? "");
$programme_code = strtoupper(trim($_POST["programme_code"] ?? ""));
$semester = (int)($_POST["semester"] ?? 0);
$section = trim($_POST["section"] ?? "");
$expected_students = (int)($_POST["expected_students"] ?? 0);

if (
    $subject_code === "" ||
    $subject_name === "" ||
    $programme_code === "" ||
    $semester <= 0 ||
    $section === "" ||
    $expected_students <= 0
) {
    header("Location: lecturer_subjects.php?subject=empty");
    exit;
}

$sql = "SELECT subject_id
        FROM subjects
        WHERE subject_code = ?
        LIMIT 1";

$stmt = $conn->prepare($sql);
$stmt->bind_param("s", $subject_code);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows > 0) {
    $subject = $result->fetch_assoc();
    $subject_id = $subject["subject_id"];
} else {
    $sql = "INSERT INTO subjects
            (subject_code, subject_name)
            VALUES (?, ?)";

    $stmt = $conn->prepare($sql);
    $stmt->bind_param("ss", $subject_code, $subject_name);
    $stmt->execute();

    $subject_id = $conn->insert_id;
}

$sql = "SELECT lecturer_subject_id
        FROM lecturer_subjects
        WHERE lecturer_id = ?
        AND subject_id = ?
        LIMIT 1";

$stmt = $conn->prepare($sql);
$stmt->bind_param("ii", $lecturer_id, $subject_id);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows === 0) {
    $sql = "INSERT INTO lecturer_subjects
            (lecturer_id, subject_id)
            VALUES (?, ?)";

    $stmt = $conn->prepare($sql);
    $stmt->bind_param("ii", $lecturer_id, $subject_id);
    $stmt->execute();
}

$join_code = strtoupper(
    substr(bin2hex(random_bytes(4)), 0, 6)
);

$sql = "INSERT INTO subject_sections
        (
            subject_id,
            lecturer_id,
            programme_code,
            semester,
            section,
            join_code,
            expected_students
        )
        VALUES (?, ?, ?, ?, ?, ?, ?)";

$stmt = $conn->prepare($sql);

$stmt->bind_param(
    "iisissi",
    $subject_id,
    $lecturer_id,
    $programme_code,
    $semester,
    $section,
    $join_code,
    $expected_students
);

if ($stmt->execute()) {
    header("Location: lecturer_subjects.php?subject=success");
    exit;
}

header("Location: lecturer_subjects.php?subject=error");
exit;
?>