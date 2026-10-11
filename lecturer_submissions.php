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

$subjects = [];
$sql = "SELECT DISTINCT s.subject_id, s.subject_code, s.subject_name
        FROM lecturer_subjects ls
        JOIN subjects s ON ls.subject_id = s.subject_id
        WHERE ls.lecturer_id = ?
        ORDER BY s.subject_code ASC";
$stmt = $conn->prepare($sql);
$stmt->bind_param("i", $lecturer_id);
$stmt->execute();
$result = $stmt->get_result();
while ($row = $result->fetch_assoc()) {
    $subjects[] = $row;
}
$stmt->close();

$programmes = [
    "FA001", "FC001", "FT001", "AA103", "CC101",
    "BK101", "AA201", "AB201", "AB202", "CT207",
    "AB203", "BE201", "BE202", "BK201", "BE203",
    "CC204", "CM201", "CC203", "CT204", "CT206"
];

$semesters = ["1", "2", "3", "4", "5", "6", "7"];

$sections = ["1", "2", "3", "4", "5", "6"];

$assignments = [];
$sql = "SELECT assignment_id, title
        FROM assignments
        WHERE lecturer_id = ?
        ORDER BY created_at DESC";
$stmt = $conn->prepare($sql);
$stmt->bind_param("i", $lecturer_id);
$stmt->execute();
$result = $stmt->get_result();
while ($row = $result->fetch_assoc()) {
    $assignments[] = $row;
}
$stmt->close();

$sql = "SELECT fs.final_submission_id, fs.file_name, fs.file_path, fs.submitted_at,
        sg.group_id, sg.group_name,
        u.user_id, u.user_code, u.full_name, u.nickname,
        u.programme_code, u.semester, u.section,
        a.assignment_id, a.title AS assignment_title,
        s.subject_id, s.subject_code, s.subject_name
        FROM final_submissions fs
        JOIN student_groups sg ON fs.group_id = sg.group_id
        JOIN users u ON fs.submitted_by = u.user_id
        JOIN assignments a ON fs.assignment_id = a.assignment_id
        LEFT JOIN subjects s ON a.subject_id = s.subject_id
        WHERE a.lecturer_id = ?";

$params = [$lecturer_id];
$types = "i";

if ($selectedSubject !== "") {
    $sql .= " AND a.subject_id = ?";
    $params[] = $selectedSubject;
    $types .= "i";
}

if ($selectedProgramme !== "") {
    $sql .= " AND u.programme_code = ?";
    $params[] = $selectedProgramme;
    $types .= "s";
}

if ($selectedSemester !== "") {
    $sql .= " AND u.semester = ?";
    $params[] = $selectedSemester;
    $types .= "s";
}

if ($selectedSection !== "") {
    $sql .= " AND u.section = ?";
    $params[] = $selectedSection;
    $types .= "s";
}

if ($selectedAssignment !== "") {
    $sql .= " AND a.assignment_id = ?";
    $params[] = $selectedAssignment;
    $types .= "i";
}

$sql .= " ORDER BY fs.submitted_at DESC";

