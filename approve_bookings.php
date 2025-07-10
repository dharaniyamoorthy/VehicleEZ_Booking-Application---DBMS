<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);
session_start();
require_once 'config.php';

// Verify admin role
if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'admin') {
    header('Location: login.php');
    exit();
}

$pdo = getDBConnection();

// Handle approval/rejection
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        $booking_id = $_POST['booking_id'];
        $action = $_POST['action']; // 'approve' or 'reject'
        
        if ($action === 'approve') {
            // Update booking status
            $stmt = $pdo->prepare("UPDATE bookings SET status = 'confirmed' WHERE booking_id = ?");
            $stmt->execute([$booking_id]);
            
            // Mark vehicle as booked
            $stmt = $pdo->prepare("UPDATE vehicles SET status = 'booked' WHERE vehicle_id = 
                                  (SELECT vehicle_id FROM bookings WHERE booking_id = ?)");
            $stmt->execute([$booking_id]);
            
            $_SESSION['success'] = "Booking #$booking_id approved successfully";
        } 
        elseif ($action === 'reject') {
            $stmt = $pdo->prepare("UPDATE bookings SET status = 'rejected' WHERE booking_id = ?");
            $stmt->execute([$booking_id]);
            
            $_SESSION['success'] = "Booking #$booking_id rejected";
        }
        
        header("Location: approve_bookings.php");
        exit();
    } catch (PDOException $e) {
        $_SESSION['error'] = "Database error: " . $e->getMessage();
    }
}

