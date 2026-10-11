<?php
session_start();
require_once "db.php";

if (!isset($_SESSION["user_id"]) || $_SESSION["user_role"] !== "Lecturer") {
    header("Location: index.php");
    exit;
}

$lecturer_id = (int)$_SESSION["user_id"];
$group_id = (int)($_GET["group_id"] ?? 0);

if ($group_id <= 0) {
    header("Location: lecturer_contributions.php");
    exit;
}

$groupQuery = "
    SELECT
        sg.group_id,
        sg.group_name,
        sg.created_by,
        sg.assignment_id,
        a.title AS assignment_title,
        sub.subject_code,
        sub.subject_name,
        leader.full_name AS leader_name,
        leader.nickname AS leader_nickname,
        leader.programme_code,
        leader.semester,
        leader.section
    FROM student_groups sg
    INNER JOIN assignments a
        ON sg.assignment_id = a.assignment_id
    INNER JOIN subject_sections sec
        ON a.subject_section_id = sec.subject_section_id
    INNER JOIN subjects sub
        ON sec.subject_id = sub.subject_id
    LEFT JOIN Users leader
        ON sg.created_by = leader.user_id
    WHERE sg.group_id = ?
    AND sec.lecturer_id = ?
    LIMIT 1
";

$groupStmt = $conn->prepare($groupQuery);
$groupStmt->bind_param(
    "ii",
    $group_id,
    $lecturer_id
);
$groupStmt->execute();
$groupResult = $groupStmt->get_result();

if ($groupResult->num_rows === 0) {
    header("Location: lecturer_contributions.php");
    exit;
}

$group = $groupResult->fetch_assoc();
$groupStmt->close();

$memberQuery = "
    SELECT
        u.user_id,
        u.user_code,
        u.full_name,
        u.nickname
    FROM group_members gm
    INNER JOIN Users u
        ON gm.user_id = u.user_id
    WHERE gm.group_id = ?
    ORDER BY
        CASE
            WHEN u.user_id = ? THEN 0
            ELSE 1
        END,
        u.full_name ASC
";

$memberStmt = $conn->prepare($memberQuery);
$memberStmt->bind_param(
    "ii",
    $group_id,
    $group["created_by"]
);
$memberStmt->execute();
$memberResult = $memberStmt->get_result();

$members = [];

while ($member = $memberResult->fetch_assoc()) {
    $member["tasks"] = [];
    $member["contribution"] = 0;
    $members[$member["user_id"]] = $member;
}

$memberStmt->close();

$taskQuery = "
    SELECT
        gt.task_id,
        gt.assigned_to,
        gt.task_title,
        gt.task_description,
        gt.status,
        gt.created_at,
        gt.completed_at
    FROM group_tasks gt
    WHERE gt.group_id = ?
    ORDER BY gt.created_at ASC
";

$taskStmt = $conn->prepare($taskQuery);
$taskStmt->bind_param(
    "i",
    $group_id
);
$taskStmt->execute();
$taskResult = $taskStmt->get_result();

$totalTaskProgress = 0;
$totalTasks = 0;

while ($task = $taskResult->fetch_assoc()) {
    $status = strtolower(trim($task["status"]));

    if ($status === "completed") {
        $taskProgress = 100;
    } elseif ($status === "under review") {
        $taskProgress = 50;
    } elseif ($status === "revision required") {
        $taskProgress = 25;
    } else {
        $taskProgress = 0;
    }

    $task["progress"] = $taskProgress;
    $task["submission"] = null;
    $task["review_decision"] = null;

    $submissionQuery = "
        SELECT
            submission_id,
            file_name,
            file_path,
            version,
            uploaded_at
        FROM submissions
        WHERE task_id = ?
        ORDER BY uploaded_at DESC, submission_id DESC
        LIMIT 1
    ";

    $submissionStmt = $conn->prepare($submissionQuery);
    $submissionStmt->bind_param(
        "i",
        $task["task_id"]
    );
    $submissionStmt->execute();
    $submissionResult = $submissionStmt->get_result();

    if ($submissionResult->num_rows > 0) {
        $task["submission"] = $submissionResult->fetch_assoc();

        $feedbackQuery = "
            SELECT
                review_decision
            FROM feedback
            WHERE submission_id = ?
            ORDER BY created_at DESC, feedback_id DESC
            LIMIT 1
        ";

        $feedbackStmt = $conn->prepare($feedbackQuery);
        $feedbackStmt->bind_param(
            "i",
            $task["submission"]["submission_id"]
        );
        $feedbackStmt->execute();
        $feedbackResult = $feedbackStmt->get_result();

        if ($feedbackResult->num_rows > 0) {
            $feedback = $feedbackResult->fetch_assoc();
            $task["review_decision"] = $feedback["review_decision"];
        }

        $feedbackStmt->close();
    }

    $submissionStmt->close();

    if (isset($members[$task["assigned_to"]])) {
        $members[$task["assigned_to"]]["tasks"][] = $task;
    }

    $totalTaskProgress += $taskProgress;
    $totalTasks++;
}

