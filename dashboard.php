<?php
session_start();
require 'db_config.php'; 
$ai_recommendation = null;
date_default_timezone_set('Asia/Kuala_Lumpur');

// 1. SESSION CHECK
if (!isset($_SESSION['user_id'])) { 
    header("Location: index.php");
    exit();
}

$user_id = $_SESSION['user_id'];

// 2. HELPER FUNCTIONS
if (!function_exists('getTimeOfDay')) {
    function getTimeOfDay() {
        $hour = date('H');
        if ($hour < 12) return 'Morning';
        if ($hour < 18) return 'Afternoon';
        return 'Evening';
    }
}

if (!function_exists('getColorForCourse')) {
    function getColorForCourse($index) {
        $colors = ['#99F6E4', '#FBCFE8', '#DDD6FE', '#FDE68A', '#BBF7D0', '#FCA5A5'];
        return $colors[$index % count($colors)];
    }
}

// 3. PRE-FETCH DATA (Moved up so variables exist for the AI logic)
// Fetch User
$stmt = $pdo->prepare("SELECT Name, Email FROM Users WHERE UserID = ?");
$stmt->execute([$user_id]);
$user = $stmt->fetch(PDO::FETCH_ASSOC);

// Fetch Courses for UI and AI context
$stmt = $pdo->prepare("SELECT c.CourseID, c.CourseName FROM Course c JOIN User_Course uc ON c.CourseID = uc.CourseID WHERE uc.UserID = ?");
$stmt->execute([$user_id]);
$user_courses = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Fetch Today's Schedule
$today = date('Y-m-d');
$stmt = $pdo->prepare("SELECT s.*, c.CourseName FROM Schedule s LEFT JOIN Course c ON s.CourseID = c.CourseID WHERE s.UserID = ? AND DATE(s.startDateTime) = ? ORDER BY s.startDateTime ASC");
$stmt->execute([$user_id, $today]);
$schedules = $stmt->fetchAll(PDO::FETCH_ASSOC);

// 4. HANDLE FORM SUBMISSIONS (POST)
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    
    // Add Course Logic
    if (isset($_POST['add_course'])) {
        $stmt = $pdo->prepare("SELECT CourseID FROM Course WHERE CourseName = ?");
        $stmt->execute([$_POST['course_name']]);
        $course = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$course) {
            $stmt = $pdo->prepare("INSERT INTO Course (CourseName) VALUES (?)");
            $stmt->execute([$_POST['course_name']]);
            $course_id = $pdo->lastInsertId();
        } else {
            $course_id = $course['CourseID'];
        }
        $stmt = $pdo->prepare("INSERT IGNORE INTO User_Course (UserID, CourseID) VALUES (?, ?)");
        $stmt->execute([$user_id, $course_id]);
        header("Location: dashboard.php");
        exit();
    }

    // Delete Course (remove only from this user)
    if (isset($_POST['delete_course'])) {
        $course_id = $_POST['course_id'];

        $stmt = $pdo->prepare(
            "DELETE FROM User_Course WHERE UserID = ? AND CourseID = ?"
        );
        $stmt->execute([$user_id, $course_id]);

        header("Location: dashboard.php");
        exit();
    }


    // Add Schedule Logic
    if (isset($_POST['add_schedule'])) {
        $stmt = $pdo->prepare("INSERT INTO Schedule (UserID, CourseID, startDateTime, endDateTime, Deadlines) VALUES (?, ?, ?, ?, ?)");
        $stmt->execute([$user_id, $_POST['course_id'], $_POST['start_time'], $_POST['end_time'], $_POST['deadlines'] ?: null]);
        header("Location: dashboard.php");
        exit();
    }

    // GROQ AI REQUEST
