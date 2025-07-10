<?php
session_start();
require_once 'config.php';

// Redirect if not logged in
if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit();
}

// Get booking ID from URL
$booking_id = $_GET['booking_id'] ?? null;

try {
    $pdo = getDBConnection();
    
    // Get booking details with vehicle and user info
    $stmt = $pdo->prepare("
    SELECT b.*, 
           v.make, v.model, v.image_path, v.daily_rate, v.license_plate,
           u.full_name, u.email, u.phone,
           DATEDIFF(b.return_date, b.pickup_date) + 1 as days
    FROM bookings b
    JOIN vehicles v ON b.vehicle_id = v.vehicle_id
    JOIN users u ON b.user_id = u.user_id
    WHERE b.booking_id = ? AND b.user_id = ?
");
    $stmt->execute([$booking_id, $_SESSION['user_id']]);
    $booking = $stmt->fetch();
    
    if (!$booking) {
        throw new Exception("Booking not found or you don't have permission to view it");
    }
    
    // Calculate total price if not already stored
    if (empty($booking['total_price'])) {
        $booking['total_price'] = $booking['days'] * $booking['daily_rate'];
    }
    
} catch (Exception $e) {
    $error = $e->getMessage();
}
?>
<!DOCTYPE html>
<html>
<head>
    <title>Booking Details | #<?= $booking_id ?></title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <style>
        :root {
            --primary-color: #4a6bff;
            --secondary-color: #28a745;
            --dark-color: #343a40;
            --light-color: #f8f9fa;
        }
        
        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            background-color: #f5f7fa;
            margin: 0;
            padding: 20px;
            color: #333;
        }
        
        .container {
            max-width: 800px;
            margin: 0 auto;
            background: white;
            border-radius: 10px;
            box-shadow: 0 5px 15px rgba(0,0,0,0.1);
            padding: 30px;
        }
        
        .header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 30px;
            padding-bottom: 20px;
            border-bottom: 1px solid #eee;
        }
        
        .page-title {
            font-size: 24px;
            color: var(--dark-color);
            margin: 0;
        }
        
        .status-badge {
            padding: 5px 15px;
            border-radius: 20px;
            font-weight: bold;
            font-size: 14px;
        }
        
        .status-confirmed {
            background-color: var(--secondary-color);
            color: white;
        }
        
        .booking-summary {
            display: flex;
            gap: 30px;
            margin-bottom: 30px;
        }
        
        .vehicle-image {
            width: 300px;
            height: 200px;
            object-fit: cover;
            border-radius: 8px;
        }
        
        .details-section {
            flex: 1;
        }
        
        .detail-card {
            background: #f9f9f9;
            border-radius: 8px;
            padding: 20px;
            margin-bottom: 20px;
        }
        
        .detail-card h3 {
            margin-top: 0;
            padding-bottom: 10px;
            border-bottom: 1px solid #ddd;
        }
        
        .detail-row {
            display: flex;
            margin-bottom: 10px;
        }
        
        .detail-label {
            font-weight: 600;
            min-width: 150px;
            color: #666;
        }
        
        .detail-value {
            flex: 1;
        }
        
        .action-buttons {
            display: flex;
            gap: 15px;
            margin-top: 30px;
        }
        
        .btn {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            padding: 10px 20px;
            text-decoration: none;
            border-radius: 5px;
            font-weight: 500;
            transition: all 0.3s;
        }
        
        .btn-primary {
            background: var(--primary-color);
            color: white;
        }
        
        .btn-primary:hover {
            background: #3a56d4;
        }
        
        .btn-secondary {
            background: #f8f9fa;
            color: var(--dark-color);
            border: 1px solid #ddd;
        }
        
        .btn-secondary:hover {
            background: #e9ecef;
        }
        
        @media (max-width: 768px) {
            .booking-summary {
                flex-direction: column;
            }
            
            .vehicle-image {
                width: 100%;
            }
        }
    </style>