$stmt = $conn->prepare($sql);
$stmt->bind_param($types, ...$params);
$stmt->execute();
$submissions = $stmt->get_result();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Student Submissions | FairPeer</title>
    <link rel="stylesheet" href="css/style.css">
    <link rel="stylesheet" href="css/dashboard.css">
    <link rel="stylesheet" href="css/lecturer.css">
    <link rel="stylesheet" href="css/lecturer-submissions.css">
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
        <a href="lecturer_submissions.php" class="menu-item active">
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
            <h1>Submissions</h1>
            <p>View final assignment submissions from student groups.</p>
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

    <section class="lecturer-submissions-page">
        <div class="lecturer-submissions-heading">
            <p class="lecturer-submissions-label">SUBMISSIONS</p>
            <h2>Final Assignment Submissions</h2>
        </div>

        <form method="GET" action="" class="lecturer-submission-filter">
            <div class="lecturer-submission-filter-group">
                <label>Subject</label>
                <select name="subject">
                    <option value="">All Subjects</option>
                    <?php foreach ($subjects as $subject): ?>
                        <option value="<?php echo $subject["subject_id"]; ?>"
                            <?php if ($selectedSubject == $subject["subject_id"]) echo "selected"; ?>>
                            <?php echo htmlspecialchars($subject["subject_code"]); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div class="lecturer-submission-filter-group">
                <label>Programme</label>
                <select name="programme">
                    <option value="">All Programmes</option>
                    <?php foreach ($programmes as $programme): ?>
                        <option value="<?php echo htmlspecialchars($programme); ?>"
                            <?php if ($selectedProgramme === $programme) echo "selected"; ?>>
                            <?php echo htmlspecialchars($programme); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div class="lecturer-submission-filter-group">
                <label>Semester</label>
                <select name="semester">
                    <option value="">All Semesters</option>
                    <?php foreach ($semesters as $semester): ?>
                        <option value="<?php echo htmlspecialchars($semester); ?>"
                            <?php if ($selectedSemester === $semester) echo "selected"; ?>>
                            <?php echo htmlspecialchars($semester); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div class="lecturer-submission-filter-group">
                <label>Section</label>
                <select name="section">
                    <option value="">All Sections</option>
                    <?php foreach ($sections as $section): ?>
                        <option value="<?php echo htmlspecialchars($section); ?>"
                            <?php if ($selectedSection === $section) echo "selected"; ?>>
                            <?php echo htmlspecialchars($section); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div class="lecturer-submission-filter-group">
                <label>Assignment</label>
                <select name="assignment">
                    <option value="">All Assignments</option>
                    <?php foreach ($assignments as $assignment): ?>
                        <option value="<?php echo $assignment["assignment_id"]; ?>"
                            <?php if ($selectedAssignment == $assignment["assignment_id"]) echo "selected"; ?>>
                            <?php echo htmlspecialchars($assignment["title"]); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div class="lecturer-submission-filter-action">
                <button type="submit" class="lecturer-submission-filter-btn">
                    <i class="fa-solid fa-filter"></i>
                    Filter
                </button>
            </div>
        </form>

        <?php if ($submissions->num_rows > 0): ?>
            <div class="lecturer-submission-list">
                <?php while ($submission = $submissions->fetch_assoc()): ?>
                    <div class="lecturer-submission-card">
                        <div class="lecturer-submission-student">
    <div class="lecturer-submission-avatar">
        <?php echo strtoupper(substr($submission["group_name"], 0, 1)); ?>
    </div>
    <div>
        <h3><?php echo htmlspecialchars($submission["group_name"]); ?></h3>
        <p>
            Team Leader:
            <?php echo htmlspecialchars($submission["nickname"] ?? $submission["full_name"]); ?>
        </p>
    </div>
</div>

<div class="lecturer-submission-detail">
    <span>Assignment</span>
    <strong><?php echo htmlspecialchars($submission["assignment_title"]); ?></strong>
    <small>
        <?php echo htmlspecialchars($submission["subject_code"] ?? "No Subject"); ?>
        <?php if (!empty($submission["subject_name"])): ?>
            - <?php echo htmlspecialchars($submission["subject_name"]); ?>
        <?php endif; ?>
    </small>
</div>

                        <div class="lecturer-submission-detail">
                            <span>Class</span>
                            <strong><?php echo htmlspecialchars($submission["programme_code"] ?? "-"); ?></strong>
                            <small>
                                Semester <?php echo htmlspecialchars($submission["semester"] ?? "-"); ?>
                                · Section <?php echo htmlspecialchars($submission["section"] ?? "-"); ?>
                            </small>
                        </div>

<div class="lecturer-submission-detail">
    <span>Final File</span>
    <strong><?php echo htmlspecialchars($submission["file_name"]); ?></strong>
    <small>Final Assignment</small>
</div>

<div class="lecturer-submission-detail">
    <span>Submitted</span>
    <strong>
        <?php echo date("d M Y", strtotime($submission["submitted_at"])); ?>
    </strong>
    <small>
        <?php echo date("h:i A", strtotime($submission["submitted_at"])); ?>
    </small>
</div>

                        <div class="lecturer-submission-action">
                            <a href="<?php echo htmlspecialchars($submission["file_path"]); ?>"
                               target="_blank"
                               class="lecturer-submission-view-btn">
                                <i class="fa-solid fa-eye"></i>
                                View File
                            </a>
                        </div>
                    </div>
                <?php endwhile; ?>
            </div>
        <?php else: ?>
            <div class="lecturer-submissions-empty">
                <i class="fa-solid fa-file-circle-check"></i>
                <h3>No submissions yet</h3>
                 <p>Final assignment submissions from student groups will appear here.</p>
            </div>
        <?php endif; ?>
    </section>
</main>

<?php include "lecturer_profile.php"; ?>

<script>
document.addEventListener("DOMContentLoaded", function () {
    const sidebar = document.querySelector(".sidebar");
    const sidebarToggle = document.getElementById("sidebarToggle");

    if (sidebarToggle) {
        sidebarToggle.addEventListener("click", function () {
            sidebar.classList.toggle("closed");
        });
    }
});
</script>

</body>
</html>