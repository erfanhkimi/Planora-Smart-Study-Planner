
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Planora - Register</title>
    <link rel="stylesheet" href="style.css">
</head>
<body>
    <div class="container">
        <div class="left-section">
            <div class="logo">
                <div class="logo-circle"></div>
                <div class="logo-text">Planora</div>
            </div></br>
            </br></br></br></br></br>

            <div class="form-container">
                <form method="POST" action="register_process.php">
                    <h1>Create Account!</h1>
                    
                    <?php if (isset($_GET['error'])): ?>
                        <div class="error-message">
                            <?php echo htmlspecialchars($_GET['error']); ?>
                        </div>
                    <?php endif; ?>
                    
                    <div class="form-group">
                        <label for="name">Full Name</label>
                        <input type="text" id="name" name="name" required>
                    </div>

                    <div class="form-group">
                        <label for="email">Email</label>
                        <input type="email" id="email" name="email" required>
                    </div>

                    <div class="form-group">
                        <label for="password">Password</label>
                        <input type="password" id="password" name="password" required minlength="6">
                    </div>

                    <div class="form-group">
                        <label for="confirm_password">Confirm Password</label>
                        <input type="password" id="confirm_password" name="confirm_password" required>
                    </div>

                    <button type="submit" class="submit-btn">Sign Up</button>

                    <div class="toggle-form">
                        Already have an account? <a href="index.php">Login</a>
                    </div>
                </form>
            </div>
        </div>

        <div class="right-section">
            <a href="index.php" class="signup-btn">Login</a>
            
            <div class="hero-content">
                <div class="hero-title">Clear<br>Cluttered<br>Schedules</div>
                

            </div>
        </div>
    </div>
</body>
</html>