if (isset($_POST['ask_ai'])) {
    $user_prompt = trim($_POST['prompt_text']);
    $apiKey = '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'; 
    $url = 'https://api.groq.com/openai/v1/chat/completions';

    $course_names = [];
    foreach ($user_courses as $c) { $course_names[] = $c['CourseName']; }
    $course_list = implode(", ", $course_names);

    $data = [
        'model' => 'llama3-8b-8192',
        'messages' => [
            ['role' => 'system', 'content' => "You are Planora AI. The user studies: $course_list. Give a very short, specific study tip (max 15 words)."],
            ['role' => 'user', 'content' => $user_prompt]
        ],
        'temperature' => 0.5
    ];

    $ch = curl_init($url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        'Content-Type: application/json',
        'Authorization: Bearer ' . $apiKey
    ]);
    
    $response = curl_exec($ch);
    $responseData = json_decode($response, true);
    curl_close($ch);

    // Better extraction of the AI reply
    if (isset($responseData['choices'][0]['message']['content'])) {
        $ai_reply = $responseData['choices'][0]['message']['content'];
    } else {
        $ai_reply = "I'm having trouble thinking right now. Please try again!";
    }

    // Link to a valid ScheduleID
    $stmt = $pdo->prepare("SELECT ScheduleID FROM Schedule WHERE UserID = ? LIMIT 1");
    $stmt->execute([$user_id]);
    $sched = $stmt->fetch();
    $valid_schedule_id = $sched ? $sched['ScheduleID'] : null;

    if ($valid_schedule_id) {
        $stmt = $pdo->prepare("INSERT INTO AIRecommendation (ScheduleID, recommendationDesc) VALUES (?, ?)");
        $stmt->execute([$valid_schedule_id, $ai_reply]);
    }
    
    header("Location: dashboard.php");
    exit();
}
}

