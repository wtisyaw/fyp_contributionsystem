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
$user_id = (int)$_SESSION["user_id"];
$assignment_id = isset($_GET["id"]) ? (int)$_GET["id"] : 0;
if ($assignment_id <= 0) {
    header("Location: student_assignments.php");
    exit;
}
$sql = "SELECT
            a.assignment_id,
            a.title,
            a.description,
            a.attachment_file,
            a.due_date,
            a.status,
            s.subject_code,
            s.subject_name,
            sg.group_id,
            sg.group_name,
            fs.final_submission_id,
            fs.submitted_at AS final_submitted_at
        FROM assignments a
        INNER JOIN student_subjects sts
            ON a.subject_section_id=sts.subject_section_id
            AND sts.student_id=?
        INNER JOIN subject_sections sec
            ON a.subject_section_id=sec.subject_section_id
        INNER JOIN subjects s
            ON sec.subject_id=s.subject_id
        LEFT JOIN student_groups sg
            ON a.assignment_id=sg.assignment_id
            AND EXISTS (
                SELECT 1
                FROM group_members gm
                WHERE gm.group_id=sg.group_id
                AND gm.user_id=?
            )
        LEFT JOIN final_submissions fs
            ON fs.assignment_id = a.assignment_id
            AND fs.group_id = sg.group_id
        WHERE a.assignment_id=?
        LIMIT 1";
