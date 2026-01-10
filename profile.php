<?php
session_start();
require 'db_config.php';
date_default_timezone_set('Asia/Kuala_Lumpur');

// Session check
if (!isset($_SESSION['user_id'])) {
    header("Location: index.php");
    exit();
}

$user_id = $_SESSION['user_id'];
$success_message = '';
$error_message = '';

// Fetch current user data
$stmt = $pdo->prepare("SELECT Name, Email FROM Users WHERE UserID = ?");
$stmt->execute([$user_id]);
$user = $stmt->fetch(PDO::FETCH_ASSOC);

// Handle form submissions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    
    // Update Name and Email
    if (isset($_POST['update_profile'])) {
        $new_name = trim($_POST['name']);
        $new_email = trim($_POST['email']);
        
        if (empty($new_name) || empty($new_email)) {
            $error_message = "Name and email cannot be empty.";
        } elseif (!filter_var($new_email, FILTER_VALIDATE_EMAIL)) {
            $error_message = "Please enter a valid email address.";
        } else {
            // Check if email already exists (for other users)
            $stmt = $pdo->prepare("SELECT UserID FROM Users WHERE Email = ? AND UserID != ?");
            $stmt->execute([$new_email, $user_id]);
            
            if ($stmt->fetch()) {
                $error_message = "This email is already taken by another user.";
            } else {
                // Update user info
                $stmt = $pdo->prepare("UPDATE Users SET Name = ?, Email = ? WHERE UserID = ?");
                $stmt->execute([$new_name, $new_email, $user_id]);
                
                $user['Name'] = $new_name;
                $user['Email'] = $new_email;
                $success_message = "Profile updated successfully!";
            }
        }
    }
    
    // Update Password
    if (isset($_POST['update_password'])) {
        $current_password = $_POST['current_password'] ?? '';
        $new_password = $_POST['new_password'] ?? '';
        $confirm_password = $_POST['confirm_password'] ?? '';
        
        error_log("Password update attempt for user: " . $user_id);
        
        if (empty($current_password) || empty($new_password) || empty($confirm_password)) {
            $error_message = "All password fields are required.";
        } elseif ($new_password !== $confirm_password) {
            $error_message = "New passwords do not match.";
        } elseif (strlen($new_password) < 6) {
            $error_message = "Password must be at least 6 characters long.";
        } else {
            try {
                // Fetch the stored password
                $stmt = $pdo->prepare("SELECT Password FROM Users WHERE UserID = ?");
                $stmt->execute([$user_id]);
                $result = $stmt->fetch(PDO::FETCH_ASSOC);
                
                if (!$result) {
                    $error_message = "User not found. Please log in again.";
                } else {
                    $stored_password = $result['Password'];
                    
                    // Plain text password comparison
                    if ($current_password !== $stored_password) {
                        $error_message = "Current password is incorrect.";
                    } else {
                        // Update password (store as plain text)
                        $update_stmt = $pdo->prepare("UPDATE Users SET Password = ? WHERE UserID = ?");
                        $update_result = $update_stmt->execute([$new_password, $user_id]);
                        
                        if ($update_result) {
                            header("Location: profile.php?success=password_updated");
                            exit();
                        } else {
                            $error_message = "Failed to update password in database.";
                        }
                    }
                }
            } catch (Exception $e) {
                error_log("Password update error: " . $e->getMessage());
                $error_message = "An error occurred: " . $e->getMessage();
            }
        }
    }
    
    // Delete Account
    if (isset($_POST['delete_account'])) {
        $confirm_delete = $_POST['confirm_delete'] ?? '';
        
        if ($confirm_delete === 'DELETE') {
            try {
                $pdo->beginTransaction();
                
                // Delete user's data in order (foreign key constraints)
                $pdo->prepare("DELETE FROM AIRecommendation WHERE ScheduleID IN (SELECT ScheduleID FROM Schedule WHERE UserID = ?)")->execute([$user_id]);
                $pdo->prepare("DELETE FROM Tracker WHERE UserID = ?")->execute([$user_id]);
                $pdo->prepare("DELETE FROM Task WHERE ScheduleID IN (SELECT ScheduleID FROM Schedule WHERE UserID = ?)")->execute([$user_id]);
                $pdo->prepare("DELETE FROM Schedule WHERE UserID = ?")->execute([$user_id]);
                $pdo->prepare("DELETE FROM User_Course WHERE UserID = ?")->execute([$user_id]);
                $pdo->prepare("DELETE FROM Users WHERE UserID = ?")->execute([$user_id]);
                
                $pdo->commit();
                
                session_destroy();
                header("Location: index.php?deleted=1");
                exit();
            } catch (Exception $e) {
                $pdo->rollBack();
                error_log("Account deletion error: " . $e->getMessage());
                $error_message = "Failed to delete account. Please contact support.";
            }
        } else {
            $error_message = "Please type 'DELETE' to confirm account deletion.";
        }
    }
}

