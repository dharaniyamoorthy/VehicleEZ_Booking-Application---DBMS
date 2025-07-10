<?php
session_start();
require_once 'config.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        $pdo = getDBConnection();
        
        // Validate inputs
        $vehicle_id = $_POST['vehicle_id'] ?? null;
        $pickup = $_POST['pickup_datetime'] ?? null;
        $return = $_POST['return_datetime'] ?? null;
        $user_id = $_SESSION['user_id'];
        
        if (!$vehicle_id || !$pickup || !$return) {
            throw new Exception("All fields are required");
        }
        
        // Insert booking
        $stmt = $pdo->prepare("INSERT INTO bookings 
                              (user_id, vehicle_id, start_date, end_date, booking_status) 
                              VALUES (?, ?, ?, ?, 'pending')");
        $stmt->execute([$user_id, $vehicle_id, $pickup, $return]);
        
        // Update vehicle status
        $stmt = $pdo->prepare("UPDATE vehicles SET status = 'unavailable' WHERE vehicle_id = ?");
        $stmt->execute([$vehicle_id]);
        
        // Redirect to success page
        header('Location: booking_success.php');
        exit();
        
    } catch (Exception $e) {
        // Handle error
        header('Location: book.php?error=' . urlencode($e->getMessage()));
        exit();
    }
} else {
    header('Location: book.php');
    exit();
}