<?php

session_start();

require_once "db.php";

$passwordError = "";
$registrationError = "";
$registrationSuccess = false;
$fullname = "";
$nickname = "";
$studentid = "";
$email = "";
$programme = "";
$semester = "";
$section = "";

if ($_SERVER["REQUEST_METHOD"] === "POST") {
    $fullname = trim($_POST["fullname"] ?? "");
    $nickname = trim($_POST["nickname"] ?? "");
    $studentid = trim($_POST["studentid"] ?? "");
    $email = trim($_POST["email"] ?? "");
    $programme = trim($_POST["programme"] ?? "");
    $semester = trim($_POST["semester"] ?? "");
    $section = trim($_POST["section"] ?? "");
    $password = $_POST["password"] ?? "";

    if (
        $fullname === "" ||
        $nickname === "" ||
        $studentid === "" ||
        $email === "" ||
        $programme === "" ||
        $semester === "" ||
        $section === "" ||
        $password === ""
    ) {
        $registrationError = "Please fill in all fields.";
    } elseif (
        strlen($password) < 8 ||
        !preg_match('/[A-Z]/', $password) ||
        !preg_match('/[0-9]/', $password) ||
        !preg_match('/[^A-Za-z0-9]/', $password)
    ) {
        $passwordError = "Password must be at least 8 characters and include an uppercase letter, a number and a special character.";

    } else {

    $hashedPassword = password_hash($password, PASSWORD_DEFAULT);

    $sql = "INSERT INTO Users
            (user_code, full_name, nickname, email, password, user_role, programme_code, semester, section)
            VALUES (?, ?, ?, ?, ?, 'Student', ?, ?, ?)";

    $stmt = $conn->prepare($sql);

    if (!$stmt) {
        $registrationError = "Unable to process registration.";

    } else {
    $stmt->bind_param (
        "ssssssss",
        $studentid,
        $fullname,
        $nickname,
        $email,
        $hashedPassword,
        $programme,
        $semester,
        $section
    );
    
    try {
        if ($stmt->execute()) {
            $registrationSuccess = true;
        }
    } catch (mysqli_sql_exception $e) {
        if ($e->getCode() == 1062) {
            $registrationError = "The Student ID or email has already been registered.";
        } else {
            $registrationError = "Registration failed. Please try again.";
        }
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
        <title>Sign Up Page</title>
        <link rel="stylesheet" href="css/style.css">
        <link rel="stylesheet"
      href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
    </head>

    <body>
        <div class="page-container">

                <div class="signup-container signup-split">
    <div class="signup-left">
        <div class="signup-left-content">
            <div class="signup-brand-icon">
                <i class="fa-solid fa-users"></i>
            </div>
            <h1>Assignment<br>Contribution</h1>
            <p class="signup-tagline">
                Recognition. Fairness. Transparency.
            </p>
            <p class="signup-description">
                A simple space to track contributions, share feedback and recognise every team member's effort.
            </p>

            <div class="signup-features">

                <div class="signup-feature">
                    <i class="fa-solid fa-check"></i>
                    <span>Track Contributions</span>
                </div>

                <div class="signup-feature">
                    <i class="fa-solid fa-check"></i>
                    <span>Give Peer Feedback</span>
                </div>

                <div class="signup-feature">
                    <i class="fa-solid fa-check"></i>
                    <span>Recognise Team Effort</span>
                </div>
            </div>
        </div>
    </div>

    <div class="signup-right">
        <div class="signup-title">
            <h2>Create Account</h2>
            <p>Register to get started.</p>
        </div>

                <?php if ($registrationError !== ""): ?>
                    <p class="registration-error">
                        <?php echo htmlspecialchars($registrationError); ?>
                    </p>
                <?php endif; ?>

        <form action="signup.php" method="POST" id="signupForm">
            <div class="signup-form-grid">
            <div class="form-group">
                <label for="fullname">Full Name</label>
                <input
                    type="text"
                    id="fullname"  
                    name="fullname"
                    placeholder="Enter your full Name"
                    value="<?php echo htmlspecialchars($fullname); ?>"
                    required>    
            </div>

             <div class="form-group">
                <label for="nickname">Nickname</label>
                <input
                    type="text"
                    id="nickname"  
                    name="nickname"
                    placeholder="Enter your nickname"
                    value="<?php echo htmlspecialchars($nickname); ?>"
                    required>    
            </div>

            <div class="form-group">
                <label for="studentid">Student ID</label>
                <input
                    type="text"
                    id="studentid"
                    name="studentid"
                    placeholder="Enter your student ID"
                    value="<?php echo htmlspecialchars($studentid); ?>"
                    required>
            </div>    

            <div class="form-group">
                <label for="email">Student Email</label>
                <input
                    type="email"
                    id="email"
                    name="email"
                    placeholder="Enter your student email"
                    value="<?php echo htmlspecialchars($email); ?>"
                    required>
            </div>

            <div class="form-group">
                <label for="programme">Programme</label>
                <select
                    id="programme"
                    name="programme"
                    required>
                    <option value="" disabled 
                        <?php echo ($programme === "") ? "selected" : ""; ?>>Choose your programme code</option>
                    <option value="FA001"
                        <?php echo ($programme === "FA001") ? "selected" : ""; ?>>FA001
                    </option>
                    <option value="FC001"
                        <?php echo ($programme === "FC001") ? "selected" : ""; ?>>FC001
                    </option>
                    <option value="FT001"
                        <?php echo ($programme === "FT001") ? "selected" : ""; ?>>FT001
                    </option>
                    <option value="AA103"
                        <?php echo ($programme === "AA103") ? "selected" : ""; ?>>AA103
                    </option>
                    <option value="CC101"
                        <?php echo ($programme === "CC101") ? "selected" : ""; ?>>CC101
                    </option>
                    <option value="BK101"
                        <?php echo ($programme === "BK101") ? "selected" : ""; ?>>BK101
                    </option>
                    <option value="AA201"
                        <?php echo ($programme === "AA201") ? "selected" : ""; ?>>AA201
                    </option>
                    <option value="AB201"
                        <?php echo ($programme === "AB201") ? "selected" : ""; ?>>AB201
                    </option>
                    <option value="AB202"
                        <?php echo ($programme === "AB202") ? "selected" : ""; ?>>AB202
                    </option>
                    <option value="CT207"
                        <?php echo ($programme === "CT207") ? "selected" : ""; ?>>CT207
                    </option>
                    <option value="AB203"
                        <?php echo ($programme === "AB203") ? "selected" : ""; ?>>AB203
                    </option>
                    <option value="BE201"
                        <?php echo ($programme === "BE201") ? "selected" : ""; ?>>BE201
                    </option>
                    <option value="BE202"
                        <?php echo ($programme === "BE202") ? "selected" : ""; ?>>BE202
                    </option>
                    <option value="BK201"
                        <?php echo ($programme === "BK201") ? "selected" : ""; ?>>BK201
                    </option>
                    <option value="BE203"
                        <?php echo ($programme === "BE203") ? "selected" : ""; ?>>BE203
                    </option>
                    <option value="CC204"
                        <?php echo ($programme === "CC204") ? "selected" : ""; ?>>CC204
                    </option>
                    <option value="CM201"
                        <?php echo ($programme === "CM201") ? "selected" : ""; ?>>CM201
                    </option>
                    <option value="CC203"
                        <?php echo ($programme === "CC203") ? "selected" : ""; ?>>CC203
                    </option>
                    <option value="CT204"
                        <?php echo ($programme === "CT204") ? "selected" : ""; ?>>CT204
                    </option>
                    <option value="CT206"
                        <?php echo ($programme === "CT206") ? "selected" : ""; ?>>CT206
                    </option>
                </select>
            </div>

             <div class="form-group">
                <label for="semester">Semester</label>
                <select
                    id="semester"
                    name="semester"
                    required>
                    <option value="" disabled
                        <?php echo ($semester === "") ? "selected" : ""; ?>>Choose your semester</option>
                    <option value="1"
                        <?php echo ($semester === "1") ? "selected" : ""; ?>>
                        Semester 1
                    </option>
                    <option value="2"
                        <?php echo ($semester === "2") ? "selected" : ""; ?>>
                        Semester 2
                    </option>
                    <option value="3"
                        <?php echo ($semester === "3") ? "selected" : ""; ?>>
                        Semester 3
                    </option>
                    <option value="4"
                        <?php echo ($semester === "4") ? "selected" : ""; ?>>
                        Semester 4
                    </option>
                    <option value="5"
                        <?php echo ($semester === "5") ? "selected" : ""; ?>>
                        Semester 5
                    </option>
                    <option value="6"
                        <?php echo ($semester === "6") ? "selected" : ""; ?>>
                        Semester 6
                    </option>
                    <option value="7"
                        <?php echo ($semester === "7") ? "selected" : ""; ?>>
                        Semester 7
                    </option>
                </select>
            </div>

            <div class="form-group">
                <label for="section">Section</label>
                <select
                    id="section"
                    name="section"
                    required>
                    <option value="" disabled 
                        <?php echo ($section === "") ? "selected" : ""; ?>>Choose your section</option>
                    <option value="1"
                        <?php echo ($section === "1") ? "selected" : ""; ?>>1
                    </option>
                    <option value="2"
                        <?php echo ($section === "2") ? "selected" : ""; ?>>2
                    </option>
                    <option value="3"
                        <?php echo ($section === "3") ? "selected" : ""; ?>>3
                    </option>
                    <option value="4"
                        <?php echo ($section === "4") ? "selected" : ""; ?>>4
                    </option>
                    <option value="5"
                        <?php echo ($section === "5") ? "selected" : ""; ?>>5
                    </option>
                    <option value="6"
                        <?php echo ($section === "6") ? "selected" : ""; ?>>6
                    </option>
                </select>
            </div>

            <div class="form-group">
                <label for="password">Password</label>
                <div class="password-wrapper">
                <input
                   type="password"
                   id="password"
                   name="password"
                   placeholder="Create your password"
                   required>

                <button
                type="button"
                class="password-toggle"
                id="togglePassword"
                aria-label="Show password">
                <i class="fa-solid fa-eye" id="eyeIcon"></i>
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
                    <button type="button" onclick="window.location.href='index.php'">
                        OK
                    </button>
                </div>
            </div>
            <?php endif; ?>

            <?php if ($registrationError !== ""): ?>
                <div class="success-modal">
                    <div class="success-modal-box">
                        <div class="status-icon error-icon">
                            !
                        </div>
                        <h2>Registration Failed</h2>
                        <p><?php echo htmlspecialchars($registrationError); ?></p>
                        <button type="button" onclick="window.location.href='signup.php'">
                            OK
                        </button>
                    </div>
                </div>
                <?php endif; ?>
                
      </div>

<div class="signup-form-bottom">
    <p class="signup-login-link">
        Already have an account?
        <a href="index.php">Login</a>
    </p>
    <button type="submit" class="signup-button">
        Create Account
    </button>

</div>

</form>

            <script>
            const passwordInput = document.getElementById("password");
            const togglePassword = document.getElementById("togglePassword");
            const passwordWarning = document.getElementById("passwordWarning");
            const signupForm = document.getElementById("signupForm");

            const eyeIcon = document.getElementById("eyeIcon");

togglePassword.addEventListener("click", function () {
    if (passwordInput.type === "password") {
        passwordInput.type = "text";
        eyeIcon.classList.remove("fa-eye");
        eyeIcon.classList.add("fa-eye-slash");

    } else {
        passwordInput.type = "password";
        eyeIcon.classList.remove("fa-eye-slash");
        eyeIcon.classList.add("fa-eye");
    }
});

            signupForm.addEventListener("submit", function (event) {
                const password = passwordInput.value;
                const hasEightCharacters = password.length >= 8;
                const hasNumber = /[0-9]/.test(password);
                const hasSpecialCharacter = /[^A-Za-z0-9]/.test(password);
                const hasUppercase = /[A-Z]/.test(password);

                if (
                    !hasEightCharacters ||
                    !hasUppercase ||
                    !hasNumber ||
                    !hasSpecialCharacter
                ) {
                    event.preventDefault();
                    passwordWarning.textContent = "Password must be at least 8 characters and include an uppercase, a number and a special character.";
                    passwordWarning.style.display = "block"
                    passwordInput.focus();
                    return;
                } 
                    passwordWarning.style.display = "none";
            });
            </script>
</div>
</div>
</div>
    </body>
    </html>