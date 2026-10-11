<?php
session_start();
require_once "db.php";

if (!isset($_SESSION["user_id"]) || $_SESSION["user_role"] !== "Lecturer") {
    header("Location: index.php");
    exit;
}

$lecturer_id = $_SESSION["user_id"];
$nickname = $_SESSION["nickname"] ?? "Lecturer";

$sql = "SELECT
            ss.subject_section_id,
            ss.section,
            ss.programme_code,
            ss.semester,
            ss.join_code,
            ss.expected_students,
            s.subject_id,
            s.subject_code,
            s.subject_name,
            COUNT(DISTINCT sts.student_id) AS enrolled_students
        FROM subject_sections ss
        INNER JOIN subjects s
            ON ss.subject_id = s.subject_id
        LEFT JOIN student_subjects sts
            ON ss.subject_section_id = sts.subject_section_id
        WHERE ss.lecturer_id = ?
        GROUP BY
            ss.subject_section_id,
            ss.section,
            ss.programme_code,
            ss.semester,
            ss.join_code,
            ss.expected_students,
            s.subject_id,
            s.subject_code,
            s.subject_name
        ORDER BY s.subject_code ASC, ss.section ASC";

$stmt = $conn->prepare($sql);
$stmt->bind_param("i", $lecturer_id);
$stmt->execute();
$subjectsResult = $stmt->get_result();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>My Subjects | FairPeer</title>
    <link rel="stylesheet" href="css/style.css">
    <link rel="stylesheet" href="css/dashboard.css">
    <link rel="stylesheet" href="css/lecturer.css">
    <link rel="stylesheet" href="css/lecturer-subjects.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
</head>
<body class="dashboard-body lecturer-subjects-page">

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
        <a href="lecturer_subjects.php" class="menu-item active">
            <i class="fa-solid fa-book-open"></i>
            <span>My Subjects</span>
        </a>
        <a href="lecturer_assignments.php" class="menu-item">
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
            <h1>My Subjects</h1>
            <p>Create and manage the subjects and sections you teach.</p>
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

    <section class="lecturer-subjects-page">
        <div class="lecturer-subjects-heading">
            <div>
                <p class="lecturer-subjects-label">YOUR SUBJECTS</p>
                <h2>Manage Subjects</h2>
            </div>
            <button type="button" class="lecturer-create-subject-button" id="openCreateSubject">
                <i class="fa-solid fa-plus"></i>
                Create Subject
            </button>
        </div>

        <?php if ($subjectsResult->num_rows > 0): ?>
            <div class="lecturer-subjects-grid">
                <?php while ($subject = $subjectsResult->fetch_assoc()): ?>
                    <?php
$subject_section_id = (int)$subject["subject_section_id"];

$groupSql = "SELECT COUNT(DISTINCT sts.student_id) AS grouped_students
             FROM student_subjects sts
             WHERE sts.subject_section_id = ?
             AND EXISTS (
                 SELECT 1
                 FROM group_members gm
                 INNER JOIN student_groups sg
                     ON gm.group_id = sg.group_id
                 INNER JOIN assignments a
                     ON sg.assignment_id = a.assignment_id
                 WHERE gm.user_id = sts.student_id
                 AND a.subject_section_id = sts.subject_section_id
             )";

$groupStmt = $conn->prepare($groupSql);
$groupStmt->bind_param("i", $subject_section_id);
$groupStmt->execute();
$groupResult = $groupStmt->get_result();
$groupRow = $groupResult->fetch_assoc();

$expectedStudents = (int)$subject["expected_students"];
$enrolledStudents = (int)$subject["enrolled_students"];
$groupedStudents = (int)$groupRow["grouped_students"];

$enrolmentPercentage = $expectedStudents > 0
    ? min(100, round(($enrolledStudents / $expectedStudents) * 100))
    : 0;

$groupPercentage = $enrolledStudents > 0
    ? min(100, round(($groupedStudents / $enrolledStudents) * 100))
    : 0;

