<?php

$loginError = "";

if (isset($_GET["error"])) {

    if ($_GET["error"] === "password") {
        $loginError = "Password is incorrect. Please try again.";
    }
    elseif ($_GET["error"] === "login") {
        $loginError = "Email is not found.";
    }
    elseif ($_GET["error"] === "empty") {
        $loginError = "Please enter your email and password.";
    }
    elseif ($_GET["error"] === "system") {
        $loginError = "Unable to process login. Please try again.";
    }
    elseif ($_GET["error"] === "role") {
        $loginError = "Your account role is not recognised.";
    }
}
?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>
        Login Contribution Documentation System
    </title>
    <link rel="stylesheet" href="css/style.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
</head>

<body class="login-page">
<div class="login-page-container">
    <div class="login-container login-split">
        <div class="login-left">
            <div class="login-left-content">
                <div class="login-brand-icon">
                    <i class="fa-solid fa-users"></i>
                </div>
                <h1>
                    Assignment<br>
                    Contribution
                </h1>
                <p class="login-tagline">
                    Recognition. Fairness. Transparency.
                </p>
                <p class="login-description">
                    A simple space to track contributions, share feedback and recognise every team member's effort.
                </p>

                <div class="login-features">
                    <div class="login-feature">
                        <i class="fa-solid fa-check"></i>
                        <span>
                            Track Contributions
                        </span>
                    </div>
                    <div class="login-feature">
                        <i class="fa-solid fa-check"></i>
                        <span>
                            Give Peer Feedback
                        </span>
                    </div>
                    <div class="login-feature">
                        <i class="fa-solid fa-check"></i>
                        <span>
                            Recognise Team Effort
                        </span>
                    </div>
                </div>
            </div>
        </div>

        <div class="login-right">
            <div class="login-title">
                <h2>Welcome Back</h2>
                <p>
                    Sign in to access your account.
                </p>
            </div>
            <form
                action="login.php"
                method="POST"
                id="loginForm">
                <div class="form-group">
                    <label for="email">
                        Email
                    </label>
                    <input
                        type="email"
                        id="email"
                        name="email"
                        placeholder="Enter your email"
                        required>
                </div>

                <div class="form-group">
                    <label for="password">
                        Password
                    </label>

                    <div class="password-wrapper">
                        <input
                            type="password"
                            id="password"
                            name="password"
                            placeholder="Enter your password"
                            required>
                        <button
                            type="button"
                            class="password-toggle"
                            id="togglePassword"
                            aria-label="Show password">
                            <i class="fa-solid fa-eye"></i>
                        </button>
                    </div>

                    <div class="forgot-password">
                        <a href="forgot_password.php">
                            Forgot your password?
                        </a>
                    </div>

                    <?php if ($loginError !== ""): ?>
                        <p class="login-error">
                            <?php echo htmlspecialchars($loginError); ?>
                        </p>
                    <?php endif; ?>
                </div>

                <div class="login-form-bottom">
                <p class="signup-link">
                    Don't have an account?
                <a href="#" id="openAccountType">Sign Up</a>
                </p>
                <button
                    type="submit"
                    class="login-button">
                    Login
                </button>
            </form>
        </div>
    </div>
</div>

<div class="account-type-modal" id="accountTypeModal">
    <div class="account-type-box">
        <button type="button" class="account-type-close" id="closeAccountType">
            <i class="fa-solid fa-xmark"></i>
        </button>
        <h2>Create Account</h2>
        <p class="account-type-subtitle">Choose your account type to continue.</p>
        <div class="account-type-options">
            <a href="signup.php" class="account-type-card">
                <div class="account-type-icon">
                    <i class="fa-solid fa-user-graduate"></i>
                </div>
                <div>
                    <h3>Student</h3>
                    <p>Create a student account.</p>
                </div>
                <i class="fa-solid fa-chevron-right account-type-arrow"></i>
            </a>
            <a href="lecturer_register.php" class="account-type-card">
                <div class="account-type-icon">
                    <i class="fa-solid fa-chalkboard-user"></i>
                </div>
                <div>
                    <h3>Lecturer</h3>
                    <p>Create a lecturer account.</p>
                </div>
                <i class="fa-solid fa-chevron-right account-type-arrow"></i>
            </a>
        </div>
    </div>
</div>

<script>
document.addEventListener("DOMContentLoaded", function () {
    const passwordInput =
        document.getElementById("password");
    const togglePassword =
        document.getElementById("togglePassword");

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
            }

            else {
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
});
</script>

<script>
const accountTypeModal = document.getElementById("accountTypeModal");
const openAccountType = document.getElementById("openAccountType");
const closeAccountType = document.getElementById("closeAccountType");

openAccountType.addEventListener("click", function(event) {
    event.preventDefault();
    accountTypeModal.classList.add("show");
});

closeAccountType.addEventListener("click", function() {
    accountTypeModal.classList.remove("show");
});

accountTypeModal.addEventListener("click", function(event) {
    if (event.target === accountTypeModal) {
        accountTypeModal.classList.remove("show");
    }
});
</script>

</body>
</html>