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

// 3. PRE-FETCH DATA 
// Fetch User
$stmt = $pdo->prepare("SELECT Name, Email FROM Users WHERE UserID = ?");
$stmt->execute([$user_id]);
$user = $stmt->fetch(PDO::FETCH_ASSOC);

// Fetch Courses for UI and AI context
$stmt = $pdo->prepare("SELECT c.CourseID, c.CourseName FROM Course c JOIN User_Course uc ON c.CourseID = uc.CourseID WHERE uc.UserID = ?");
$stmt->execute([$user_id]);
$user_courses = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Fetch ALL schedules for task dropdown (moved earlier)
$stmt = $pdo->prepare("
    SELECT s.ScheduleID, s.startDateTime, s.endDateTime, c.CourseName 
    FROM Schedule s 
    LEFT JOIN Course c ON s.CourseID = c.CourseID 
    WHERE s.UserID = ?
      AND s.IsDeleted = 0
      AND s.ScheduleStatus != 'completed'
    ORDER BY s.startDateTime ASC
");

$stmt->execute([$user_id]);
$all_schedules = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Fetch Today's Schedule
$today = date('Y-m-d');
$stmt = $pdo->prepare("
    SELECT s.*, c.CourseName
    FROM Schedule s
    LEFT JOIN Course c ON s.CourseID = c.CourseID
    WHERE s.UserID = ?
      AND s.IsDeleted = 0
      AND s.ScheduleStatus != 'completed'
      AND DATE(s.startDateTime) = ?
    ORDER BY s.startDateTime ASC
");


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
    

    // MARK SCHEDULE AS DONE
    if (isset($_POST['mark_schedule_done'])) {
        $schedule_id = $_POST['schedule_id'];

        $stmt = $pdo->prepare("
            UPDATE Schedule
            SET ScheduleStatus = 'completed'
            WHERE ScheduleID = ? AND UserID = ?
       ");
        $stmt->execute([$schedule_id, $user_id]);

        header("Location: dashboard.php");
        exit();
    }




    // DELETE SCHEDULE 

    if (isset($_POST['delete_schedule'])) {
        $schedule_id = $_POST['schedule_id'];

        $stmt = $pdo->prepare("
            DELETE FROM Schedule 
            WHERE ScheduleID = ? AND UserID = ?
        ");
        $stmt->execute([$schedule_id, $user_id]);

        header("Location: dashboard.php");
        exit();
    }


    // RESET PROGRESS (Delete Completed/Past Schedules)

    if (isset($_POST['reset_progress'])) {
        // This deletes all schedules for this user that are either completed 
        // or from the past, effectively resetting the 'Total' count for the week.
        $stmt = $pdo->prepare("
            DELETE FROM Schedule 
            WHERE UserID = ? 
            AND (ScheduleStatus = 'completed' OR startDateTime < NOW())
       ");
        $stmt->execute([$user_id]);

        header("Location: dashboard.php");
        exit();
    }


   // Delete Account
    if (isset($_POST['delete_account'])) {
        $confirm_delete = $_POST['confirm_delete'] ?? '';
    
    if ($confirm_delete === 'DELETE') {
        try {
            // Start transaction for safe deletion
            $pdo->beginTransaction();
            
            // 1. Delete AI Recommendations
            $pdo->prepare("DELETE FROM AIRecommendation WHERE ScheduleID IN (SELECT ScheduleID FROM Schedule WHERE UserID = ?)")->execute([$user_id]);
            
            // 2. Delete Tracker entries
            $pdo->prepare("DELETE FROM Tracker WHERE UserID = ?")->execute([$user_id]);
            
            // 3. Delete Tasks (both linked to schedules and directly to user)
            $pdo->prepare("DELETE FROM Task WHERE UserID = ?")->execute([$user_id]);
            $pdo->prepare("DELETE FROM Task WHERE ScheduleID IN (SELECT ScheduleID FROM Schedule WHERE UserID = ?)")->execute([$user_id]);
            
            // 4. Delete Schedules
            $pdo->prepare("DELETE FROM Schedule WHERE UserID = ?")->execute([$user_id]);
            
            // 5. Delete User-Course relationships
            $pdo->prepare("DELETE FROM User_Course WHERE UserID = ?")->execute([$user_id]);
            
            // 6. Finally delete the user
            $pdo->prepare("DELETE FROM Users WHERE UserID = ?")->execute([$user_id]);
            
            // Commit transaction
            $pdo->commit();
            
            session_destroy();
            header("Location: index.php?deleted=1");
            exit();
            
        } catch (Exception $e) {
            // Rollback on error
            $pdo->rollBack();
            $error_message = "Error deleting account: " . $e->getMessage();
        }
        } else {
            $error_message = "Please type 'DELETE' to confirm account deletion.";
        }
    }




// AI SCHEDULER
 
   if (isset($_POST['generate_ai_multiday'])) {

        $course_id = $_POST['ai_course_id'];
        $total_hours = (int) $_POST['total_hours'];
        $days = (int) $_POST['days'];
        $preferred = $_POST['preferred_time'];
        $deadline = $_POST['deadline'] ?: null;

        if ($days <= 0 || $total_hours <= 0) {
            header("Location: dashboard.php");
            exit();
        }

    // Hours per day (rounded)
    $hours_per_day = ceil($total_hours / $days);

    // Fetch course name
    $stmt = $pdo->prepare("SELECT CourseName FROM Course WHERE CourseID = ?");
    $stmt->execute([$course_id]);
    $course_name = $stmt->fetchColumn();

    // Preferred time windows
    $time_windows = [
        'morning' => ['09:00', '12:00'],
        'afternoon' => ['13:00', '17:00'],
        'evening' => ['18:00', '22:00']
    ];

    [$window_start, $window_end] = $time_windows[$preferred];

    // GROQ API
    $apiKey = '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';
    $url = 'https://api.groq.com/openai/v1/chat/completions';

    for ($i = 0; $i < $days; $i++) {

        $study_date = date('Y-m-d', strtotime("+$i day"));

        // --- FIX START: Check for existing busy times ---
        $stmt = $pdo->prepare("
            SELECT startDateTime, endDateTime 
            FROM Schedule 
            WHERE UserID = ? 
            AND DATE(startDateTime) = ? 
            AND IsDeleted = 0
        ");
        $stmt->execute([$user_id, $study_date]);
        $existing_schedules = $stmt->fetchAll(PDO::FETCH_ASSOC);

        $busy_times_list = [];
        foreach ($existing_schedules as $sch) {
            $start = date('H:i', strtotime($sch['startDateTime']));
            $end = date('H:i', strtotime($sch['endDateTime']));
            $busy_times_list[] = "$start to $end";
        }
        $busy_context = !empty($busy_times_list) ? implode(", ", $busy_times_list) : "None";
        // --- FIX END ---

        $prompt = "
                You are a study planner AI.
                Choose the best study START time.

                Course: $course_name
                Date: $study_date
                Study duration: $hours_per_day hours
                Allowed time window: $window_start to $window_end

                IMPORTANT: The user is already busy at these times: $busy_context.
                Select a start time where the $hours_per_day hour duration does NOT overlap with the busy times.

                Respond ONLY in JSON:
                {\"start\":\"HH:MM\"}
        ";

        $data = [
            'model' => 'openai/gpt-oss-120b', 
            'messages' => [
                ['role' => 'system', 'content' => 'You generate multi-day study schedules'],
                ['role' => 'user', 'content' => $prompt]
            ],
            'temperature' => 0.3
        ];

        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => json_encode($data),
            CURLOPT_HTTPHEADER => [
                'Content-Type: application/json',
                'Authorization: Bearer ' . $apiKey
            ]
        ]);

        $response = curl_exec($ch);
        curl_close($ch);

        $responseData = json_decode($response, true);
        $ai_text = $responseData['choices'][0]['message']['content'] ?? '';

        preg_match('/\{.*\}/', $ai_text, $matches);
        $json = json_decode($matches[0] ?? '', true);

        // Fallback time
        $start_time = $json['start'] ?? $window_start;

        $startDateTime = "$study_date $start_time:00";
        $endDateTime = date(
            'Y-m-d H:i:s',
            strtotime("$startDateTime +$hours_per_day hours")
        );

        // Insert schedule
        $stmt = $pdo->prepare("
            INSERT INTO Schedule
            (UserID, CourseID, startDateTime, endDateTime, Deadlines, IsDeleted)
             VALUES (?, ?, ?, ?, ?, 0)
        ");

        $stmt->execute([
            $user_id,
            $course_id,
            $startDateTime,
            $endDateTime,
            $deadline
        ]);
    }

    header("Location: dashboard.php");
    exit();
}

// Add Task Logic
if (isset($_POST['add_task'])) {
    $title = $_POST['title'];
    $description = $_POST['description'] ?? null;
    $schedule_id = $_POST['schedule_id']; // Now required
    $priority = $_POST['priority'];
    $deadline = !empty($_POST['deadline']) ? $_POST['deadline'] : null;
    
    if (empty($schedule_id)) {
        header("Location: dashboard.php?error=schedule_required");
        exit();
    }
    
    $stmt = $pdo->prepare("
        INSERT INTO Task (UserID, ScheduleID, Title, Description, Priority, TaskDeadlines, TaskStatus)
        VALUES (?, ?, ?, ?, ?, ?, 'pending')
    ");
    $stmt->execute([$user_id, $schedule_id, $title, $description, $priority, $deadline]);
    
    header("Location: dashboard.php");
    exit();
}



// GROQ AI REQUEST - USING BEST MODEL
if (isset($_POST['ask_ai'])) {
    try {
        $user_prompt = trim($_POST['prompt_text'] ?? '');
        $selected_course_id = $_POST['ai_course_selection'] ?? '';
        
        // Validate inputs
        if (empty($user_prompt)) {
            $_SESSION['error_message'] = "Please enter a question or prompt.";
            header("Location: dashboard.php");
            exit();
        }
        
        if (empty($selected_course_id)) {
            $_SESSION['error_message'] = "Please select a course.";
            header("Location: dashboard.php");
            exit();
        }
        
        // Get the selected course name
        $stmt = $pdo->prepare("SELECT CourseName FROM Course WHERE CourseID = ?");
        $stmt->execute([$selected_course_id]);
        $selected_course = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$selected_course) {
            $_SESSION['error_message'] = "Invalid course selected.";
            header("Location: dashboard.php");
            exit();
        }
        
        $course_name = $selected_course['CourseName'];
        
        // Get a fresh API key from: https://console.groq.com/keys
        $apiKey = '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';
        $url = 'https://api.groq.com/openai/v1/chat/completions';

        // Using the LATEST and BEST Llama model
        $payload = json_encode([
            'model' => 'llama-3.3-70b-versatile',  // ✅ Latest model
            'messages' => [
                [
                    'role' => 'system',
                    'content' => 'You are Planora AI, a helpful study assistant. Provide practical, specific study advice in 2-3 sentences.'
                ],
                [
                    'role' => 'user', 
                    'content' => "Course: $course_name\n\nQuestion: $user_prompt\n\nProvide actionable study advice."
                ]
            ],
            'temperature' => 0.7,
            'max_tokens' => 200
        ]);

        $ch = curl_init();
        
        curl_setopt_array($ch, [
            CURLOPT_URL => $url,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => $payload,
            CURLOPT_TIMEOUT => 30,
            CURLOPT_HTTPHEADER => [
                'Content-Type: application/json',
                'Authorization: Bearer ' . $apiKey
            ],
        ]);
        
        $response = curl_exec($ch);
        $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        // Log for debugging
        if ($http_code != 200) {
            error_log("GROQ Error ($http_code): $response");
        }

        if ($http_code == 401) {
            throw new Exception("Invalid API key - Get new key from console.groq.com/keys");
        }
        
        if ($http_code == 429) {
            throw new Exception("Rate limit reached. Wait a moment and try again.");
        }
        
        if ($http_code != 200) {
            throw new Exception("API error ($http_code). Check error logs.");
        }

        $responseData = json_decode($response, true);
        
        if (!isset($responseData['choices'][0]['message']['content'])) {
            throw new Exception("Invalid API response format");
        }
        
        $ai_reply = trim($responseData['choices'][0]['message']['content']);

        // Find a schedule to link the recommendation
        $stmt = $pdo->prepare("
            SELECT ScheduleID 
            FROM Schedule 
            WHERE UserID = ? AND CourseID = ? AND IsDeleted = 0 
            ORDER BY startDateTime DESC 
            LIMIT 1
        ");
        $stmt->execute([$user_id, $selected_course_id]);
        $course_schedule = $stmt->fetch();
        
        if (!$course_schedule) {
            $stmt = $pdo->prepare("SELECT ScheduleID FROM Schedule WHERE UserID = ? AND IsDeleted = 0 LIMIT 1");
            $stmt->execute([$user_id]);
            $course_schedule = $stmt->fetch();
        }
        
        $valid_schedule_id = $course_schedule ? $course_schedule['ScheduleID'] : null;

        if ($valid_schedule_id) {
            $stmt = $pdo->prepare("INSERT INTO AIRecommendation (ScheduleID, recommendationDesc) VALUES (?, ?)");
            $stmt->execute([$valid_schedule_id, $ai_reply]);
            $_SESSION['success_message'] = "🤖 AI study tip generated!";
        } else {
            $_SESSION['ai_temp_recommendation'] = [
                'desc' => $ai_reply,
                'course' => $course_name
            ];
            $_SESSION['success_message'] = "🤖 AI tip ready! Create a schedule to save recommendations.";
        }
        
    } catch (Exception $e) {
        $_SESSION['error_message'] = "AI Error: " . $e->getMessage();
        error_log("Planora AI Error: " . $e->getMessage());
    }
    
    header("Location: dashboard.php");
    exit();
}
}

// Mark task as completed
if (isset($_POST['update_task_status'])) {
    $task_id = $_POST['task_id'];

    $stmt = $pdo->prepare("
        UPDATE Task 
        SET TaskStatus = 'completed' 
        WHERE TaskID = ? AND UserID = ?
    ");
    $stmt->execute([$task_id, $user_id]);

    header("Location: dashboard.php");
    exit();
}

// Delete task
if (isset($_POST['delete_task'])) {
    $task_id = $_POST['task_id'];

    $stmt = $pdo->prepare("
        DELETE FROM Task 
        WHERE TaskID = ? AND UserID = ?
    ");
    $stmt->execute([$task_id, $user_id]);

    header("Location: dashboard.php");
    exit();
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
} catch (Exception $e) {// Temporary debug
    error_log("AI Recommendation fetch error: " . $e->getMessage());
}

// TEMPORARY DEBUG - Remove after testing
if (isset($_GET['debug'])) {
    echo "<pre>";
    echo "User ID: $user_id\n";
    echo "Courses: " . count($user_courses) . "\n";
    echo "AI Recommendation: ";
    print_r($ai_recommendation);
    echo "</pre>";
}

$stmt = $pdo->prepare("SELECT t.*, tr.completionRates, tr.TrackerID FROM Task t LEFT JOIN Tracker tr ON t.TaskID = tr.TaskID WHERE (tr.UserID = ? OR t.ScheduleID IN (SELECT ScheduleID FROM Schedule WHERE UserID = ?)) AND t.TaskStatus != 'completed'");
$stmt->execute([$user_id, $user_id]);
$tasks = $stmt->fetchAll(PDO::FETCH_ASSOC);

$stmt = $pdo->prepare("SELECT SUM(studyHours) as total_hours, AVG(completionRates) as avg_completion FROM Tracker WHERE UserID = ?");
$stmt->execute([$user_id]);
$tracker_stats = $stmt->fetch(PDO::FETCH_ASSOC);
$total_study_hours = $tracker_stats['total_hours'] ?? 0;
$stmt = $pdo->prepare("
    SELECT
        COUNT(*) AS total,
        SUM(CASE WHEN ScheduleStatus = 'completed' THEN 1 ELSE 0 END) AS completed
    FROM Schedule
    WHERE UserID = ? 
    AND IsDeleted = 0 
");
$stmt->execute([$user_id]);
$data = $stmt->fetch(PDO::FETCH_ASSOC);

$total = $data['total'] ?? 0;
$completed = $data['completed'] ?? 0;

$weekly_progress = 0;
if ($total > 0) {
    $weekly_progress = round(($completed / $total) * 100);
}


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
    
    <!-- Error/Success Messages -->
    <?php if (isset($_SESSION['error_message'])): ?>
        <div class="mb-4 bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded-xl">
            <?php 
                echo htmlspecialchars($_SESSION['error_message']); 
                unset($_SESSION['error_message']);
            ?>
        </div>
    <?php endif; ?>
    
    <?php if (isset($_SESSION['success_message'])): ?>
        <div class="mb-4 bg-green-100 border border-green-400 text-green-700 px-4 py-3 rounded-xl">
            <?php 
                echo htmlspecialchars($_SESSION['success_message']); 
                unset($_SESSION['success_message']);
            ?>
        </div>
    <?php endif; ?>
    
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
                <button onclick="openModal('aiMultiDayModal')"class="bg-rose-500 hover:bg-rose-600 text-white px-4 py-2 rounded-full">
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
                                        <div class="flex items-center gap-4">

    <!-- TIME -->
    <div class="text-sm text-gray-600 w-28">
        <?php echo date('g:i A', strtotime($schedule['startDateTime'])); ?>
    </div>

    <!-- SCHEDULE CARD -->
    <div class="flex-1 rounded-2xl px-5 py-3"
         style="background-color: <?php echo getColorForCourse($index); ?>;">
        <div class="font-semibold">
            <?php echo htmlspecialchars($schedule['CourseName']); ?>
        </div>
        <div class="text-sm">
            <?php echo date('g:i A', strtotime($schedule['startDateTime'])); ?>
            -
            <?php echo date('g:i A', strtotime($schedule['endDateTime'])); ?>
        </div>
    </div>

    <!-- DONE BUTTON -->
    <?php if ($schedule['ScheduleStatus'] !== 'completed'): ?>
<form method="POST">
    <input type="hidden" name="schedule_id" value="<?= $schedule['ScheduleID']; ?>">
    <button name="mark_schedule_done" class="text-xs bg-green-500 text-white px-3 py-1 rounded-full hover:bg-green-600">✔</button>
</form>
<?php endif; ?>


    <!-- DELETE BUTTON -->
    <form method="POST" onsubmit="return confirm('Delete this schedule?')">
        <input type="hidden" name="schedule_id"
               value="<?php echo $schedule['ScheduleID']; ?>">
        <button type="submit" name="delete_schedule"
                class="text-red-600 hover:text-red-800 font-semibold">
            ✕
        </button>
    </form>

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

                <div class="bg-white rounded-3xl shadow-lg p-6 border border-purple-100">
    <h3 class="text-xl font-semibold text-purple-900 mb-4 text-center">
        All Study Schedules
    </h3>

    <?php if (count($all_schedules) > 0): ?>
        <div class="space-y-3">
            <?php foreach ($all_schedules as $idx => $schedule): ?>
                <div class="flex items-center gap-4">

    <!-- DATE -->
    <div class="text-sm text-gray-600 w-28">
        <?php echo date('M d', strtotime($schedule['startDateTime'])); ?>
    </div>

    <!-- SCHEDULE CARD -->
    <div class="flex-1 rounded-2xl px-5 py-3"
         style="background-color: <?php echo getColorForCourse($idx); ?>;">
        <div class="font-semibold">
            <?php echo htmlspecialchars($schedule['CourseName']); ?>
        </div>
        <div class="text-sm">
            <?php echo date('g:i A', strtotime($schedule['startDateTime'])); ?>
            -
            <?php echo date('g:i A', strtotime($schedule['endDateTime'])); ?>
        </div>
    </div>

<!-- DONE BUTTON -->
<form method="POST" class="inline">
    <input type="hidden" name="schedule_id"
           value="<?= $schedule['ScheduleID']; ?>">
    <button type="submit"
            name="mark_schedule_done"
            class="text-xs bg-green-500 text-white px-3 py-1 rounded-full hover:bg-green-600">
        ✔
    </button>
</form>

<!-- DELETE BUTTON -->
<form method="POST"
      class="inline"
      onsubmit="return confirm('Delete this schedule?')">
    <input type="hidden" name="schedule_id"
           value="<?= $schedule['ScheduleID']; ?>">
    <button type="submit"
            name="delete_schedule"
            class="text-red-600 hover:text-red-800 font-semibold">
        ✕
    </button>
</form>

</div>

            <?php endforeach; ?>
        </div>
    <?php else: ?>
        <p class="text-center text-gray-500 py-6">
            No schedules found.
        </p>
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

                <<div class="bg-gradient-to-br from-purple-50 to-pink-50 rounded-3xl shadow-lg p-6 border border-purple-200">
    <h3 class="text-lg font-semibold text-purple-900 mb-3">Planora AI Assistant</h3>
    
    <?php if ($ai_recommendation): ?>
    <div class="mb-4 p-3 bg-white rounded-xl text-sm text-purple-800 border-l-4 border-purple-500 shadow-sm">
        <strong>Latest Tip:</strong> <?php echo htmlspecialchars($ai_recommendation['recommendationDesc']); ?>
        <?php if ($ai_recommendation['CourseName']): ?>
            <span class="text-xs text-purple-600 block mt-1">For: <?php echo htmlspecialchars($ai_recommendation['CourseName']); ?></span>
        <?php endif; ?>
    </div>
    <?php endif; ?>
    
    <?php if (isset($_SESSION['ai_temp_recommendation'])): ?>
    <div class="mb-4 p-3 bg-yellow-50 rounded-xl text-sm text-yellow-800 border-l-4 border-yellow-500 shadow-sm">
        <strong>New Tip:</strong> <?php echo htmlspecialchars($_SESSION['ai_temp_recommendation']['desc']); ?>
        <span class="text-xs text-yellow-600 block mt-1">For: <?php echo htmlspecialchars($_SESSION['ai_temp_recommendation']['course']); ?></span>
    </div>
    <?php 
        unset($_SESSION['ai_temp_recommendation']);
    endif; ?>

    <form method="POST" class="space-y-3">
        <!-- Course Selection Dropdown -->
        <div>
            <label class="block text-sm font-medium text-purple-700 mb-1">Select Course</label>
            <select name="ai_course_selection" required
                    class="w-full px-3 py-2 border border-purple-200 rounded-xl text-sm focus:outline-none focus:ring-2 focus:ring-purple-500">
                <option value="">-- Choose a course --</option>
                <?php foreach ($user_courses as $course): ?>
                    <option value="<?php echo $course['CourseID']; ?>">
                        <?php echo htmlspecialchars($course['CourseName']); ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </div>
        
        <!-- Prompt Text Area -->
        <textarea name="prompt_text" rows="3" 
                  placeholder="Ask for study tips, techniques, or strategies..."
                  required
                  class="w-full px-3 py-2 border border-purple-200 rounded-xl text-sm focus:outline-none focus:ring-2 focus:ring-purple-500"></textarea>
        
        <button type="submit" name="ask_ai" 
                class="w-full bg-purple-500 hover:bg-purple-600 text-white px-4 py-2.5 rounded-xl text-sm transition-colors font-semibold">
            Get AI Recommendation
        </button>
    </form>
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
<div id="aiMultiDayModal" class="modal">
  <div class="bg-white rounded-3xl p-8 max-w-md w-full mx-4">

    <h2 class="text-2xl font-bold text-purple-900 mb-6">
      AI Multi-Day Study Plan
    </h2>

    <form method="POST" class="space-y-4">

      <div>
        <label class="text-sm font-medium">Course</label>
        <select name="ai_course_id" required
                class="w-full px-4 py-2 border rounded-lg">
          <?php foreach ($user_courses as $course): ?>
            <option value="<?= $course['CourseID']; ?>">
              <?= htmlspecialchars($course['CourseName']); ?>
            </option>
          <?php endforeach; ?>
        </select>
      </div>

      <div>
        <label class="text-sm font-medium">Total Study Hours</label>
        <input type="number" name="total_hours" min="1" max="40" required
               class="w-full px-4 py-2 border rounded-lg">
      </div>

      <div>
        <label class="text-sm font-medium">Number of Days</label>
        <input type="number" name="days" min="1" max="14" required
               class="w-full px-4 py-2 border rounded-lg">
      </div>

      <div>
        <label class="text-sm font-medium">Preferred Time</label>
        <select name="preferred_time"
                class="w-full px-4 py-2 border rounded-lg">
          <option value="morning">Morning</option>
          <option value="afternoon">Afternoon</option>
          <option value="evening">Evening</option>
        </select>
      </div>

      <div>
        <label class="text-sm font-medium">Deadline (optional)</label>
        <input type="date" name="deadline"
               class="w-full px-4 py-2 border rounded-lg">
      </div>

      <div class="flex gap-3 pt-4">
        <button type="submit" name="generate_ai_multiday"
                class="flex-1 bg-purple-500 text-white py-2 rounded-lg">
          Generate Plan
        </button>
        <button type="button"
                onclick="closeModal('aiMultiDayModal')"
                class="flex-1 bg-gray-200 py-2 rounded-lg">
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
                    <input type="text" name="title" required 
                           placeholder="e.g., Complete homework, Study chapter 5"
                           class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-purple-500">
                </div>
                
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Description</label>
                    <textarea name="description" rows="3" 
                              placeholder="Add more details about this task..."
                              class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-purple-500"></textarea>
                </div>
                
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">
                        Link to Schedule <span class="text-red-500">*</span>
                    </label>
                    <select name="schedule_id" required 
                            class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-purple-500">
                        <option value="">-- Select a schedule --</option>
                        <?php foreach ($all_schedules as $schedule): ?> <option value="<?php echo $schedule['ScheduleID']; ?>">
        <?php echo htmlspecialchars($schedule['CourseName'] ?? 'General Study'); ?> - 
        <?php echo date('M d, Y g:i A', strtotime($schedule['startDateTime'])); ?>
    </option>
<?php endforeach; ?>
                    </select>
                    <p class="text-xs text-gray-500 mt-1">Tasks must be linked to a study schedule</p>
                </div>
                
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Priority</label>
                    <select name="priority" 
                            class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-purple-500">
                        <option value="low">Low</option>
                        <option value="medium" selected>Medium</option>
                        <option value="high">High</option>
                    </select>
                </div>
                
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Deadline (Optional)</label>
                    <input type="date" name="deadline" 
                           class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-purple-500">
                </div>
            </div>

            <div class="flex gap-3 mt-6">
                <button type="submit" name="add_task" 
                        class="flex-1 bg-blue-500 hover:bg-blue-600 text-white px-4 py-2 rounded-lg transition-colors">
                    Add Task
                </button>
                <button type="button" onclick="closeModal('taskModal')" 
                        class="flex-1 bg-gray-200 hover:bg-gray-300 text-gray-700 px-4 py-2 rounded-lg transition-colors">
                    Cancel
                </button>
            </div>
        </form>
    </div>
</div>
    </div> <?php if ($total > 0 && $weekly_progress >= 100): ?>
    <div id="congratsModal" class="fixed inset-0 bg-black bg-opacity-60 flex items-center justify-center z-[100] animate-fade-in" style="display: flex;">
        <div class="bg-white p-8 rounded-3xl shadow-2xl text-center max-w-sm mx-4 transform transition-all scale-110 border-4 border-yellow-400">
            <div class="text-6xl mb-4">🏆</div>
            <h2 class="text-3xl font-extrabold text-gray-800 mb-2">Amazing Work!</h2>
            <p class="text-gray-600 mb-6">
                You've reached <strong>100%</strong> of your goals. Your hard work is paying off!
            </p>
            
            <form method="POST">
                <button type="submit" name="reset_progress" 
                    class="w-full bg-gradient-to-r from-green-500 to-emerald-600 hover:from-green-600 hover:to-emerald-700 text-white font-bold py-4 rounded-xl shadow-lg transform active:scale-95 transition-all">
                    Clear History & Start Fresh
                </button>
            </form>
            
            <button onclick="document.getElementById('congratsModal').style.display='none'" 
                class="mt-4 text-sm text-gray-400 hover:text-gray-600 underline">
                Just look at my success for a bit
            </button>
        </div>
    </div>
    <?php endif; ?>

    <script>
        function openModal(id) {
            document.getElementById(id).classList.add('active');
        }
        function closeModal(id) {
            document.getElementById(id).classList.remove('active');
        }
    </script>
</body>
</html>