$groupStmt->close();
?>
                    <div class="lecturer-subject-card">
 <div class="lecturer-subject-card-top">

    <div class="lecturer-subject-icon">
        <i class="fa-solid fa-book-open"></i>
    </div>

    <div class="lecturer-subject-header-content">

        <div class="lecturer-subject-header-row">
            <p class="lecturer-subject-code">
                <?php echo htmlspecialchars($subject["subject_code"]); ?>
            </p>

            <span class="lecturer-enrolment-label">
                Student Enrolment
            </span>
        </div>

        <div class="lecturer-subject-header-row">
            <h3>
                <?php echo htmlspecialchars($subject["subject_name"]); ?>
            </h3>

            <strong class="lecturer-enrolment-count">
                <?php echo $enrolledStudents; ?>
                /
                <?php echo $expectedStudents; ?>
                Students
            </strong>
        </div>

        <div class="lecturer-subject-enrolment-note">
            Joined this subject
        </div>

    </div>

</div>

                        <div class="lecturer-subject-details">
                            <div>
                                <span>Section</span>
                                <strong><?php echo htmlspecialchars($subject["section"]); ?></strong>
                            </div>
                            <div>
                                <span>Semester</span>
                                <strong><?php echo htmlspecialchars($subject["semester"]); ?></strong>
                            </div>
                            <div>
                                <span>Programme</span>
                                <strong><?php echo htmlspecialchars($subject["programme_code"]); ?></strong>
                            </div>
                        </div>

                        <div class="lecturer-subject-join">
                            <div>
                                <span>Student Join Code</span>
                                <strong><?php echo htmlspecialchars($subject["join_code"]); ?></strong>
                            </div>
                            <button type="button"
                                    class="lecturer-copy-code-button"
                                    data-code="<?php echo htmlspecialchars($subject["join_code"]); ?>">
                                <i class="fa-regular fa-copy"></i>
                                Copy Code
                            </button>
                        </div>
                        <div class="lecturer-subject-progress">

<div class="lecturer-subject-group-status">

    <div>
        <span>Group Formation</span>

        <strong>
            <?php echo $groupedStudents; ?>
            /
            <?php echo $enrolledStudents; ?>
            Students
        </strong>

        <small>
            Enrolled students currently in a group.
        </small>
    </div>

<div class="lecturer-subject-actions">
    <button
        type="button"
        class="lecturer-edit-subject-button"
        data-section-id="<?php echo $subject_section_id; ?>"
        data-subject-code="<?php echo htmlspecialchars($subject["subject_code"]); ?>"
        data-subject-name="<?php echo htmlspecialchars($subject["subject_name"]); ?>"
        data-programme="<?php echo htmlspecialchars($subject["programme_code"]); ?>"
        data-semester="<?php echo htmlspecialchars($subject["semester"]); ?>"
        data-section="<?php echo htmlspecialchars($subject["section"]); ?>"
        data-expected="<?php echo $expectedStudents; ?>">
        <i class="fa-solid fa-pen"></i>
        Edit Subject
    </button>

    <button
        type="button"
        class="lecturer-view-students-button"
        data-section-id="<?php echo $subject_section_id; ?>">
        <i class="fa-solid fa-users"></i>
        View Students
    </button>
</div>
</div>
</div>
</div>
                <?php endwhile; ?>
            </div>
        <?php else: ?>
            <div class="lecturer-subject-empty">
                <div class="lecturer-subject-empty-icon">
                    <i class="fa-solid fa-book-open"></i>
                </div>
                <h3>No subjects created yet</h3>
                <p>Create your first subject to start managing your classes.</p>
            </div>
        <?php endif; ?>
    </section>
</main>

<div class="lecturer-subject-modal" id="createSubjectModal">
    <div class="lecturer-subject-modal-box">
        <button type="button" class="lecturer-subject-modal-close" id="closeCreateSubject">
            <i class="fa-solid fa-xmark"></i>
        </button>

        <h2>Create Subject</h2>
        <p class="lecturer-subject-modal-subtitle">
            Add a subject and create a section for your students.
        </p>

        <form action="create_lecturer_subject.php" method="POST">
            <div class="lecturer-subject-form-group">
                <label>Subject Code</label>
                <input type="text" name="subject_code" placeholder="e.g. CSC123" required>
            </div>

            <div class="lecturer-subject-form-group">
                <label>Subject Name</label>
                <input type="text" name="subject_name" placeholder="e.g. Web Application Development" required>
            </div>

            <div class="lecturer-subject-form-row">
                <div class="lecturer-subject-form-group">
                    <label>Programme</label>
                    <input type="text" name="programme_code" placeholder="e.g. CS" required>
                </div>

                <div class="lecturer-subject-form-group">
                    <label>Semester</label>
                    <input type="number" name="semester" min="1" placeholder="e.g. 3" required>
                </div>
            </div>

