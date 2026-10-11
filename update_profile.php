<?php

session_start();
require_once "db.php";


/* CHECK LOGIN */
if (!isset($_SESSION["user_id"])) {
    header("Location: index.php");
    exit;
}

$userId = $_SESSION["user_id"];


/* ONLY ALLOW POST */
if ($_SERVER["REQUEST_METHOD"] !== "POST") {
    header("Location: student_dashboard.php");
    exit;
}


/* GET FORM DATA */
$fullName = trim($_POST["full_name"] ?? "");
$nickname = trim($_POST["nickname"] ?? "");
$email = trim($_POST["email"] ?? "");
$semester = trim($_POST["semester"] ?? "");
$section = trim($_POST["section"] ?? "");


/* CHECK EMPTY FIELDS */
if (
    $fullName === "" ||
    $nickname === "" ||
    $email === "" ||
    $semester === "" ||
    $section === ""
) {
    header("Location: student_dashboard.php?profile=empty");
    exit;
}


/* CHECK EMAIL */
if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
    header("Location: student_dashboard.php?profile=email");
    exit;
}


/* CHECK SEMESTER 1 - 7 */
if (
    !ctype_digit($semester) ||
    (int)$semester < 1 ||
    (int)$semester > 7
) {
    header("Location: student_dashboard.php?profile=semester");
    exit;
}


/* CHECK SECTION 1 - 6 */
if (
    !ctype_digit($section) ||
    (int)$section < 1 ||
    (int)$section > 6
) {
    header("Location: student_dashboard.php?profile=section");
    exit;
}


/* CHECK IF EMAIL IS ALREADY USED BY ANOTHER USER */
$emailQuery = "
    SELECT user_id
    FROM Users
    WHERE email = ?
    AND user_id != ?
";

$emailStmt = $conn->prepare($emailQuery);

$emailStmt->bind_param(
    "si",
    $email,
    $userId
);

$emailStmt->execute();

$emailResult = $emailStmt->get_result();


if ($emailResult->num_rows > 0) {

    header("Location: student_dashboard.php?profile=email_exists");
    exit;
}


/* UPDATE PROFILE */
$updateQuery = "
    UPDATE Users
    SET
        full_name = ?,
        nickname = ?,
        email = ?,
        semester = ?,
        section = ?
    WHERE user_id = ?
";

$updateStmt = $conn->prepare($updateQuery);

$updateStmt->bind_param(
    "sssssi",
    $fullName,
    $nickname,
    $email,
    $semester,
    $section,
    $userId
);


if ($updateStmt->execute()) {

    /* UPDATE SESSION */
    $_SESSION["full_name"] = $fullName;
    $_SESSION["nickname"] = $nickname;
    $_SESSION["email"] = $email;
    $_SESSION["semester"] = $semester;
    $_SESSION["section"] = $section;

    header("Location: student_dashboard.php?profile=success");
    exit;

} else {

    header("Location: student_dashboard.php?profile=error");
    exit;
}
?>