// Handle success message from redirect
if (isset($_GET['success']) && $_GET['success'] === 'password_updated') {
    $success_message = "Password updated successfully!";
}

// Get user statistics
$stmt = $pdo->prepare("SELECT COUNT(*) FROM User_Course WHERE UserID = ?");
$stmt->execute([$user_id]);
$total_courses = $stmt->fetchColumn();

$stmt = $pdo->prepare("SELECT COUNT(*) FROM Schedule WHERE UserID = ? AND IsDeleted = 0");
$stmt->execute([$user_id]);
$total_schedules = $stmt->fetchColumn();

$stmt = $pdo->prepare("SELECT SUM(studyHours) FROM Tracker WHERE UserID = ?");
$stmt->execute([$user_id]);
$total_study_hours = $stmt->fetchColumn() ?? 0;
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Planora - Profile</title>
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
    
    <div class="max-w-4xl mx-auto p-6">
        
        <!-- Header -->
        <div class="flex justify-between items-center mb-8">
            <div class="flex items-center gap-3">
                <div class="w-12 h-12 rounded-full bg-gradient-to-br from-purple-400 to-pink-400 flex items-center justify-center">
                    <span class="text-2xl">📚</span>
                </div>
                <h1 class="text-3xl font-bold bg-gradient-to-r from-purple-600 to-pink-600 bg-clip-text text-transparent">
                    Planora Profile
                </h1>
            </div>
            <a href="dashboard.php" class="bg-purple-500 hover:bg-purple-600 text-white px-5 py-2 rounded-full transition-colors">
                ← Back to Dashboard
            </a>
        </div>

        <!-- Success/Error Messages -->
        <?php if ($success_message): ?>
        <div class="mb-6 bg-green-50 border border-green-200 text-green-800 px-4 py-3 rounded-xl">
            ✓ <?php echo htmlspecialchars($success_message); ?>
        </div>
        <?php endif; ?>

        <?php if ($error_message): ?>
        <div class="mb-6 bg-red-50 border border-red-200 text-red-800 px-4 py-3 rounded-xl">
            ✕ <?php echo htmlspecialchars($error_message); ?>
        </div>
        <?php endif; ?>

        <!-- User Stats -->
        <div class="grid grid-cols-1 md:grid-cols-3 gap-4 mb-6">
            <div class="bg-white rounded-2xl shadow-lg p-6 border border-purple-100 text-center">
                <div class="text-3xl font-bold text-purple-600"><?php echo $total_courses; ?></div>
                <div class="text-sm text-gray-600 mt-1">Courses Enrolled</div>
            </div>
            <div class="bg-white rounded-2xl shadow-lg p-6 border border-pink-100 text-center">
                <div class="text-3xl font-bold text-pink-600"><?php echo $total_schedules; ?></div>
                <div class="text-sm text-gray-600 mt-1">Active Schedules</div>
            </div>
            <div class="bg-white rounded-2xl shadow-lg p-6 border border-blue-100 text-center">
                <div class="text-3xl font-bold text-blue-600"><?php echo $total_study_hours; ?>h</div>
                <div class="text-sm text-gray-600 mt-1">Total Study Hours</div>
            </div>
        </div>

        <!-- Profile Information -->
        <div class="bg-white rounded-3xl shadow-lg p-8 border border-purple-100 mb-6">
            <h2 class="text-2xl font-bold text-purple-900 mb-6">Profile Information</h2>
            
            <form method="POST" class="space-y-4">
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-2">Full Name</label>
                    <input type="text" name="name" value="<?php echo htmlspecialchars($user['Name']); ?>" required
                           class="w-full px-4 py-3 border border-gray-300 rounded-xl focus:outline-none focus:ring-2 focus:ring-purple-500">
                </div>
                
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-2">Email Address</label>
                    <input type="email" name="email" value="<?php echo htmlspecialchars($user['Email']); ?>" required
                           class="w-full px-4 py-3 border border-gray-300 rounded-xl focus:outline-none focus:ring-2 focus:ring-purple-500">
                </div>
                
                <button type="submit" name="update_profile" 
                        class="w-full bg-gradient-to-r from-purple-500 to-pink-500 hover:from-purple-600 hover:to-pink-600 text-white px-6 py-3 rounded-xl font-semibold transition-colors">
                    Update Profile
                </button>
            </form>
        </div>

        <!-- Change Password -->
        <div class="bg-white rounded-3xl shadow-lg p-8 border border-purple-100 mb-6">
            <h2 class="text-2xl font-bold text-purple-900 mb-6">Change Password</h2>
            
            <form method="POST" class="space-y-4">
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-2">Current Password</label>
                    <input type="password" name="current_password" required
                           class="w-full px-4 py-3 border border-gray-300 rounded-xl focus:outline-none focus:ring-2 focus:ring-purple-500">
                </div>
                
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-2">New Password</label>
                    <input type="password" name="new_password" required minlength="6"
                           class="w-full px-4 py-3 border border-gray-300 rounded-xl focus:outline-none focus:ring-2 focus:ring-purple-500">
                    <p class="text-xs text-gray-500 mt-1">Minimum 6 characters</p>
                </div>
                
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-2">Confirm New Password</label>
                    <input type="password" name="confirm_password" required minlength="6"
                           class="w-full px-4 py-3 border border-gray-300 rounded-xl focus:outline-none focus:ring-2 focus:ring-purple-500">
                </div>
                
                <button type="submit" name="update_password" 
                        class="w-full bg-gradient-to-r from-indigo-500 to-purple-500 hover:from-indigo-600 hover:to-purple-600 text-white px-6 py-3 rounded-xl font-semibold transition-colors">
                    Change Password
                </button>
            </form>
        </div>

        <!-- Danger Zone -->
        <div class="bg-white rounded-3xl shadow-lg p-8 border-2 border-red-200">
            <h2 class="text-2xl font-bold text-red-900 mb-2">Danger Zone</h2>
            <p class="text-sm text-gray-600 mb-6">Once you delete your account, there is no going back. All your data will be permanently deleted.</p>
            
            <button onclick="openModal('deleteModal')" 
                    class="bg-red-500 hover:bg-red-600 text-white px-6 py-3 rounded-xl font-semibold transition-colors">
                Delete Account
            </button>
        </div>

        <!-- Logout Button -->
        <div class="mt-6 text-center">
            <a href="logout.php" class="inline-block bg-gray-500 hover:bg-gray-600 text-white px-8 py-3 rounded-xl font-semibold transition-colors">
                Logout
            </a>
        </div>
    </div>

    <!-- Delete Account Modal -->
    <div id="deleteModal" class="modal">
        <div class="bg-white rounded-3xl p-8 max-w-md w-full mx-4">
            <h2 class="text-2xl font-bold text-red-900 mb-4">⚠️ Delete Account</h2>
            <p class="text-gray-700 mb-6">
                This action cannot be undone. All your courses, schedules, tasks, and study history will be permanently deleted.
            </p>
            
            <form method="POST">
                <div class="mb-6">
                    <label class="block text-sm font-medium text-gray-700 mb-2">
                        Type <strong>DELETE</strong> to confirm:
                    </label>
                    <input type="text" name="confirm_delete" required
                           placeholder="DELETE"
                           class="w-full px-4 py-3 border-2 border-red-300 rounded-xl focus:outline-none focus:ring-2 focus:ring-red-500">
                </div>
                
                <div class="flex gap-3">
                    <button type="submit" name="delete_account" 
                            class="flex-1 bg-red-600 hover:bg-red-700 text-white px-4 py-3 rounded-xl font-semibold transition-colors">
                        Delete Forever
                    </button>
                    <button type="button" onclick="closeModal('deleteModal')" 
                            class="flex-1 bg-gray-200 hover:bg-gray-300 text-gray-700 px-4 py-3 rounded-xl font-semibold transition-colors">
                        Cancel
                    </button>
                </div>
            </form>
        </div>
    </div>

    <script>
        function openModal(id) {
            document.getElementById(id).classList.add('active');
        }
        function closeModal(id) {
            document.getElementById(id).classList.remove('active');
        }
        
        // Close modal when clicking outside
        window.onclick = function(event) {
            if (event.target.classList.contains('modal')) {
                event.target.classList.remove('active');
            }
        }
    </script>
