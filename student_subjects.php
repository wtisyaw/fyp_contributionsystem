<?php

session_start();

require_once "db.php";

if (!isset($_SESSION["user_id"])) {
    header("Location: index.php");
    exit;
}

if (!isset($_SESSION["user_role"]) || $_SESSION["user_role"] !== "Student") {
    header("Location: index.php?error=role");
    exit;
}

$user_id = $_SESSION["user_id"];
$message = "";
$messageType = "";

$profileQuery = "SELECT
                    user_code,
                    full_name,
                    nickname,
                    email,
                    user_role,
                    programme_code,
                    semester,
                    section
                 FROM Users
                 WHERE user_id = ?";

$profileStmt = $conn->prepare($profileQuery);
$profileStmt->bind_param("i", $user_id);
$profileStmt->execute();

$profileResult = $profileStmt->get_result();
$profileUser = $profileResult->fetch_assoc();

$profileStmt->close();


$displayName = !empty($profileUser["nickname"])
    ? $profileUser["nickname"]
    : $profileUser["full_name"];

$profileInitial = strtoupper(substr($displayName, 0, 1));

if (
    $_SERVER["REQUEST_METHOD"] === "POST" &&
    isset($_POST["join_subject"])
) {

    $joinCode = strtoupper(
        trim($_POST["join_code"] ?? "")
    );


    if ($joinCode === "") {

        $message = "Please enter a subject join code.";
        $messageType = "error";

    } else {

        $findSubjectQuery =
            "SELECT
                ss.subject_section_id,
                ss.programme_code,
                ss.semester,
                ss.section,
                s.subject_code,
                s.subject_name

             FROM subject_sections ss

             INNER JOIN subjects s
                ON ss.subject_id = s.subject_id

             WHERE ss.join_code = ?";


        $findSubjectStmt =
            $conn->prepare($findSubjectQuery);

        $findSubjectStmt->bind_param(
            "s",
            $joinCode
        );

        $findSubjectStmt->execute();

        $findSubjectResult =
            $findSubjectStmt->get_result();


        if ($findSubjectResult->num_rows !== 1) {

            $message = "Invalid subject join code.";
            $messageType = "error";

        } else {

            $subjectClass =
                $findSubjectResult->fetch_assoc();

            $subjectSectionId =
                $subjectClass["subject_section_id"];

            if (
                $subjectClass["programme_code"]
                !== $profileUser["programme_code"]
            ) {

                $message =
                    "This subject is not available for your programme.";

                $messageType = "error";

            }

            elseif (
                $subjectClass["semester"]
                !== $profileUser["semester"]
            ) {

                $message =
                    "This subject is not available for your semester.";

                $messageType = "error";

            }

            elseif (
                $subjectClass["section"]
                !== $profileUser["section"]
            ) {

                $message =
                    "This subject is not available for your section.";

                $messageType = "error";

            }

            else {

                $checkJoinedQuery =
                    "SELECT student_subject_id
                     FROM student_subjects
                     WHERE student_id = ?
                     AND subject_section_id = ?";


                $checkJoinedStmt =
                    $conn->prepare(
                        $checkJoinedQuery
                    );

                $checkJoinedStmt->bind_param(
                    "ii",
                    $user_id,
                    $subjectSectionId
                );

                $checkJoinedStmt->execute();

                $checkJoinedResult =
                    $checkJoinedStmt->get_result();


                if (
                    $checkJoinedResult->num_rows > 0
                ) {

                    $message =
                        "You have already joined this subject.";

                    $messageType = "error";

                } else {

                    $joinSubjectQuery =
                        "INSERT INTO student_subjects
                         (
                            student_id,
                            subject_section_id
                         )
                         VALUES (?, ?)";


                    $joinSubjectStmt =
                        $conn->prepare(
                            $joinSubjectQuery
                        );

                    $joinSubjectStmt->bind_param(
                        "ii",
                        $user_id,
                        $subjectSectionId
                    );


                    if (
                        $joinSubjectStmt->execute()
                    ) {

                        $message =
                            "You have successfully joined " .
                            $subjectClass["subject_code"] .
                            " - " .
                            $subjectClass["subject_name"] .
                            ".";

                        $messageType = "success";

                    } else {

                        $message =
                            "Unable to join subject.";

                        $messageType = "error";
                    }

                    $joinSubjectStmt->close();
                }

                $checkJoinedStmt->close();
            }
        }

        $findSubjectStmt->close();
    }
}

