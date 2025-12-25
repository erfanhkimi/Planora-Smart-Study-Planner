<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Planora - Login</title>
    <script src="https://cdn.tailwindcss.com"></script>
</head>
<body class="bg-gradient-to-br from-purple-50 via-pink-50 to-blue-50 min-h-screen flex items-center justify-center p-4">
    
    <div class="w-full max-w-6xl grid grid-cols-1 lg:grid-cols-2 gap-8 items-center">
        
        <!-- Left Section - Login Form -->
        <div class="bg-white rounded-3xl shadow-2xl p-8 md:p-12 border border-purple-100">
            
            <!-- Logo -->
            <div class="flex items-center gap-3 mb-8">
                <div class="w-12 h-12 rounded-full bg-gradient-to-br from-purple-400 to-pink-400 flex items-center justify-center">
                    <span class="text-2xl">📚</span>
                </div>
                <h1 class="text-2xl font-bold bg-gradient-to-r from-purple-600 to-pink-600 bg-clip-text text-transparent">
                    Planora
                </h1>
            </div>

            <!-- Welcome Message -->
            <h2 class="text-3xl font-bold text-purple-900 mb-2">Hi, Welcome back!</h2>
            <p class="text-gray-600 mb-8">Sign in to continue to your dashboard</p>

            <!-- Error/Success Messages -->
            <?php if (isset($_GET['error'])): ?>
                <div class="mb-6 bg-red-50 border border-red-200 text-red-800 px-4 py-3 rounded-xl">
                    ✕ <?php echo htmlspecialchars($_GET['error']); ?>
                </div>
            <?php endif; ?>

            <?php if (isset($_GET['success'])): ?>
                <div class="mb-6 bg-green-50 border border-green-200 text-green-800 px-4 py-3 rounded-xl">
                    ✓ <?php echo htmlspecialchars($_GET['success']); ?>
                </div>
            <?php endif; ?>

            <?php if (isset($_GET['deleted']) && $_GET['deleted'] == '1'): ?>
                <div class="mb-6 bg-blue-50 border border-blue-200 text-blue-800 px-4 py-3 rounded-xl">
                    ✓ Your account has been successfully deleted.
                </div>
            <?php endif; ?>

            <!-- Login Form -->
            <form method="POST" action="login_process.php" class="space-y-5">
                
                <div>
                    <label for="email" class="block text-sm font-medium text-gray-700 mb-2">Email Address</label>
                    <input type="email" id="email" name="email" required
                           placeholder="Enter your email"
                           class="w-full px-4 py-3 border border-gray-300 rounded-xl focus:outline-none focus:ring-2 focus:ring-purple-500 focus:border-transparent transition-all">
                </div>

                <div>
                    <label for="password" class="block text-sm font-medium text-gray-700 mb-2">Password</label>
                    <input type="password" id="password" name="password" required
                           placeholder="Enter your password"
                           class="w-full px-4 py-3 border border-gray-300 rounded-xl focus:outline-none focus:ring-2 focus:ring-purple-500 focus:border-transparent transition-all">
                </div>

                <div class="flex items-center justify-between">
                    <label class="flex items-center gap-2 cursor-pointer">
                        <input type="checkbox" name="remember" 
                               class="w-4 h-4 text-purple-600 border-gray-300 rounded focus:ring-purple-500">
                        <span class="text-sm text-gray-700">Remember me</span>
                    </label>
                    <a href="forgot_password.php" class="text-sm text-purple-600 hover:text-purple-700 font-medium">
                        Forgot Password?
                    </a>
                </div>

                <button type="submit" 
                        class="w-full bg-gradient-to-r from-purple-500 to-pink-500 hover:from-purple-600 hover:to-pink-600 text-white font-semibold py-3 rounded-xl shadow-lg transition-all transform hover:scale-[1.02]">
                    Login
                </button>

                <div class="text-center text-gray-600 text-sm">
                    Don't have an account? 
                    <a href="register.php" class="text-purple-600 hover:text-purple-700 font-semibold">
                        Sign Up
                    </a>
                </div>
            </form>
        </div>

        <!-- Right Section - Hero -->
        <div class="hidden lg:block">
            <div class="text-center space-y-6">
                
                <!-- Large Icon -->
                <div class="flex justify-center mb-8">
                    <div class="w-32 h-32 rounded-full bg-gradient-to-br from-purple-400 via-pink-400 to-blue-400 flex items-center justify-center shadow-2xl">
                        <span class="text-6xl">📚</span>
                    </div>
                </div>

                <!-- Hero Title -->
                <h2 class="text-5xl md:text-6xl font-bold leading-tight">
                    <span class="bg-gradient-to-r from-purple-600 to-pink-600 bg-clip-text text-transparent">
                        Clear
                    </span>
                    <br>
                    <span class="bg-gradient-to-r from-pink-600 to-blue-600 bg-clip-text text-transparent">
                        Cluttered
                    </span>
                    <br>
                    <span class="bg-gradient-to-r from-blue-600 to-purple-600 bg-clip-text text-transparent">
                        Schedules
                    </span>
                </h2>

                <!-- Description -->
                <p class="text-xl text-gray-600 max-w-md mx-auto">
                    Organize your study time, track your progress, and achieve your academic goals with AI-powered scheduling.
                </p>

                <!-- Features -->
                <div class="grid grid-cols-1 gap-4 mt-8 max-w-md mx-auto">
                    <div class="bg-white rounded-2xl p-4 shadow-lg border border-purple-100">
                        <div class="flex items-center gap-3">
                            <div class="w-10 h-10 rounded-full bg-purple-100 flex items-center justify-center">
                                <span class="text-xl">🎯</span>
                            </div>
                            <div class="text-left">
                                <div class="font-semibold text-purple-900">Smart Scheduling</div>
                                <div class="text-sm text-gray-600">AI-powered study plans</div>
                            </div>
                        </div>
                    </div>

                    <div class="bg-white rounded-2xl p-4 shadow-lg border border-pink-100">
                        <div class="flex items-center gap-3">
                            <div class="w-10 h-10 rounded-full bg-pink-100 flex items-center justify-center">
                                <span class="text-xl">📊</span>
                            </div>
                            <div class="text-left">
                                <div class="font-semibold text-pink-900">Track Progress</div>
                                <div class="text-sm text-gray-600">Monitor your study time</div>
                            </div>
                        </div>
                    </div>

                    <div class="bg-white rounded-2xl p-4 shadow-lg border border-blue-100">
                        <div class="flex items-center gap-3">
                            <div class="w-10 h-10 rounded-full bg-blue-100 flex items-center justify-center">
                                <span class="text-xl">✅</span>
                            </div>
                            <div class="text-left">
                                <div class="font-semibold text-blue-900">Task Management</div>
                                <div class="text-sm text-gray-600">Never miss a deadline</div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

</body>
</html>