<?php
session_start();
require_once "db.php";

if (!isset($_SESSION["user_id"]) || $_SESSION["user_role"] !== "Lecturer") {
    header("Location: index.php");
    exit;
}

$lecturer_id = $_SESSION["user_id"];
$nickname = $_SESSION["nickname"] ?? "Lecturer";

$error = "";
$success = "";

if (isset($_SESSION["assignment_success"])) {
    $success = $_SESSION["assignment_success"];
    unset($_SESSION["assignment_success"]);
}

if (isset($_GET["created"])) {
    $success = "Assignment created successfully.";
}

/* GET LECTURER SUBJECT SECTIONS */
$sql = "SELECT ss.subject_section_id, ss.subject_id, ss.programme_code,
        ss.semester, ss.section, s.subject_code, s.subject_name
        FROM subject_sections ss
        JOIN subjects s ON ss.subject_id = s.subject_id
        WHERE ss.lecturer_id = ?
        ORDER BY s.subject_code ASC, ss.programme_code ASC,
        ss.semester ASC, ss.section ASC";

$stmt = $conn->prepare($sql);
$stmt->bind_param("i", $lecturer_id);
$stmt->execute();
$subjectSectionsResult = $stmt->get_result();

$sections = [];

while ($row = $subjectSectionsResult->fetch_assoc()) {
    $sections[] = $row;
}

/* CREATE ASSIGNMENT */
if ($_SERVER["REQUEST_METHOD"] === "POST" && isset($_POST["create_assignment"])) {
    $subject_section_id = intval($_POST["subject_section_id"] ?? 0);
    $title = trim($_POST["title"] ?? "");
    $description = trim($_POST["description"] ?? "");
    $due_date = trim($_POST["due_date"] ?? "");

    if ($subject_section_id === 0 || $title === "" || $due_date === "") {
        $error = "Please fill in all required fields.";
    } else {
        $sql = "SELECT subject_id
                FROM subject_sections
                WHERE subject_section_id = ? AND lecturer_id = ?
                LIMIT 1";

        $stmt = $conn->prepare($sql);
        $stmt->bind_param("ii", $subject_section_id, $lecturer_id);
        $stmt->execute();
        $sectionResult = $stmt->get_result();

        if ($sectionResult->num_rows === 1) {
            $sectionData = $sectionResult->fetch_assoc();
            $subject_id = $sectionData["subject_id"];
            $attachment_file = null;

            /* UPLOAD ATTACHMENT */
            if (isset($_FILES["attachment_file"]) && $_FILES["attachment_file"]["error"] === 0) {
                $uploadFolder = "uploads/assignments/";

                if (!is_dir($uploadFolder)) {
                    mkdir($uploadFolder, 0777, true);
                }

                $originalName = basename($_FILES["attachment_file"]["name"]);
                $extension = strtolower(pathinfo($originalName, PATHINFO_EXTENSION));
                $allowedExtensions = ["pdf", "doc", "docx", "ppt", "pptx"];

                if (!in_array($extension, $allowedExtensions)) {
                    $error = "Only PDF, DOC, DOCX, PPT and PPTX files are allowed.";
                } else {
                    $newFileName = time() . "_" . uniqid() . "." . $extension;
                    $targetFile = $uploadFolder . $newFileName;

                    if (move_uploaded_file($_FILES["attachment_file"]["tmp_name"], $targetFile)) {
                        $attachment_file = $newFileName;
                    } else {
                        $error = "Unable to upload attachment.";
                    }
                }
            }

            if ($error === "") {
                $status = "Active";

                $sql = "INSERT INTO assignments
                        (lecturer_id, subject_id, subject_section_id, title,
                        description, attachment_file, due_date, status)
                        VALUES (?, ?, ?, ?, ?, ?, ?, ?)";

                $stmt = $conn->prepare($sql);
                $stmt->bind_param(
                    "iiisssss",
                    $lecturer_id,
                    $subject_id,
                    $subject_section_id,
                    $title,
                    $description,
                    $attachment_file,
                    $due_date,
                    $status
                );

                if ($stmt->execute()) {
                    header("Location: lecturer_assignments.php?created=1");
                    exit;
                } else {
                    $error = "Unable to create assignment.";
                }
            }
        } else {
            $error = "Invalid subject section.";
        }
    }
}