$subjectsQuery =
    "SELECT
        sts.student_subject_id,
        ss.subject_section_id,

        s.subject_id,
        s.subject_code,
        s.subject_name,

        ss.programme_code,
        ss.semester,
        ss.section,

        u.full_name AS lecturer_name

     FROM student_subjects sts

     INNER JOIN subject_sections ss
        ON sts.subject_section_id =
           ss.subject_section_id

     INNER JOIN subjects s
        ON ss.subject_id =
           s.subject_id

     INNER JOIN users u
        ON ss.lecturer_id =
           u.user_id

     WHERE sts.student_id = ?

     ORDER BY s.subject_code ASC";


$subjectsStmt =
    $conn->prepare($subjectsQuery);

$subjectsStmt->bind_param(
    "i",
    $user_id
);

$subjectsStmt->execute();

$subjects =
    $subjectsStmt->get_result();

?>

<!DOCTYPE html>
<html lang="en">

<head>

    <meta charset="UTF-8">

    <meta
        name="viewport"
        content="width=device-width, initial-scale=1.0">

    <title>My Subjects | FairPeer</title>

    <link
        rel="stylesheet"
        href="css/style.css">

    <link
        rel="stylesheet"
        href="css/dashboard.css">

    <link
        rel="stylesheet"
        href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">

</head>


<body class="dashboard-body">


<aside class="sidebar">

    <div class="sidebar-top">

        <div class="sidebar-logo">

            <div class="logo-icon">
                <i class="fa-solid fa-users"></i>
            </div>

            <div class="logo-text">
                <h2>FairPeer</h2>
            </div>

        </div>


        <button
            class="sidebar-toggle"
            id="sidebarToggle"
            type="button">

            <i class="fa-solid fa-bars"></i>

        </button>

    </div>


    <nav class="sidebar-menu">

        <a
            href="student_dashboard.php"
            class="menu-item">

            <i class="fa-solid fa-house"></i>
            <span>Dashboard</span>

        </a>


        <a
            href="student_subjects.php"
            class="menu-item active">

            <i class="fa-solid fa-book-open"></i>
            <span>My Subjects</span>

        </a>


        <a
            href="student_assignments.php"
            class="menu-item">

            <i class="fa-solid fa-book"></i>
            <span>My Assignments</span>

        </a>


        <a
            href="student_groups.php"
            class="menu-item">

            <i class="fa-solid fa-users"></i>
            <span>My Groups</span>

        </a>


        <a
            href="student_contribution.php"
            class="menu-item">

            <i class="fa-solid fa-chart-pie"></i>
            <span>My Contribution</span>

        </a>


        <a
            href="student_feedback.php"
            class="menu-item">

            <i class="fa-solid fa-comment"></i>
            <span>Feedback</span>

        </a>


        <a
            href="student_upload.php"
            class="menu-item">

            <i class="fa-solid fa-upload"></i>
            <span>Upload File</span>

        </a>

    </nav>


    <div class="sidebar-bottom">

        <a
            href="logout.php"
            class="menu-item logout">

            <i class="fa-solid fa-right-from-bracket"></i>
            <span>Logout</span>

        </a>

    </div>

</aside>

