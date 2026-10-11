<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once "db.php";

if (!isset($_SESSION["user_id"]) || $_SESSION["user_role"] !== "Lecturer") {
    header("Location: index.php");
    exit;
}

$lecturer_id = $_SESSION["user_id"];
$profileSuccess = "";
$profileError = "";
$openProfileModal = false;

if ($_SERVER["REQUEST_METHOD"] === "POST" && isset($_POST["save_lecturer_profile"])) {
    $full_name = trim($_POST["full_name"] ?? "");
    $nickname = trim($_POST["nickname"] ?? "");
    $email = trim($_POST["email"] ?? "");
    $openProfileModal = true;

    if ($full_name === "" || $nickname === "" || $email === "") {
        $profileError = "Please fill in all fields.";
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $profileError = "Please enter a valid email address.";
    } else {
        $sql = "SELECT user_id
                FROM Users
                WHERE email = ? AND user_id != ?
                LIMIT 1";

        $stmt = $conn->prepare($sql);
        $stmt->bind_param("si", $email, $lecturer_id);
        $stmt->execute();
        $emailResult = $stmt->get_result();

        if ($emailResult->num_rows > 0) {
            $profileError = "The email address is already registered.";
            $stmt->close();
        } else {
            $stmt->close();

            $sql = "UPDATE Users
                    SET full_name = ?, nickname = ?, email = ?
                    WHERE user_id = ?";

            $stmt = $conn->prepare($sql);
            $stmt->bind_param(
                "sssi",
                $full_name,
                $nickname,
                $email,
                $lecturer_id
            );

            if ($stmt->execute()) {
                $_SESSION["full_name"] = $full_name;
                $_SESSION["nickname"] = $nickname;
                $profileSuccess = "Profile updated successfully.";
            } else {
                $profileError = "Unable to update profile. Please try again.";
            }

            $stmt->close();
        }
    }
}

$sql = "SELECT user_code, full_name, nickname, email, user_role
        FROM Users
        WHERE user_id = ?
        LIMIT 1";

$stmt = $conn->prepare($sql);
$stmt->bind_param("i", $lecturer_id);
$stmt->execute();
$result = $stmt->get_result();
$lecturerProfile = $result->fetch_assoc();
$stmt->close();

if (!$lecturerProfile) {
    header("Location: index.php");
    exit;
}

$nickname = $lecturerProfile["nickname"];
?>

<div class="profile-modal-overlay<?php echo $openProfileModal ? " show" : ""; ?>" id="lecturerProfileModal">
    <div class="profile-modal">

        <button type="button" class="profile-modal-close" id="closeLecturerProfile">
            <i class="fa-solid fa-xmark"></i>
        </button>

        <div class="profile-modal-header">
            <h2>My Profile</h2>
            <p>Manage your personal information and account details.</p>
        </div>

        <?php if ($profileSuccess !== ""): ?>
            <div class="profile-success">
                <?php echo htmlspecialchars($profileSuccess); ?>
            </div>
        <?php endif; ?>

        <?php if ($profileError !== ""): ?>
            <div class="profile-error">
                <?php echo htmlspecialchars($profileError); ?>
            </div>
        <?php endif; ?>

        <div class="profile-modal-summary">
            <div class="lecturer-profile-avatar">
                <?php echo strtoupper(substr($lecturerProfile["nickname"], 0, 1)); ?>
            </div>

            <div class="lecturer-profile-info">
                <h3><?php echo htmlspecialchars($lecturerProfile["nickname"]); ?></h3>
                <p>Lecturer</p>
            </div>
        </div>

        <div class="profile-modal-divider"></div>

        <form method="POST">
            <div class="profile-modal-grid">

                <div class="profile-modal-field">
                    <label for="lecturerFullName">Full Name</label>
                    <input
                        type="text"
                        id="lecturerFullName"
                        name="full_name"
                        value="<?php echo htmlspecialchars($lecturerProfile["full_name"]); ?>"
                        required>
                </div>

                <div class="profile-modal-field">
                    <label for="lecturerNickname">Nickname</label>
                    <input
                        type="text"
                        id="lecturerNickname"
                        name="nickname"
                        value="<?php echo htmlspecialchars($lecturerProfile["nickname"]); ?>"
                        required>
                </div>

                <div class="profile-modal-field">
                    <label for="lecturerCode">Lecturer ID</label>
                    <input
                        type="text"
                        id="lecturerCode"
                        value="<?php echo htmlspecialchars($lecturerProfile["user_code"]); ?>"
                        disabled>
                    <small>Lecturer ID cannot be changed.</small>
                </div>

                <div class="profile-modal-field">
                    <label for="lecturerEmail">Email Address</label>
                    <input
                        type="email"
                        id="lecturerEmail"
                        name="email"
                        value="<?php echo htmlspecialchars($lecturerProfile["email"]); ?>"
                        required>
                </div>

                <div class="profile-modal-field">
                    <label for="lecturerRole">Account Role</label>
                    <input
                        type="text"
                        id="lecturerRole"
                        value="Lecturer"
                        disabled>
                    <small>Account role cannot be changed.</small>
                </div>

            </div>

            <div class="profile-modal-actions">

            <button type="button" class="change-password-button" id="openPasswordModal">
                <i class="fa-solid fa-lock"></i>
                Change Password
            </button>

                <button
                    type="submit"
                    name="save_lecturer_profile"
                    class="save-profile-button">
                    <i class="fa-solid fa-floppy-disk"></i>
                    Save Changes
                </button>

            </div>
        </form>
    </div>
