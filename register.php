<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Planora - Register</title>
    <script src="https://cdn.tailwindcss.com"></script>
</head>
<body class="bg-gradient-to-br from-purple-50 via-pink-50 to-blue-50 min-h-screen flex items-center justify-center p-4">
    
    <div class="w-full max-w-6xl grid grid-cols-1 lg:grid-cols-2 gap-8 items-center">
        
        <!-- Left Section - Register Form -->
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
            <h2 class="text-3xl font-bold text-purple-900 mb-2">Create Account!</h2>
            <p class="text-gray-600 mb-8">Join us and start organizing your study life</p>

            <!-- Error Messages -->
            <?php if (isset($_GET['error'])): ?>
                <div class="mb-6 bg-red-50 border border-red-200 text-red-800 px-4 py-3 rounded-xl">
                    ✕ <?php echo htmlspecialchars($_GET['error']); ?>
                </div>
            <?php endif; ?>

            <!-- Register Form -->
            <form method="POST" action="register_process.php" class="space-y-5">
                
                <div>
                    <label for="name" class="block text-sm font-medium text-gray-700 mb-2">Full Name</label>
                    <input type="text" id="name" name="name" required
                           placeholder="Enter your full name"
                           class="w-full px-4 py-3 border border-gray-300 rounded-xl focus:outline-none focus:ring-2 focus:ring-purple-500 focus:border-transparent transition-all">
                </div>

                <div>
                    <label for="email" class="block text-sm font-medium text-gray-700 mb-2">Email Address</label>
                    <input type="email" id="email" name="email" required
                           placeholder="Enter your email"
                           class="w-full px-4 py-3 border border-gray-300 rounded-xl focus:outline-none focus:ring-2 focus:ring-purple-500 focus:border-transparent transition-all">
                </div>

                <div>
                    <label for="password" class="block text-sm font-medium text-gray-700 mb-2">Password</label>
                    <input type="password" id="password" name="password" required minlength="6"
                           placeholder="Create a password (min. 6 characters)"
                           class="w-full px-4 py-3 border border-gray-300 rounded-xl focus:outline-none focus:ring-2 focus:ring-purple-500 focus:border-transparent transition-all">
                    <p class="text-xs text-gray-500 mt-1">Must be at least 6 characters long</p>
                </div>

                <div>
                    <label for="confirm_password" class="block text-sm font-medium text-gray-700 mb-2">Confirm Password</label>
                    <input type="password" id="confirm_password" name="confirm_password" required
                           placeholder="Re-enter your password"
                           class="w-full px-4 py-3 border border-gray-300 rounded-xl focus:outline-none focus:ring-2 focus:ring-purple-500 focus:border-transparent transition-all">
                </div>

                <button type="submit" 
                        class="w-full bg-gradient-to-r from-purple-500 to-pink-500 hover:from-purple-600 hover:to-pink-600 text-white font-semibold py-3 rounded-xl shadow-lg transition-all transform hover:scale-[1.02]">
                    Sign Up
                </button>

                <div class="text-center text-gray-600 text-sm">
                    Already have an account? 
                    <a href="index.php" class="text-purple-600 hover:text-purple-700 font-semibold">
                        Login
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
                        <span class="text-6xl">🚀</span>
                    </div>
                </div>

                <!-- Hero Title -->
                <h2 class="text-5xl md:text-6xl font-bold leading-tight">
                    <span class="bg-gradient-to-r from-purple-600 to-pink-600 bg-clip-text text-transparent">
                        Start Your
                    </span>
                    <br>
                    <span class="bg-gradient-to-r from-pink-600 to-blue-600 bg-clip-text text-transparent">
                        Journey
                    </span>
                    <br>
                    <span class="bg-gradient-to-r from-blue-600 to-purple-600 bg-clip-text text-transparent">
                        Today
                    </span>
                </h2>

                <!-- Description -->
                <p class="text-xl text-gray-600 max-w-md mx-auto">
                    Join thousands of students who have transformed their study habits with Planora's intelligent planning system.
                </p>

                <!-- Benefits -->
                <div class="grid grid-cols-1 gap-4 mt-8 max-w-md mx-auto">
                    <div class="bg-white rounded-2xl p-4 shadow-lg border border-purple-100">
                        <div class="flex items-center gap-3">
                            <div class="w-10 h-10 rounded-full bg-purple-100 flex items-center justify-center">
                                <span class="text-xl">⚡</span>
                            </div>
                            <div class="text-left">
                                <div class="font-semibold text-purple-900">Quick Setup</div>
                                <div class="text-sm text-gray-600">Get started in minutes</div>
                            </div>
                        </div>
                    </div>

                    <div class="bg-white rounded-2xl p-4 shadow-lg border border-pink-100">
                        <div class="flex items-center gap-3">
                            <div class="w-10 h-10 rounded-full bg-pink-100 flex items-center justify-center">
                                <span class="text-xl">🤖</span>
                            </div>
                            <div class="text-left">
                                <div class="font-semibold text-pink-900">AI Assistant</div>
                                <div class="text-sm text-gray-600">Get personalized study tips</div>
                            </div>
                        </div>
                    </div>

                    <div class="bg-white rounded-2xl p-4 shadow-lg border border-blue-100">
                        <div class="flex items-center gap-3">
                            <div class="w-10 h-10 rounded-full bg-blue-100 flex items-center justify-center">
                                <span class="text-xl">🎓</span>
                            </div>
                            <div class="text-left">
                                <div class="font-semibold text-blue-900">Better Grades</div>
                                <div class="text-sm text-gray-600">Achieve your academic goals</div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Trust Badge -->
                <div class="mt-8 pt-8 border-t border-gray-200">
                    <p class="text-sm text-gray-500">
                        🔒 Your data is secure and private
                    </p>
                </div>
            </div>
        </div>
    </div>

</body>
</html>