<main class="dashboard-main">


    <!-- HEADER -->

    <header class="dashboard-header">

        <div class="welcome-area">

            <h1>My Subjects</h1>

            <p>
                View and manage the subjects you have joined.
            </p>

        </div>


        <button
            type="button"
            class="header-profile"
            id="openProfileModal">

            <div class="profile-avatar">

                <?php
                echo htmlspecialchars(
                    $profileInitial
                );
                ?>

            </div>


            <div class="profile-info">

                <strong>

                    <?php
                    echo htmlspecialchars(
                        $displayName
                    );
                    ?>

                </strong>

                <span>Student</span>

            </div>

        </button>

    </header>

    <?php if ($message !== ""): ?>

        <div
            class="profile-update-message <?php echo $messageType; ?>">

            <?php if ($messageType === "success"): ?>

                <i class="fa-solid fa-circle-check"></i>

            <?php else: ?>

                <i class="fa-solid fa-circle-exclamation"></i>

            <?php endif; ?>

            <?php
            echo htmlspecialchars($message);
            ?>

        </div>

    <?php endif; ?>

    <section class="dashboard-content">


        <div class="subject-page-heading">

            <div>

                <p class="block-label">
                    YOUR SUBJECTS
                </p>

                <h2 class="subject-section-title">
                    My Subjects
                </h2>

            </div>


            <button
                type="button"
                class="subject-join-open"
                id="openJoinSubject">

                <i class="fa-solid fa-plus"></i>
                Join Subject

            </button>

        </div>



        <!-- =========================
             SUBJECT LIST
        ========================== -->

        <?php if ($subjects->num_rows > 0): ?>


            <div class="recent-activity-card subject-list-card">


                <?php
                while (
                    $subject =
                    $subjects->fetch_assoc()
                ):
                ?>


                    <div class="subject-item">


                        <div class="subject-item-icon">

                            <i class="fa-solid fa-book-open"></i>

                        </div>


                        <div class="subject-item-content">


                            <div class="subject-item-title">

                                <strong>

                                    <?php
                                    echo htmlspecialchars(
                                        $subject["subject_code"]
                                    );
                                    ?>

                                </strong>

                                <span>

                                    <?php
                                    echo htmlspecialchars(
                                        $subject["subject_name"]
                                    );
                                    ?>

                                </span>

                            </div>


                            <p class="subject-meta">
                                <i class="fa-solid fa-user-tie"></i>
                                Lecturer:
                                <?php
                                echo htmlspecialchars(
                                    $subject["lecturer_name"]
                                );
                                ?>
                            </p>

                            <p class="subject-meta">
                                <?php
                                echo htmlspecialchars(
                                    $subject["programme_code"]
                                );
                                ?>
                                &bull;
                                Semester
                                <?php
                                echo htmlspecialchars(
                                    $subject["semester"]
                                );
                                ?>

                                &bull;
                                Section
                                <?php
                                echo htmlspecialchars(
                                    $subject["section"]
                                );
                                ?>
                            </p>
                        </div>
                    </div>
                <?php endwhile; ?>
            </div>


        <?php else: ?>


            <div class="subject-empty-card">

                <div class="subject-empty-icon">

                    <i class="fa-solid fa-book-open"></i>

                </div>


                <div>

                    <h3>No subjects joined yet</h3>

                    <p>
                        Join a subject using the code provided by your lecturer.
                    </p>

                </div>

            </div>


        <?php endif; ?>


    </section>

    <?php if (isset($_GET["password"])): ?>

        <?php if ($_GET["password"] === "success"): ?>

            <div class="profile-update-message success">

                <i class="fa-solid fa-circle-check"></i>

                Password changed successfully.

            </div>


        <?php elseif ($_GET["password"] === "current"): ?>

            <div class="profile-update-message error">

                <i class="fa-solid fa-circle-exclamation"></i>

                Your current password is incorrect.

            </div>


        <?php elseif ($_GET["password"] === "invalid"): ?>

            <div class="profile-update-message error">

                <i class="fa-solid fa-circle-exclamation"></i>

                Password must be at least 8 characters and include an uppercase letter, a number and a special character.

            </div>


        <?php elseif ($_GET["password"] === "match"): ?>

            <div class="profile-update-message error">

                <i class="fa-solid fa-circle-exclamation"></i>

                New password and confirm password do not match.

            </div>


        <?php elseif ($_GET["password"] === "same"): ?>

            <div class="profile-update-message error">

                <i class="fa-solid fa-circle-exclamation"></i>

                New password cannot be the same as your current password.

            </div>


        <?php else: ?>

            <div class="profile-update-message error">

                <i class="fa-solid fa-circle-exclamation"></i>

                Unable to change your password. Please try again.

            </div>

        <?php endif; ?>

    <?php endif; ?>


</main>

