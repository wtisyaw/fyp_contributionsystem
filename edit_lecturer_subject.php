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

$subject_section_id = (int)($_POST["subject_section_id"] ?? 0);
$subject_code = strtoupper(trim($_POST["subject_code"] ?? ""));
$subject_name = trim($_POST["subject_name"] ?? "");
$programme_code = strtoupper(trim($_POST["programme_code"] ?? ""));
$semester = (int)($_POST["semester"] ?? 0);
$section = trim($_POST["section"] ?? "");
$expected_students = (int)($_POST["expected_students"] ?? 0);

if (
    $subject_section_id <= 0 ||
    $subject_code === "" ||
    $subject_name === "" ||
    $programme_code === "" ||
    $semester <= 0 ||
    $section === "" ||
    $expected_students <= 0
) {
    header("Location: lecturer_subjects.php?edit=empty");
    exit;
}

$sql = "SELECT subject_id
        FROM subject_sections
        WHERE subject_section_id = ?
        AND lecturer_id = ?
        LIMIT 1";

$stmt = $conn->prepare($sql);
$stmt->bind_param(
    "ii",
    $subject_section_id,
    $lecturer_id
);

$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows === 0) {
    header("Location: lecturer_subjects.php?edit=invalid");
    exit;
}

$row = $result->fetch_assoc();
$subject_id = (int)$row["subject_id"];
$stmt->close();

/* Check whether the new subject code belongs to another subject */
$checkSql = "SELECT subject_id
             FROM subjects
             WHERE subject_code = ?
             AND subject_id != ?
             LIMIT 1";

$checkStmt = $conn->prepare($checkSql);
$checkStmt->bind_param(
    "si",
    $subject_code,
    $subject_id
);

$checkStmt->execute();
$checkResult = $checkStmt->get_result();

if ($checkResult->num_rows > 0) {

    $checkStmt->close();

    header("Location: lecturer_subjects.php?edit=duplicate");
    exit;
}

$checkStmt->close();

/* Check if subject code already exists */
$sql = "SELECT subject_id
        FROM subjects
        WHERE subject_code = ?
        LIMIT 1";

$stmt = $conn->prepare($sql);
$stmt->bind_param("s", $subject_code);
$stmt->execute();

$result = $stmt->get_result();

if ($result->num_rows > 0) {

    /* Use existing subject */
    $existingSubject = $result->fetch_assoc();
    $subject_id = (int)$existingSubject["subject_id"];

    $stmt->close();

    /* Update subject name */
    $sql = "UPDATE subjects
            SET subject_name = ?
            WHERE subject_id = ?";

    $stmt = $conn->prepare($sql);
    $stmt->bind_param(
        "si",
        $subject_name,
        $subject_id
    );

    $stmt->execute();
    $stmt->close();

} else {

    $stmt->close();

    /* Subject code does not exist, create it */
    $sql = "INSERT INTO subjects
            (subject_code, subject_name)
            VALUES (?, ?)";

    $stmt = $conn->prepare($sql);
    $stmt->bind_param(
        "ss",
        $subject_code,
        $subject_name
    );

    $stmt->execute();

    $subject_id = $conn->insert_id;
    $stmt->close();
}

$sql = "UPDATE subject_sections
        SET subject_id = ?,
            programme_code = ?,
            semester = ?,
            section = ?,
            expected_students = ?
        WHERE subject_section_id = ?
        AND lecturer_id = ?";

$stmt = $conn->prepare($sql);

$stmt->bind_param(
    "isisiii",
    $subject_id,
    $programme_code,
    $semester,
    $section,
    $expected_students,
    $subject_section_id,
    $lecturer_id
);

if ($stmt->execute()) {
    header("Location: lecturer_subjects.php?edit=success");
    exit;
}

header("Location: lecturer_subjects.php?edit=error");
exit;
?>