/* UPDATE ASSIGNMENT */
if ($_SERVER["REQUEST_METHOD"] === "POST" && isset($_POST["update_assignment"])) {

    $assignment_id = (int)($_POST["assignment_id"] ?? 0);
    $title = trim($_POST["edit_title"] ?? "");
    $description = trim($_POST["edit_description"] ?? "");
    $due_date = trim($_POST["edit_due_date"] ?? "");

    if (
        $assignment_id <= 0 ||
        $title === "" ||
        $due_date === ""
    ) {
        $error = "Please fill in all required fields.";
    } else {

        /* Get current attachment */
        $currentSql = "SELECT attachment_file
                       FROM assignments
                       WHERE assignment_id = ?
                       AND lecturer_id = ?
                       LIMIT 1";

            $currentStmt = $conn->prepare($currentSql);
            $currentStmt->bind_param(
                "ii",
                $assignment_id,
                $lecturer_id
            );
            $currentStmt->execute();

            $currentResult = $currentStmt->get_result();

            if ($currentResult->num_rows === 0) {

                $error = "Assignment not found.";

            } else {

                $currentAssignment = $currentResult->fetch_assoc();

                /* Keep old attachment by default */
                $attachment_file =
                    $currentAssignment["attachment_file"];

                /* New attachment selected */
                if (
                    isset($_FILES["edit_attachment_file"]) &&
                    $_FILES["edit_attachment_file"]["error"] === UPLOAD_ERR_OK
                ) {

                    $uploadFolder = "uploads/assignments/";

                    if (!is_dir($uploadFolder)) {
                        mkdir($uploadFolder, 0777, true);
                    }

                    $originalName = basename(
                        $_FILES["edit_attachment_file"]["name"]
                    );

                    $extension = strtolower(
                        pathinfo($originalName, PATHINFO_EXTENSION)
                    );

                    $allowedExtensions = [
                        "pdf",
                        "doc",
                        "docx",
                        "ppt",
                        "pptx"
                    ];

                    if (!in_array($extension, $allowedExtensions)) {
                        $error =
                            "Only PDF, DOC, DOCX, PPT and PPTX files are allowed.";

                    } else {
                        $newFileName =
                            time() . "_" .
                            uniqid() . "." .
                            $extension;
                        $targetFile =
                            $uploadFolder . $newFileName;

                        if (
                            move_uploaded_file(
                                $_FILES["edit_attachment_file"]["tmp_name"],
                                $targetFile
                            )
                        ) {
                            $attachment_file = $newFileName;
                        } else {
                            $error =
                                "Unable to upload attachment.";
                        }
                    }
                }

                if ($error === "") {
                    $updateSql = "UPDATE assignments
                                  SET title = ?,
                                      description = ?,
                                      attachment_file = ?,
                                      due_date = ?
                                      
                                  WHERE assignment_id = ?
                                  AND lecturer_id = ?";

                    $updateStmt = $conn->prepare($updateSql);

                    $updateStmt->bind_param(
                        "ssssii",
                        $title,
                        $description,
                        $attachment_file,
                        $due_date,
                        $assignment_id,
                        $lecturer_id
                    );

                    if ($updateStmt->execute()) {
                        $_SESSION["assignment_success"] =
                            "Assignment updated successfully.";
                        header(
                            "Location: lecturer_assignments.php"
                        );
                        exit;

                    } else {
                        $error =
                            "Unable to update assignment.";
                    }
                    $updateStmt->close();
                }
            }
            $currentStmt->close();
        }
        $sectionStmt->close();
    }