<div
    class="subject-modal-overlay"
    id="joinSubjectModal">

    <div class="subject-modal">


        <div class="subject-modal-header">

            <div>

                <h2>Join Subject</h2>

                <p>
                    Enter the join code provided by your lecturer.
                </p>

            </div>


            <button
                type="button"
                class="subject-modal-close"
                id="closeJoinSubject"
                aria-label="Close">

                <i class="fa-solid fa-xmark"></i>

            </button>

        </div>


        <form method="POST" class="subject-modal-form">


            <div class="subject-modal-field">

                <label for="join_code">
                    Subject Join Code
                </label>

                <input
                    type="text"
                    id="join_code"
                    name="join_code"
                    placeholder="Example: A7K29P"
                    maxlength="20"
                    autocomplete="off"
                    required>

            </div>


            <div class="subject-modal-actions">

                <button
                    type="submit"
                    name="join_subject"
                    class="subject-modal-submit">

                    <i class="fa-solid fa-plus"></i>
                    Join Subject

                </button>

            </div>


        </form>


    </div>

</div>

<div class="profile-modal-overlay" id="profileModal">

    <div class="profile-modal">

        <div class="profile-modal-header">

            <div>

                <h2>My Profile</h2>

                <p>
                    Manage your personal and academic information.
                </p>

            </div>


            <button
                type="button"
                class="profile-modal-close"
                id="closeProfileModal">

                <i class="fa-solid fa-xmark"></i>

            </button>

        </div>


        <div class="profile-modal-summary">

            <div class="profile-modal-avatar">

                <?php
                echo strtoupper(
                    substr($profileUser["nickname"], 0, 1)
                );
                ?>

            </div>


            <div>

                <h3>

                    <?php
                    echo htmlspecialchars(
                        $profileUser["nickname"]
                    );
                    ?>

                </h3>

                <p>Student</p>

            </div>

        </div>


        <div class="profile-modal-divider"></div>


        <form
            action="update_profile.php"
            method="POST"
            class="profile-modal-form">


            <div class="profile-modal-grid">


                <div class="profile-modal-field">

                    <label>Full Name</label>

                    <input
                        type="text"
                        name="full_name"
                        value="<?php
                        echo htmlspecialchars(
                            $profileUser["full_name"]
                        );
                        ?>"
                        required>

                </div>


                <div class="profile-modal-field">

                    <label>Nickname</label>

                    <input
                        type="text"
                        name="nickname"
                        value="<?php
                        echo htmlspecialchars(
                            $profileUser["nickname"]
                        );
                        ?>"
                        required>

                </div>


                <div class="profile-modal-field">

                    <label>Student ID</label>

                    <input
                        type="text"
                        value="<?php
                        echo htmlspecialchars(
                            $profileUser["user_code"]
                        );
                        ?>"
                        disabled>

                    <small>
                        Student ID cannot be changed.
                    </small>

                </div>


                <div class="profile-modal-field">

                    <label>Email Address</label>

                    <input
                        type="email"
                        name="email"
                        value="<?php
                        echo htmlspecialchars(
                            $profileUser["email"]
                        );
                        ?>"
                        required>

                </div>


                <div class="profile-modal-field">

                    <label>Programme</label>

                    <input
                        type="text"
                        value="<?php
                        echo htmlspecialchars(
                            $profileUser["programme_code"]
                        );
                        ?>"
                        disabled>

                    <small>
                        Programme cannot be changed.
                    </small>

                </div>


                <div class="profile-modal-field">

                    <label>Semester</label>

                    <select name="semester" required>

                        <?php for ($i = 1; $i <= 7; $i++): ?>

                            <option
                                value="<?php echo $i; ?>"
                                <?php

                                if (
                                    (string)$profileUser["semester"]
                                    ===
                                    (string)$i
                                ) {
                                    echo "selected";
                                }

                                ?>>

                                Semester <?php echo $i; ?>

                            </option>

                        <?php endfor; ?>

                    </select>

                </div>


                <div class="profile-modal-field">

                    <label>Section</label>

                    <select name="section" required>

                        <?php for ($i = 1; $i <= 6; $i++): ?>

                            <option
                                value="<?php echo $i; ?>"
                                <?php

                                if (
                                    (string)$profileUser["section"]
                                    ===
                                    (string)$i
                                ) {
                                    echo "selected";
                                }

                                ?>>

                                Section <?php echo $i; ?>

                            </option>

                        <?php endfor; ?>

                    </select>

                </div>


                <div class="profile-modal-field">

                    <label>Account Role</label>

                    <input
                        type="text"
                        value="Student"
                        disabled>

                    <small>
                        Account role cannot be changed.
                    </small>

                </div>


            </div>


            <div class="profile-modal-actions">


                <button
                    type="button"
                    class="change-password-modal-button"
                    id="openPasswordModal">

                    <i class="fa-solid fa-lock"></i>
                    Change Password

                </button>


                <button
                    type="submit"
                    class="save-profile-modal-button">

                    <i class="fa-solid fa-floppy-disk"></i>
                    Save Changes

                </button>


            </div>


        </form>

    </div>

