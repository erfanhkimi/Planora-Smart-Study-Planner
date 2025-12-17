<?php
require 'db_config.php';

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $name = trim($_POST['name']);
    $email = trim($_POST['email']);
    $password = $_POST['password'];
    $confirm_password = $_POST['confirm_password'];

    if ($password !== $confirm_password) {
        header("Location: register.php?error=Passwords do not match");
        exit();
    }

    try {
        // SAVING AS PLAIN TEXT: Inserting $password directly instead of a hash
        $stmt = $pdo->prepare("INSERT INTO Users (Name, Email, Password) VALUES (?, ?, ?)");
        $stmt->execute([$name, $email, $password]);
        
        header("Location: index.php?success=Account created! Please login.");
        exit();
    } catch (PDOException $e) {
        if ($e->getCode() == 23000) {
            header("Location: register.php?error=Email already exists");
        } else {
            header("Location: register.php?error=Something went wrong");
        }
        exit();
    }
}
?>