/* GET ASSIGNMENTS */
$sql = "SELECT a.assignment_id, a.subject_section_id, a.title, a.description, a.attachment_file,
        a.due_date, a.status, a.created_at,
        s.subject_code, s.subject_name,
        ss.programme_code, ss.semester, ss.section
        FROM assignments a
        JOIN subjects s ON a.subject_id = s.subject_id
        JOIN subject_sections ss ON a.subject_section_id = ss.subject_section_id
        WHERE a.lecturer_id = ?
        ORDER BY a.created_at DESC";

$stmt = $conn->prepare($sql);
$stmt->bind_param("i", $lecturer_id);
$stmt->execute();
$assignments = $stmt->get_result();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>My Assignments | FairPeer</title>
    <link rel="stylesheet" href="css/style.css">
    <link rel="stylesheet" href="css/dashboard.css">
    <link rel="stylesheet" href="css/lecturer.css">
    <link rel="stylesheet" href="css/lecturer-assignments.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
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

        <button class="sidebar-toggle" id="sidebarToggle" type="button">
            <i class="fa-solid fa-bars"></i>
        </button>
    </div>

    <nav class="sidebar-menu">
        <a href="lecturer_dashboard.php" class="menu-item">
            <i class="fa-solid fa-house"></i>
            <span>Dashboard</span>
        </a>

        <a href="lecturer_subjects.php" class="menu-item">
            <i class="fa-solid fa-book-open"></i>
            <span>My Subjects</span>
        </a>

        <a href="lecturer_assignments.php" class="menu-item active">
            <i class="fa-solid fa-book"></i>
            <span>My Assignments</span>
        </a>

        <a href="lecturer_submissions.php" class="menu-item">
            <i class="fa-solid fa-file-lines"></i>
            <span>Submissions</span>
        </a>

        <a href="lecturer_contributions.php" class="menu-item">
            <i class="fa-solid fa-chart-pie"></i>
            <span>Contributions</span>
        </a>
    </nav>

    <div class="sidebar-bottom">
            <a href="logout.php" class="menu-item logout">
                <i class="fa-solid fa-right-from-bracket"></i>
                <span>Logout</span>
            </a>
        </div>
</aside>

<main class="dashboard-main">

<header class="dashboard-header">
    <div>
        <h1>My Assignments</h1>
        <p>Create and manage assignments for your subject sections.</p>
    </div>

    <button type="button" class="lecturer-header-profile" id="openLecturerProfile">
    <div class="lecturer-header-avatar">
        <?php echo strtoupper(substr($nickname, 0, 1)); ?>
    </div>

    <div class="lecturer-header-profile-text">
        <strong><?php echo htmlspecialchars($nickname); ?></strong>
        <span>Lecturer</span>
    </div>
</button>
</div>
</header>

<section class="lecturer-assignment-page">

<div class="lecturer-assignment-heading">
    <p class="lecturer-assignment-label">ASSIGNMENTS</p>
    <h2>Your Assignments</h2>

    <button type="button" class="lecturer-create-assignment-btn" id="openAssignmentModal">
        <i class="fa-solid fa-plus"></i>
        Create Assignment
    </button>
</div>

<?php if ($success !== ""): ?>
<div class="lecturer-assignment-success" id="assignmentSuccessMessage">
    <?php echo htmlspecialchars($success); ?>
</div>
<?php endif; ?>

<?php if ($error !== ""): ?>
<div class="lecturer-assignment-error" id="assignmentErrorMessage">
    <?php echo htmlspecialchars($error); ?>
</div>
<?php endif; ?>

<?php if ($assignments->num_rows > 0): ?>

<div class="lecturer-assignment-grid">

<?php while ($assignment = $assignments->fetch_assoc()): ?>

