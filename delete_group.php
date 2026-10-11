<?php

session_start();

require_once "db.php";

if (!isset($_SESSION["user_id"])) {
    header("Location: index.php");
    exit;
}

if ($_SERVER["REQUEST_METHOD"] !== "POST") {
    header("Location: student_groups.php");
    exit;
}

$user_id = $_SESSION["user_id"];
$group_id = intval($_POST["group_id"] ?? 0);

if ($group_id <= 0) {
    header("Location: student_groups.php");
    exit;
}

$check = $conn->prepare(
    "SELECT group_id
     FROM group_members
     WHERE group_id = ?
     AND user_id = ?"
);

$check->bind_param(
    "ii",
    $group_id,
    $user_id
);

$check->execute();
$result = $check->get_result();

if ($result->num_rows > 0) {
    $deleteMembers = $conn->prepare(
        "DELETE FROM group_members
         WHERE group_id = ?"
    );
    $deleteMembers->bind_param(
        "i",
        $group_id
    );
    $deleteMembers->execute();

    $deleteGroup = $conn->prepare(
        "DELETE FROM student_groups
         WHERE group_id = ?"
    );
    $deleteGroup->bind_param(
        "i",
        $group_id
    );
    $deleteGroup->execute();
}

header("Location: student_groups.php");
exit;

?>