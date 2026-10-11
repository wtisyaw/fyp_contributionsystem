<?php
session_start();
require_once "db.php";

$error = "";
$registrationSuccess = false;

$user_code = "";
$full_name = "";
$nickname = "";
$email = "";
$faculty_code = "";

if ($_SERVER["REQUEST_METHOD"] === "POST") {
    $user_code = trim($_POST["user_code"] ?? "");
    $full_name = trim($_POST["full_name"] ?? "");
    $nickname = trim($_POST["nickname"] ?? "");
    $email = trim($_POST["email"] ?? "");
    $faculty_code = trim($_POST["faculty_code"] ?? "");
    $password = $_POST["password"] ?? "";

    if (
        $user_code === "" ||
        $full_name === "" ||
        $nickname === "" ||
        $email === "" ||
        $faculty_code === "" ||
        $password === ""
    ) {
        $error = "Please fill in all fields.";
    } 
    else {
        $sql = "SELECT user_id FROM Users WHERE email = ? OR user_code = ?";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("ss", $email, $user_code);
        $stmt->execute();
        $result = $stmt->get_result();

        if ($result->num_rows > 0) {
            $error = "Email or Lecturer ID already exists.";
        } else {
            $hashed_password = password_hash($password, PASSWORD_DEFAULT);
            $user_role = "Lecturer";

            $sql = "INSERT INTO Users
                    (user_code, full_name, nickname, email, password, user_role, faculty_code)
                    VALUES (?, ?, ?, ?, ?, ?, ?)";

            $stmt = $conn->prepare($sql);

            $stmt->bind_param(
                "sssssss",
                $user_code,
                $full_name,
                $nickname,
                $email,
                $hashed_password,
                $user_role,
                $faculty_code
            );

            if ($stmt->execute()) {
                $registrationSuccess = true;
            } else {
                $error = "Unable to create lecturer account.";
            }
        }
    }
}
?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">

    <meta
        name="viewport"
        content="width=device-width, initial-scale=1.0">
    <title>Lecturer Registration | FairPeer</title>

    <link
        rel="stylesheet"
        href="css/lecturer-register.css">
    <link
        rel="stylesheet"
        href="css/style.css">
    <link
        rel="stylesheet"
        href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
</head>

<body>

