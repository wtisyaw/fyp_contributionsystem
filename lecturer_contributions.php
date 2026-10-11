<?php
session_start();
require_once "db.php";

if (!isset($_SESSION["user_id"]) || $_SESSION["user_role"] !== "Lecturer") {
    header("Location: index.php");
    exit;
}

$lecturer_id = $_SESSION["user_id"];
$nickname = $_SESSION["nickname"] ?? "Lecturer";

$selectedSubject = $_GET["subject"] ?? "";
$selectedProgramme = $_GET["programme"] ?? "";
$selectedSemester = $_GET["semester"] ?? "";
$selectedSection = $_GET["section"] ?? "";
$selectedAssignment = $_GET["assignment"] ?? "";

$programmes = [
    "FA001", "FC001", "FT001", "AA103", "CC101", "BK101",
    "AA201", "AB201", "AB202", "CT207", "AB203", "BE201",
    "BE202", "BK201", "BE203", "CC204", "CM201", "CC203",
    "CT204", "CT206"
];

$semesters = ["1", "2", "3", "4", "5", "6", "7"];
$sections = ["1", "2", "3", "4", "5", "6"];

$sql = "SELECT DISTINCT s.subject_id, s.subject_code, s.subject_name
        FROM lecturer_subjects ls
        JOIN subjects s ON ls.subject_id = s.subject_id
        WHERE ls.lecturer_id = ?
        ORDER BY s.subject_code ASC";
$stmt = $conn->prepare($sql);
$stmt->bind_param("i", $lecturer_id);
$stmt->execute();
$subjects = $stmt->get_result();
$stmt->close();

$sql = "SELECT a.assignment_id, a.title, a.subject_id,
        ss.programme_code, ss.semester, ss.section
        FROM assignments a
        LEFT JOIN subject_sections ss
        ON a.subject_section_id = ss.subject_section_id
        WHERE a.lecturer_id = ?
        ORDER BY a.created_at DESC";
$stmt = $conn->prepare($sql);
$stmt->bind_param("i", $lecturer_id);
$stmt->execute();
$assignmentResult = $stmt->get_result();

$assignments = [];
while ($row = $assignmentResult->fetch_assoc()) {
    $assignments[] = $row;
}
$stmt->close();

$groups = [];

if ($selectedAssignment !== "") {
    $sql = "SELECT sg.group_id, sg.group_name, sg.num_members,
            sg.assignment_id, a.title,
            s.subject_code, s.subject_name,
            ss.programme_code, ss.semester, ss.section
            FROM student_groups sg
            JOIN assignments a ON sg.assignment_id = a.assignment_id
            LEFT JOIN subjects s ON a.subject_id = s.subject_id
            LEFT JOIN subject_sections ss
            ON a.subject_section_id = ss.subject_section_id
            WHERE a.lecturer_id = ?
            AND a.assignment_id = ?";

    $params = [$lecturer_id, $selectedAssignment];
    $types = "ii";

    if ($selectedSubject !== "") {
        $sql .= " AND a.subject_id = ?";
        $params[] = $selectedSubject;
        $types .= "i";
    }

    if ($selectedProgramme !== "") {
        $sql .= " AND ss.programme_code = ?";
        $params[] = $selectedProgramme;
        $types .= "s";
    }

    if ($selectedSemester !== "") {
        $sql .= " AND ss.semester = ?";
        $params[] = $selectedSemester;
        $types .= "s";
    }

    if ($selectedSection !== "") {
        $sql .= " AND ss.section = ?";
        $params[] = $selectedSection;
        $types .= "s";
    }

    $sql .= " ORDER BY sg.group_name ASC";

    $stmt = $conn->prepare($sql);
    $stmt->bind_param($types, ...$params);
    $stmt->execute();
    $groupResult = $stmt->get_result();

    while ($row = $groupResult->fetch_assoc()) {
        $groups[] = $row;
    }

    $stmt->close();
}

$viewGroup = null;
$groupMembers = [];