$stmt = $conn->prepare($sql);
$stmt->bind_param("iii",$user_id,$user_id,$assignment_id);
$stmt->execute();
$result = $stmt->get_result();
if ($result->num_rows === 0) {
    $stmt->close();
    header("Location: student_assignments.php");
    exit;
}
$assignment = $result->fetch_assoc();
$stmt->close();
$hasGroup = !empty($assignment["group_id"]);
$hasAttachment = !empty($assignment["attachment_file"]);
$isCompleted = !empty($assignment["final_submission_id"]);
$today = new DateTime();
$today->setTime(0,0,0);
$dueDate = new DateTime($assignment["due_date"]);
$dueDate->setTime(0,0,0);
$daysDifference = (int)$today->diff($dueDate)->format("%r%a");
if ($daysDifference > 1) {
    $dueText = $daysDifference." days to go";
    $dueClass = "upcoming";
} elseif ($daysDifference === 1) {
    $dueText = "1 day to go";
    $dueClass = "upcoming";
} elseif ($daysDifference === 0) {
    $dueText = "Due today";
    $dueClass = "today";
} elseif ($daysDifference === -1) {
    $dueText = "1 day overdue";
    $dueClass = "overdue";
} else {
    $dueText = abs($daysDifference)." days overdue";
    $dueClass = "overdue";
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width,initial-scale=1.0">
<title>Assignment Detail | FairPeer</title>
<link rel="stylesheet" href="css/style.css">
<link rel="stylesheet" href="css/assignments.css">
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
        <a href="student_dashboard.php" class="menu-item">
            <i class="fa-solid fa-house"></i>
            <span>Dashboard</span>
        </a>
        <a href="student_subjects.php" class="menu-item">
            <i class="fa-solid fa-book-open"></i>
            <span>My Subjects</span>
        </a>
        <a href="student_assignments.php" class="menu-item active">
            <i class="fa-solid fa-book"></i>
            <span>My Assignments</span>
        </a>
        <a href="student_groups.php" class="menu-item">
            <i class="fa-solid fa-users"></i>
            <span>My Groups</span>
        </a>
        <a href="student_contribution.php" class="menu-item">
            <i class="fa-solid fa-chart-pie"></i>
            <span>My Contribution</span>
        </a>
        <a href="student_feedback.php" class="menu-item">
            <i class="fa-solid fa-comment"></i>
            <span>Feedback</span>
        </a>
        <a href="student_upload.php" class="menu-item">
            <i class="fa-solid fa-upload"></i>
            <span>Upload File</span>
        </a>
    </nav>
    <div class="sidebar-bottom">
        <a href="logout.php" class="menu-item logout">
            <i class="fa-solid fa-right-from-bracket"></i>
            <span>Logout</span>
        </a>
    </div>
</aside>
<main class="dashboard-main assignment-detail-page">
    <a href="student_assignments.php" class="simple-link">
        <i class="fa-solid fa-arrow-left"></i>
        Back to My Assignments
    </a>
    <header class="dashboard-header assignment-detail-header">
        <p class="assignment-detail-subject">
            <?php echo htmlspecialchars($assignment["subject_code"]." - ".$assignment["subject_name"]); ?>
        </p>
        <h1><?php echo htmlspecialchars($assignment["title"]); ?></h1>
        <p>Assignment details and group information.</p>
    </header>
    <section class="assignment-detail-card">
        <div class="detail-top">
            <div>
                <p class="detail-label">Group</p>
                <?php if ($hasGroup): ?>
                <h3><?php echo htmlspecialchars($assignment["group_name"]); ?></h3>
                <?php else: ?>
                <h3>No group yet</h3>
                <?php endif; ?>
            </div>
<div class="assignment-status <?php echo $isCompleted ? "completed" : ""; ?>">
    <?php echo $isCompleted ? "Completed" : ucfirst(htmlspecialchars($assignment["status"])); ?>
</div>
        </div>
<div class="detail-info">
    <div class="detail-due-date">

        <?php if ($isCompleted): ?>

            <span>Submitted</span>

            <strong>
                <?php echo date("d M Y", strtotime($assignment["final_submitted_at"])); ?>
            </strong>

            <small class="assignment-countdown completed">
                <i class="fa-solid fa-circle-check"></i>
                Final assignment submitted
            </small>

        <?php else: ?>

            <span>Due Date</span>

            <strong>
                <?php echo date("d M Y", strtotime($assignment["due_date"])); ?>
            </strong>

            <small class="assignment-countdown <?php echo $dueClass; ?>">
                <i class="fa-regular fa-clock"></i>
                <?php echo htmlspecialchars($dueText); ?>
            </small>

        <?php endif; ?>

    </div>
</div>
    </section>
    <section class="assignment-detail-card">
        <h2>Description</h2>
        <p><?php echo nl2br(htmlspecialchars($assignment["description"])); ?></p>
    </section>
    <section class="assignment-detail-card">
        <h2>Assignment Brief</h2>
        <?php if ($hasAttachment): ?>
        <a href="uploads/assignments/<?php echo rawurlencode($assignment["attachment_file"]); ?>" target="_blank" class="attachment-box">
            <i class="fa-solid fa-file-lines"></i>
            <div>
                <strong>View Assignment Attachment</strong>
                <p>Click to view the file provided by your lecturer.</p>
            </div>
        </a>
        <?php else: ?>
        <div class="attachment-box">
            <i class="fa-solid fa-file-lines"></i>
            <div>
                <strong>No attachment available</strong>
                <p>No file was provided for this assignment.</p>
            </div>
        </div>
        <?php endif; ?>
    </section>
    <section class="assignment-detail-card">
        <h2>Group</h2>
        <?php if ($hasGroup): ?>
        <p>You are currently in <strong><?php echo htmlspecialchars($assignment["group_name"]); ?></strong>.</p>
        <a href="student_groups.php?group_id=<?php echo (int)$assignment["group_id"]; ?>" class="simple-link">
            <i class="fa-solid fa-users"></i>
            View Group
        </a>
        <?php else: ?>
        <p>You have not joined a group for this assignment yet.</p>
        <a href="student_groups.php?assignment_id=<?php echo (int)$assignment["assignment_id"]; ?>" class="simple-link">
            <i class="fa-solid fa-users"></i>
            Create or Join Group
        </a>
        <?php endif; ?>
    </section>
</main>
<script>
document.addEventListener("DOMContentLoaded",function(){
    const sidebar=document.querySelector(".sidebar");
    const sidebarToggle=document.getElementById("sidebarToggle");
    if(sidebarToggle&&sidebar){
        sidebarToggle.addEventListener("click",function(){
            sidebar.classList.toggle("closed");
        });
    }
});
</script>
</body>
</html>