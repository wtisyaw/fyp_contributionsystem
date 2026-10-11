<?php
session_start();
require_once "db.php";

header("Content-Type: application/json");

if (!isset($_SESSION["user_id"]) || $_SESSION["user_role"] !== "Lecturer") {
    echo json_encode([
        "success" => false,
        "message" => "Unauthorized."
    ]);
    exit;
}

$lecturer_id = $_SESSION["user_id"];
$subject_section_id = (int)($_GET["section_id"] ?? 0);

if ($subject_section_id <= 0) {
    echo json_encode([
        "success" => false,
        "message" => "Invalid subject section."
    ]);
    exit;
}

$sql = "SELECT
            ss.subject_section_id,
            s.subject_code,
            s.subject_name
        FROM subject_sections ss
        JOIN subjects s
            ON ss.subject_id = s.subject_id
        WHERE ss.subject_section_id = ?
        AND ss.lecturer_id = ?
        LIMIT 1";

$stmt = $conn->prepare($sql);
$stmt->bind_param("ii", $subject_section_id, $lecturer_id);
$stmt->execute();

$result = $stmt->get_result();

if ($result->num_rows === 0) {
    echo json_encode([
        "success" => false,
        "message" => "Subject section not found."
    ]);
    exit;
}

$subject = $result->fetch_assoc();
$stmt->close();

$sql = "SELECT
            u.user_id,
            u.user_code,
            u.full_name,
            u.nickname,
            MAX(sg.group_name) AS group_name

        FROM student_subjects stsub

        JOIN users u
            ON stsub.student_id = u.user_id

        LEFT JOIN group_members gm
            ON gm.user_id = u.user_id

        LEFT JOIN student_groups sg
            ON gm.group_id = sg.group_id
            AND sg.assignment_id IN (
                SELECT assignment_id
                FROM assignments
                WHERE subject_section_id = stsub.subject_section_id
            )

        WHERE stsub.subject_section_id = ?
        AND u.user_role = 'Student'

        GROUP BY
            u.user_id,
            u.user_code,
            u.full_name,
            u.nickname

        ORDER BY
        CASE
        WHEN MAX(sg.group_name) IS NOT NULL THEN 0
        ELSE 1
        END ASC,
            u.full_name ASC";

$stmt = $conn->prepare($sql);
$stmt->bind_param("i", $subject_section_id);
$stmt->execute();

$result = $stmt->get_result();

$students = [];

while ($row = $result->fetch_assoc()) {

    $students[] = [
        "user_id" => $row["user_id"],
        "user_code" => $row["user_code"],
        "full_name" => $row["full_name"],
        "nickname" => $row["nickname"],
        "group_name" => $row["group_name"],
        "grouped" => !empty($row["group_name"])
    ];
}

$stmt->close();

echo json_encode([
    "success" => true,
    "subject_code" => $subject["subject_code"],
    "subject_name" => $subject["subject_name"],
    "total" => count($students),
    "students" => $students
]);

exit;
?>