// Fetch latest AI recommendation
$ai_recommendation = null; 
try {
    $stmt = $pdo->prepare("
        SELECT ar.recommendationDesc, c.CourseName
        FROM AIRecommendation ar
        JOIN Schedule s ON ar.ScheduleID = s.ScheduleID
        LEFT JOIN Course c ON s.CourseID = c.CourseID
        WHERE s.UserID = ?
        ORDER BY ar.RecommendationID DESC LIMIT 1
    ");
    $stmt->execute([$user_id]);
    $ai_recommendation = $stmt->fetch(PDO::FETCH_ASSOC);
} catch (Exception $e) {}

$stmt = $pdo->prepare("SELECT t.*, tr.completionRates, tr.TrackerID FROM Task t LEFT JOIN Tracker tr ON t.TaskID = tr.TaskID WHERE (tr.UserID = ? OR t.ScheduleID IN (SELECT ScheduleID FROM Schedule WHERE UserID = ?)) AND t.TaskStatus != 'completed'");
$stmt->execute([$user_id, $user_id]);
$tasks = $stmt->fetchAll(PDO::FETCH_ASSOC);

$stmt = $pdo->prepare("SELECT SUM(studyHours) as total_hours, AVG(completionRates) as avg_completion FROM Tracker WHERE UserID = ?");
$stmt->execute([$user_id]);
$tracker_stats = $stmt->fetch(PDO::FETCH_ASSOC);
$total_study_hours = $tracker_stats['total_hours'] ?? 0;
$weekly_progress = round($tracker_stats['avg_completion'] ?? 0);
?>



<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Planora - Dashboard</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <style>
        .modal {
            display: none;
            position: fixed;
            z-index: 50;
            left: 0;
            top: 0;
            width: 100%;
            height: 100%;
            background-color: rgba(0,0,0,0.5);
        }
        .modal.active {
            display: flex;
            align-items: center;
            justify-content: center;
        }
    </style>
</head>
<body class="bg-gradient-to-br from-purple-50 via-pink-50 to-blue-50 min-h-screen">
    
    <!-- Header -->
    <div class="max-w-7xl mx-auto p-6">
        <div class="flex justify-between items-center mb-8">
            <div class="flex items-center gap-3">
                <div class="w-12 h-12 rounded-full bg-gradient-to-br from-purple-400 to-pink-400 flex items-center justify-center">
                    <span class="text-2xl">📚</span>
                </div>
                <h1 class="text-3xl font-bold bg-gradient-to-r from-purple-600 to-pink-600 bg-clip-text text-transparent">
                    Planora
                </h1>
            </div>
            <div class="flex gap-3">
                <button onclick="openModal('courseModal')" class="bg-indigo-500 hover:bg-indigo-600 text-white px-4 py-2 rounded-full transition-colors">
                    + Add Course
                </button>
                <button onclick="openModal('scheduleModal')" class="bg-green-500 hover:bg-green-600 text-white px-4 py-2 rounded-full transition-colors">
                    + Add Schedule
                </button>
                <button onclick="openModal('taskModal')" class="bg-blue-500 hover:bg-blue-600 text-white px-4 py-2 rounded-full transition-colors">
                    + Add Task
                </button>
                <a href="profile.php" class="bg-purple-500 hover:bg-purple-600 text-white px-5 py-2 rounded-full transition-colors">
                    Profile
                </a>
            </div>
        </div>

        <!-- Greeting -->
        <div class="mb-6">
            <h2 class="text-2xl font-semibold text-purple-900 mb-1">
                Hello , <?php echo htmlspecialchars($user['Name']); ?>
            </h2>
            <p class="text-purple-600">Here's your plan for today</p>
        </div>

        <!-- Main Grid -->
        <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
            
            <!-- Left Column -->
            <div class="lg:col-span-2 space-y-6">
                
                <!-- My Courses -->
                <div class="bg-white rounded-3xl shadow-lg p-6 border border-purple-100">
                    <h3 class="text-xl font-semibold text-purple-900 mb-4">My Courses</h3>
                    <?php if (count($user_courses) > 0): ?>
                        <div class="grid grid-cols-2 md:grid-cols-3 gap-3">
                            <?php foreach ($user_courses as $idx => $course): ?>
                                <div class="relative rounded-xl p-4 text-center"style="background-color: <?php echo getColorForCourse($idx); ?>;">

                                <!-- Delete Course -->
                                <form method="POST" class="absolute top-1 right-1">
        <input type="hidden" name="course_id" value="<?php echo $course['CourseID']; ?>">
        <button type="submit"
                name="delete_course"
                onclick="return confirm('Remove this course?')"
                class="bg-red-500 text-white text-xs px-2 py-0.5 rounded-full hover:bg-red-600">
            ✕
        </button>
    </form>

    <div class="font-semibold text-gray-800 mt-2">
        <?php echo htmlspecialchars($course['CourseName']); ?>
    </div>
</div>

                            <?php endforeach; ?>
                        </div>
                    <?php else: ?>
                        <p class="text-center text-gray-500 py-4">No courses enrolled. Add a course to get started!</p>
                    <?php endif; ?>
                </div>

                <!-- Today's Schedule -->
                <div class="bg-white rounded-3xl shadow-lg p-6 border border-purple-100">
                    <h3 class="text-xl font-semibold text-purple-900 mb-4 text-center">
                        Today's Study Schedule
                    </h3>
                    <?php if (count($schedules) > 0): ?>
                        <div class="space-y-3">
                            <?php foreach ($schedules as $idx => $schedule): ?>
                                <div class="flex items-center gap-4">
                                    <div class="text-sm font-medium text-gray-600 w-20">
                                        <?php echo date('g:i A', strtotime($schedule['startDateTime'])); ?>
                                    </div>
                                    <div class="flex-1 rounded-2xl px-5 py-3" style="background-color: <?php echo getColorForCourse($idx); ?>;">
                                        <div class="font-semibold text-gray-800">
                                            <?php echo htmlspecialchars($schedule['CourseName'] ?? 'General Study'); ?>
                                        </div>
                                        <div class="text-sm opacity-80">
                                            <?php echo date('g:i A', strtotime($schedule['startDateTime'])); ?> - 
                                            <?php echo date('g:i A', strtotime($schedule['endDateTime'])); ?>
                                        </div>
                                        <?php if ($schedule['Deadlines']): ?>
                                            <div class="text-xs mt-1 opacity-70">
                                                Deadline: <?php echo date('M d, Y', strtotime($schedule['Deadlines'])); ?>
                                            </div>
                                        <?php endif; ?>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    <?php else: ?>
                        <p class="text-center text-gray-500 py-8">No schedules for today. Add one to plan your study time!</p>
                    <?php endif; ?>
                </div>

                <!-- Task Management -->
                <div class="bg-white rounded-3xl shadow-lg p-6 border border-purple-100">
                    <h3 class="text-xl font-semibold text-purple-900 mb-4">
                        Task Management & Tracker
                    </h3>
                    
                    <div class="space-y-3 mb-6">
                        <?php if (count($tasks) > 0): ?>
                            <?php foreach ($tasks as $task): ?>
                                <?php 
                                    $is_deadline_soon = false;
                                    if ($task['TaskDeadlines']) {
                                        $deadline = strtotime($task['TaskDeadlines']);
                                        $three_days = time() + (3 * 24 * 60 * 60);
                                        $is_deadline_soon = $deadline <= $three_days;
                                    }
                                ?>
                                <div class="p-3 rounded-xl <?php echo $is_deadline_soon ? 'bg-red-50 border border-red-200' : 'bg-gray-50'; ?>">
                                    <div class="flex items-center gap-3 mb-2">
                                        <form method="POST" class="inline">
                                            <input type="hidden" name="task_id" value="<?php echo $task['TaskID']; ?>">
                                            <input type="hidden" name="new_status" value="completed">
                                            <button type="submit" name="update_task_status" class="text-gray-400 hover:text-green-500">
                                                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                    <circle cx="12" cy="12" r="10" stroke-width="2"/>
                                                </svg>
                                            </button>
                                        </form>
                                        <div class="flex-1">
                                            <div class="font-semibold text-gray-800">
                                                <?php echo htmlspecialchars($task['Title']); ?>
                                            </div>
                                            <?php if ($task['Description']): ?>
                                                <div class="text-sm text-gray-600">
                                                    <?php echo htmlspecialchars($task['Description']); ?>
                                                </div>
                                            <?php endif; ?>
                                        </div>
                                        <?php if ($is_deadline_soon): ?>
                                            <span class="text-xs bg-red-500 text-white px-2 py-1 rounded-full">
                                                Due Soon
                                            </span>
                                        <?php endif; ?>
                                        <span class="text-xs px-2 py-1 rounded-full <?php 
                                            echo $task['Priority'] == 'high' ? 'bg-red-100 text-red-700' : 
                                                ($task['Priority'] == 'medium' ? 'bg-yellow-100 text-yellow-700' : 'bg-green-100 text-green-700'); 
                                        ?>">
                                            <?php echo ucfirst($task['Priority']); ?>
                                        </span>
                                    </div>
                                    <?php if ($task['TrackerID']): ?>
                                        <div class="ml-8">
                                            <div class="flex items-center gap-2 text-sm">
                                                <span class="text-gray-600">Completion:</span>
                                                <div class="flex-1 bg-gray-200 rounded-full h-2">
                                                    <div class="bg-gradient-to-r from-teal-400 to-cyan-400 h-2 rounded-full" 
                                                         style="width: <?php echo $task['completionRates']; ?>%"></div>
                                                </div>
                                                <form method="POST" class="inline-flex items-center gap-1">
                                                    <input type="hidden" name="tracker_id" value="<?php echo $task['TrackerID']; ?>">
                                                    <input type="number" name="completion_rate" min="0" max="100" 
                                                           value="<?php echo $task['completionRates']; ?>" 
                                                           class="w-12 px-1 py-0.5 border rounded text-xs">
                                                    <button type="submit" name="update_completion" class="text-xs bg-purple-500 text-white px-2 py-0.5 rounded">
                                                        ✓
                                                    </button>
                                                </form>
                                            </div>
                                        </div>
                                    <?php endif; ?>
                                </div>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <p class="text-center text-gray-500 py-4">No active tasks. Add one to track your progress!</p>
                        <?php endif; ?>
                    </div>
                </div>

            </div>

            <!-- Right Column -->
            <div class="space-y-6">
                
                <!-- Study Tracker Stats -->
                <div class="bg-white rounded-3xl shadow-lg p-6 border border-purple-100">
                    <h3 class="text-lg font-semibold text-purple-900 mb-4 text-center">
                        Study Tracker
                    </h3>
                    <div class="flex items-center justify-center mb-4">
                        <div class="relative w-32 h-32">
                            <svg class="transform -rotate-90 w-32 h-32">
                                <circle cx="64" cy="64" r="56" stroke="#E9D5FF" stroke-width="12" fill="none"/>
                                <circle cx="64" cy="64" r="56" stroke="url(#gradient)" stroke-width="12" fill="none"
                                    stroke-dasharray="<?php echo 2 * pi() * 56; ?>"
                                    stroke-dashoffset="<?php echo 2 * pi() * 56 * (1 - $weekly_progress / 100); ?>"
                                    stroke-linecap="round"/>
                                <defs>
                                    <linearGradient id="gradient" x1="0%" y1="0%" x2="100%" y2="100%">
                                        <stop offset="0%" stop-color="#EC4899"/>
                                        <stop offset="100%" stop-color="#8B5CF6"/>
                                    </linearGradient>
                                </defs>
                            </svg>
                            <div class="absolute inset-0 flex items-center justify-center">
                                <span class="text-3xl font-bold text-purple-900"><?php echo $weekly_progress; ?>%</span>
                            </div>
                        </div>
                    </div>
                    <div class="text-center mb-4">
                        <div class="text-2xl font-bold text-purple-900"><?php echo $total_study_hours; ?> hrs</div>
                        <div class="text-sm text-gray-600">Total Study Time</div>
                    </div>
                    <div class="bg-purple-50 rounded-xl p-4 text-sm text-purple-700">
                        💡 Focus tip - Study the hardest subject first while your mind is fresh
                    </div>
                </div>

                <div class="bg-gradient-to-br from-purple-50 to-pink-50 rounded-3xl shadow-lg p-6 border border-purple-200">
    <h3 class="text-lg font-semibold text-purple-900 mb-3">Planora AI Assistant</h3>
    
    <?php if ($ai_recommendation): ?>
    <div class="mb-4 p-3 bg-white rounded-xl text-sm text-purple-800 border-l-4 border-purple-500 shadow-sm">
        <strong>Latest Tip:</strong> <?php echo htmlspecialchars($ai_recommendation['recommendationDesc']); ?>
    </div>
    <?php endif; ?>

    <form method="POST" class="space-y-3">
        <textarea name="prompt_text" rows="3" 
                  placeholder="Ask me for a study strategy for today..."
                  class="w-full px-3 py-2 border border-purple-200 rounded-xl text-sm focus:outline-none focus:ring-2 focus:ring-purple-500"></textarea>
        <button type="submit" name="ask_ai" 
                class="w-full bg-purple-500 hover:bg-purple-600 text-white px-4 py-2.5 rounded-xl text-sm transition-colors font-semibold">
            Get AI Recommendation
        </button>
    </form>
</div>

            </div>
        </div>

        <!-- AI Recommendation -->
        <?php if ($ai_recommendation): ?>
        <div class="mt-6 bg-white rounded-3xl shadow-lg p-6 border border-purple-100">
            <div class="bg-pink-50 border border-pink-200 rounded-2xl p-4">
                <div class="flex items-start gap-3">
                    <span class="text-2xl">🤖</span>
                    <div>
                        <h4 class="font-semibold text-pink-900 mb-1">AI Recommendation</h4>
                        <p class="text-sm text-pink-800"><?php echo htmlspecialchars($ai_recommendation['recommendationDesc']); ?></p>
                        <?php if ($ai_recommendation['CourseName']): ?>
                            <p class="text-xs text-pink-700 mt-2">For: <?php echo htmlspecialchars($ai_recommendation['CourseName']); ?></p>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
        <?php endif; ?>
    </div>

    <!-- Add Course Modal -->
    <div id="courseModal" class="modal">
        <div class="bg-white rounded-3xl p-8 max-w-md w-full mx-4">
            <h2 class="text-2xl font-bold text-purple-900 mb-6">Add Course</h2>
            <form method="POST">
                <div class="space-y-4">
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Course Name</label>
                        <input type="text" name="course_name" required 
                               placeholder="e.g., Mathematics, Physics, Programming"
                               class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-purple-500">
                    </div>
                </div>
                <div class="flex gap-3 mt-6">
                    <button type="submit" name="add_course" class="flex-1 bg-indigo-500 hover:bg-indigo-600 text-white px-4 py-2 rounded-lg transition-colors">
                        Add Course
                    </button>
                    <button type="button" onclick="closeModal('courseModal')" class="flex-1 bg-gray-200 hover:bg-gray-300 text-gray-700 px-4 py-2 rounded-lg transition-colors">
                        Cancel
                    </button>
                </div>
            </form>
        </div>
    </div>

    <!-- Add Schedule Modal -->
    <div id="scheduleModal" class="modal">
        <div class="bg-white rounded-3xl p-8 max-w-md w-full mx-4">
            <h2 class="text-2xl font-bold text-purple-900 mb-6">Add Study Schedule</h2>
            <form method="POST">
                <div class="space-y-4">
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Course</label>
                        <select name="course_id" required class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-purple-500">
                            <option value="">Select a course</option>
                            <?php foreach ($user_courses as $course): ?>
                                <option value="<?php echo $course['CourseID']; ?>">
                                    <?php echo htmlspecialchars($course['CourseName']); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Start Time</label>
                        <input type="datetime-local" name="start_time" required class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-purple-500">
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">End Time</label>
                        <input type="datetime-local" name="end_time" required class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-purple-500">
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Deadline (Optional)</label>
                        <input type="date" name="deadlines" class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-purple-500">
                    </div>
                </div>
                <div class="flex gap-3 mt-6">
                    <button type="submit" name="add_schedule" class="flex-1 bg-green-500 hover:bg-green-600 text-white px-4 py-2 rounded-lg transition-colors">
                        Add Schedule
                    </button>
                    <button type="button" onclick="closeModal('scheduleModal')" class="flex-1 bg-gray-200 hover:bg-gray-300 text-gray-700 px-4 py-2 rounded-lg transition-colors">
                        Cancel
                    </button>
                </div>
            </form>
        </div>
    </div>

    <!-- Add Task Modal -->
    <div id="taskModal" class="modal">
        <div class="bg-white rounded-3xl p-8 max-w-md w-full mx-4">
            <h2 class="text-2xl font-bold text-purple-900 mb-6">Add Task</h2>
            <form method="POST">
                <div class="space-y-4">
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Task Title</label>
                        <input type="text" name="title" required class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-purple-500">
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Description</label>
                        <textarea name="description" rows="3" class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-purple-500"></textarea>
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Link to Schedule (Optional)</label>
                        <select name="schedule_id" class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-purple-500">
                            <option value="">No schedule link</option>
                            <?php foreach ($schedules as $schedule): ?>
                                <option value="<?php echo $schedule['ScheduleID']; ?>">
                                    <?php echo htmlspecialchars($schedule['CourseName'] ?? 'General'); ?> - 
                                    <?php echo date('M d, g:i A', strtotime($schedule['startDateTime'])); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Priority</label>
                        <select name="priority" class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-purple-500">
                            <option value="low">Low</option>
                            </div>

<script>
function openModal(id) {
    document.getElementById(id).classList.add('active');
}
function closeModal(id) {
    document.getElementById(id).classList.remove('active');
}
</script>
