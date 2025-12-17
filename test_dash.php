<?php
// Mock Data
$userName = "Ahmad";
$weeklyProgress = 60;
$studyOverview = [
    ['subject' => 'Mathematics', 'time' => '6:00pm - 8:00pm', 'color' => '#efbdb6'],
    ['subject' => 'History', 'time' => '4:00pm - 6:00pm', 'color' => '#a0dbd6'],
    ['subject' => 'Physics', 'time' => '9:00pm - 10:00pm', 'color' => '#8780b1'],
    ['subject' => 'Programming', 'time' => '12:00am - 2:00am', 'color' => '#fff1b8'],
];
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Planora Dashboard</title>
    <link rel="stylesheet" href="styless.css">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;600;700&display=swap" rel="stylesheet">
</head>
<body>

<div class="container">
    <header class="header">
        <div class="user-greeting">
            <h1>Good Evening, <?php echo $userName; ?></h1>
            <p>Here's your plan for today</p>
        </div>
        <div class="profile-section">
            <div class="avatar">👤</div>
            <button class="btn-profile">Profile</button>
        </div>
    </header>

    <main class="dashboard-grid">
        <section class="card overview-card">
            <h2>Today's Study Overview</h2>
            <div class="subjects-grid">
                <?php foreach ($studyOverview as $item): ?>
                    <div class="subject-item" style="background-color: <?php echo $item['color']; ?>;">
                        <strong><?php echo $item['subject']; ?></strong>
                        <span><?php echo $item['time']; ?></span>
                    </div>
                <?php endforeach; ?>
            </div>
        </section>

        <section class="card progress-card">
            <h2>Weekly Progress</h2>
            <div class="progress-circle" style="--percent: <?php echo $weeklyProgress; ?>;">
                <div class="inner-circle">
                    <span class="percentage"><?php echo $weeklyProgress; ?>%</span>
                </div>
            </div>
            <div class="focus-tip">
                <p><strong>Focus tip</strong> – Study the hardest subject first while your mind is fresh</p>
            </div>
        </section>

        <section class="card tasks-card">
            <h2>Task & Goal Management</h2>
            <div class="tabs">
                <span class="active">In Progress</span>
                <span>Completed</span>
                <span>Upcoming</span>
            </div>
            
            <div class="task-list">
                <label class="checkbox-container">
                    <input type="checkbox"> Read Chapter 4
                    <span class="checkmark"></span>
                </label>
                <div class="priority-tag">
                    <span class="badge">Prioritize Physics</span>
                    <span class="deadline">Deadline Soon</span>
                </div>
            </div>

            <div class="goal-progress">
                <h3>Goal Progress</h3>
                <div class="progress-bar-bg">
                    <div class="progress-bar-fill" style="width: 70%;"></div>
                </div>
                <p class="goal-text">Finish Chapter 5 by Friday</p>
            </div>
        </section>

        <section class="card ai-card">
            <h2 class="ai-title">Planora AI Assistant</h2>
            <p class="ai-greeting">Hello! How can I assist you today?</p>
            <div class="ai-actions">
                <button>Summarize Study Notes</button>
                <button>Generate quiz questions</button>
                <button>Create a quick micro-plan</button>
            </div>
        </section>
    </main>
</div>

</body>
</html>