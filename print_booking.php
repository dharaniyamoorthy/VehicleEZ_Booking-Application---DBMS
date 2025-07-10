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
    die("Error: " . $e->getMessage());
}
?>
<!DOCTYPE html>
<html>
<head>
    <title>Booking Receipt | #<?= $booking_id ?></title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <style>
        @page {
            size: A4;
            margin: 15mm;
        }
        
        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            color: #333;
            line-height: 1.6;
            background: white;
            padding: 20px;
        }
        
        .print-container {
            max-width: 800px;
            margin: 0 auto;
        }
        
        .header {
            text-align: center;
            margin-bottom: 30px;
            padding-bottom: 20px;
            border-bottom: 2px solid #eee;
        }
        
        .logo {
            font-size: 24px;
            font-weight: bold;
            color: #4a6bff;
            margin-bottom: 10px;
        }
        
        .receipt-title {
            font-size: 20px;
            margin: 10px 0;
        }
        
        .status-badge {
            display: inline-block;
            padding: 5px 15px;
            background: #28a745;
            color: white;
            border-radius: 20px;
            font-weight: bold;
            font-size: 14px;
            margin-bottom: 20px;
        }
        
        .booking-summary {
            display: flex;
            gap: 30px;
            margin-bottom: 30px;
        }
        
        .vehicle-info {
            flex: 1;
        }
        
        .vehicle-image {
            width: 200px;
            height: 150px;
            object-fit: cover;
            border: 1px solid #ddd;
            border-radius: 5px;
        }
        
        .section {
            margin-bottom: 25px;
            page-break-inside: avoid;
        }
        
        .section-title {
            font-size: 18px;
            border-bottom: 1px solid #ddd;
            padding-bottom: 5px;
            margin-bottom: 15px;
        }
        
        .detail-row {
            display: flex;
            margin-bottom: 8px;
        }
        
        .detail-label {
            font-weight: 600;
            min-width: 150px;
        }
        
        .detail-value {
            flex: 1;
        }
        
        .total-row {
            font-weight: bold;
            font-size: 18px;
            margin-top: 10px;
            padding-top: 10px;
            border-top: 2px solid #eee;
        }
        
        .footer {
            text-align: center;
            margin-top: 40px;
            padding-top: 20px;
            border-top: 2px solid #eee;
            font-size: 14px;
            color: #777;
        }
        
        @media print {
            body {
                padding: 0;
            }
            
            .no-print {
                display: none;
            }
            
            a {
                text-decoration: none;
                color: inherit;
            }
        }
    </style>
</head>
<body>
    <div class="print-container">
        <div class="header">
            <div class="logo">VehicleEZ</div>
            <div class="receipt-title">Booking Receipt</div>
            <div class="status-badge">
                <i class="fas fa-check-circle"></i> CONFIRMED
            </div>
            <div>Booking ID: #<?= $booking['booking_id'] ?></div>
            <div>Date: <?= date('M j, Y', strtotime($booking['created_at'])) ?></div>
        </div>
        
        <div class="booking-summary">
            <div class="vehicle-info">
                <h3><?= htmlspecialchars($booking['make'] . ' ' . $booking['model']) ?></h3>
                <div class="detail-row">
                   <span class="detail-label">License Plate:</span>
                    <span class="detail-value"><?= htmlspecialchars($booking['license_plate']) ?></span>
                </div>
                <div class="detail-row">
                    <span class="detail-label">Daily Rate:</span>
                    <span class="detail-value">₹<?= number_format($booking['daily_rate'], 2) ?></span>
                </div>
            </div>
            <img src="<?= htmlspecialchars($booking['image_path']) ?>" alt="Vehicle Image" class="vehicle-image">
        </div>
        
        <div class="section">
            <h3 class="section-title">
                <i class="fas fa-calendar-alt"></i> Rental Period
            </h3>
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
            <div class="detail-row total-row">
                <span class="detail-label">Total Amount:</span>
                <span class="detail-value">₹<?= number_format($booking['total_price'], 2) ?></span>
            </div>
        </div>
        
        <div class="section">
            <h3 class="section-title">
                <i class="fas fa-user"></i> Customer Information
            </h3>
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
        
        <div class="footer">
            <p>Thank you for choosing VehicleEZ for your rental needs</p>
            <p>For any questions, please contact support@vehicleez.com</p>
            <p class="no-print">
                <button onclick="window.print()" style="padding: 8px 15px; background: #4a6bff; color: white; border: none; border-radius: 4px; cursor: pointer;">
                    <i class="fas fa-print"></i> Print Receipt
                </button>
                <button onclick="window.close()" style="padding: 8px 15px; background: #f8f9fa; color: #333; border: 1px solid #ddd; border-radius: 4px; cursor: pointer; margin-left: 10px;">
                    <i class="fas fa-times"></i> Close
                </button>
            </p>
        </div>
    </div>
    
    <script>
        // Auto-print when page loads (optional)
        window.onload = function() {
            setTimeout(function() {
                window.print();
            }, 500);
        };
    </script>
</body>
</html>