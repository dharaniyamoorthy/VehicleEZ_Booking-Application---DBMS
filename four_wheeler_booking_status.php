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
    header('Location: four_wheelers.php');
    exit();
}

try {
    $pdo = getDBConnection();
    
    // Get booking details with vehicle type verification
    $stmt = $pdo->prepare("SELECT b.*, v.make, v.model, v.year, v.color, v.image_path, v.daily_rate, v.vehicle_type
                          FROM bookings b
                          JOIN vehicles v ON b.vehicle_id = v.vehicle_id
                          WHERE b.booking_id = ? AND b.user_id = ?");
    $stmt->execute([$booking_id, $_SESSION['user_id']]);
    $booking = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$booking) {
        throw new Exception("Booking details not found for ID: $booking_id");
    }
    
    // Verify it's a four-wheeler booking
    if ($booking['vehicle_type'] !== 'four_wheeler') {
        header('Location: two_wheeler_booking_status.php?booking_id='.$booking_id);
        exit();
    }
    
    // Calculate days and additional charges
    $pickup = new DateTime($booking['pickup_date']);
    $return = new DateTime($booking['return_date']);
    $days = $pickup->diff($return)->days + 1;
    $child_seat_charge = $booking['child_seat'] ? 200 * $days : 0;
    $gps_charge = $booking['gps_navigation'] ? 150 * $days : 0;
    
} catch (PDOException $e) {
    $error = "Database error: " . $e->getMessage();
} catch (Exception $e) {
    $error = $e->getMessage();
}
?>

<!DOCTYPE html>
<html>
<head>
    <title>Four-Wheeler Booking Status</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <style>
        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            background-color: #f5f7fa;
            margin: 0;
            padding: 20px;
        }
        .container {
            max-width: 900px;
            margin: 0 auto;
            background: white;
            padding: 30px;
            border-radius: 10px;
            box-shadow: 0 0 20px rgba(0,0,0,0.1);
        }
        .status-header {
            display: flex;
            align-items: center;
            gap: 15px;
            margin-bottom: 20px;
        }
        .status-icon {
            font-size: 2.5rem;
        }
        .status-pending {
            color: #FFA500;
        }
        .status-approved {
            color: #28a745;
        }
        .status-rejected {
            color: #dc3545;
        }
        .vehicle-display {
            display: flex;
            gap: 30px;
            margin: 30px 0;
        }
        .vehicle-image {
            width: 350px;
            height: 220px;
            object-fit: cover;
            border-radius: 8px;
            box-shadow: 0 4px 10px rgba(0,0,0,0.1);
        }
        .booking-details {
            flex: 1;
        }
        .detail-section {
            margin-bottom: 25px;
        }
        .detail-title {
            font-size: 1.2rem;
            color: #2c3e50;
            margin-bottom: 15px;
            padding-bottom: 8px;
            border-bottom: 1px solid #eee;
        }
        .detail-row {
            display: flex;
            justify-content: space-between;
            margin-bottom: 12px;
            padding: 8px 0;
            border-bottom: 1px dashed #f0f0f0;
        }
        .detail-label {
            font-weight: 600;
            color: #555;
        }
        .detail-value {
            text-align: right;
        }
        .price-breakdown {
            background: #f9f9f9;
            padding: 15px;
            border-radius: 8px;
            margin-top: 10px;
        }
        .price-row {
            display: flex;
            justify-content: space-between;
            margin-bottom: 8px;
        }
        .total-row {
            font-weight: bold;
            font-size: 1.1rem;
            margin-top: 10px;
            padding-top: 10px;
            border-top: 2px solid #ddd;
        }
        .btn-group {
            display: flex;
            gap: 15px;
            margin-top: 30px;
        }
        .btn {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            padding: 12px 25px;
            background: #3498db;
            color: white;
            text-decoration: none;
            border-radius: 8px;
            font-weight: 600;
            transition: all 0.3s ease;
        }
        .btn:hover {
            transform: translateY(-2px);
            box-shadow: 0 4px 8px rgba(0,0,0,0.1);
        }
        .btn-secondary {
            background: #6c757d;
        }
        .btn-print {
            background: #2c3e50;
        }
        .admin-notes {
            background: #fff3cd;
            padding: 15px;
            border-left: 4px solid #ffc107;
            margin: 20px 0;
            border-radius: 4px;
        }
        @media print {
            .btn-group { display: none; }
            body { background: white; }
            .container { box-shadow: none; }
        }
    </style>
