<?php
function getDBConnection() {
    $host = 'localhost';
    $dbname = 'Vehicle_booking_system';
    $username = 'root';    // Default XAMPP username
    $password = '';        // Default XAMPP password (empty)

    try {
        $pdo = new PDO("mysql:host=$host;dbname=$dbname;charset=utf8", $username, $password);
        $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        return $pdo;
    } catch (PDOException $e) {
        die("Database connection failed: " . $e->getMessage());
    }
}
?>