<div class="lecturer-subject-form-row">

    <div class="lecturer-subject-form-group">
        <label>Section</label>
        <input
            type="text"
            name="section"
            placeholder="e.g. 1"
            required>
    </div>

    <div class="lecturer-subject-form-group">
        <label>Expected Students</label>
        <input
            type="number"
            name="expected_students"
            min="1"
            placeholder="e.g. 20"
            required>
    </div>

</div>

<button type="submit" class="lecturer-subject-submit-button">
    <i class="fa-solid fa-plus"></i>
    Create Subject
</button>
        </form>
    </div>
</div>

<div class="lecturer-subject-modal" id="editSubjectModal">

    <div class="lecturer-subject-modal-box">

        <button
            type="button"
            class="lecturer-subject-modal-close"
            id="closeEditSubjectModal">
            <i class="fa-solid fa-xmark"></i>
        </button>

        <div class="lecturer-subject-modal-header">
            <h2>Edit Subject</h2>
            <p>Update the subject and section information.</p>
        </div>

        <form action="edit_lecturer_subject.php" method="POST">

            <input
                type="hidden"
                name="subject_section_id"
                id="editSubjectSectionId">

            <div class="lecturer-subject-form-group">
                <label>Subject Code</label>

                <input
                    type="text"
                    name="subject_code"
                    id="editSubjectCode"
                    required>
            </div>

            <div class="lecturer-subject-form-group">
                <label>Subject Name</label>

                <input
                    type="text"
                    name="subject_name"
                    id="editSubjectName"
                    required>
            </div>

            <div class="lecturer-subject-form-row">

                <div class="lecturer-subject-form-group">
                    <label>Programme</label>

                    <input
                        type="text"
                        name="programme_code"
                        id="editProgrammeCode"
                        required>
                </div>

                <div class="lecturer-subject-form-group">
                    <label>Semester</label>

                    <input
                        type="number"
                        name="semester"
                        id="editSemester"
                        min="1"
                        required>
                </div>

            </div>

            <div class="lecturer-subject-form-row">

                <div class="lecturer-subject-form-group">
                    <label>Section</label>

                    <input
                        type="text"
                        name="section"
                        id="editSection"
                        required>
                </div>

                <div class="lecturer-subject-form-group">
                    <label>Expected Students</label>

                    <input
                        type="number"
                        name="expected_students"
                        id="editExpectedStudents"
                        min="1"
                        required>
                </div>

            </div>

            <button
                type="submit"
                class="lecturer-subject-submit-button">
                <i class="fa-solid fa-floppy-disk"></i>
                Save Changes
            </button>
        </form>
    </div>
</div>

<div class="lecturer-students-modal" id="viewStudentsModal">
    <div class="lecturer-students-modal-box">

        <button
            type="button"
            class="lecturer-students-modal-close"
            id="closeStudentsModal">
            <i class="fa-solid fa-xmark"></i>
        </button>

        <div class="lecturer-students-modal-header">
            <p>ENROLLED STUDENTS</p>
            <h2 id="studentsModalTitle">Students</h2>
            <span id="studentsModalCount"></span>
        </div>

        <div class="lecturer-students-list" id="studentsModalList">
        </div>

    </div>
</div>

<?php include "lecturer_profile.php"; ?>

<script>
document.addEventListener("DOMContentLoaded", function () {
    const sidebar = document.querySelector(".sidebar");
    const sidebarToggle = document.getElementById("sidebarToggle");
    const modal = document.getElementById("createSubjectModal");
    const openModal = document.getElementById("openCreateSubject");
    const closeModal = document.getElementById("closeCreateSubject");

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

    document.querySelectorAll(".lecturer-copy-code-button").forEach(function (button) {
        button.addEventListener("click", function () {
            navigator.clipboard.writeText(this.dataset.code);
            this.innerHTML = '<i class="fa-solid fa-check"></i> Copied';
        });
    });
});

const editSubjectModal = document.getElementById("editSubjectModal");
const closeEditSubjectModal = document.getElementById("closeEditSubjectModal");
const editSubjectButtons = document.querySelectorAll(".lecturer-edit-subject-button");