$taskStmt->close();

foreach ($members as $memberId => $member) {
    $memberTaskTotal = 0;

    foreach ($member["tasks"] as $task) {
        $memberTaskTotal += $task["progress"];
    }

    if (count($member["tasks"]) > 0) {
        $members[$memberId]["contribution"] = round(
            $memberTaskTotal / count($member["tasks"])
        );
    }
}

$overallProgress = $totalTasks > 0
    ? round($totalTaskProgress / $totalTasks)
    : 0;

$memberCount = count($members);

$leaderName = !empty($group["leader_nickname"])
    ? $group["leader_nickname"]
    : $group["leader_name"];
?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Contribution Report | FairPeer</title>
    <link rel="stylesheet" href="css/style.css">
    <link
        rel="stylesheet"
        href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">

    <style>
        body {
            background: #eaf0f7;
        }

        .report-page {
            width: calc(100% - 80px);
            max-width: 1350px;
            margin: 35px auto;
            font-family: Arial, sans-serif;
        }

        .report-back {
            width: fit-content;
            margin-bottom: 20px;
            display: inline-flex;
            align-items: center;
            gap: 9px;
            color: #173b6c;
            font-size: 15px;
            font-weight: 600;
            text-decoration: none;
        }

        .report-back:hover {
            opacity: 0.75;
        }

        .contribution-report {
            padding: 35px 40px;
            background: #ffffff;
            border-radius: 22px;
            box-sizing: border-box;
        }

        .report-label {
            margin: 0 0 6px;
            color: #64748b;
            font-size: 13px;
            font-weight: 600;
            text-transform: uppercase;
        }

        .report-title {
            margin: 0 0 7px;
            color: #173b6c;
            font-size: 30px;
            font-weight: 700;
        }

        .report-assignment {
            margin: 0;
            color: #64748b;
            font-size: 15px;
        }

        .report-info {
            margin-top: 28px;
            display: grid;
            grid-template-columns: repeat(4, 1fr);
            gap: 15px;
        }

        .report-info-item {
            padding: 14px 16px;
            background: #f8fafc;
            border-radius: 10px;
        }

        .report-info-item span {
            display: block;
            margin-bottom: 5px;
            color: #64748b;
            font-size: 14px;
        }

        .report-info-item strong {
            color: #172235;
            font-size: 16px;
            font-weight: 600;
        }

        .overall-progress {
            margin-top: 30px;
        }

        .overall-progress-heading {
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 20px;
            margin-bottom: 10px;
        }

        .overall-progress-heading h3 {
            margin: 0;
            color: #173b6c;
            font-size: 17px;
            font-weight: 700;
        }

        .overall-progress-heading strong {
            color: #173b6c;
            font-size: 22px;
        }

        .report-progress {
            width: 100%;
            height: 10px;
            overflow: hidden;
            background: #e2e8f0;
            border-radius: 20px;
        }

        .report-progress-fill {
            height: 100%;
            background: #2563eb;
            border-radius: 20px;
        }

        .overall-progress-meta {
            margin: 9px 0 0;
            color: #64748b;
            font-size: 14px;
        }

        .report-divider {
            height: 1px;
            margin: 30px 0;
            background: #e2e8f0;
        }

        .member-section-heading {
            margin-bottom: 5px;
            color: #173b6c;
            font-size: 20px;
            font-weight: 700;
        }

        .member-section-subtitle {
            margin: 0 0 10px;
            color: #64748b;
            font-size: 14px;
        }

        .member-contribution {
            padding: 28px 0;
            border-bottom: 1px solid #e2e8f0;
        }

        .member-contribution:last-child {
            padding-bottom: 0;
            border-bottom: none;
        }

        .member-contribution.no-contribution {
            margin: 20px 0;
            padding: 22px;
            background: #fef2f2;
            border: 1px solid #fecaca;
            border-radius: 14px;
        }

        .member-header {
            display: flex;
            align-items: flex-start;
            justify-content: space-between;
            gap: 20px;
        }

        .member-name {
            margin: 0 0 5px;
            color: #173b6c;
            font-size: 18px;
            font-weight: 700;
        }

        .member-id {
            margin: 0;
            color: #64748b;
            font-size: 13px;
        }

        .leader-badge {
            margin-left: 6px;
            padding: 4px 8px;
            background: #eef4fb;
            color: #173b6c;
            border-radius: 20px;
            font-size: 11px;
            font-weight: 600;
        }

        .member-contribution-value {
            color: #173b6c;
            font-size: 22px;
            font-weight: 700;
            text-align: right;
        }

        .member-contribution.no-contribution .member-name,
        .member-contribution.no-contribution .member-contribution-value {
            color: #b91c1c;
        }

        .no-contribution-label {
            display: block;
            margin-bottom: 4px;
            color: #b91c1c;
            font-size: 11px;
            font-weight: 700;
            text-transform: uppercase;
        }

        .member-tasks {
            margin-top: 22px;
            display: flex;
            flex-direction: column;
            gap: 18px;
        }

        .member-task {
            padding: 18px;
            background: #f8fafc;
            border-radius: 12px;
        }

        .member-contribution.no-contribution .member-task {
            background: #fffafa;
        }

        .task-information {
            display: grid;
            grid-template-columns: 1.2fr 1.8fr 1.2fr 1fr;
            gap: 18px;
        }

        .task-information-item span {
            display: block;
            margin-bottom: 6px;
            color: #64748b;
            font-size: 14px;
            font-weight: 500;
        }

        .task-information-item strong {
            display: block;
            color: #172235;
            font-size: 16px;
            font-weight: 600;
            line-height: 1.5;
        }

        .task-file {
            color: #173b6c !important;
            text-decoration: none;
        }

        .task-file:hover {
            text-decoration: underline;
        }

        .task-status {
            width: fit-content;
            padding: 5px 10px;
            border-radius: 20px;
            font-size: 12px !important;
            font-weight: 600 !important;
        }