if (isset($_GET["group_id"]) && $_GET["group_id"] !== "") {
    $group_id = (int) $_GET["group_id"];

    $sql = "SELECT sg.group_id, sg.group_name, sg.num_members,
            a.assignment_id, a.title,
            s.subject_code, s.subject_name,
            ss.programme_code, ss.semester, ss.section
            FROM student_groups sg
            JOIN assignments a ON sg.assignment_id = a.assignment_id
            LEFT JOIN subjects s ON a.subject_id = s.subject_id
            LEFT JOIN subject_sections ss
            ON a.subject_section_id = ss.subject_section_id
            WHERE sg.group_id = ?
            AND a.lecturer_id = ?
            LIMIT 1";

    $stmt = $conn->prepare($sql);
    $stmt->bind_param("ii", $group_id, $lecturer_id);
    $stmt->execute();
    $result = $stmt->get_result();
    $viewGroup = $result->fetch_assoc();
    $stmt->close();

    if ($viewGroup) {
        $sql = "SELECT u.user_id, u.user_code, u.full_name
                FROM group_members gm
                JOIN users u ON gm.user_id = u.user_id
                WHERE gm.group_id = ?
                ORDER BY u.full_name ASC";

        $stmt = $conn->prepare($sql);
        $stmt->bind_param("i", $group_id);
        $stmt->execute();
        $memberResult = $stmt->get_result();

        while ($row = $memberResult->fetch_assoc()) {
            $groupMembers[] = $row;
        }

        $stmt->close();
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Student Contributions | FairPeer</title>
    <link rel="stylesheet" href="css/style.css">
    <link rel="stylesheet" href="css/dashboard.css">
    <link rel="stylesheet" href="css/lecturer.css">
    <link rel="stylesheet" href="css/lecturer-contributions.css">
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
        <a href="lecturer_assignments.php" class="menu-item">
            <i class="fa-solid fa-book"></i>
            <span>My Assignments</span>
        </a>
        <a href="lecturer_submissions.php" class="menu-item">
            <i class="fa-solid fa-file-lines"></i>
            <span>Submissions</span>
        </a>
        <a href="lecturer_contributions.php" class="menu-item active">
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
            <h1>Contributions</h1>
            <p>Monitor student group contributions and peer review activity.</p>
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

    <section class="lecturer-contributions-page">

        <div class="lecturer-contributions-heading">
            <p class="lecturer-contributions-label">CONTRIBUTIONS</p>
            <h2>Student Contributions</h2>
        </div>

        <form method="GET" action="lecturer_contributions.php" class="lecturer-contribution-filter">

            <div class="lecturer-contribution-filter-group">
                <label>Subject</label>
                <select name="subject" id="subjectFilter">
                    <option value="">All Subjects</option>

                    <?php while ($subject = $subjects->fetch_assoc()): ?>
                        <option
                            value="<?php echo $subject["subject_id"]; ?>"
                            <?php echo ($selectedSubject == $subject["subject_id"]) ? "selected" : ""; ?>>
                            <?php echo htmlspecialchars($subject["subject_code"]); ?>
                        </option>
                    <?php endwhile; ?>
                </select>
            </div>

            <div class="lecturer-contribution-filter-group">
                <label>Programme</label>
                <select name="programme" id="programmeFilter">
                    <option value="">All Programmes</option>

                    <?php foreach ($programmes as $programme): ?>
                        <option
                            value="<?php echo $programme; ?>"
                            <?php echo ($selectedProgramme === $programme) ? "selected" : ""; ?>>
                            <?php echo $programme; ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div class="lecturer-contribution-filter-group">
                <label>Semester</label>
                <select name="semester" id="semesterFilter">
                    <option value="">All Semesters</option>

                    <?php foreach ($semesters as $semester): ?>
                        <option
                            value="<?php echo $semester; ?>"
                            <?php echo ($selectedSemester === $semester) ? "selected" : ""; ?>>
                            Semester <?php echo $semester; ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div class="lecturer-contribution-filter-group">
                <label>Section</label>
                <select name="section" id="sectionFilter">
                    <option value="">All Sections</option>

                    <?php foreach ($sections as $section): ?>
                        <option
                            value="<?php echo $section; ?>"
                            <?php echo ($selectedSection === $section) ? "selected" : ""; ?>>
                            Section <?php echo $section; ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div class="lecturer-contribution-filter-group">
                <label>Assignment</label>

                <select name="assignment" id="assignmentFilter" required>
                    <option value="">Choose Assignment</option>

                    <?php foreach ($assignments as $assignment): ?>
                        <option
                            value="<?php echo $assignment["assignment_id"]; ?>"
                            data-subject="<?php echo $assignment["subject_id"]; ?>"
                            data-programme="<?php echo htmlspecialchars($assignment["programme_code"] ?? ""); ?>"
                            data-semester="<?php echo htmlspecialchars($assignment["semester"] ?? ""); ?>"
                            data-section="<?php echo htmlspecialchars($assignment["section"] ?? ""); ?>"
                            <?php echo ($selectedAssignment == $assignment["assignment_id"]) ? "selected" : ""; ?>>
                            <?php echo htmlspecialchars($assignment["title"]); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div class="lecturer-contribution-filter-action">
                <button type="submit" class="lecturer-contribution-filter-btn">
                    <i class="fa-solid fa-filter"></i>
                    Filter
                </button>
            </div>

        </form>

        <?php if ($selectedAssignment !== ""): ?>

            <div class="lecturer-group-heading">
                <p>GROUPS</p>
                <h2>Student Groups</h2>
            </div>

            <?php if (count($groups) > 0): ?>

                <div class="lecturer-group-grid">

                    <?php foreach ($groups as $group): ?>

                        <div class="lecturer-group-card">

                            <div class="lecturer-group-card-top">

                                <div class="lecturer-group-icon">
                                    <i class="fa-solid fa-user-group"></i>
                                </div>

                                <div>
                                    <h3><?php echo htmlspecialchars($group["group_name"]); ?></h3>
                                    <p>
                                        <?php echo htmlspecialchars($group["subject_code"] ?? ""); ?>
                                        <?php if (!empty($group["section"])): ?>
                                            · Section <?php echo htmlspecialchars($group["section"]); ?>
                                        <?php endif; ?>
                                    </p>
                                </div>

                            </div>

                            <div class="lecturer-group-details">

                                <div>
                                    <span>Assignment</span>
                                    <strong><?php echo htmlspecialchars($group["title"]); ?></strong>
                                </div>

                                <div>
                                    <span>Members</span>
                                    <strong><?php echo (int) $group["num_members"]; ?></strong>
                                </div>

                                <div>
                                    <span>Programme</span>
                                    <strong><?php echo htmlspecialchars($group["programme_code"] ?? "-"); ?></strong>
                                </div>

                            </div>

                            <div class="lecturer-group-card-bottom">

                                <span>
                                    Semester <?php echo htmlspecialchars($group["semester"] ?? "-"); ?>
                                </span>

                                <a
                                    href="lecturer_contributions.php?subject=<?php echo urlencode($selectedSubject); ?>&programme=<?php echo urlencode($selectedProgramme); ?>&semester=<?php echo urlencode($selectedSemester); ?>&section=<?php echo urlencode($selectedSection); ?>&assignment=<?php echo urlencode($selectedAssignment); ?>&group_id=<?php echo $group["group_id"]; ?>"
                                    class="lecturer-view-group-btn">
                                    View Group
                                </a>

                            </div>

                        </div>

                    <?php endforeach; ?>

                </div>

            <?php else: ?>

                <div class="lecturer-groups-empty">
                    <i class="fa-solid fa-user-group"></i>
                    <h3>No groups found</h3>
                    <p>No student groups are available for the selected assignment.</p>
                </div>

            <?php endif; ?>

        <?php endif; ?>

    </section>
</main>

<?php if ($viewGroup): ?>

<div class="lecturer-group-modal show" id="groupModal">

    <div class="lecturer-group-modal-box">

        <button type="button" class="lecturer-group-modal-close" id="closeGroupModal">
            <i class="fa-solid fa-xmark"></i>
        </button>

        <div class="lecturer-group-modal-header">
            <p>GROUP DETAILS</p>
            <h2><?php echo htmlspecialchars($viewGroup["group_name"]); ?></h2>
            <span>
                <?php echo htmlspecialchars($viewGroup["subject_code"] ?? ""); ?>
                · <?php echo htmlspecialchars($viewGroup["title"]); ?>
            </span>
        </div>

        <div class="lecturer-group-summary">

            <div>
                <span>Programme</span>
                <strong><?php echo htmlspecialchars($viewGroup["programme_code"] ?? "-"); ?></strong>
            </div>

            <div>
                <span>Semester</span>
                <strong><?php echo htmlspecialchars($viewGroup["semester"] ?? "-"); ?></strong>
            </div>

            <div>
                <span>Section</span>
                <strong><?php echo htmlspecialchars($viewGroup["section"] ?? "-"); ?></strong>
            </div>

            <div>
                <span>Members</span>
                <strong><?php echo count($groupMembers); ?></strong>
            </div>

        </div>

        <div class="lecturer-group-members-title">
            <h3>Group Members</h3>
        </div>

        <?php if (count($groupMembers) > 0): ?>

            <div class="lecturer-group-member-list">

                <?php foreach ($groupMembers as $member): ?>

                    <div class="lecturer-group-member">

                        <div class="lecturer-member-avatar">
                            <?php echo strtoupper(substr($member["full_name"], 0, 1)); ?>
                        </div>

                        <div class="lecturer-member-info">
                            <strong><?php echo htmlspecialchars($member["full_name"]); ?></strong>
                            <span>Student ID: <?php echo htmlspecialchars($member["user_code"]); ?></span>
                        </div>

                    </div>

                <?php endforeach; ?>

            </div>

        <?php else: ?>

            <div class="lecturer-no-members">
                No members found in this group.
            </div>

        <?php endif; ?>

        <div class="lecturer-group-modal-action">
            <a
                href="lecturer_report.php?group_id=<?php echo $viewGroup["group_id"]; ?>"
                class="lecturer-generate-report-btn">
                <i class="fa-solid fa-file-lines"></i>
                Generate Report
            </a>
        </div>

    </div>

</div>

<?php endif; ?>

<?php include "lecturer_profile.php"; ?>

<script>
const sidebar = document.querySelector(".sidebar");
const sidebarToggle = document.getElementById("sidebarToggle");

sidebarToggle.addEventListener("click", function () {
    sidebar.classList.toggle("closed");
});

const closeGroupModal = document.getElementById("closeGroupModal");
const groupModal = document.getElementById("groupModal");

if (closeGroupModal && groupModal) {
    closeGroupModal.addEventListener("click", function () {
        groupModal.classList.remove("show");
    });

    groupModal.addEventListener("click", function (event) {
        if (event.target === groupModal) {
            groupModal.classList.remove("show");
        }
    });
}

const subjectFilter = document.getElementById("subjectFilter");
const programmeFilter = document.getElementById("programmeFilter");
const semesterFilter = document.getElementById("semesterFilter");
const sectionFilter = document.getElementById("sectionFilter");
const assignmentFilter = document.getElementById("assignmentFilter");

function updateAssignments() {
    const subject = subjectFilter.value;
    const programme = programmeFilter.value;
    const semester = semesterFilter.value;
    const section = sectionFilter.value;

    Array.from(assignmentFilter.options).forEach(function(option) {
        if (option.value === "") {
            return;
        }

        const matchSubject =
            subject === "" ||
            option.dataset.subject === subject;

        const matchProgramme =
            programme === "" ||
            option.dataset.programme === programme;

        const matchSemester =
            semester === "" ||
            option.dataset.semester === semester;

        const matchSection =
            section === "" ||
            option.dataset.section === section;

        option.hidden = !(
            matchSubject &&
            matchProgramme &&
            matchSemester &&
            matchSection
        );
    });

    const selected = assignmentFilter.options[assignmentFilter.selectedIndex];

    if (selected && selected.hidden) {
        assignmentFilter.value = "";
    }
}

subjectFilter.addEventListener("change", updateAssignments);
programmeFilter.addEventListener("change", updateAssignments);
semesterFilter.addEventListener("change", updateAssignments);
sectionFilter.addEventListener("change", updateAssignments);

updateAssignments();
</script>

</body>
</html>