<div class="lecturer-assignment-card">

    <div class="lecturer-assignment-card-top">
        <div class="lecturer-assignment-card-icon">
            <i class="fa-solid fa-file-lines"></i>
        </div>

        <span class="lecturer-assignment-status">
            <?php echo htmlspecialchars($assignment["status"]); ?>
        </span>
    </div>

    <h3><?php echo htmlspecialchars($assignment["title"]); ?></h3>

    <p class="lecturer-assignment-subject">
        <?php echo htmlspecialchars($assignment["subject_code"]); ?>
        <span>•</span>
        Section <?php echo htmlspecialchars($assignment["section"]); ?>
    </p>

    <div class="lecturer-assignment-info">
        <span><?php echo htmlspecialchars($assignment["programme_code"]); ?></span>
        <span>Semester <?php echo htmlspecialchars($assignment["semester"]); ?></span>
    </div>

    <?php if (!empty($assignment["description"])): ?>
    <p class="lecturer-assignment-description">
        <?php echo htmlspecialchars($assignment["description"]); ?>
    </p>
    <?php endif; ?>

    <?php if (!empty($assignment["attachment_file"])): ?>
    <a class="lecturer-assignment-file"
       href="uploads/assignments/<?php echo urlencode($assignment["attachment_file"]); ?>"
       target="_blank">
        <i class="fa-solid fa-paperclip"></i>
        View Attachment
    </a>
    <?php endif; ?>

    <div class="lecturer-assignment-card-bottom">
        <div>
            <span>Due Date</span>
            <strong><?php echo date("d M Y", strtotime($assignment["due_date"])); ?></strong>
        </div>
        <button type="button"
        class="edit-assignment-button"
        onclick='openEditAssignment(
            <?php echo (int)$assignment["assignment_id"]; ?>,
            <?php echo json_encode($assignment["title"]); ?>,
            <?php echo json_encode($assignment["description"] ?? ""); ?>,
            <?php echo json_encode($assignment["due_date"]); ?>,
            <?php echo (int)$assignment["subject_section_id"]; ?>
        )'>

    <i class="fa-solid fa-pen"></i>
    Edit Assignment

</button>
</div>

</div>

<?php endwhile; ?>

</div>

<?php else: ?>

<div class="lecturer-assignment-empty">
    <i class="fa-solid fa-file-circle-plus"></i>
    <h3>No assignments created yet</h3>
    <p>Create your first assignment for one of your subject sections.</p>
</div>

<?php endif; ?>

</section>
</main>

<!-- CREATE ASSIGNMENT MODAL -->

<div class="lecturer-assignment-modal" id="assignmentModal">

<div class="lecturer-assignment-modal-box">
    <button type="button"
                class="lecturer-assignment-modal-close"
                id="closeAssignmentModal">
            <i class="fa-solid fa-xmark"></i>
    </button>

<div class="lecturer-assignment-modal-header">
    <div>
        <h2>Create Assignment</h2>
        <p>Create an assignment for your subject section.</p>
    </div>
</div>

<form method="POST" action="" enctype="multipart/form-data">

<div class="lecturer-assignment-form-group">
    <label>Subject</label>
    <select id="assignmentSubject" required>
        <option value="">Choose subject</option>
        <?php
        $shownSubjects = [];

        foreach ($sections as $section):
            if (!in_array($section["subject_id"], $shownSubjects)):
                $shownSubjects[] = $section["subject_id"];
        ?>
            <option value="<?php echo $section["subject_id"]; ?>">
                <?php echo htmlspecialchars($section["subject_code"] . " - " . $section["subject_name"]); ?>
            </option>
        <?php
            endif;
        endforeach;
        ?>
    </select>
</div>

<div class="lecturer-assignment-form-group">
    <label>Programme</label>
    <select id="assignmentProgramme" disabled required>
        <option value="">Choose programme</option>
    </select>
</div>

<div class="lecturer-assignment-form-group">
    <label>Semester</label>
    <select id="assignmentSemester" disabled required>
        <option value="">Choose semester</option>
    </select>
</div>

<div class="lecturer-assignment-form-group">
    <label>Section</label>
    <select id="assignmentSection" name="subject_section_id" disabled required>
        <option value="">Choose section</option>
    </select>
