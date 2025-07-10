<?php
session_start();
require_once 'config.php';

// Redirect if not logged in
if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit();
}

// Get booking ID from either:
// 1. URL parameter (?booking_id=123)
// 2. Session (set after booking creation)
// 3. Redirect if neither exists
$booking_id = $_GET['booking_id'] ?? $_SESSION['last_booking_id'] ?? null;

if (!$booking_id) {
    // If no booking ID found, redirect with error
    $_SESSION['error'] = "No booking reference found. Please start your booking again.";
    header('Location: '.($vehicle_type === 'four_wheeler' ? 'four_wheelers.php' : 'two_wheelers.php'));
    exit();
}

try {
    $pdo = getDBConnection();
    
    // Get complete booking details
    $stmt = $pdo->prepare("
        SELECT b.*, 
               v.make, v.model, v.image_path, v.vehicle_type,
               v.year, v.color, v.license_plate, v.fuel_type, v.daily_rate,
               DATEDIFF(b.return_date, b.pickup_date) + 1 as days,
               u.full_name, u.email, u.phone
        FROM bookings b
        JOIN vehicles v ON b.vehicle_id = v.vehicle_id
        JOIN users u ON b.user_id = u.user_id
        WHERE b.booking_id = ? AND b.user_id = ?
    ");
    $stmt->execute([$booking_id, $_SESSION['user_id']]);
    $booking = $stmt->fetch();

    if (!$booking) {
        throw new Exception("We couldn't find your booking. Please contact support.");
    }

    // Set vehicle type
    $vehicle_type = $booking['vehicle_type'];
    $vehicle_type_name = $vehicle_type === 'four_wheeler' ? 'Four-Wheeler' : 'Two-Wheeler';

    // Calculate charges
    $base_price = $booking['days'] * $booking['daily_rate'];
    
    if ($vehicle_type === 'four_wheeler') {
        $accessories = [
            'Child Seat' => $booking['child_seat'] ? 200 * $booking['days'] : 0,
            'GPS' => $booking['gps_navigation'] ? 150 * $booking['days'] : 0
        ];
    } else {
        $accessories = [
            'Helmet' => $booking['helmet_request'] ? 100 * $booking['days'] : 0,
            'Riding Gear' => $booking['riding_gear_request'] ? 300 * $booking['days'] : 0
        ];
    }

    $total_price = $base_price + array_sum($accessories);

} catch (Exception $e) {
    $_SESSION['error'] = $e->getMessage();
    header('Location: '.($vehicle_type === 'four_wheeler' ? 'four_wheelers.php' : 'two_wheelers.php'));
    exit();
}
?>

<!DOCTYPE html>
<html>
<head>
    <title>Booking Status | Vehicle Rental</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <style>
        :root {
            --primary-color: #4a6bff;
            --secondary-color: #28a745;
            --danger-color: #e74c3c;
            --warning-color: #f39c12;
            --dark-color: #343a40;
            --light-color: #f8f9fa;
        }
        
        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            background-color: #f5f7fa;
            margin: 0;
            padding: 20px;
        }
        
        .container {
            max-width: 800px;
            margin: 0 auto;
            background: white;
            padding: 30px;
            border-radius: 10px;
            box-shadow: 0 0 20px rgba(0,0,0,0.1);
        }
        
        .back-btn {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            margin-bottom: 20px;
            color: var(--primary-color);
            text-decoration: none;
            font-weight: 600;
            padding: 8px 15px;
            border-radius: 5px;
            transition: all 0.3s;
        }
        
        .back-btn:hover {
            background-color: #f0f7ff;
        }
        
        .status-header {
            text-align: center;
            margin-bottom: 30px;
        }
        
        .status-icon {
            font-size: 3rem;
            margin-bottom: 15px;
        }
        
        .status-pending {
            color: var(--warning-color);
        }
        
        .status-approved {
            color: var(--secondary-color);
        }
        
        .status-rejected {
            color: var(--danger-color);
        }
        
        .booking-card {
            display: flex;
            gap: 20px;
            margin-bottom: 30px;
        }
        
        .vehicle-image {
            width: 250px;
            height: 180px;
            object-fit: cover;
            border-radius: 8px;
        }
        
        .booking-details {
            flex: 1;
            padding: 15px;
            background: #f9f9f9;
            border-radius: 8px;
        }
        
        .detail-row {
            display: flex;
            justify-content: space-between;
            margin-bottom: 10px;
            padding-bottom: 10px;
            border-bottom: 1px solid #eee;
        }
        
        .detail-label {
            font-weight: 600;
            color: #666;
        }
        
        .detail-value {
            text-align: right;
        }
        
        .accessories-list {
            margin-top: 15px;
        }
        
        .accessory-item {
            display: flex;
            justify-content: space-between;
            margin-bottom: 5px;
        }
        
        .btn {
            display: inline-block;
            padding: 10px 20px;
            background: var(--primary-color);
            color: white;
            text-decoration: none;
            border-radius: 5px;
            margin-top: 20px;
            text-align: center;
            transition: all 0.3s;
        }
        
        .btn:hover {
            transform: translateY(-2px);
            box-shadow: 0 4px 8px rgba(0,0,0,0.1);
        }
        
        .btn-secondary {
            background: var(--dark-color);
            margin-left: 10px;
        }
        
        .payment-proof {
            margin-top: 20px;
            text-align: center;
        }
        
        .payment-proof img {
            max-width: 100%;
            max-height: 300px;
            border: 1px solid #ddd;
            border-radius: 8px;
        }
        
        .error-message {
            color: var(--danger-color);
            background: #fadbd8;
            padding: 15px;
            border-radius: 5px;
            margin-bottom: 20px;
            display: flex;
            align-items: center;
            gap: 10px;
        }
        
        @media (max-width: 768px) {
            .booking-card {
                flex-direction: column;
            }
            
            .vehicle-image {
                width: 100%;
            }
            
            .btn-container {
                display: flex;
                flex-direction: column;
                gap: 10px;
            }
            
            .btn, .btn-secondary {
                margin-left: 0;
                width: 100%;
            }
        }
    </style>