// Get pending bookings with additional details
try {
    $stmt = $pdo->prepare("
        SELECT b.*, u.full_name, u.email, u.phone, 
               v.make, v.model, v.daily_rate, v.image_path,
               DATEDIFF(b.return_date, b.pickup_date) + 1 as days,
               (SELECT COUNT(*) FROM bookings b2 
                WHERE b2.vehicle_id = b.vehicle_id 
                AND b2.status = 'confirmed'
                AND (
                    (b2.pickup_date BETWEEN b.pickup_date AND b.return_date)
                    OR (b2.return_date BETWEEN b.pickup_date AND b.return_date)
                )) as overlapping_bookings
        FROM bookings b
        JOIN users u ON b.user_id = u.user_id
        JOIN vehicles v ON b.vehicle_id = v.vehicle_id
        WHERE b.status = 'pending'
        ORDER BY b.created_at DESC
    ");
    $stmt->execute();
    $pending_bookings = $stmt->fetchAll();
} catch (PDOException $e) {
    $error = "Database error: " . $e->getMessage();
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Approve Bookings | VehicleEZ</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <style>
        :root {
            --primary-color: #3498db;
            --secondary-color: #2ecc71;
            --danger-color: #e74c3c;
            --warning-color: #f39c12;
            --dark-color: #2c3e50;
            --light-color: #ecf0f1;
            --text-color: #333;
            --border-radius: 8px;
            --box-shadow: 0 2px 10px rgba(0,0,0,0.1);
            --admin-color: #6c5ce7;
            --brand-color: #3a86ff;
        }
        
        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            margin: 0;
            padding: 0;
            background-color: #f5f7fa;
            color: var(--text-color);
            line-height: 1.6;
        }
        
        .dashboard-header {
            background-color: white;
            padding: 20px;
            box-shadow: var(--box-shadow);
            display: flex;
            justify-content: space-between;
            align-items: center;
            position: sticky;
            top: 0;
            z-index: 100;
        }
        
        .brand-title {
            display: flex;
            flex-direction: column;
        }
        
        .brand-name {
            font-size: 24px;
            font-weight: 700;
            color: var(--brand-color);
            margin: 0;
        }
        
        .brand-tagline {
            font-size: 14px;
            color: #7f8c8d;
            margin: 0;
            font-weight: 400;
        }
        
        .header-right {
            display: flex;
            align-items: center;
            gap: 20px;
        }
        
        .admin-badge {
            background-color: var(--admin-color);
            color: white;
            padding: 5px 12px;
            border-radius: 20px;
            font-size: 14px;
            display: inline-flex;
            align-items: center;
            gap: 8px;
        }
        
        .container {
            max-width: 1200px;
            margin: 0 auto;
            padding: 20px;
        }
        
        .dashboard-section {
            background-color: white;
            border-radius: var(--border-radius);
            box-shadow: var(--box-shadow);
            padding: 25px;
            margin-bottom: 30px;
        }
        
        .section-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 25px;
            padding-bottom: 15px;
            border-bottom: 1px solid #eee;
        }
        
        .section-header h2 {
            margin: 0;
            color: var(--dark-color);
            display: flex;
            align-items: center;
            gap: 10px;
        }
        
        .alert {
            padding: 15px;
            border-radius: var(--border-radius);
            margin-bottom: 25px;
            font-weight: 500;
        }
        
        .alert-success {
            background-color: #d4edda;
            color: #155724;
            border-left: 4px solid var(--secondary-color);
        }
        
        .alert-danger {
            background-color: #f8d7da;
            color: #721c24;
            border-left: 4px solid var(--danger-color);
        }
        
        .no-pending {
            text-align: center;
            padding: 40px;
            color: #7f8c8d;
        }
        
        .no-pending i {
            font-size: 3rem;
            margin-bottom: 15px;
            color: var(--secondary-color);
        }
        
        .booking-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(350px, 1fr));
            gap: 20px;
        }
        
        .booking-card {
            background: white;
            border-radius: var(--border-radius);
            box-shadow: var(--box-shadow);
            overflow: hidden;
            transition: all 0.3s ease;
            border: 1px solid #eee;
        }
        
        .booking-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 10px 20px rgba(0,0,0,0.1);
        }
        
        .booking-header {
            background-color: var(--primary-color);
            color: white;
            padding: 15px;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        
        .booking-id {
            font-weight: bold;
            font-size: 1.1rem;
        }
        
        .booking-status {
            background-color: var(--warning-color);
            color: white;
            padding: 3px 10px;
            border-radius: 20px;
            font-size: 0.8rem;
            font-weight: bold;
        }
        
        .booking-body {
            padding: 20px;
        }
        
        .vehicle-image {
            width: 100%;
            height: 180px;
            object-fit: cover;
            border-radius: var(--border-radius);
            margin-bottom: 15px;
        }
        
        .detail-row {
            display: flex;
            margin-bottom: 10px;
        }
        
        .detail-label {
            font-weight: 600;
            min-width: 100px;
            color: #7f8c8d;
        }
        
        .detail-value {
            flex: 1;
        }
        
        .customer-info {
            background-color: #f9f9f9;
            padding: 15px;
            border-radius: var(--border-radius);
            margin: 15px 0;
        }
        
        .payment-proof {
            margin: 15px 0;
        }
        
        .payment-proof img {
            width: 100%;
            border-radius: var(--border-radius);
            border: 1px solid #eee;
            margin-top: 10px;
            cursor: pointer;
            transition: transform 0.3s;
        }
        
        .payment-proof img:hover {
            transform: scale(1.02);
        }
        
        .action-buttons {
            display: flex;
            gap: 10px;
            margin-top: 20px;
        }
        
        .btn {
            padding: 10px 15px;
            border-radius: var(--border-radius);
            font-weight: 500;
            cursor: pointer;
            border: none;
            transition: all 0.3s;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 8px;
            flex: 1;
        }
        
        .btn-approve {
            background-color: var(--secondary-color);
            color: white;
        }
        
        .btn-approve:hover {
            background-color: #27ae60;
        }
        
        .btn-reject {
            background-color: var(--danger-color);
            color: white;
        }
        
        .btn-reject:hover {
            background-color: #c0392b;
        }
        
        .availability-badge {
            background-color: #f39c12;
            color: white;
            padding: 3px 8px;
            border-radius: 4px;
            font-size: 0.8rem;
            margin-left: 10px;
        }
        
        .availability-badge.available {
            background-color: var(--secondary-color);
        }
        
        .availability-badge.unavailable {
            background-color: var(--danger-color);
        }
        
        /* Modal for image preview */
        .modal {
            display: none;
            position: fixed;
            z-index: 1000;
            left: 0;
            top: 0;
            width: 100%;
            height: 100%;
            background-color: rgba(0,0,0,0.8);
            overflow: auto;
        }
        
        .modal-content {
            display: block;
            margin: 5% auto;
            max-width: 90%;
            max-height: 90%;
        }
        
        .close {
            position: absolute;
            top: 20px;
            right: 30px;
            color: white;
            font-size: 35px;
            font-weight: bold;
            cursor: pointer;
        }
        
        #live-date-time {
            font-size: 14px;
            color: var(--dark-color);
        }
        
        @media (max-width: 768px) {
            .booking-grid {
                grid-template-columns: 1fr;
            }
            
            .action-buttons {
                flex-direction: column;
            }
            
            .dashboard-header {
                flex-direction: column;
                align-items: flex-start;
                gap: 15px;
            }
            
            .header-right {
                width: 100%;
                justify-content: space-between;
            }
        }
    </style>