</body>
</html><?php
session_start();
require 'db_config.php';
date_default_timezone_set('Asia/Kuala_Lumpur');

// Session check
if (!isset($_SESSION['user_id'])) {
    header("Location: index.php");
    exit();
}

$user_id = $_SESSION['user_id'];
$success_message = '';
$error_message = '';

// Fetch current user data
$stmt = $pdo->prepare("SELECT Name, Email FROM Users WHERE UserID = ?");
$stmt->execute([$user_id]);
$user = $stmt->fetch(PDO::FETCH_ASSOC);

// Handle form submissions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    
    // Update Name and Email
    if (isset($_POST['update_profile'])) {
        $new_name = trim($_POST['name']);
        $new_email = trim($_POST['email']);
        
        if (empty($new_name) || empty($new_email)) {
            $error_message = "Name and email cannot be empty.";
        } elseif (!filter_var($new_email, FILTER_VALIDATE_EMAIL)) {
            $error_message = "Please enter a valid email address.";
        } else {
            // Check if email already exists (for other users)
            $stmt = $pdo->prepare("SELECT UserID FROM Users WHERE Email = ? AND UserID != ?");
            $stmt->execute([$new_email, $user_id]);
            
            if ($stmt->fetch()) {
                $error_message = "This email is already taken by another user.";
            } else {
                // Update user info
                $stmt = $pdo->prepare("UPDATE Users SET Name = ?, Email = ? WHERE UserID = ?");
                $stmt->execute([$new_name, $new_email, $user_id]);
                
                $user['Name'] = $new_name;
                $user['Email'] = $new_email;
                $success_message = "Profile updated successfully!";
            }
        }
    }
    
    // Update Password
    if (isset($_POST['update_password'])) {
        $current_password = $_POST['current_password'] ?? '';
        $new_password = $_POST['new_password'] ?? '';
        $confirm_password = $_POST['confirm_password'] ?? '';
        
        error_log("Password update attempt for user: " . $user_id);
        
        if (empty($current_password) || empty($new_password) || empty($confirm_password)) {
            $error_message = "All password fields are required.";
        } elseif ($new_password !== $confirm_password) {
            $error_message = "New passwords do not match.";
        } elseif (strlen($new_password) < 6) {
            $error_message = "Password must be at least 6 characters long.";
        } else {
            try {
                // Fetch the stored password
                $stmt = $pdo->prepare("SELECT Password FROM Users WHERE UserID = ?");
                $stmt->execute([$user_id]);
                $result = $stmt->fetch(PDO::FETCH_ASSOC);
                
                if (!$result) {
                    $error_message = "User not found. Please log in again.";
                } else {
                    $stored_password = $result['Password'];
                    
                    // Plain text password comparison
                    if ($current_password !== $stored_password) {
                        $error_message = "Current password is incorrect.";
                    } else {
                        // Update password (store as plain text)
                        $update_stmt = $pdo->prepare("UPDATE Users SET Password = ? WHERE UserID = ?");
                        $update_result = $update_stmt->execute([$new_password, $user_id]);
                        
                        if ($update_result) {
                            header("Location: profile.php?success=password_updated");
                            exit();
                        } else {
                            $error_message = "Failed to update password in database.";
                        }
                    }
                }
            } catch (Exception $e) {
                error_log("Password update error: " . $e->getMessage());
                $error_message = "An error occurred: " . $e->getMessage();
            }
        }
    }
    
    // Delete Account
    if (isset($_POST['delete_account'])) {
        $confirm_delete = $_POST['confirm_delete'] ?? '';
        
        if ($confirm_delete === 'DELETE') {
            try {
                $pdo->beginTransaction();
                
                // Delete user's data in order (foreign key constraints)
                $pdo->prepare("DELETE FROM AIRecommendation WHERE ScheduleID IN (SELECT ScheduleID FROM Schedule WHERE UserID = ?)")->execute([$user_id]);
                $pdo->prepare("DELETE FROM Tracker WHERE UserID = ?")->execute([$user_id]);
                $pdo->prepare("DELETE FROM Task WHERE ScheduleID IN (SELECT ScheduleID FROM Schedule WHERE UserID = ?)")->execute([$user_id]);
                $pdo->prepare("DELETE FROM Schedule WHERE UserID = ?")->execute([$user_id]);
                $pdo->prepare("DELETE FROM User_Course WHERE UserID = ?")->execute([$user_id]);
                $pdo->prepare("DELETE FROM Users WHERE UserID = ?")->execute([$user_id]);
                
                $pdo->commit();
                
                session_destroy();
                header("Location: index.php?deleted=1");
                exit();
            } catch (Exception $e) {
                $pdo->rollBack();
                error_log("Account deletion error: " . $e->getMessage());
                $error_message = "Failed to delete account. Please contact support.";
            }
        } else {
            $error_message = "Please type 'DELETE' to confirm account deletion.";
        }
    }
}