</div>


<div
    class="password-modal-overlay"
    id="passwordModal">

    <div class="password-modal">


        <div class="password-modal-header">

            <div>

                <h2>Change Password</h2>

                <p>
                    Enter your current password and choose a new password.
                </p>

            </div>


            <button
                type="button"
                class="password-modal-close"
                id="closePasswordModal"
                aria-label="Close">

                <i class="fa-solid fa-xmark"></i>

            </button>

        </div>


        <form
            action="change_password.php"
            method="POST"
            class="password-form">


            <div class="password-field">

                <label for="currentPassword">
                    Current Password
                </label>


                <div class="password-input-wrapper">

                    <input
                        type="password"
                        id="currentPassword"
                        name="current_password"
                        required>


                    <button
                        type="button"
                        class="password-eye"
                        data-target="currentPassword"
                        aria-label="Show password">

                        <i class="fa-solid fa-eye"></i>

                    </button>

                </div>

            </div>


            <div class="password-field">

                <label for="newPassword">
                    New Password
                </label>


                <div class="password-input-wrapper">

                    <input
                        type="password"
                        id="newPassword"
                        name="new_password"
                        required>


                    <button
                        type="button"
                        class="password-eye"
                        data-target="newPassword"
                        aria-label="Show password">

                        <i class="fa-solid fa-eye"></i>

                    </button>

                </div>


                <small>
                    Minimum 8 characters, including an uppercase letter,
                    a number and a special character.
                </small>

            </div>


            <div class="password-field">

                <label for="confirmPassword">
                    Confirm New Password
                </label>


                <div class="password-input-wrapper">

                    <input
                        type="password"
                        id="confirmPassword"
                        name="confirm_password"
                        required>


                    <button
                        type="button"
                        class="password-eye"
                        data-target="confirmPassword"
                        aria-label="Show password">

                        <i class="fa-solid fa-eye"></i>

                    </button>

                </div>

            </div>


            <div class="password-modal-actions">


                <button
                    type="button"
                    class="back-profile-button"
                    id="backToProfile">

                    <i class="fa-solid fa-arrow-left"></i>
                    Back to Profile

                </button>


                <button
                    type="submit"
                    class="update-password-button">

                    <i class="fa-solid fa-lock"></i>
                    Update Password

                </button>


            </div>


        </form>


    </div>

</div>

<script>