</div>

<div class="lecturer-assignment-form-group">
    <label>Assignment Title</label>
    <input type="text" name="title" placeholder="Enter assignment title" required>
</div>

<div class="lecturer-assignment-form-group">
    <label>Description <span class="lecturer-optional">(Optional)</span></label>
    <textarea name="description" rows="4" placeholder="Enter assignment instructions"></textarea>
</div>

<div class="lecturer-assignment-form-group">
    <label>Attachment</label>
    <input type="file" name="attachment_file" accept=".pdf,.doc,.docx,.ppt,.pptx">
    <small>PDF, DOC, DOCX, PPT or PPTX</small>
</div>

<div class="lecturer-assignment-form-group">
    <label>Due Date</label>
    <input type="date" name="due_date" required>
</div>

<div class="lecturer-assignment-modal-actions">

    <button type="submit" name="create_assignment" class="lecturer-assignment-submit-btn">
        Create Assignment
    </button>
</div>

</form>
</div>
</div>

<div class="lecturer-assignment-modal" id="editAssignmentModal">
    <div class="lecturer-assignment-modal-box">
        <button type="button"
                class="lecturer-assignment-modal-close"
                onclick="closeEditAssignment()">
            <i class="fa-solid fa-xmark"></i>
        </button>
        <h2>Edit Assignment</h2>
        <p class="lecturer-assignment-modal-subtitle">
            Update the assignment information.
        </p>

        <form method="POST" action="" enctype="multipart/form-data">

            <input type="hidden"
                   name="assignment_id"
                   id="editAssignmentId">

<div class="edit-assignment-readonly">

    <div class="edit-assignment-readonly-item">
        <span>Subject</span>
        <strong id="editDisplaySubject"></strong>
    </div>

    <div class="edit-assignment-readonly-item">
        <span>Programme</span>
        <strong id="editDisplayProgramme"></strong>
    </div>

    <div class="edit-assignment-readonly-item">
        <span>Semester</span>
        <strong id="editDisplaySemester"></strong>
    </div>

    <div class="edit-assignment-readonly-item">
        <span>Section</span>
        <strong id="editDisplaySection"></strong>
    </div>
</div>

<div class="lecturer-assignment-form-group">
    <label>Assignment Title</label>
    <input type="text"
           name="edit_title"
           id="editAssignmentTitle"
           required>
</div>

            <div class="lecturer-assignment-form-group">
                <label>
                    Description
                    <span class="lecturer-optional">(Optional)</span>
                </label>
                <textarea name="edit_description"
                          id="editAssignmentDescription"
                          rows="4"></textarea>
            </div>

            <div class="lecturer-assignment-form-group">
                <label>
                    Replace Attachment
                    <span class="lecturer-optional">(Optional)</span>
                </label>
                <input type="file"
                       name="edit_attachment_file"
                       accept=".pdf,.doc,.docx,.ppt,.pptx">
                <small>
                    Leave empty to keep the current attachment.
                </small>
            </div>

            <div class="lecturer-assignment-form-group">
                <label>Due Date</label>
                <input type="date"
                       name="edit_due_date"
                       id="editAssignmentDueDate"
                       required>
            </div>

            <div class="lecturer-assignment-modal-actions">
                <button type="submit"
                        name="update_assignment"
                        class="lecturer-assignment-submit-btn">
                    <i class="fa-solid fa-floppy-disk"></i>
                    Save Changes
                </button>
            </div>
        </form>
    </div>
</div>

<?php include "lecturer_profile.php"; ?>

