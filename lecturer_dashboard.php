<?php
session_start();
require_once "db.php";

if (!isset($_SESSION["user_id"]) || $_SESSION["user_role"] !== "Lecturer") {
    header("Location: index.php");
    exit;
}

$lecturer_id = $_SESSION["user_id"];
$nickname = $_SESSION["nickname"] ?? "Lecturer";
$full_name = $_SESSION["full_name"] ?? "Lecturer";

$subjectCount = 0;
$assignmentCount = 0;
$sectionCount = 0;

$sql = "SELECT COUNT(DISTINCT subject_id) AS total
        FROM lecturer_subjects
        WHERE lecturer_id = ?";
$stmt = $conn->prepare($sql);
$stmt->bind_param("i", $lecturer_id);
$stmt->execute();
$result = $stmt->get_result();
if ($row = $result->fetch_assoc()) {
    $subjectCount = $row["total"];
}
$stmt->close();

$sql = "SELECT COUNT(*) AS total
        FROM assignments
        WHERE lecturer_id = ?";
$stmt = $conn->prepare($sql);
$stmt->bind_param("i", $lecturer_id);
$stmt->execute();
$result = $stmt->get_result();
if ($row = $result->fetch_assoc()) {
    $assignmentCount = $row["total"];
}
$stmt->close();

$sql = "SELECT COUNT(*) AS total
        FROM subject_sections
        WHERE lecturer_id = ?";
$stmt = $conn->prepare($sql);
$stmt->bind_param("i", $lecturer_id);
$stmt->execute();
$result = $stmt->get_result();
if ($row = $result->fetch_assoc()) {
    $sectionCount = $row["total"];
}
$stmt->close();

$sql = "SELECT a.assignment_id, a.title, a.due_date, a.status,
        s.subject_code, s.subject_name, ss.section
        FROM assignments a
        LEFT JOIN subjects s ON a.subject_id = s.subject_id
        LEFT JOIN subject_sections ss ON a.subject_section_id = ss.subject_section_id
        WHERE a.lecturer_id = ?
        ORDER BY a.created_at DESC
        LIMIT 5";
$stmt = $conn->prepare($sql);
$stmt->bind_param("i", $lecturer_id);
$stmt->execute();
$recentAssignments = $stmt->get_result();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Lecturer Dashboard | FairPeer</title>
    <link rel="stylesheet" href="css/style.css">
    <link rel="stylesheet" href="css/dashboard.css">
    <link rel="stylesheet" href="css/lecturer.css">
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
        <a href="lecturer_dashboard.php" class="menu-item active">
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
        <h1>Welcome back, <?php echo htmlspecialchars($nickname); ?></h1>
        <p>Manage your subjects, assignments and student progress.</p>
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

    <section class="lecturer-dashboard">
        <div class="lecturer-stat-grid">
            <div class="lecturer-stat-card">
                <div class="lecturer-stat-icon">
                    <i class="fa-solid fa-book-open"></i>
                </div>
                <div>
                    <p>My Subjects</p>
                    <h2><?php echo $subjectCount; ?></h2>
                </div>
            </div>

            <div class="lecturer-stat-card">
                <div class="lecturer-stat-icon">
                    <i class="fa-solid fa-book"></i>
                </div>
                <div>
                    <p>My Assignments</p>
                    <h2><?php echo $assignmentCount; ?></h2>
                </div>
            </div>

            <div class="lecturer-stat-card">
                <div class="lecturer-stat-icon">
                    <i class="fa-solid fa-users"></i>
                </div>
                <div>
                    <p>Sections</p>
                    <h2><?php echo $sectionCount; ?></h2>
                </div>
            </div>
        </div>

        <div class="lecturer-recent-heading">
            <p class="section-label">RECENT ACTIVITY</p>
            <h2>Recent Assignments</h2>
        </div>

<div class="lecturer-recent-section">
    <?php if ($recentAssignments->num_rows > 0): ?>
                <div class="lecturer-assignment-list">
                    <?php while ($assignment = $recentAssignments->fetch_assoc()): ?>
                        <div class="lecturer-assignment-item">
                            <div>
                                <h3><?php echo htmlspecialchars($assignment["title"]); ?></h3>
                                <p>
                                    <?php echo htmlspecialchars($assignment["subject_code"] ?? "No Subject"); ?>
                                    <?php if (!empty($assignment["section"])): ?>
                                        · Section <?php echo htmlspecialchars($assignment["section"]); ?>
                                    <?php endif; ?>
                                </p>
                            </div>
                            <div class="lecturer-assignment-date">
                                <span>Due Date</span>
                                <strong><?php echo date("d M Y", strtotime($assignment["due_date"])); ?></strong>
                            </div>
                        </div>
                    <?php endwhile; ?>
                </div>
            <?php else: ?>
                <div class="lecturer-empty-card">
                    <i class="fa-solid fa-file-circle-plus"></i>
                    <h3>No assignments created yet</h3>
                    <p>Your recently created assignments will appear here.</p>
                </div>
            <?php endif; ?>
        </div>
    </section>
</main>

<?php include "lecturer_profile.php"; ?>

<script>
document.addEventListener("DOMContentLoaded", function () {
    const sidebar = document.querySelector(".sidebar");
    const sidebarToggle = document.getElementById("sidebarToggle");
    sidebarToggle.addEventListener("click", function () {
        sidebar.classList.toggle("closed");
    });
});
</script>
</body>
</html>