editSubjectButtons.forEach(function (button) {
    button.addEventListener("click", function () {

        document.getElementById("editSubjectSectionId").value =
            button.dataset.sectionId;

        document.getElementById("editSubjectCode").value =
            button.dataset.subjectCode;

        document.getElementById("editSubjectName").value =
            button.dataset.subjectName;

        document.getElementById("editProgrammeCode").value =
            button.dataset.programme;

        document.getElementById("editSemester").value =
            button.dataset.semester;

        document.getElementById("editSection").value =
            button.dataset.section;

        document.getElementById("editExpectedStudents").value =
            button.dataset.expected;

        editSubjectModal.classList.add("show");
    });
});

closeEditSubjectModal.addEventListener("click", function () {
    editSubjectModal.classList.remove("show");
});

editSubjectModal.addEventListener("click", function (event) {
    if (event.target === editSubjectModal) {
        editSubjectModal.classList.remove("show");
    }
});

const viewStudentsModal = document.getElementById("viewStudentsModal");
const closeStudentsModal = document.getElementById("closeStudentsModal");
const studentsModalTitle = document.getElementById("studentsModalTitle");
const studentsModalCount = document.getElementById("studentsModalCount");
const studentsModalList = document.getElementById("studentsModalList");

const viewStudentsButtons =
    document.querySelectorAll(".lecturer-view-students-button");

viewStudentsButtons.forEach(function (button) {
    button.addEventListener("click", function () {

        const sectionId = button.dataset.sectionId;

        studentsModalTitle.textContent = "Students";
        studentsModalCount.textContent = "Loading...";
        studentsModalList.innerHTML = "";

        viewStudentsModal.classList.add("show");

        fetch("get_subject_students.php?section_id=" + sectionId)
            .then(function (response) {
                return response.json();
            })
            .then(function (data) {

                if (!data.success) {
                    studentsModalCount.textContent = "";

                    studentsModalList.innerHTML =
                        '<div class="lecturer-students-empty">' +
                        '<i class="fa-solid fa-circle-exclamation"></i>' +
                        data.message +
                        '</div>';

                    return;
                }

                studentsModalTitle.textContent =
                    data.subject_code + " - " + data.subject_name;

                studentsModalCount.textContent =
                    data.total + " student(s) enrolled";

                studentsModalList.innerHTML = "";

                if (data.students.length === 0) {
                    studentsModalList.innerHTML =
                        '<div class="lecturer-students-empty">' +
                        '<i class="fa-solid fa-user-group"></i>' +
                        'No students have joined this subject yet.' +
                        '</div>';

                    return;
                }

                data.students.forEach(function (student) {

                    const item = document.createElement("div");
                    item.className = "lecturer-student-item";

                    const firstLetter =
                        student.full_name.charAt(0).toUpperCase();

                    let groupName = "No Group";
                    let statusClass = "lecturer-student-not-grouped";
                    let statusText = "Not Grouped";

                    if (student.grouped) {
                        groupName = student.group_name;
                        statusClass = "lecturer-student-grouped";
                        statusText = "Grouped";
                    }

                    item.innerHTML =
                        '<div class="lecturer-student-avatar">' +
                            firstLetter +
                        '</div>' +

                        '<div class="lecturer-student-info">' +
                            '<strong>' + escapeHtml(student.full_name) + '</strong>' +
                            '<span>' + escapeHtml(student.user_code) + '</span>' +
                        '</div>' +

                        '<div class="lecturer-student-group">' +
                            '<strong>' + escapeHtml(groupName) + '</strong>' +
                            '<span class="' + statusClass + '">' +
                                statusText +
                            '</span>' +
                        '</div>';

                    studentsModalList.appendChild(item);
                });
            })
            .catch(function () {
                studentsModalCount.textContent = "";

                studentsModalList.innerHTML =
                    '<div class="lecturer-students-empty">' +
                    '<i class="fa-solid fa-circle-exclamation"></i>' +
                    'Unable to load students.' +
                    '</div>';
            });
    });
});

closeStudentsModal.addEventListener("click", function () {
    viewStudentsModal.classList.remove("show");
});

viewStudentsModal.addEventListener("click", function (event) {
    if (event.target === viewStudentsModal) {
        viewStudentsModal.classList.remove("show");
    }
});

function escapeHtml(text) {
    const div = document.createElement("div");
    div.textContent = text ?? "";
    return div.innerHTML;
}
</script>
</body>
</html>