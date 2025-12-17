<?php
try {
    $pdo = new PDO("mysql:host=localhost;dbname=planora", "root", "");
    echo "CONNECTED";
} catch (PDOException $e) {
    echo $e->getMessage();
}
