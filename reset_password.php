<?php

require_once "db.php";

$passwordError = "";
$resetSuccess = false;

$email = $_GET["email"] ?? $_POST["email"] ?? "";

if ($_SERVER["REQUEST_METHOD"] === "POST") {

    $email = trim($_POST["email"] ?? "");
    $password = $_POST["password"] ?? "";
    $confirmPassword = $_POST["confirm_password"] ?? "";

    if ($email === "") {
        $passwordError = "Invalid reset request. Please try again.";

    } elseif ($password === "" || $confirmPassword === "") {
        $passwordError = "Please fill in both password fields.";

    } elseif (
        strlen($password) < 8 ||
        !preg_match('/[A-Z]/', $password) ||
        !preg_match('/[0-9]/', $password) ||
        !preg_match('/[^A-Za-z0-9]/', $password)
    ) {

        $passwordError =
            "Password must be at least 8 characters and include an uppercase letter, a number, and a special character.";

    } elseif ($password !== $confirmPassword) {
        $passwordError = "Passwords do not match.";

    } else {
        $hashedPassword = password_hash(
            $password,
            PASSWORD_DEFAULT
        );
        $sql = "UPDATE Users
                SET password = ?
                WHERE email = ?";
        $stmt = $conn->prepare($sql);

        if (!$stmt) {
            $passwordError =
                "Unable to process your request.";

        } else {
            $stmt->bind_param(
                "ss",
                $hashedPassword,
                $email
            );

            if ($stmt->execute()) {
                if ($stmt->affected_rows >= 0) {
                    $resetSuccess = true;
                }

            } else {
                $passwordError =
                    "Unable to reset password. Please try again.";
            }
            $stmt->close();
        }
    }
}

?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Reset Password</title>
    <link rel="stylesheet" href="css/style.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
</head>

<body>
<div class="forgot-background">
    <div class="page-container">
        <div class="signup-container signup-split login-split">
            <div class="signup-left">
                <div class="signup-left-content">
                    <div class="signup-brand-icon">
                        <i class="fa-solid fa-users"></i>
                    </div>

                    <h1>
                        Assignment<br>
                        Contribution
                    </h1>
                    <p class="signup-tagline">
                        Recognition. Fairness. Transparency.
                    </p>
                    <p class="signup-description">
                        A simple space to track contributions, share feedback and recognise every team member's effort.
                    </p>
                    <div class="signup-features">
                        <div class="signup-feature">
                            <i class="fa-solid fa-check"></i>
                            <span>
                                Track Contributions
                            </span>
                        </div>

                        <div class="signup-feature">
                            <i class="fa-solid fa-check"></i>
                            <span>
                                Give Peer Feedback
                            </span>
                        </div>

                        <div class="signup-feature">
                            <i class="fa-solid fa-check"></i>
                            <span>
                                Recognise Team Effort
                            </span>
                        </div>
                    </div>
                </div>
            </div>

            <div class="signup-right login-right">
                <div class="login-title">
                    <h2>Welcome Back</h2>
                    <p>
                        Sign in to access your account.
                    </p>
                </div>

                <div class="form-group">
                    <label>Email</label>
                    <input
                        type="email"
                        placeholder="Enter your email"
                        disabled>
                </div>

                <div class="form-group">
                    <label>Password</label>
                    <div class="password-wrapper">
                        <input
                            type="password"
                            placeholder="Enter your password"
                            disabled>
                        <button
                            type="button"
                            class="password-toggle"
                            disabled>
                            <i class="fa-solid fa-eye"></i>
                        </button>
                    </div>

                    <div class="forgot-password">
                        <a href="#">
                            Forgot your password?
                        </a>
                    </div>
                </div>

                <div class="login-form-bottom">
                    <p class="signup-link">
                        Don't have an account?
                        <a href="signup.php">
                            Sign Up
                        </a>
                    </p>
                    <button
                        type="button"
                        class="login-button">
                        Login
                    </button>
                </div>
            </div>
        </div>
    </div>
</div>

