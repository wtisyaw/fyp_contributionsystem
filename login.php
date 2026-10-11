<?php

session_start();

require_once "db.php";

if ($_SERVER["REQUEST_METHOD"] === "POST") {
    $email = trim($_POST["email"] ?? "");
    $password = $_POST["password"] ?? "";

    if ($email === "" || $password === "") {
        header("Location: index.php?error=empty");
        exit;
    }

    $sql = "SELECT user_id, user_code, full_name, nickname, email, password, user_role, programme_code
            FROM Users
            WHERE email = ?";
    $stmt = $conn->prepare($sql);

    if (!$stmt) {
        header("Location: index.php?error=system");
        exit;
    }

    $stmt->bind_param("s", $email);
    $stmt->execute();
    $result = $stmt->get_result();

    if ($result->num_rows === 1) {
        $user = $result->fetch_assoc();

        if (password_verify($password, $user["password"])) {
            $_SESSION["user_id"] = $user["user_id"];
            $_SESSION["user_code"] = $user["user_code"];
            $_SESSION["full_name"] = $user["full_name"];
            $_SESSION["nickname"] = $user["nickname"];
            $_SESSION["email"] = $user["email"];
            $_SESSION["user_role"] = $user["user_role"];
            $_SESSION["programme_code"] = $user["programme_code"];

            if ($user["user_role"] === "Student") {
                header("Location: student_dashboard.php");
                exit;
            }
            elseif ($user["user_role"] === "Lecturer") {
                header("Location: lecturer_dashboard.php");
                exit;
            }
            else {
                header("Location: index.php?error=role");
                exit;
            }
        } else {
            header("Location: index.php?error=password");
            exit;
        }
    } else {
        header("Location: index.php?error=login");
        exit;
    }
} else {
    header("Location: index.php");
    exit;
}
?>