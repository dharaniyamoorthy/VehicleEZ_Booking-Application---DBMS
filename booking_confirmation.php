<?php
session_start();
require_once 'config.php';

// Redirect if not logged in
if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit();
}

// Initialize variables
$error = null;
$booking = null;
$days = 0;
$booking_id = null;

// Validate and get booking ID
if (isset($_GET['booking_id'])) {
    $booking_id = (int)$_GET['booking_id'];
} elseif (isset($_SESSION['last_booking_id'])) {
    $booking_id = (int)$_SESSION['last_booking_id'];
}

if (!$booking_id || $booking_id < 1) {
    $_SESSION['error'] = "Invalid booking reference";
    header('Location: book_tw.php');
    exit();
}

// Database connection and query
try {
    // Get database connection
    $pdo = getDBConnection();
    
    if (!$pdo) {
        throw new Exception("Could not connect to database");
    }

    // Get booking details with vehicle information
    $stmt = $pdo->prepare("
        SELECT b.*, v.make, v.image_path, v.daily_rate, 
               v.license_plate, v.color, v.fuel_type
        FROM bookings b
        JOIN vehicles v ON b.vehicle_id = v.vehicle_id
        WHERE b.booking_id = :booking_id AND b.user_id = :user_id
    ");
    
    // Execute with named parameters
    $stmt->execute([
        ':booking_id' => $booking_id,
        ':user_id' => $_SESSION['user_id']
    ]);
    
    $booking = $stmt->fetch();
    
    if (!$booking) {
        throw new Exception("No booking found with ID: $booking_id for your account.");
    }
    
    // Calculate rental period
    $pickup = new DateTime($booking['pickup_date']);
    $return = new DateTime($booking['return_date']);
    $days = $pickup->diff($return)->days + 1; // Include both start and end day
    
    // Verify booking data
    if (!isset($booking['daily_rate']) || !isset($booking['total_price'])) {
        throw new Exception("Incomplete booking data received from database.");
    }

} catch (PDOException $e) {
    error_log("Database error in booking_confirmation.php: " . $e->getMessage());
    $error = "We're experiencing technical difficulties. Please try again later.";
} catch (Exception $e) {
    error_log("Error in booking_confirmation.php: " . $e->getMessage());
    $error = $e->getMessage();
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Booking Confirmation</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <style>
        body {
            font-family: 'Poppins', sans-serif;
            background-color: #f5f5f5;
            margin: 0;
            padding: 20px;
            color: #333;
        }
        .container {
            max-width: 800px;
            margin: 0 auto;
            background: white;
            padding: 30px;
            border-radius: 8px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
        }
        .confirmation-header {
            text-align: center;
            margin-bottom: 30px;
            color: #4CAF50;
        }
        .confirmation-header h1 {
            margin-bottom: 10px;
        }
        .booking-details {
            display: flex;
            gap: 30px;
            margin-bottom: 30px;
        }
        .vehicle-image {
            width: 300px;
            height: 200px;
            object-fit: cover;
            border-radius: 8px;
            box-shadow: 0 2px 5px rgba(0,0,0,0.1);
        }
        .booking-info {
            flex: 1;
        }
        .detail-section {
            background: #f9f9f9;
            padding: 20px;
            border-radius: 8px;
            margin-bottom: 20px;
        }
        .detail-row {
            display: flex;
            justify-content: space-between;
            margin-bottom: 10px;
            padding-bottom: 10px;
            border-bottom: 1px solid #eee;
        }
        .detail-row:last-child {
            border-bottom: none;
        }
        .detail-label {
            font-weight: 600;
            color: #555;
        }
        .detail-value {
            text-align: right;
        }
        .total-row {
            font-weight: bold;
            font-size: 1.1em;
            color: #4CAF50;
        }
        .btn {
            display: inline-block;
            padding: 12px 24px;
            background-color: #4CAF50;
            color: white;
            text-decoration: none;
            border-radius: 4px;
            margin-right: 10px;
            margin-top: 10px;
            font-weight: 600;
            transition: background-color 0.3s;
        }
        .btn:hover {
            background-color: #45a049;
        }
        .btn-blue {
            background-color: #2196F3;
        }
        .btn-blue:hover {
            background-color: #0b7dda;
        }
        .text-center {
            text-align: center;
        }
        .error-message {
            background-color: #ffebee;
            border-left: 4px solid #f44336;
            padding: 20px;
            margin-bottom: 20px;
            border-radius: 4px;
        }
        .error-message h2 {
            color: #f44336;
            margin-top: 0;
        }
        .support-info {
            background-color: #e3f2fd;
            padding: 15px;
            border-radius: 4px;
            margin-top: 20px;
        }
    </style>
</head>
<body>
    <div class="container">
        <?php if (isset($error)): ?>
            <div class="error-message">
                <h2><i class="fas fa-exclamation-triangle"></i> Error Retrieving Booking</h2>
                <p><?php echo htmlspecialchars($error); ?></p>
                
                <div class="support-info">
                    <h3><i class="fas fa-life-ring"></i> Support Information</h3>
                    <p><i class="fas fa-phone"></i> <strong>Phone:</strong> +1 (555) 123-4567</p>
                    <p><i class="fas fa-envelope"></i> <strong>Email:</strong> support@vehicle-rentals.com</p>
                    <p><i class="fas fa-info-circle"></i> Please provide reference #<?php echo htmlspecialchars($booking_id); ?> when contacting support.</p>
                </div>
            </div>
            
            <div class="text-center">
                <a href="two_wheelers.php" class="btn"><i class="fas fa-motorcycle"></i> Browse Vehicles</a>
                <a href="my_booking.php" class="btn btn-blue"><i class="fas fa-calendar-alt"></i> My Bookings</a>
            </div>
        <?php else: ?>
            <div class="confirmation-header">
                <h1><i class="fas fa-check-circle"></i> Booking Confirmed!</h1>
                <p>Your booking #<?php echo htmlspecialchars($booking_id); ?> has been successfully processed.</p>
            </div>
            
            <div class="booking-details">
                <div>
                    <img src="<?php echo htmlspecialchars($booking['image_path']); ?>" 
     alt="<?php echo htmlspecialchars($booking['make']); ?>" 
     class="vehicle-image">
                </div>
                <div class="booking-info">
                    <div class="detail-section">
    <h2><i class="fas fa-motorcycle"></i> Vehicle Details</h2>
    <div class="detail-row">
        <span class="detail-label">Vehicle Make:</span>
        <span class="detail-value"><?php echo htmlspecialchars($booking['make']); ?></span>
    </div>
                        <div class="detail-row">
                            <span class="detail-label">License Plate:</span>
                            <span class="detail-value"><?php echo htmlspecialchars($booking['license_plate']); ?></span>
                        </div>
                        <div class="detail-row">
                            <span class="detail-label">Color:</span>
                            <span class="detail-value"><?php echo htmlspecialchars($booking['color']); ?></span>
                        </div>
                        <div class="detail-row">
                            <span class="detail-label">Fuel Type:</span>
                            <span class="detail-value"><?php echo htmlspecialchars($booking['fuel_type']); ?></span>
                        </div>
                    </div>
                    
                    <div class="detail-section">
                        <h2><i class="fas fa-calendar-alt"></i> Rental Period</h2>
                        <div class="detail-row">
                            <span class="detail-label">Pickup Date:</span>
                            <span class="detail-value"><?php echo date('D, M j Y', strtotime($booking['pickup_date'])); ?></span>
                        </div>
                        <div class="detail-row">
                            <span class="detail-label">Return Date:</span>
                            <span class="detail-value"><?php echo date('D, M j Y', strtotime($booking['return_date'])); ?></span>
                        </div>
                        <div class="detail-row">
                            <span class="detail-label">Total Days:</span>
                            <span class="detail-value"><?php echo $days; ?></span>
                        </div>
                    </div>
                </div>
            </div>
            
            <div class="detail-section">
                <h2><i class="fas fa-receipt"></i> Payment Summary</h2>
                <div class="detail-row">
                    <span class="detail-label">Daily Rate:</span>
                    <span class="detail-value">₹<?php echo number_format($booking['daily_rate'], 2); ?></span>
                </div>
                <?php if ($booking['helmet_request']): ?>
                <div class="detail-row">
                    <span class="detail-label">Helmet Rental (<?php echo $days; ?> days):</span>
                    <span class="detail-value">₹<?php echo number_format(100 * $days, 2); ?></span>
                </div>
                <?php endif; ?>
                <?php if ($booking['riding_gear_request']): ?>
                <div class="detail-row">
                    <span class="detail-label">Riding Gear Rental (<?php echo $days; ?> days):</span>
                    <span class="detail-value">₹<?php echo number_format(300 * $days, 2); ?></span>
                </div>
                <?php endif; ?>
                <div class="detail-row total-row">
                    <span class="detail-label">Total Amount:</span>
                    <span class="detail-value">₹<?php echo number_format($booking['total_price'], 2); ?></span>
                </div>
            </div>
            
            <div class="text-center">
                <a href="my_booking.php" class="btn"><i class="fas fa-calendar-alt"></i> View All Bookings</a>
                <a href="two_wheelers.php" class="btn btn-blue"><i class="fas fa-motorcycle"></i> Book Another Vehicle</a>
                <a href="#" class="btn" style="background-color: #ff9800;"><i class="fas fa-print"></i> Print Confirmation</a>
            </div>
        <?php endif; ?>
    </div>
</body>
</html>