<div class="password-modal-overlay">
    <div class="password-modal">
        <?php if ($resetSuccess): ?>
            <div class="password-modal-icon">
                <i class="fa-solid fa-check"></i>
            </div>

            <div class="password-modal-title">
                <h2>Password Reset!</h2>
                <p>
                    Your password has been reset successfully.
                    You can now login with your new password.
                </p>
            </div>

            <div class="reset-success-action">
                <a
                    href="index.php"
                    class="modal-continue-button">
                    Back to Login
                </a>
            </div>

        <?php else: ?>
            <a
                href="index.php"
                class="password-modal-close"
                aria-label="Back to login">
                <i class="fa-solid fa-xmark"></i>
            </a>
            <div class="password-modal-icon">
                <i class="fa-solid fa-key"></i>
            </div>

            <div class="password-modal-title">
                <h2>Reset Password</h2>
                <p>
                    Create a new password for your account.
                </p>
            </div>

            <form
                action="reset_password.php"
                method="POST">
                <input
                    type="hidden"
                    name="email"
                    value="<?php echo htmlspecialchars($email); ?>">

                <div class="form-group">
                    <label for="password">
                        New Password
                    </label>
                    <div class="password-wrapper">
                        <input
                            type="password"
                            id="password"
                            name="password"
                            placeholder="Enter your new password"
                            required>
                        <button
                            type="button"
                            class="password-toggle"
                            id="togglePassword"
                            aria-label="Show password">
                            <i class="fa-solid fa-eye"></i>
                        </button>
                    </div>
                </div>

                <div class="form-group reset-confirm-group">
                    <label for="confirm_password">
                        Confirm Password
                    </label>

                    <div class="password-wrapper">
                        <input
                            type="password"
                            id="confirm_password"
                            name="confirm_password"
                            placeholder="Re-enter your new password"
                            required>
                        <button
                            type="button"
                            class="password-toggle"
                            id="toggleConfirmPassword"
                            aria-label="Show password">
                            <i class="fa-solid fa-eye"></i>
                        </button>
                    </div>
                </div>

                <p class="password-requirement">
                    <i class="fa-solid fa-circle-info"></i>
                    Password must be at least 8 characters and include an uppercase letter, a number, and a special character.
                </p>

                <?php if ($passwordError !== ""): ?>
                    <p class="login-error">
                        <?php echo htmlspecialchars($passwordError); ?>
                    </p>
                <?php endif; ?>

                <div class="password-modal-actions">
                    <a
                        href="index.php"
                        class="modal-back-button">
                        Cancel
                    </a>
                    <button
                        type="submit"
                        class="modal-continue-button">
                        Reset Password
                    </button>
                </div>
            </form>
        <?php endif; ?>
    </div>
</div>

<script>
document.addEventListener("DOMContentLoaded", function () {
    const passwordInput =
        document.getElementById("password");
    const confirmPasswordInput =
        document.getElementById("confirm_password");
    const togglePassword =
        document.getElementById("togglePassword");
    const toggleConfirmPassword =
        document.getElementById("toggleConfirmPassword");

    if (togglePassword && passwordInput) {
        togglePassword.addEventListener("click", function () {
            const icon = this.querySelector("i");

            if (passwordInput.type === "password") {
                passwordInput.type = "text";
                icon.classList.remove("fa-eye");
                icon.classList.add("fa-eye-slash");
                this.setAttribute(
                    "aria-label",
                    "Hide password"
                );

            } else {
                passwordInput.type = "password";
                icon.classList.remove("fa-eye-slash");
                icon.classList.add("fa-eye");
                this.setAttribute(
                    "aria-label",
                    "Show password"
                );
            }
        });
    }

    if (toggleConfirmPassword && confirmPasswordInput) {
        toggleConfirmPassword.addEventListener("click", function () {
            const icon = this.querySelector("i");

            if (confirmPasswordInput.type === "password") {
                confirmPasswordInput.type = "text";
                icon.classList.remove("fa-eye");
                icon.classList.add("fa-eye-slash");
                this.setAttribute(
                    "aria-label",
                    "Hide password"
                );

            } else {
                confirmPasswordInput.type = "password";
                icon.classList.remove("fa-eye-slash");
                icon.classList.add("fa-eye");
                this.setAttribute(
                    "aria-label",
                    "Show password"
                );
            }
        });
    }
});
</script>

</body>
</html>