<div class="lecturer-page-container">

    <div class="lecturer-signup-container">

        <!-- LEFT SIDE -->
        <div class="lecturer-signup-left">

            <div class="lecturer-left-content">

                <div class="lecturer-brand-icon">
                    <i class="fa-solid fa-users"></i>
                </div>

                <h1>
                    Lecturer<br>
                    Workspace
                </h1>

                <p class="lecturer-tagline">
                    Guide. Manage. Empower.
                </p>

                <p class="lecturer-description">
                    Manage your subjects, create assignments
                    and monitor student progress in one place.
                </p>

                <div class="lecturer-features">

                    <div class="lecturer-feature">
                        <i class="fa-solid fa-check"></i>
                        <span>Manage Subjects</span>
                    </div>

                    <div class="lecturer-feature">
                        <i class="fa-solid fa-check"></i>
                        <span>Create Assignments</span>
                    </div>

                    <div class="lecturer-feature">
                        <i class="fa-solid fa-check"></i>
                        <span>Monitor Student Progress</span>
                    </div>

                </div>

            </div>

        </div>

        <!-- RIGHT SIDE -->
        <div class="lecturer-signup-right">

            <div class="lecturer-signup-title">

                <h2>Create Lecturer Account</h2>

                <p>
                    Register to get started.
                </p>

            </div>

            <?php if ($error !== ""): ?>

                <p class="lecturer-registration-error">
                    <?php echo htmlspecialchars($error); ?>
                </p>

            <?php endif; ?>

            <form method="POST" action="">

                <div class="lecturer-form-grid">

                    <!-- FULL NAME -->
                    <div class="lecturer-form-group">

                        <label for="full_name">
                            Full Name
                        </label>

                        <input
                            type="text"
                            id="full_name"
                            name="full_name"
                            placeholder="Enter your full name"
                            value="<?php echo htmlspecialchars($full_name); ?>"
                            required
                        >

                    </div>

                    <!-- NICKNAME -->
                    <div class="lecturer-form-group">

                        <label for="nickname">
                            Nickname
                        </label>

                        <input
                            type="text"
                            id="nickname"
                            name="nickname"
                            placeholder="Enter your nickname"
                            value="<?php echo htmlspecialchars($nickname); ?>"
                            required
                        >

                    </div>

                    <!-- LECTURER ID -->
                    <div class="lecturer-form-group">

                        <label for="user_code">
                            Lecturer ID
                        </label>

                        <input
                            type="text"
                            id="user_code"
                            name="user_code"
                            placeholder="Enter your lecturer ID"
                            value="<?php echo htmlspecialchars($user_code); ?>"
                            required
                        >

                    </div>

                    <!-- LECTURER EMAIL -->
                    <div class="lecturer-form-group">

                        <label for="email">
                            Lecturer Email
                        </label>

                        <input
                            type="email"
                            id="email"
                            name="email"
                            placeholder="Enter your lecturer email"
                            value="<?php echo htmlspecialchars($email); ?>"
                            required
                        >

                    </div>

                    <!-- FACULTY -->
                    <div class="lecturer-form-group">

                        <label for="faculty_code">
                            Faculty
                        </label>

                        <select
                            id="faculty_code"
                            name="faculty_code"
                            required>

                            <option
                                value=""
                                disabled
                                <?php echo ($faculty_code === "") ? "selected" : ""; ?>>
                                Choose your faculty
                            </option>

                            <option
                                value="FCOM"
                                <?php echo ($faculty_code === "FCOM") ? "selected" : ""; ?>>
                                FCOM
                            </option>
                            <option
                                value="FABA"
                                <?php echo ($faculty_code === "FABA") ? "selected" : ""; ?>>
                                FABA
                            </option>
                            <option
                                value="FESSH"
                                <?php echo ($faculty_code === "FESSH") ? "selected" : ""; ?>>
                                FESSH
                            </option>
                            <option
                                value="IPS"
                                <?php echo ($faculty_code === "IPS") ? "selected" : ""; ?>>
                                IPS
                            </option>
                            <option
                                value="IGS"
                                <?php echo ($faculty_code === "IGS") ? "selected" : ""; ?>>
                                IGS
                            </option>
                            <option
                                value="CIGLS"
                                <?php echo ($faculty_code === "CIGLS") ? "selected" : ""; ?>>
                                CIGLS
                            </option>

                        </select>

                    </div>

                    <!-- PASSWORD -->
                    <div class="lecturer-form-group">

                        <label for="lecturerPassword">
                            Password
                        </label>

                        <div class="lecturer-password-wrapper">

                            <input
                                type="password"
                                id="lecturerPassword"
                                name="password"
                                placeholder="Create your password"
                                required>

                            <button
                                type="button"
                                class="lecturer-password-toggle"
                                onclick="toggleLecturerPassword('lecturerPassword', this)">
                                <i class="fa-solid fa-eye"></i>
                            </button>
                        </div>

                        <p id="passwordWarning" class="password-warning">
                        Password must be at least 8 characters and include an uppercase letter and a number and a special character.
                      </p>
                    </div>

<?php if ($registrationSuccess): ?>
    <div class="success-modal">
        <div class="success-modal-box">
            <div class="status-icon success-icon"></div>

            <h2>Registration Successful!</h2>

            <p>Your account has been created successfully.</p>
            <p>Please login to continue.</p>

            <button type="button"
                    onclick="window.location.href='index.php'">
                OK
            </button>
        </div>
    </div>
<?php endif; ?>


<?php if ($error !== ""): ?>
    <div class="success-modal">
        <div class="success-modal-box">

            <div class="status-icon error-icon">
                !
            </div>

            <h2>Registration Failed</h2>

            <p><?php echo htmlspecialchars($error); ?></p>

            <button type="button"
                    onclick="window.location.href='lecturer_register.php'">
                OK
            </button>

        </div>
    </div>
<?php endif; ?>
            </div>

                <div class="lecturer-form-bottom">
                    <p class="lecturer-login-link">
                        Already have an account?
                        <a href="index.php">
                            Login
                        </a>
                    </p>

                    <button
                        type="submit"
                        class="lecturer-signup-button">
                        Create Account
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
function toggleLecturerPassword(inputId, button) {

    const input = document.getElementById(inputId);
    const icon = button.querySelector("i");

    if (input.type === "password") {

        input.type = "text";

        icon.classList.remove("fa-eye");
        icon.classList.add("fa-eye-slash");

    } else {

        input.type = "password";

        icon.classList.remove("fa-eye-slash");
        icon.classList.add("fa-eye");
    }
}
</script>

</body>
</html>