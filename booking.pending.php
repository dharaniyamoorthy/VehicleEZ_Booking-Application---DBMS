<?php
session_start();
require_once 'config.php';

// Redirect if not logged in
if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit();
}

// Get booking ID
$booking_id = $_GET['booking_id'] ?? $_SESSION['last_booking_id'] ?? null;

if (!$booking_id) {
    die("Booking ID not provided");
}

try {
    $pdo = getDBConnection();
    
    // Get booking details
    $stmt = $pdo->prepare("SELECT b.*, v.vehicle_name 
                         FROM bookings b
                         JOIN vehicles v ON b.vehicle_id = v.vehicle_id
                         WHERE b.booking_id = ? AND b.user_id = ?");
    $stmt->execute([$booking_id, $_SESSION['user_id']]);
    $booking = $stmt->fetch();
    
    if (!$booking) {
        throw new Exception("Booking not found");
    }
    
    // Calculate rental days
    $pickup = new DateTime($booking['pickup_date']);
    $return = new DateTime($booking['return_date']);
    $days = $pickup->diff($return)->days + 1;
    
} catch (Exception $e) {
    $error = $e->getMessage();
}
?>
<!DOCTYPE html>
<html>
<head>
    <title>Booking Confirmation</title>
    <style>
        body { font-family: Arial, sans-serif; padding: 20px; }
        .container { max-width: 800px; margin: 0 auto; }
        .success { color: green; }
        .error { color: red; }
    </style>
</head>
<body>
    <div class="container">
        <?php if (isset($error)): ?>
            <div class="error">Error: <?= htmlspecialchars($error) ?></div>
            <a href="two_wheelers.php">Back to Vehicles</a>
        <?php else: ?>
            <h1 class="success">Booking Received!</h1>
            <p>Your booking #<?= $booking_id ?> is being processed.</p>
            
            <h3>Details:</h3>
            <p>Vehicle: <?= htmlspecialchars($booking['vehicle_name']) ?></p>
            <p>Pickup: <?= $booking['pickup_date'] ?></p>
            <p>Return: <?= $booking['return_date'] ?></p>
            <p>Total Days: <?= $days ?></p>
            <p>Amount: ₹<?= number_format($booking['total_price'], 2) ?></p>
            
            <a href="my_bookings.php">View My Bookings</a>
        <?php endif; ?>
    </div>
</body>
</html>