.contribution-status {
    width: fit-content;
    padding: 6px 11px;
    border-radius: 20px;
    font-size: 12px !important;
    font-weight: 600 !important;
}

.contribution-status.contributed {
    background: #dcfce7;
    color: #166534;
}

.contribution-status.partial {
    background: #fef3c7;
    color: #92400e;
}

.contribution-status.not-contributed {
    background: #fee2e2;
    color: #b91c1c;
}

        .member-progress {
            margin-top: 18px;
        }

        .member-progress-info {
            margin-bottom: 8px;
            display: flex;
            align-items: center;
            justify-content: space-between;
            color: #64748b;
            font-size: 14px;
        }

        .member-contribution.no-contribution .report-progress-fill {
            background: #dc2626;
        }

        .no-task {
            margin-top: 18px;
            padding: 16px;
            background: #f8fafc;
            color: #64748b;
            border-radius: 10px;
            font-size: 14px;
        }

        .member-contribution.no-contribution .no-task {
            background: #fffafa;
            color: #b91c1c;
        }

        @media (max-width: 900px) {
            .report-info {
                grid-template-columns: repeat(2, 1fr);
            }

            .task-information {
                grid-template-columns: repeat(2, 1fr);
            }
        }

        @media (max-width: 600px) {
            .report-page {
                width: calc(100% - 30px);
                margin: 20px auto;
            }

            .contribution-report {
                padding: 25px 20px;
            }

            .report-info,
            .task-information {
                grid-template-columns: 1fr;
            }

            .member-header {
                flex-direction: column;
            }

            .member-contribution-value {
                text-align: left;
            }
        }
    </style>
</head>

<body>