</head>
<body>
    <div class="container">
        <?php if (isset($error)): ?>
            <div class="error-message">
                <i class="fas fa-exclamation-circle"></i> <?= htmlspecialchars($error) ?>
            </div>
            <a href="four_wheelers.php" class="btn">Back to Four-Wheelers</a>
        <?php else: ?>
            <div class="status-header">
                <?php if ($booking['status'] == 'pending'): ?>
                    <i class="fas fa-hourglass-half status-icon status-pending"></i>
                    <div>
                        <h1>Booking Under Review</h1>
                        <p>Your four-wheeler booking is awaiting admin approval</p>
                    </div>
                <?php elseif ($booking['status'] == 'approved'): ?>
                    <i class="fas fa-check-circle status-icon status-approved"></i>
                    <div>
                        <h1>Booking Approved!</h1>
                        <p>Your four-wheeler is ready for pickup</p>
                    </div>
                <?php else: ?>
                    <i class="fas fa-times-circle status-icon status-rejected"></i>
                    <div>
                        <h1>Booking Rejected</h1>
                        <p>Please contact support for more information</p>
                    </div>
                <?php endif; ?>
            </div>
            
            <div class="vehicle-display">
                <img src="<?= htmlspecialchars($booking['image_path'] ?? 'images/default-car.jpg') ?>" 
                     alt="<?= htmlspecialchars($booking['make'] . ' ' . $booking['model']) ?>" 
                     class="vehicle-image">
                
                <div class="booking-details">
                    <div class="detail-section">
                        <h2 class="detail-title">Vehicle Details</h2>
                        <div class="detail-row">
                            <span class="detail-label">Vehicle:</span>
                            <span class="detail-value"><?= htmlspecialchars($booking['make'] . ' ' . $booking['model'] . ' (' . $booking['year'] . ')') ?></span>
                        </div>
                        <div class="detail-row">
                            <span class="detail-label">Color:</span>
                            <span class="detail-value"><?= htmlspecialchars($booking['color']) ?></span>
                        </div>
                        <div class="detail-row">
                            <span class="detail-label">Daily Rate:</span>
                            <span class="detail-value">₹<?= number_format($booking['daily_rate'], 2) ?></span>
                        </div>
                    </div>
                    
                    <div class="detail-section">
                        <h2 class="detail-title">Booking Information</h2>
                        <div class="detail-row">
                            <span class="detail-label">Booking ID:</span>
                            <span class="detail-value">#<?= $booking_id ?></span>
                        </div>
                        <div class="detail-row">
                            <span class="detail-label">Pickup Date:</span>
                            <span class="detail-value"><?= date('F j, Y', strtotime($booking['pickup_date'])) ?></span>
                        </div>
                        <div class="detail-row">
                            <span class="detail-label">Return Date:</span>
                            <span class="detail-value"><?= date('F j, Y', strtotime($booking['return_date'])) ?></span>
                        </div>
                        <div class="detail-row">
                            <span class="detail-label">Total Days:</span>
                            <span class="detail-value"><?= $days ?></span>
                        </div>
                    </div>
                </div>
            </div>
            
            <div class="detail-section">
                <h2 class="detail-title">Payment Summary</h2>
                <div class="price-breakdown">
                    <div class="price-row">
                        <span>Base Rate (<?= $days ?> days × ₹<?= number_format($booking['daily_rate'], 2) ?>):</span>
                        <span>₹<?= number_format($days * $booking['daily_rate'], 2) ?></span>
                    </div>
                    <?php if ($booking['child_seat']): ?>
                    <div class="price-row">
                        <span>Child Seat (<?= $days ?> days × ₹200):</span>
                        <span>₹<?= number_format($child_seat_charge, 2) ?></span>
                    </div>
                    <?php endif; ?>
                    <?php if ($booking['gps_navigation']): ?>
                    <div class="price-row">
                        <span>GPS Navigation (<?= $days ?> days × ₹150):</span>
                        <span>₹<?= number_format($gps_charge, 2) ?></span>
                    </div>
                    <?php endif; ?>
                    <div class="price-row total-row">
                        <span>Total Amount:</span>
                        <span>₹<?= number_format($booking['total_price'], 2) ?></span>
                    </div>
                </div>
            </div>
            
            <?php if ($booking['status'] == 'rejected' && !empty($booking['admin_notes'])): ?>
                <div class="admin-notes">
                    <h3><i class="fas fa-info-circle"></i> Admin Notes</h3>
                    <p><?= htmlspecialchars($booking['admin_notes']) ?></p>
                </div>
            <?php endif; ?>
            
            <div class="btn-group">
                <?php if ($booking['status'] == 'approved'): ?>
                    <a href="confirmed_bookings.php" class="btn">
                        <i class="fas fa-calendar-check"></i> View All Bookings
                    </a>
                <?php else: ?>
                    <a href="four_wheeler_booking_status.php?booking_id=<?= $booking_id ?>" class="btn">
                        <i class="fas fa-sync-alt"></i> Refresh Status
                    </a>
                <?php endif; ?>
                <a href="four_wheelers.php" class="btn btn-secondary">
                    <i class="fas fa-car"></i> Browse Four-Wheelers
                </a>
                <button onclick="window.print()" class="btn btn-print">
                    <i class="fas fa-print"></i> Print Details
                </button>
            </div>
        <?php endif; ?>
    </div>
</body>
</html>