</head>
<body>
    <!-- Dashboard Header (replaced the include with direct content) -->
    <div class="dashboard-header">
        <div class="brand-title">
            <h1 class="brand-name">VehicleEZ</h1>
            <p class="brand-tagline">Admin Dashboard</p>
        </div>
        <div class="header-right">
            <div id="live-date-time">
                <?php echo date('l, F j, Y | H:i:s'); ?>
            </div>
            <div>
                <span><?php echo htmlspecialchars($_SESSION['username'] ?? 'Admin'); ?></span>
                <span class="admin-badge"><i class="fas fa-shield-alt"></i> ADMIN</span>
            </div>
        </div>
    </div>
    
    <div class="container">
        <div class="dashboard-section">
            <div class="section-header">
                <h2><i class="fas fa-clipboard-check"></i> Booking Approvals</h2>
                <div>
                    <span class="admin-badge">
                        <i class="fas fa-hourglass-half"></i> <?= count($pending_bookings) ?> Pending
                    </span>
                </div>
            </div>
            
            <?php if (isset($_SESSION['success'])): ?>
                <div class="alert alert-success">
                    <i class="fas fa-check-circle"></i> <?= $_SESSION['success'] ?>
                </div>
                <?php unset($_SESSION['success']); ?>
            <?php endif; ?>
            
            <?php if (isset($error)): ?>
                <div class="alert alert-danger">
                    <i class="fas fa-exclamation-circle"></i> <?= $error ?>
                </div>
            <?php endif; ?>
            
            <?php if (empty($pending_bookings)): ?>
                <div class="no-pending">
                    <i class="fas fa-check-circle"></i>
                    <h3>All Caught Up!</h3>
                    <p>There are currently no bookings waiting for approval.</p>
                    <a href="dashboard.php" class="btn" style="margin-top: 20px;">
                        <i class="fas fa-arrow-left"></i> Back to Dashboard
                    </a>
                </div>
            <?php else: ?>
                <div class="booking-grid">
                    <?php foreach ($pending_bookings as $booking): 
                        $is_available = $booking['overlapping_bookings'] == 0;
                    ?>
                    <div class="booking-card">
                        <div class="booking-header">
                            <span class="booking-id">Booking #<?= $booking['booking_id'] ?></span>
                            <span class="booking-status">PENDING</span>
                        </div>
                        
                        <div class="booking-body">
                            <img src="<?= htmlspecialchars($booking['image_path'] ?? 'images/default-vehicle.jpg') ?>" 
                                 alt="<?= htmlspecialchars($booking['make'] . ' ' . $booking['model']) ?>" 
                                 class="vehicle-image">
                            
                            <div class="detail-row">
                                <span class="detail-label">Vehicle:</span>
                                <span class="detail-value">
                                    <?= htmlspecialchars($booking['make'] . ' ' . $booking['model']) ?>
                                    <span class="availability-badge <?= $is_available ? 'available' : 'unavailable' ?>">
                                        <?= $is_available ? 'Available' : 'Conflict' ?>
                                    </span>
                                </span>
                            </div>
                            
                            <div class="detail-row">
                                <span class="detail-label">Duration:</span>
                                <span class="detail-value">
                                    <?= date('M j, Y', strtotime($booking['pickup_date'])) ?> - 
                                    <?= date('M j, Y', strtotime($booking['return_date'])) ?>
                                    (<?= $booking['days'] ?> days)
                                </span>
                            </div>
                            
                            <div class="detail-row">
                                <span class="detail-label">Rate:</span>
                                <span class="detail-value">₹<?= number_format($booking['daily_rate'], 2) ?>/day</span>
                            </div>
                            
                            <div class="detail-row">
                                <span class="detail-label">Total:</span>
                                <span class="detail-value">₹<?= number_format($booking['total_price'], 2) ?></span>
                            </div>
                            
                            <div class="customer-info">
                                <div class="detail-row">
                                    <span class="detail-label">Customer:</span>
                                    <span class="detail-value"><?= htmlspecialchars($booking['full_name']) ?></span>
                                </div>
                                <div class="detail-row">
                                    <span class="detail-label">Contact:</span>
                                    <span class="detail-value">
                                        <?= htmlspecialchars($booking['email']) ?><br>
                                        <?= htmlspecialchars($booking['phone']) ?>
                                    </span>
                                </div>
                            </div>
                            
                            <div class="payment-proof">
                                <p><strong>Payment Proof:</strong></p>
                                <img src="<?= htmlspecialchars($booking['payment_screenshot']) ?>" 
                                     alt="Payment proof for booking #<?= $booking['booking_id'] ?>"
                                     onclick="openModal(this)">
                            </div>
                            
                            <form method="POST" class="action-buttons">
                                <input type="hidden" name="booking_id" value="<?= $booking['booking_id'] ?>">
                                <button type="submit" name="action" value="approve" class="btn btn-approve" 
                                    <?= !$is_available ? 'disabled title="Cannot approve due to scheduling conflict"' : '' ?>>
                                    <i class="fas fa-check"></i> Approve
                                </button>
                                <button type="submit" name="action" value="reject" class="btn btn-reject">
                                    <i class="fas fa-times"></i> Reject
                                </button>
                            </form>
                        </div>
                    </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </div>
    </div>
    
    <!-- Modal for image preview -->
    <div id="imageModal" class="modal">
        <span class="close" onclick="closeModal()">&times;</span>
        <img class="modal-content" id="modalImage">
    </div>
    
    <script>
        // Image modal functionality
        function openModal(img) {
            const modal = document.getElementById('imageModal');
            const modalImg = document.getElementById('modalImage');
            modal.style.display = "block";
            modalImg.src = img.src;
        }
        
        function closeModal() {
            document.getElementById('imageModal').style.display = "none";
        }
        
        // Close modal when clicking outside the image
        window.onclick = function(event) {
            const modal = document.getElementById('imageModal');
            if (event.target == modal) {
                closeModal();
            }
        }
        
        // Auto-refresh the page every 60 seconds to check for new bookings
        setTimeout(function() {
            window.location.reload();
        }, 60000);
        
        // Update live clock
        function updateClock() {
            const now = new Date();
            const dateStr = now.toLocaleDateString([], { weekday: 'long', year: 'numeric', month: 'long', day: 'numeric' });
            const timeStr = now.toLocaleTimeString([], { hour: '2-digit', minute: '2-digit', second: '2-digit' });
            document.getElementById('live-date-time').textContent = dateStr + ' | ' + timeStr;
        }
        setInterval(updateClock, 1000);
        updateClock();
    </script>
</body>
</html>