<div class="report-page">

    <a
        href="lecturer_contributions.php"
        class="report-back">
        <i class="fa-solid fa-arrow-left"></i>
        Back to Contributions
    </a>

    <div class="contribution-report">

        <div class="report-header">
            <p class="report-label">
                Contribution Report
            </p>

            <h1 class="report-title">
                <?php echo htmlspecialchars($group["group_name"]); ?>
            </h1>

            <p class="report-assignment">
                <?php echo htmlspecialchars($group["assignment_title"]); ?>
                ·
                <?php echo htmlspecialchars($group["subject_code"]); ?>
                -
                <?php echo htmlspecialchars($group["subject_name"]); ?>
            </p>
        </div>

        <div class="report-info">

            <div class="report-info-item">
                <span>Team Leader</span>
                <strong>
                    <?php echo htmlspecialchars($leaderName ?? "-"); ?>
                </strong>
            </div>

            <div class="report-info-item">
                <span>Programme</span>
                <strong>
                    <?php echo htmlspecialchars($group["programme_code"] ?? "-"); ?>
                </strong>
            </div>

            <div class="report-info-item">
                <span>Semester</span>
                <strong>
                    <?php echo htmlspecialchars($group["semester"] ?? "-"); ?>
                </strong>
            </div>

            <div class="report-info-item">
                <span>Section</span>
                <strong>
                    <?php echo htmlspecialchars($group["section"] ?? "-"); ?>
                </strong>
            </div>

        </div>

        <div class="overall-progress">

            <div class="overall-progress-heading">
                <h3>Overall Team Progress</h3>

                <strong>
                    <?php echo $overallProgress; ?>%
                </strong>
            </div>

            <div class="report-progress">
                <div
                    class="report-progress-fill"
                    style="width: <?php echo $overallProgress; ?>%;">
                </div>
            </div>

            <p class="overall-progress-meta">
                <?php echo $memberCount; ?>
                member(s)
                ·
                <?php echo $totalTasks; ?>
                task(s)
            </p>

        </div>

        <div class="report-divider"></div>

        <h2 class="member-section-heading">
            Member Contributions
        </h2>

        <p class="member-section-subtitle">
            Individual task progress and submission activity for each group member.
        </p>

        <?php if (count($members) > 0): ?>

            <?php foreach ($members as $member): ?>

                <div class="member-contribution <?php echo $member["contribution"] === 0 ? "no-contribution" : ""; ?>">

                    <div class="member-header">

                        <div>
                            <h3 class="member-name">
                                <?php echo htmlspecialchars($member["full_name"]); ?>

                                <?php if ((int)$member["user_id"] === (int)$group["created_by"]): ?>
                                    <span class="leader-badge">
                                        Team Leader
                                    </span>
                                <?php endif; ?>
                            </h3>

                            <p class="member-id">
                                <?php echo htmlspecialchars($member["user_code"] ?? "-"); ?>
                            </p>
                        </div>

                        <div class="member-contribution-value">

                            <?php if ($member["contribution"] === 0): ?>
                                <span class="no-contribution-label">
                                    No Contribution
                                </span>
                            <?php endif; ?>

                            <?php echo $member["contribution"]; ?>%

                        </div>

                    </div>

                    <?php if (count($member["tasks"]) > 0): ?>

                        <div class="member-tasks">

                            <?php foreach ($member["tasks"] as $task): ?>

                                <?php
                                $taskStatus = strtolower(trim($task["status"]));

                                if ($taskStatus === "completed") {
                                    $statusClass = "completed";
                                } elseif ($taskStatus === "under review") {
                                    $statusClass = "review";
                                } elseif ($taskStatus === "revision required") {
                                    $statusClass = "revision";
                                } else {
                                    $statusClass = "pending";
                                }
                                ?>

                                <div class="member-task">

                                    <div class="task-information">

                                        <div class="task-information-item">
                                            <span>Task Assigned</span>

                                            <strong>
                                                <?php echo htmlspecialchars($task["task_title"]); ?>
                                            </strong>
                                        </div>

                                        <div class="task-information-item">
                                            <span>Task Description</span>

                                            <strong>
                                                <?php
                                                echo !empty($task["task_description"])
                                                    ? htmlspecialchars($task["task_description"])
                                                    : "-";
                                                ?>
                                            </strong>
                                        </div>

                                        <div class="task-information-item">
                                            <span>Latest Submission</span>

                                            <?php if ($task["submission"]): ?>

                                                <a
                                                    href="<?php echo htmlspecialchars($task["submission"]["file_path"]); ?>"
                                                    target="_blank"
                                                    class="task-file">

                                                    <?php echo htmlspecialchars($task["submission"]["file_name"]); ?>

                                                    ·

                                                    <?php echo htmlspecialchars($task["submission"]["version"]); ?>

                                                </a>

                                            <?php else: ?>

                                                <strong>
                                                    No submission
                                                </strong>

                                            <?php endif; ?>
                                        </div>

<?php
if ($task["progress"] === 100) {
    $contributionLabel = "Contributed";
    $contributionClass = "contributed";
} elseif ($task["progress"] > 0) {
    $contributionLabel = "Partially Contributed";
    $contributionClass = "partial";
} else {
    $contributionLabel = "Not Contributed";
    $contributionClass = "not-contributed";
}
?>

<div class="task-information-item">
    <span>Contribution Status</span>

    <strong class="contribution-status <?php echo $contributionClass; ?>">
        <?php echo $contributionLabel; ?>
    </strong>
</div>
                                    </div>

                                </div>

                            <?php endforeach; ?>

                        </div>

                    <?php else: ?>

                        <div class="no-task">
                            No task has been assigned to this member.
                        </div>

                    <?php endif; ?>

                    <div class="member-progress">

                        <div class="member-progress-info">
                            <span>Contribution Progress</span>

                            <span>
                                <?php echo $member["contribution"]; ?>%
                            </span>
                        </div>

                        <div class="report-progress">
                            <div
                                class="report-progress-fill"
                                style="width: <?php echo $member["contribution"]; ?>%;">
                            </div>
                        </div>

                    </div>

                </div>

            <?php endforeach; ?>

        <?php else: ?>

            <div class="no-task">
                No group members found.
            </div>

        <?php endif; ?>

    </div>

</div>

</body>
</html>