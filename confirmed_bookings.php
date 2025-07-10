<?php
session_start();
require_once 'config.php';

// Redirect if not logged in
if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit();
}

try {
    $pdo = getDBConnection();
    
    // Get only confirmed bookings for the logged-in user
    $stmt = $pdo->prepare("
        SELECT b.*, v.make, v.model, v.image_path, v.year, v.color, v.license_plate,
               DATEDIFF(b.return_date, b.pickup_date) + 1 as days,
               u.full_name, u.email, u.phone
        FROM bookings b
        JOIN vehicles v ON b.vehicle_id = v.vehicle_id
        JOIN users u ON b.user_id = u.user_id
        WHERE b.user_id = ? 
        AND b.status = 'confirmed'
        ORDER BY b.pickup_date DESC
    ");
    $stmt->execute([$_SESSION['user_id']]);
    $bookings = $stmt->fetchAll();
    
} catch (Exception $e) {
    $error = $e->getMessage();
}
?>
<!DOCTYPE html>
<html>
<head>
    <title>Confirmed Bookings</title>
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
            max-width: 1200px;
            margin: 0 auto;
        }
        
        .header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 30px;
        }
        
        .page-title {
            font-size: 28px;
            color: var(--dark-color);
            margin: 0;
        }
        
        .btn {
            display: inline-block;
            padding: 10px 20px;
            background: var(--primary-color);
            color: white;
            text-decoration: none;
            border-radius: 5px;
            transition: all 0.3s;
        }
        
        .btn:hover {
            background: #3a56d4;
            transform: translateY(-2px);
        }
        
        .booking-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(350px, 1fr));
            gap: 25px;
        }
        
        .booking-card {
            background: white;
            border-radius: 10px;
            box-shadow: 0 5px 15px rgba(0,0,0,0.1);
            overflow: hidden;
            transition: all 0.3s;
        }
        
        .booking-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 10px 25px rgba(0,0,0,0.15);
        }
        
        .booking-image {
            width: 100%;
            height: 200px;
            object-fit: cover;
        }
        
        .booking-content {
            padding: 20px;
        }
        
        .booking-header {
            display: flex;
            justify-content: space-between;
            margin-bottom: 15px;
            padding-bottom: 10px;
            border-bottom: 1px solid #eee;
        }
        
        .booking-title {
            font-size: 18px;
            font-weight: 600;
            margin: 0;
        }
        
        .booking-status {
            background-color: var(--secondary-color);
            color: white;
            padding: 3px 10px;
            border-radius: 20px;
            font-size: 12px;
            font-weight: bold;
        }
        
        .booking-details {
            margin-bottom: 15px;
        }
        
        .detail-row {
            display: flex;
            margin-bottom: 8px;
        }
        
        .detail-label {
            font-weight: 600;
            min-width: 100px;
            color: #666;
        }
        
        .detail-value {
            flex: 1;
        }
        
        .booking-actions {
            display: flex;
            gap: 10px;
            margin-top: 20px;
        }
        
        .action-btn {
            flex: 1;
            padding: 8px;
            text-align: center;
            border-radius: 5px;
            font-size: 14px;
            transition: all 0.3s;
            text-decoration: none;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 5px;
        }
        
        .print-btn {
            background: #f8f9fa;
            color: var(--dark-color);
            border: 1px solid #ddd;
        }
        
        .print-btn:hover {
            background: #e9ecef;
        }
        
        .details-btn {
            background: var(--primary-color);
            color: white;
        }
        
        .details-btn:hover {
            background: #3a56d4;
        }
        
        .no-bookings {
            text-align: center;
            padding: 50px;
            background: white;
            border-radius: 10px;
            box-shadow: 0 5px 15px rgba(0,0,0,0.1);
        }
        
        .no-bookings i {
            font-size: 50px;
            color: #ddd;
            margin-bottom: 20px;
        }
        
        .no-bookings h3 {
            color: #666;
            margin-bottom: 15px;
        }
        
        .customer-info {
            background: #f9f9f9;
            padding: 10px;
            border-radius: 5px;
            margin-top: 10px;
        }
        
        @media (max-width: 768px) {
            .booking-grid {
                grid-template-columns: 1fr;
            }
            
            .header {
                flex-direction: column;
                align-items: flex-start;
                gap: 15px;
            }
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <h1 class="page-title">
                <i class="fas fa-check-circle"></i> Confirmed Bookings
            </h1>
            <a href="two_wheelers.php" class="btn">
                <i class="fas fa-plus"></i> New Booking
            </a>
        </div>
        
        <?php if (isset($error)): ?>
            <div class="error-message" style="background: #f8d7da; color: #721c24; padding: 15px; border-radius: 5px; margin-bottom: 20px;">
                <i class="fas fa-exclamation-circle"></i> <?= htmlspecialchars($error) ?>
            </div>
        <?php endif; ?>
        
        <?php if (empty($bookings)): ?>
            <div class="no-bookings">
                <i class="fas fa-calendar-check"></i>
                <h3>No Confirmed Bookings Yet</h3>
                <p>You don't have any confirmed bookings at the moment. Once your pending bookings are approved by admin, they will appear here.</p>
                <a href="my_bookings.php" class="btn" style="margin-top: 20px;">
                    <i class="fas fa-arrow-left"></i> View All Bookings
                </a>
            </div>
        <?php else: ?>
            <div class="booking-grid">
                <?php foreach ($bookings as $booking): ?>
                    <div class="booking-card">
                        <img src="<?= htmlspecialchars($booking['image_path'] ?? 'images/default-vehicle.jpg') ?>" 
                             alt="<?= htmlspecialchars($booking['make'] . ' ' . $booking['model']) ?>" 
                             class="booking-image">
                        
                        <div class="booking-content">
                            <div class="booking-header">
                                <h3 class="booking-title"><?= htmlspecialchars($booking['make'] . ' ' . $booking['model']) ?></h3>
                                <span class="booking-status">CONFIRMED</span>
                            </div>
                            
                            <div class="booking-details">
                                <div class="detail-row">
                                    <span class="detail-label">Booking ID:</span>
                                    <span class="detail-value">#<?= $booking['booking_id'] ?></span>
                                </div>
                                <div class="detail-row">
                                    <span class="detail-label">Year:</span>
                                    <span class="detail-value"><?= htmlspecialchars($booking['year']) ?></span>
                                </div>
                                <div class="detail-row">
                                    <span class="detail-label">Color:</span>
                                    <span class="detail-value"><?= htmlspecialchars($booking['color']) ?></span>
                                </div>
                                <div class="detail-row">
                                    <span class="detail-label">License:</span>
                                    <span class="detail-value"><?= htmlspecialchars($booking['license_plate']) ?></span>
                                </div>
                                <div class="detail-row">
                                    <span class="detail-label">Pickup Date:</span>
                                    <span class="detail-value"><?= date('M j, Y', strtotime($booking['pickup_date'])) ?></span>
                                </div>
                                <div class="detail-row">
                                    <span class="detail-label">Return Date:</span>
                                    <span class="detail-value"><?= date('M j, Y', strtotime($booking['return_date'])) ?></span>
                                </div>
                                <div class="detail-row">
                                    <span class="detail-label">Duration:</span>
                                    <span class="detail-value"><?= $booking['days'] ?> days</span>
                                </div>
                                <div class="detail-row">
                                    <span class="detail-label">Total Amount:</span>
                                    <span class="detail-value">₹<?= number_format($booking['total_price'], 2) ?></span>
                                </div>
                                
                                <div class="customer-info">
                                    <div class="detail-row">
                                        <span class="detail-label">Your Name:</span>
                                        <span class="detail-value"><?= htmlspecialchars($booking['full_name']) ?></span>
                                    </div>
                                    <div class="detail-row">
                                        <span class="detail-label">Contact:</span>
                                        <span class="detail-value"><?= htmlspecialchars($booking['phone']) ?></span>
                                    </div>
                                </div>
                            </div>
                            
                            <div class="booking-actions">
                                <a href="booking_details.php?booking_id=<?= $booking['booking_id'] ?>" class="action-btn details-btn">
                                    <i class="fas fa-info-circle"></i> Details
                                </a>
                                <a href="print_booking.php?booking_id=<?= $booking['booking_id'] ?>" target="_blank" class="action-btn print-btn">
                                    <i class="fas fa-print"></i> Print
                                </a>
                            </div>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </div>
</body>
</html>