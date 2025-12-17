<?php
session_start();
require 'db_config.php';

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $email = trim($_POST['email']);
    $password = $_POST['password'];

    $stmt = $pdo->prepare("SELECT * FROM Users WHERE Email = ?");
    $stmt->execute([$email]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);

    // PLAIN TEXT CHECK: Compare input directly to database column
    if ($user && $password === $user['Password']) {
        $_SESSION['user_id'] = $user['UserID'];
        $_SESSION['user_name'] = $user['Name'];

        header("Location: dashboard.php");
        exit();
    } else {
        header("Location: index.php?error=Invalid email or password");
        exit();
    }
}
?>