</head>
<body>
 <div class="container">
     <a href="<?= $vehicle_type === 'four_wheeler' ? 'four_wheelers.php' : 'two_wheelers.php' ?>" class="back-btn">
            <i class="fas fa-arrow-left"></i> Back to <?= $vehicle_type_name ?>s
        </a>
        <?php if (isset($error)): ?>
            <div class="error-message">
                <i class="fas fa-exclamation-circle"></i> <?= htmlspecialchars($error) ?>
            </div>
            <div class="btn-container" style="text-align: center; margin-top: 20px;">
                <a href="two_wheelers.php" class="btn">
                    <i class="fas fa-motorcycle"></i> Two-Wheelers
                </a>
                <a href="four_wheelers.php" class="btn btn-secondary">
                    <i class="fas fa-car"></i> Four-Wheelers
                </a>
            </div>
        <?php else: ?>
            <a href="<?= $vehicle_type === 'four_wheeler' ? 'four_wheelers.php' : 'two_wheelers.php' ?>" class="back-btn">
                <i class="fas fa-arrow-left"></i> Back to <?= $vehicle_type_name ?>s
            </a>
            
            <div class="status-header">
                <?php if ($booking['status'] == 'pending'): ?>
                    <i class="fas fa-hourglass-half status-icon status-pending"></i>
                    <h1><?= $vehicle_type_name ?> Booking Under Review</h1>
                    <p>Your booking is awaiting admin approval.</p>
                <?php elseif ($booking['status'] == 'confirmed'): ?>
                    <i class="fas fa-check-circle status-icon status-approved"></i>
                    <h1><?= $vehicle_type_name ?> Booking Approved!</h1>
                    <p>Your booking has been confirmed.</p>
                <?php else: ?>
                    <i class="fas fa-times-circle status-icon status-rejected"></i>
                    <h1><?= $vehicle_type_name ?> Booking Rejected</h1>
                    <p>Your booking request was not approved.</p>
                <?php endif; ?>
            </div>
            
            <div class="booking-card">
                <img src="<?= htmlspecialchars($booking['image_path'] ?? ($vehicle_type === 'four_wheeler' ? 'images/default-car.jpg' : 'images/default-bike.jpg')) ?>" 
                     alt="<?= htmlspecialchars($booking['make'] . ' ' . $booking['model']) ?>" 
                     class="vehicle-image">
                
                <div class="booking-details">
                    <h3><?= htmlspecialchars($booking['make'] . ' ' . $booking['model']) ?></h3>
                    <p><?= htmlspecialchars($booking['year']) ?> • <?= htmlspecialchars($booking['color']) ?></p>
                    
                    <div class="detail-row">
                        <span class="detail-label">Booking ID:</span>
                        <span class="detail-value">#<?= $booking['booking_id'] ?></span>
                    </div>
                    <div class="detail-row">
                        <span class="detail-label">Vehicle Type:</span>
                        <span class="detail-value"><?= $vehicle_type_name ?></span>
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
                        <span class="detail-label">Daily Rate:</span>
                        <span class="detail-value">₹<?= number_format($booking['daily_rate'], 2) ?></span>
                    </div>
                    
                    <?php if ($accessories_total > 0): ?>
                    <div class="accessories-list">
                        <h4>Accessories:</h4>
                        <?php if ($vehicle_type === 'four_wheeler'): ?>
                            <?php if ($booking['child_seat']): ?>
                            <div class="accessory-item">
                                <span>Child Seat:</span>
                                <span>₹<?= number_format($child_seat_charge, 2) ?></span>
                            </div>
                            <?php endif; ?>
                            <?php if ($booking['gps_navigation']): ?>
                            <div class="accessory-item">
                                <span>GPS Navigation:</span>
                                <span>₹<?= number_format($gps_charge, 2) ?></span>
                            </div>
                            <?php endif; ?>
                        <?php else: ?>
                            <?php if ($booking['helmet_request']): ?>
                            <div class="accessory-item">
                                <span>Helmet:</span>
                                <span>₹<?= number_format($helmet_charge, 2) ?></span>
                            </div>
                            <?php endif; ?>
                            <?php if ($booking['riding_gear_request']): ?>
                            <div class="accessory-item">
                                <span>Riding Gear:</span>
                                <span>₹<?= number_format($gear_charge, 2) ?></span>
                            </div>
                            <?php endif; ?>
                        <?php endif; ?>
                    </div>
                    <?php endif; ?>
                    
                    <div class="detail-row" style="font-weight: bold; border-bottom: none;">
                        <span>Total Amount:</span>
                        <span>₹<?= number_format($total_price, 2) ?></span>
                    </div>
                </div>
            </div>
            
            <?php if (!empty($booking['payment_screenshot'])): ?>
            <div class="payment-proof">
                <h3>Payment Proof</h3>
                <img src="<?= htmlspecialchars($booking['payment_screenshot']) ?>" alt="Payment proof">
            </div>
            <?php endif; ?>
            
            <?php if ($booking['status'] == 'rejected' && !empty($booking['admin_notes'])): ?>
                <div style="background: #f8d7da; color: #721c24; padding: 15px; border-radius: 5px; margin-top: 20px;">
                    <h4>Admin Note:</h4>
                    <p><?= htmlspecialchars($booking['admin_notes']) ?></p>
                </div>
            <?php endif; ?>
            
            <div class="btn-container" style="text-align: center; margin-top: 30px;">
                <a href="confirmed_bookings.php" class="btn">
                    <i class="fas fa-check-circle"></i> View Confirmed Bookings
                </a>
                <a href="<?= $vehicle_type === 'four_wheeler' ? 'four_wheelers.php' : 'two_wheelers.php' ?>" class="btn btn-secondary">
                    <i class="fas fa-<?= $vehicle_type === 'four_wheeler' ? 'car' : 'motorcycle' ?>"></i> Browse <?= $vehicle_type_name ?>s
                </a>
            </div>
        <?php endif; ?>
    </div>
</body>
</html>