<?php

require_once "db.php";

$emailError = "";
$email = "";

if ($_SERVER["REQUEST_METHOD"] === "POST") {
    $email = trim($_POST["email"] ?? "");

    if ($email === "") {
        $emailError = "Please enter your email.";

    } else {
        $sql = "SELECT user_id FROM Users WHERE email = ?";
        $stmt = $conn->prepare($sql);

        if ($stmt) {
            $stmt->bind_param("s", $email);
            $stmt->execute();
            $result = $stmt->get_result();

            if ($result->num_rows === 1) {

                header(
                    "Location: reset_password.php?email=" .
                    urlencode($email)
                );
                exit;

            } else {
                $emailError =
                    "Email address not found. Please check and try again.";
            }
            $stmt->close();

        } else {
            $emailError =
                "Unable to process your request.";
        }
    }
}

?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Forgot Password</title>
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
                    <div class="passord-wrapper">
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
        <a
            href="index.php"
            class="password-modal-close"
            aria-label="Back to login">
            <i class="fa-solid fa-xmark"></i>
        </a>
        <div class="password-modal-icon">
            <i class="fa-solid fa-lock"></i>
        </div>

        <div class="password-modal-title">
            <h2>Forgot Password?</h2>
            <p>
                Enter your registered email to continue.
            </p>
        </div>

        <form
            action="forgot_password.php"
            method="POST">
            <div class="form-group">
                <label for="email">
                    Email
                </label>
                <input
                    type="email"
                    id="email"
                    name="email"
                    placeholder="Enter your registered email"
                    value="<?php echo htmlspecialchars($email); ?>"
                    required>
                <?php if ($emailError !== ""): ?>
                    <p class="login-error">
                        <?php echo htmlspecialchars($emailError); ?>
                    </p>
                <?php endif; ?>
            </div>

            <div class="password-modal-actions">
                <a
                    href="index.php"
                    class="modal-back-button">
                    Cancel
                </a>
                <button
                    type="submit"
                    class="modal-continue-button">
                    Continue
                </button>
            </div>
        </form>
    </div>
</div>

</body>
</html>