document.addEventListener(
    "DOMContentLoaded",
    function () {

        const sidebar =
            document.querySelector(".sidebar");

        const sidebarToggle =
            document.getElementById("sidebarToggle");


        if (sidebar && sidebarToggle) {

            sidebarToggle.addEventListener(
                "click",
                function () {

                    sidebar.classList.toggle(
                        "closed"
                    );

                }
            );

        }

        const joinSubjectModal =
            document.getElementById("joinSubjectModal");

        const openJoinSubject =
            document.getElementById("openJoinSubject");

        const closeJoinSubject =
            document.getElementById("closeJoinSubject");

        const cancelJoinSubject =
            document.getElementById("cancelJoinSubject");


        function openSubjectModal() {

            joinSubjectModal.classList.add("show");
            document.body.classList.add("modal-open");

        }


        function closeSubjectModal() {

            joinSubjectModal.classList.remove("show");
            document.body.classList.remove("modal-open");

        }


        if (openJoinSubject) {

            openJoinSubject.addEventListener(
                "click",
                openSubjectModal
            );

        }


        if (closeJoinSubject) {

            closeJoinSubject.addEventListener(
                "click",
                closeSubjectModal
            );

        }


        if (cancelJoinSubject) {

            cancelJoinSubject.addEventListener(
                "click",
                closeSubjectModal
            );

        }


        if (joinSubjectModal) {

            joinSubjectModal.addEventListener(
                "click",
                function (event) {

                    if (
                        event.target === joinSubjectModal
                    ) {

                        closeSubjectModal();

                    }

                }
            );

        }

        const profileModal =
            document.getElementById("profileModal");

        const openProfileModal =
            document.getElementById("openProfileModal");

        const closeProfileModal =
            document.getElementById("closeProfileModal");


        if (openProfileModal) {

            openProfileModal.addEventListener(
                "click",
                function () {

                    profileModal.classList.add("show");
                    document.body.classList.add("modal-open");

                }
            );

        }


        if (closeProfileModal) {

            closeProfileModal.addEventListener(
                "click",
                function () {

                    profileModal.classList.remove("show");
                    document.body.classList.remove("modal-open");

                }
            );

        }


        if (profileModal) {

            profileModal.addEventListener(
                "click",
                function (event) {

                    if (event.target === profileModal) {

                        profileModal.classList.remove("show");
                        document.body.classList.remove("modal-open");

                    }

                }
            );

        }

        const passwordModal =
            document.getElementById("passwordModal");

        const openPasswordModal =
            document.getElementById("openPasswordModal");

        const closePasswordModal =
            document.getElementById("closePasswordModal");

        const backToProfile =
            document.getElementById("backToProfile");


        if (openPasswordModal) {

            openPasswordModal.addEventListener(
                "click",
                function () {

                    profileModal.classList.remove("show");
                    passwordModal.classList.add("show");

                }
            );

        }


        if (closePasswordModal) {

            closePasswordModal.addEventListener(
                "click",
                function () {

                    passwordModal.classList.remove("show");
                    document.body.classList.remove("modal-open");

                }
            );

        }


        if (backToProfile) {

            backToProfile.addEventListener(
                "click",
                function () {

                    passwordModal.classList.remove("show");
                    profileModal.classList.add("show");

                }
            );

        }

        if (passwordModal) {

            passwordModal.addEventListener(
                "click",
                function (event) {

                    if (event.target === passwordModal) {

                        passwordModal.classList.remove("show");
                        document.body.classList.remove("modal-open");

                    }

                }
            );

        }

        const passwordEyes =
            document.querySelectorAll(".password-eye");

        passwordEyes.forEach(
            function (button) {

                button.addEventListener(
                    "click",
                    function () {

                        const targetId =
                            button.getAttribute("data-target");

                        const input =
                            document.getElementById(targetId);

                        const icon =
                            button.querySelector("i");


                        if (input.type === "password") {

                            input.type = "text";

                            icon.classList.remove(
                                "fa-eye"
                            );

                            icon.classList.add(
                                "fa-eye-slash"
                            );

                        } else {

                            input.type = "password";

                            icon.classList.remove(
                                "fa-eye-slash"
                            );

                            icon.classList.add(
                                "fa-eye"
                            );

                        }

                    }
                );

            }
        );

        document.addEventListener(
            "keydown",
            function (event) {

                if (event.key === "Escape") {

                    if (joinSubjectModal) {
                        joinSubjectModal.classList.remove("show");
                    }

                    if (profileModal) {
                        profileModal.classList.remove("show");
                    }

                    if (passwordModal) {
                        passwordModal.classList.remove("show");
                    }

                    document.body.classList.remove("modal-open");

                }

            }
        );

        const profileMessages =
            document.querySelectorAll(
                ".profile-update-message"
            );


        profileMessages.forEach(
            function (message) {

                setTimeout(
                    function () {

                        message.style.opacity = "0";

                        setTimeout(
                            function () {
                                message.remove();
                            },
                            400
                        );

                    },
                    4000
                );

            }
        );

        if (
            window.location.search.includes("profile=") ||
            window.location.search.includes("password=")
        ) {

            window.history.replaceState(
                {},
                document.title,
                window.location.pathname
            );

        }


    }
);

</script>

</body>

</html>