// Handle success message from redirect
if (isset($_GET['success']) && $_GET['success'] === 'password_updated') {
    $success_message = "Password updated successfully!";
}

// Get user statistics
$stmt = $pdo->prepare("SELECT COUNT(*) FROM User_Course WHERE UserID = ?");
$stmt->execute([$user_id]);
$total_courses = $stmt->fetchColumn();

$stmt = $pdo->prepare("SELECT COUNT(*) FROM Schedule WHERE UserID = ? AND IsDeleted = 0");
$stmt->execute([$user_id]);
$total_schedules = $stmt->fetchColumn();

$stmt = $pdo->prepare("SELECT SUM(studyHours) FROM Tracker WHERE UserID = ?");
$stmt->execute([$user_id]);
$total_study_hours = $stmt->fetchColumn() ?? 0;
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Planora - Profile</title>
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
    
    <div class="max-w-4xl mx-auto p-6">
        
        <!-- Header -->
        <div class="flex justify-between items-center mb-8">
            <div class="flex items-center gap-3">
                <div class="w-12 h-12 rounded-full bg-gradient-to-br from-purple-400 to-pink-400 flex items-center justify-center">
                    <span class="text-2xl">📚</span>
                </div>
                <h1 class="text-3xl font-bold bg-gradient-to-r from-purple-600 to-pink-600 bg-clip-text text-transparent">
                    Planora Profile
                </h1>
            </div>
            <a href="dashboard.php" class="bg-purple-500 hover:bg-purple-600 text-white px-5 py-2 rounded-full transition-colors">
                ← Back to Dashboard
            </a>
        </div>

        <!-- Success/Error Messages -->
        <?php if ($success_message): ?>
        <div class="mb-6 bg-green-50 border border-green-200 text-green-800 px-4 py-3 rounded-xl">
            ✓ <?php echo htmlspecialchars($success_message); ?>
        </div>
        <?php endif; ?>

        <?php if ($error_message): ?>
        <div class="mb-6 bg-red-50 border border-red-200 text-red-800 px-4 py-3 rounded-xl">
            ✕ <?php echo htmlspecialchars($error_message); ?>
        </div>
        <?php endif; ?>

        <!-- User Stats -->
        <div class="grid grid-cols-1 md:grid-cols-3 gap-4 mb-6">
            <div class="bg-white rounded-2xl shadow-lg p-6 border border-purple-100 text-center">
                <div class="text-3xl font-bold text-purple-600"><?php echo $total_courses; ?></div>
                <div class="text-sm text-gray-600 mt-1">Courses Enrolled</div>
            </div>
            <div class="bg-white rounded-2xl shadow-lg p-6 border border-pink-100 text-center">
                <div class="text-3xl font-bold text-pink-600"><?php echo $total_schedules; ?></div>
                <div class="text-sm text-gray-600 mt-1">Active Schedules</div>
            </div>
            <div class="bg-white rounded-2xl shadow-lg p-6 border border-blue-100 text-center">
                <div class="text-3xl font-bold text-blue-600"><?php echo $total_study_hours; ?>h</div>
                <div class="text-sm text-gray-600 mt-1">Total Study Hours</div>
            </div>
        </div>

        <!-- Profile Information -->
        <div class="bg-white rounded-3xl shadow-lg p-8 border border-purple-100 mb-6">
            <h2 class="text-2xl font-bold text-purple-900 mb-6">Profile Information</h2>
            
            <form method="POST" class="space-y-4">
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-2">Full Name</label>
                    <input type="text" name="name" value="<?php echo htmlspecialchars($user['Name']); ?>" required
                           class="w-full px-4 py-3 border border-gray-300 rounded-xl focus:outline-none focus:ring-2 focus:ring-purple-500">
                </div>
                
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-2">Email Address</label>
                    <input type="email" name="email" value="<?php echo htmlspecialchars($user['Email']); ?>" required
                           class="w-full px-4 py-3 border border-gray-300 rounded-xl focus:outline-none focus:ring-2 focus:ring-purple-500">
                </div>
                
                <button type="submit" name="update_profile" 
                        class="w-full bg-gradient-to-r from-purple-500 to-pink-500 hover:from-purple-600 hover:to-pink-600 text-white px-6 py-3 rounded-xl font-semibold transition-colors">
                    Update Profile
                </button>
            </form>
        </div>

        <!-- Change Password -->
        <div class="bg-white rounded-3xl shadow-lg p-8 border border-purple-100 mb-6">
            <h2 class="text-2xl font-bold text-purple-900 mb-6">Change Password</h2>
            
            <form method="POST" class="space-y-4">
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-2">Current Password</label>
                    <input type="password" name="current_password" required
                           class="w-full px-4 py-3 border border-gray-300 rounded-xl focus:outline-none focus:ring-2 focus:ring-purple-500">
                </div>
                
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-2">New Password</label>
                    <input type="password" name="new_password" required minlength="6"
                           class="w-full px-4 py-3 border border-gray-300 rounded-xl focus:outline-none focus:ring-2 focus:ring-purple-500">
                    <p class="text-xs text-gray-500 mt-1">Minimum 6 characters</p>
                </div>
                
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-2">Confirm New Password</label>
                    <input type="password" name="confirm_password" required minlength="6"
                           class="w-full px-4 py-3 border border-gray-300 rounded-xl focus:outline-none focus:ring-2 focus:ring-purple-500">
                </div>
                
                <button type="submit" name="update_password" 
                        class="w-full bg-gradient-to-r from-indigo-500 to-purple-500 hover:from-indigo-600 hover:to-purple-600 text-white px-6 py-3 rounded-xl font-semibold transition-colors">
                    Change Password
                </button>
            </form>
        </div>

        <!-- Danger Zone -->
        <div class="bg-white rounded-3xl shadow-lg p-8 border-2 border-red-200">
            <h2 class="text-2xl font-bold text-red-900 mb-2">Danger Zone</h2>
            <p class="text-sm text-gray-600 mb-6">Once you delete your account, there is no going back. All your data will be permanently deleted.</p>
            
            <button onclick="openModal('deleteModal')" 
                    class="bg-red-500 hover:bg-red-600 text-white px-6 py-3 rounded-xl font-semibold transition-colors">
                Delete Account
            </button>
        </div>

        <!-- Logout Button -->
        <div class="mt-6 text-center">
            <a href="logout.php" class="inline-block bg-gray-500 hover:bg-gray-600 text-white px-8 py-3 rounded-xl font-semibold transition-colors">
                Logout
            </a>
        </div>
    </div>

    <!-- Delete Account Modal -->
    <div id="deleteModal" class="modal">
        <div class="bg-white rounded-3xl p-8 max-w-md w-full mx-4">
            <h2 class="text-2xl font-bold text-red-900 mb-4">⚠️ Delete Account</h2>
            <p class="text-gray-700 mb-6">
                This action cannot be undone. All your courses, schedules, tasks, and study history will be permanently deleted.
            </p>
            
            <form method="POST">
                <div class="mb-6">
                    <label class="block text-sm font-medium text-gray-700 mb-2">
                        Type <strong>DELETE</strong> to confirm:
                    </label>
                    <input type="text" name="confirm_delete" required
                           placeholder="DELETE"
                           class="w-full px-4 py-3 border-2 border-red-300 rounded-xl focus:outline-none focus:ring-2 focus:ring-red-500">
                </div>
                
                <div class="flex gap-3">
                    <button type="submit" name="delete_account" 
                            class="flex-1 bg-red-600 hover:bg-red-700 text-white px-4 py-3 rounded-xl font-semibold transition-colors">
                        Delete Forever
                    </button>
                    <button type="button" onclick="closeModal('deleteModal')" 
                            class="flex-1 bg-gray-200 hover:bg-gray-300 text-gray-700 px-4 py-3 rounded-xl font-semibold transition-colors">
                        Cancel
                    </button>
                </div>
            </form>
        </div>
    </div>

    <script>
        function openModal(id) {
            document.getElementById(id).classList.add('active');
        }
        function closeModal(id) {
            document.getElementById(id).classList.remove('active');
        }
        
        // Close modal when clicking outside
        window.onclick = function(event) {
            if (event.target.classList.contains('modal')) {
                event.target.classList.remove('active');
            }
        }
    </script>
</body>
</html>