</div>

<div class="password-modal-overlay" id="passwordModal">
    <div class="password-modal">
        <div class="password-modal-header">
            <div>
                <h2>Change Password</h2>
                <p>Enter your current password and choose a new password.</p>
            </div>

            <button type="button" class="password-modal-close" id="closePasswordModal" aria-label="Close">
                <i class="fa-solid fa-xmark"></i>
            </button>
        </div>

        <form action="change_password.php" method="POST" class="password-form">
            <div class="password-field">
                <label for="currentPassword">Current Password</label>
                <div class="password-input-wrapper">
                    <input type="password" id="currentPassword" name="current_password" required>
                    <button type="button" class="password-eye" data-target="currentPassword" aria-label="Show password">
                        <i class="fa-solid fa-eye"></i>
                    </button>
                </div>
            </div>

            <div class="password-field">
                <label for="newPassword">New Password</label>
                <div class="password-input-wrapper">
                    <input type="password" id="newPassword" name="new_password" required>
                    <button type="button" class="password-eye" data-target="newPassword" aria-label="Show password">
                        <i class="fa-solid fa-eye"></i>
                    </button>
                </div>
                <small>Minimum 8 characters, including an uppercase letter, a number and a special character.</small>
            </div>

            <div class="password-field">
                <label for="confirmPassword">Confirm New Password</label>
                <div class="password-input-wrapper">
                    <input type="password" id="confirmPassword" name="confirm_password" required>
                    <button type="button" class="password-eye" data-target="confirmPassword" aria-label="Show password">
                        <i class="fa-solid fa-eye"></i>
                    </button>
                </div>
            </div>

            <div class="password-modal-actions">
                <button type="button" class="back-profile-button" id="backToProfile">
                    <i class="fa-solid fa-arrow-left"></i>
                    Back to Profile
                </button>

                <button type="submit" class="update-password-button">
                    <i class="fa-solid fa-lock"></i>
                    Update Password
                </button>
            </div>
        </form>
    </div>
</div>

<script>
document.addEventListener("DOMContentLoaded", function () {
    const openProfile = document.getElementById("openLecturerProfile");
    const profileModal = document.getElementById("lecturerProfileModal");
    const closeProfile = document.getElementById("closeLecturerProfile");

    const openPassword = document.getElementById("openPasswordModal");
    const passwordModal = document.getElementById("passwordModal");
    const closePassword = document.getElementById("closePasswordModal");
    const backToProfile = document.getElementById("backToProfile");

    if (openProfile && profileModal) {
        openProfile.addEventListener("click", function () {
            profileModal.classList.add("show");
        });
    }

    if (closeProfile && profileModal) {
        closeProfile.addEventListener("click", function () {
            profileModal.classList.remove("show");
        });
    }

    if (profileModal) {
        profileModal.addEventListener("click", function (event) {
            if (event.target === profileModal) {
                profileModal.classList.remove("show");
            }
        });
    }

    if (openPassword && passwordModal && profileModal) {
        openPassword.addEventListener("click", function () {
            profileModal.classList.remove("show");
            passwordModal.classList.add("show");
        });
    }

    if (closePassword && passwordModal) {
        closePassword.addEventListener("click", function () {
            passwordModal.classList.remove("show");
        });
    }

    if (backToProfile && passwordModal && profileModal) {
        backToProfile.addEventListener("click", function () {
            passwordModal.classList.remove("show");
            profileModal.classList.add("show");
        });
    }

    if (passwordModal) {
        passwordModal.addEventListener("click", function (event) {
            if (event.target === passwordModal) {
                passwordModal.classList.remove("show");
            }
        });
    }

    document.querySelectorAll(".password-eye").forEach(function (button) {
        button.addEventListener("click", function () {
            const input = document.getElementById(button.dataset.target);
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
        });
    });

    document.addEventListener("keydown", function (event) {
        if (event.key === "Escape") {
            if (passwordModal && passwordModal.classList.contains("show")) {
                passwordModal.classList.remove("show");
            } else if (profileModal) {
                profileModal.classList.remove("show");
            }
        }
    });
});
</script>