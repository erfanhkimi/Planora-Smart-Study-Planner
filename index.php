<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Planora - Login</title>
    <link rel="stylesheet" href="style.css">
</head>
<body>
    <div class="container">
        <div class="left-section">
            <div class="logo">
                <div class="logo-circle"></div>
                <div class="logo-text">Planora</div>
            </div>

            <div class="form-container">
                <form method="POST" action="login_process.php">
                    <h1>Hi, Welcome back!</h1>
                    
                    <?php if (isset($_GET['error'])): ?>
                        <div class="error-message">
                            <?php echo htmlspecialchars($_GET['error']); ?>
                        </div>
                    <?php endif; ?>

                    <?php if (isset($_GET['success'])): ?>
                        <div class="success-message">
                            <?php echo htmlspecialchars($_GET['success']); ?>
                        </div>
                    <?php endif; ?>
                    
                    <div class="form-group">
                        <label for="email">Email</label>
                        <input type="email" id="email" name="email" required>
                    </div>

                    <div class="form-group">
                        <label for="password">Password</label>
                        <input type="password" id="password" name="password" required>
                    </div>

                    <div class="form-options">
                        <label class="remember-me">
                            <input type="checkbox" name="remember">
                            Remember me
                        </label>
                        <a href="forgot_password.php" class="forgot-password">Forgot Password?</a>
                    </div>

                    <button type="submit" class="submit-btn">Login</button>

                    <div class="toggle-form">
                        Don't have an account? <a href="register.php">Sign Up</a>
                    </div>
                </form>
            </div>
        </div>

        <div class="right-section">
            <a href="register.php" class="signup-btn">Sign Up</a>
            
            <div class="hero-content">
                <div class="hero-title">Clear<br>Cluttered<br>Schedules</div>
                
                <div class="paper-plane">
                    <div class="plane-wing"></div>
                    <div class="plane-body"></div>
                </div>

                <div class="dotted-line"></div>
                <div class="dot dot1"></div>
                <div class="dot dot2"></div>
                <div class="dot dot3"></div>
            </div>
        </div>
    </div>
</body>
</html>