</head>
<body>
    <div class="container">
        <?php if (isset($error)): ?>
            <div class="error-message" style="background: #f8d7da; color: #721c24; padding: 15px; border-radius: 5px; margin-bottom: 20px;">
                <i class="fas fa-exclamation-circle"></i> <?= htmlspecialchars($error) ?>
            </div>
            <a href="confirmed_bookings.php" class="btn btn-secondary">
                <i class="fas fa-arrow-left"></i> Back to Bookings
            </a>
        <?php else: ?>
            <div class="header">
                <h1 class="page-title">
                    <i class="fas fa-file-alt"></i> Booking Details
                </h1>
                <span class="status-badge status-confirmed">
                    <i class="fas fa-check-circle"></i> CONFIRMED
                </span>
            </div>
            
            <div class="booking-summary">
                <img src="<?= htmlspecialchars($booking['image_path']) ?>" alt="<?= htmlspecialchars($booking['make'] . ' ' . $booking['model']) ?>" class="vehicle-image">
                
                <div class="details-section">
                    <h2><?= htmlspecialchars($booking['make'] . ' ' . $booking['model']) ?></h2>
                    <span class="detail-label">License Plate:</span>
                    <p>Registration: <?= htmlspecialchars($booking['license_plate']) ?></p>
                    
                    <div class="detail-row">
                        <span class="detail-label">Booking ID:</span>
                        <span class="detail-value">#<?= $booking['booking_id'] ?></span>
                    </div>
                    <div class="detail-row">
                        <span class="detail-label">Booking Date:</span>
                        <span class="detail-value"><?= date('M j, Y H:i', strtotime($booking['created_at'])) ?></span>
                    </div>
                    <div class="detail-row">
                        <span class="detail-label">Daily Rate:</span>
                        <span class="detail-value">₹<?= number_format($booking['daily_rate'], 2) ?></span>
                    </div>
                </div>
            </div>
            
            <div class="detail-card">
                <h3><i class="fas fa-calendar-alt"></i> Rental Period</h3>
                <div class="detail-row">
                    <span class="detail-label">Pickup Date:</span>
                    <span class="detail-value"><?= date('l, F j, Y', strtotime($booking['pickup_date'])) ?></span>
                </div>
                <div class="detail-row">
                    <span class="detail-label">Return Date:</span>
                    <span class="detail-value"><?= date('l, F j, Y', strtotime($booking['return_date'])) ?></span>
                </div>
                <div class="detail-row">
                    <span class="detail-label">Total Days:</span>
                    <span class="detail-value"><?= $booking['days'] ?> days</span>
                </div>
                <div class="detail-row">
                    <span class="detail-label">Total Amount:</span>
                    <span class="detail-value">₹<?= number_format($booking['total_price'], 2) ?></span>
                </div>
            </div>
            
            <div class="detail-card">
                <h3><i class="fas fa-user"></i> Customer Information</h3>
                <div class="detail-row">
                    <span class="detail-label">Name:</span>
                    <span class="detail-value"><?= htmlspecialchars($booking['full_name']) ?></span>
                </div>
                <div class="detail-row">
                    <span class="detail-label">Email:</span>
                    <span class="detail-value"><?= htmlspecialchars($booking['email']) ?></span>
                </div>
                <div class="detail-row">
                    <span class="detail-label">Phone:</span>
                    <span class="detail-value"><?= htmlspecialchars($booking['phone']) ?></span>
                </div>
                
            </div>
            
            <div class="action-buttons">
                <a href="print_booking.php?booking_id=<?= $booking['booking_id'] ?>" class="btn btn-primary" target="_blank">
                    <i class="fas fa-print"></i> Print Booking
                </a>
                <a href="confirmed_bookings.php" class="btn btn-secondary">
                    <i class="fas fa-arrow-left"></i> Back to Bookings
                </a>
            </div>
        <?php endif; ?>
    </div>
</body>
</html>