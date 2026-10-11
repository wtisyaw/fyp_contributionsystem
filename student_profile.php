<?php

session_start();
require_once "db.php";

if (!isset($_SESSION["user_id"])) {
    header("Location: index.php");
    exit;
}
$userId = $_SESSION["user_id"];
$success = "";
$error = "";

if ($_SERVER["REQUEST_METHOD"] === "POST") {
    $fullName = trim($_POST["full_name"] ?? "");
    $nickname = trim($_POST["nickname"] ?? "");
    $email = trim($_POST["email"] ?? "");
    $semester = trim($_POST["semester"] ?? "");
    $section = trim($_POST["section"] ?? "");

    if (
        $fullName === "" ||
        $nickname === "" ||
        $email === "" ||
        $semester === "" ||
        $section === ""
    ) {

        $error = "Please fill in all required fields.";

    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error = "Please enter a valid email address.";

    } else {
        $updateSql = "
            UPDATE Users
            SET
                full_name = ?,
                nickname = ?,
                email = ?,
                semester = ?,
                section = ?
            WHERE user_id = ?
        ";

        $updateStmt = $conn->prepare($updateSql);

        if ($updateStmt) {
            $updateStmt->bind_param(
                "sssssi",
                $fullName,
                $nickname,
                $email,
                $semester,
                $section,
                $userId
            );

            try {
                if ($updateStmt->execute()) {
                    $_SESSION["full_name"] = $fullName;
                    $_SESSION["nickname"] = $nickname;
                    $_SESSION["email"] = $email;
                    $_SESSION["semester"] = $semester;
                    $_SESSION["section"] = $section;

                    $success = "Profile updated successfully.";
                }

            } catch (mysqli_sql_exception $e) {
                if ($e->getCode() == 1062) {
                    $error = "That email address is already registered.";

                } else {
                    $error = "Unable to update profile. Please try again.";
                }
            }

            $updateStmt->close();

        } else {
            $error = "Unable to update profile.";
        }
    }
}

$userSql = "
    SELECT
        user_code,
        full_name,
        nickname,
        email,
        user_role,
        programme_code,
        semester,
        section
    FROM Users
    WHERE user_id = ?
";

$userStmt = $conn->prepare($userSql);
$userStmt->bind_param(
    "i",
    $userId
);

$userStmt->execute();
$userResult = $userStmt->get_result();
$user = $userResult->fetch_assoc();

if (!$user) {
    header("Location: index.php");
    exit;
}

$displayName = $user["nickname"];
if ($displayName === null || $displayName === "") {
    $displayName = $user["full_name"];
}

$avatarLetter = strtoupper(
    substr($displayName, 0, 1)
);

?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>My Profile - FairPeer</title>
    <link rel="stylesheet" href="css/style.css">
    <link rel="stylesheet" href="css/profile.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
</head>

<body>
<main class="profile-main">
    <div class="profile-page">
        <a href="student_dashboard.php" class="back-dashboard">
            <i class="fa-solid fa-arrow-left"></i>
            Back to Dashboard
        </a>

        <div class="profile-page-heading">
            <h2>My Profile</h2>
            <p>Manage your personal and academic information.</p>
        </div>

        <?php if ($success !== ""): ?>
            <div class="profile-success">
                <i class="fa-solid fa-circle-check"></i>
                <?php echo htmlspecialchars($success); ?>
            </div>
        <?php endif; ?>

        <?php if ($error !== ""): ?>
            <div class="profile-error">
                <i class="fa-solid fa-circle-exclamation"></i>
                <?php echo htmlspecialchars($error); ?>
            </div>
        <?php endif; ?>

        <div class="profile-card">
            <div class="profile-summary">
                <div class="profile-large-avatar">
                    <?php echo htmlspecialchars($avatarLetter); ?>
                </div>

                <div class="profile-summary-info">
                    <h2>
                        <?php echo htmlspecialchars($displayName); ?>
                    </h2>
                    <p>
                        <?php echo htmlspecialchars($user["user_role"]); ?>
                    </p>
                </div>
            </div>

            <div class="profile-divider"></div>
            <form
                method="POST"
                action=""
                class="profile-form">
                <div class="profile-form-grid">
                    <div class="profile-field">
                        <label for="full_name">
                            Full Name
                        </label>
                        <input
                            type="text"
                            id="full_name"
                            name="full_name"
                            value="<?php
                            echo htmlspecialchars($user["full_name"]); ?>"
                            required>
                    </div>

                    <div class="profile-field">
                        <label for="nickname">
                            Nickname
                        </label>
                        <input
                            type="text"
                            id="nickname"
                            name="nickname"
                            value="<?php
                            echo htmlspecialchars($user["nickname"] ?? ""); ?>"
                            required>
                    </div>

                    <div class="profile-field">
                        <label>
                            Student ID
                        </label>
                        <input
                            type="text"
                            value="<?php
                            echo htmlspecialchars($user["user_code"]); ?>"
                            disabled>
                        <small>
                            Student ID cannot be changed.
                        </small>
                    </div>

                    <div class="profile-field">
                        <label for="email">
                            Email Address
                        </label>
                        <input
                            type="email"
                            id="email"
                            name="email"
                            value="<?php
                            echo htmlspecialchars($user["email"]); ?>"
                            required>
                    </div>

                    <div class="profile-field">
                        <label>
                            Programme
                        </label>
                        <input
                            type="text"
                            value="<?php
                            echo htmlspecialchars($user["programme_code"] ?? ""); ?>"
                            disabled>
                        <small>
                            Programme cannot be changed.
                        </small>
                    </div>

                    <div class="profile-field">
                        <label for="semester">
                            Semester
                        </label>
                        <select
                            id="semester"
                            name="semester"
                            required>
                            <option
                                value=""
                                disabled>
                                Choose your semester
                            </option>

                            <?php for ($i = 1; $i <= 7; $i++): ?>
                                <option
                                    value="<?php echo $i; ?>"
                                    <?php
                                    if (
                                        (string)$user["semester"] === (string)$i
                                    ) {
                                        echo "selected";
                                    }
                                    ?>>
                                    Semester <?php echo $i; ?>
                                </option>
                            <?php endfor; ?>
                        </select>
                    </div>

                    <div class="profile-field">
                        <label for="section">
                            Section
                        </label>
                        <select
                        id="section"
                        name="section"
                        required>
                        <option
                        value=""
                        disabled>
                        Choose your section
                    </option>
                    <?php for ($i = 1; $i <= 6; $i++): ?>
                        <option
                        value="<?php echo $i; ?>"
                        <?php
                        if (
                            (string)$user["section"] === (string)$i
                ) {
                    echo "selected";
                }
                ?>>
                Section <?php echo $i; ?>
            </option>
        <?php endfor; ?>
    </select>
</div>

                    <div class="profile-field">
                        <label>
                            Account Role
                        </label>
                        <input
                            type="text"
                            value="<?php
                            echo htmlspecialchars($user["user_role"]); ?>"
                            disabled>
                        <small>
                            Account role cannot be changed.
                        </small>
                    </div>
                </div>

                <div class="profile-actions">
                    <a
                        href="change_password.php"
                        class="change-password-button">
                        <i class="fa-solid fa-lock"></i>
                        Change Password
                    </a>
                    <button
                        type="submit"
                        class="save-profile-button">
                        <i class="fa-solid fa-floppy-disk"></i>
                        Save Changes
                    </button>
                </div>
            </form>
        </div>
    </div>
</main>
</body>
</html>