<script>
document.addEventListener("DOMContentLoaded", function () {
    const successMessage = document.getElementById("assignmentSuccessMessage");

if (successMessage) {
    setTimeout(function () {
        successMessage.style.display = "none";
    }, 4000);
}
const errorMessage = document.getElementById("assignmentErrorMessage");

if (errorMessage) {
    setTimeout(function () {
        errorMessage.style.display = "none";
    }, 4000);
}
    const sidebar = document.querySelector(".sidebar");
    const sidebarToggle = document.getElementById("sidebarToggle");
    const modal = document.getElementById("assignmentModal");
    const openModal = document.getElementById("openAssignmentModal");
    const closeModal = document.getElementById("closeAssignmentModal");

    const subject = document.getElementById("assignmentSubject");
    const programme = document.getElementById("assignmentProgramme");
    const semester = document.getElementById("assignmentSemester");
    const section = document.getElementById("assignmentSection");

    const sectionData = <?php echo json_encode($sections); ?>;

    sidebarToggle.addEventListener("click", function () {
        sidebar.classList.toggle("closed");
    });

    openModal.addEventListener("click", function () {
        modal.classList.add("show");
    });

    closeModal.addEventListener("click", function () {
        modal.classList.remove("show");
    });

    modal.addEventListener("click", function (event) {
        if (event.target === modal) {
            modal.classList.remove("show");
        }
    });

    subject.addEventListener("change", function () {
        programme.innerHTML = '<option value="">Choose programme</option>';
        semester.innerHTML = '<option value="">Choose semester</option>';
        section.innerHTML = '<option value="">Choose section</option>';

        programme.disabled = true;
        semester.disabled = true;
        section.disabled = true;

        if (subject.value === "") {
            return;
        }

        const programmes = [];

        sectionData.forEach(function (item) {
            if (
                item.subject_id == subject.value &&
                !programmes.includes(item.programme_code)
            ) {
                programmes.push(item.programme_code);
            }
        });

        programmes.forEach(function (item) {
            programme.innerHTML += `<option value="${item}">${item}</option>`;
        });

        programme.disabled = false;
    });

    programme.addEventListener("change", function () {
        semester.innerHTML = '<option value="">Choose semester</option>';
        section.innerHTML = '<option value="">Choose section</option>';

        semester.disabled = true;
        section.disabled = true;

        if (programme.value === "") {
            return;
        }

        const semesters = [];

        sectionData.forEach(function (item) {
            if (
                item.subject_id == subject.value &&
                item.programme_code === programme.value &&
                !semesters.includes(item.semester)
            ) {
                semesters.push(item.semester);
            }
        });

        semesters.forEach(function (item) {
            semester.innerHTML += `<option value="${item}">Semester ${item}</option>`;
        });

        semester.disabled = false;
    });

   semester.addEventListener("change", function () {
    section.innerHTML = '<option value="">Choose section</option>';
    section.disabled = true;

    if (semester.value === "") {
        return;
    }

    sectionData.forEach(function (item) {
        if (
            item.subject_id == subject.value &&
            item.programme_code === programme.value &&
            item.semester == semester.value
        ) {
            section.innerHTML += `
                <option value="${item.subject_section_id}">
                    Section ${item.section}
                </option>
            `;
        }
    });

    section.disabled = false;
});
}); 

function openEditAssignment(id, title, description, dueDate, sectionId) {

    const sectionData = <?php echo json_encode($sections); ?>;

    document.getElementById("editAssignmentId").value = id;
    document.getElementById("editAssignmentTitle").value = title;
    document.getElementById("editAssignmentDescription").value = description;
    document.getElementById("editAssignmentDueDate").value = dueDate;

    const currentSection = sectionData.find(function (item) {
        return item.subject_section_id == sectionId;
    });

    if (currentSection) {

        document.getElementById("editDisplaySubject").textContent =
            currentSection.subject_code + " - " + currentSection.subject_name;

        document.getElementById("editDisplayProgramme").textContent =
            currentSection.programme_code;

        document.getElementById("editDisplaySemester").textContent =
            "Semester " + currentSection.semester;

        document.getElementById("editDisplaySection").textContent =
            "Section " + currentSection.section;
    }

    document.getElementById("editAssignmentModal")
        .classList.add("show");
}

function closeEditAssignment() {
    document.getElementById("editAssignmentModal").classList.remove("show");
}

</script>

</body>
</html>