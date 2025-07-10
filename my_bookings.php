<?php
session_start();
require_once 'config.php';

// Redirect if not logged in
if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit();
}

$bookings = [];
$error = null;
$success = null;
$highlight_id = $_GET['highlight'] ?? null;

try {
    $pdo = getDBConnection();
    
    // Check for success messages
    if (isset($_GET['success'])) {
        $success_messages = [
            'booking_created' => 'Your booking was submitted successfully!',
            'booking_cancelled' => 'Booking was cancelled successfully',
            'booking_updated' => 'Booking updated successfully'
        ];
        $success = $success_messages[$_GET['success']] ?? null;
    }

    // Enhanced query with vehicle details and status handling
    $stmt = $pdo->prepare("
        SELECT 
            b.*, 
            v.make, v.model, v.year, v.color, v.fuel_type, v.image_path, 
            v.daily_rate, v.registration_number, v.vehicle_type,
            u.phone, u.driving_license,
            CASE 
                WHEN b.status = 'pending' THEN 'Under Review' 
                WHEN b.status = 'approved' THEN 'Confirmed' 
                WHEN b.status = 'rejected' THEN 'Rejected'
                WHEN b.status = 'cancelled' THEN 'Cancelled'
                WHEN b.status = 'completed' THEN 'Completed'
                ELSE 'Processing'
            END as status_text,
            DATEDIFF(b.return_date, b.pickup_date) + 1 as days,
            b.pickup_date <= CURDATE() AND b.return_date >= CURDATE() as is_active_rental,
            b.return_date < CURDATE() as is_completed
        FROM bookings b
        JOIN vehicles v ON b.vehicle_id = v.vehicle_id
        JOIN users u ON b.user_id = u.user_id
        WHERE b.user_id = ?
        ORDER BY 
            CASE 
                WHEN b.status = 'approved' AND b.pickup_date > CURDATE() THEN 1
                WHEN b.status = 'approved' AND is_active_rental = 1 THEN 2
                WHEN b.status = 'pending' THEN 3
                WHEN b.status = 'completed' THEN 4
                WHEN b.status = 'rejected' THEN 5
                WHEN b.status = 'cancelled' THEN 6
                ELSE 7
            END,
            b.pickup_date ASC
    ");
    $stmt->execute([$_SESSION['user_id']]);
    $bookings = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
} catch (PDOException $e) {
    error_log("Database error: " . $e->getMessage());
    $error = "We encountered an error loading your bookings. Please try again later.";
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>My Bookings | <?= SITE_NAME ?></title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="css/styles.css">
    <style>
        /* Status Badges */
        .status-badge {
            padding: 4px 12px;
            border-radius: 20px;
            font-size: 0.8rem;
            font-weight: 600;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }
        .status-pending { background: #FFF3CD; color: #856404; }
        .status-confirmed { background: #D4EDDA; color: #155724; }
        .status-active { background: #CCE5FF; color: #004085; animation: pulse 2s infinite; }
        .status-completed { background: #E2E3E5; color: #383D41; }
        .status-rejected { background: #F8D7DA; color: #721C24; }
        .status-cancelled { background: #F8F9FA; color: #6C757D; border: 1px solid #DEE2E6; }
        
        @keyframes pulse {
            0% { box-shadow: 0 0 0 0 rgba(0,123,255,0.4); }
            70% { box-shadow: 0 0 0 8px rgba(0,123,255,0); }
            100% { box-shadow: 0 0 0 0 rgba(0,123,255,0); }
        }
        
        /* Booking Card Enhancements */
        .booking-card {
            transition: all 0.3s ease;
            border-left: 4px solid transparent;
        }
        .booking-card.confirmed { border-left-color: #28A745; }
        .booking-card.active { border-left-color: #007BFF; }
        .booking-card.pending { border-left-color: #FFC107; }
        .booking-card.completed { border-left-color: #6C757D; }
        
        /* Action Buttons */
        .btn-details { background: #6C757D; }
        .btn-details:hover { background: #5A6268; }
        .btn-pay { background: #17A2B8; }
        .btn-pay:hover { background: #138496; }
        .btn-extend { background: #FFC107; color: #212529; }
        .btn-extend:hover { background: #E0A800; }
        
        /* Countdown Timer */
        .countdown-timer {
            background: #343A40;
            color: white;
            padding: 3px 8px;
            border-radius: 4px;
            font-family: monospace;
        }
        
        /* Document Upload */
        .document-status {
            display: inline-flex;
            align-items: center;
            gap: 5px;
            font-size: 0.85rem;
        }
        .document-status.verified { color: #28A745; }
        .document-status.pending { color: #FFC107; }
        .document-status.rejected { color: #DC3545; }
        
        /* Responsive Adjustments */
        @media (max-width: 768px) {
            .booking-actions {
                flex-direction: column;
            }
            .action-buttons .btn {
                width: 100%;
                justify-content: center;
            }
        }
    </style>
</head>
<body>
    <?php include 'includes/header.php'; ?>
    
    <div class="container py-4">
        <div class="d-flex justify-content-between align-items-center mb-4">
            <h1 class="h2"><i class="fas fa-calendar-alt me-2"></i>My Bookings</h1>
            <a href="vehicles.php" class="btn btn-primary">
                <i class="fas fa-plus me-2"></i>New Booking
            </a>
        </div>
        
        <!-- Status Messages -->
        <?php if ($error): ?>
        <div class="alert alert-danger mb-4">
            <i class="fas fa-exclamation-circle me-2"></i>
            <?= htmlspecialchars($error) ?>
        </div>
        <?php endif; ?>
        
        <?php if ($success): ?>
        <div class="alert alert-success mb-4">
            <i class="fas fa-check-circle me-2"></i>
            <?= htmlspecialchars($success) ?>
        </div>
        <?php endif; ?>
        
        <!-- Booking Filters -->
        <div class="mb-4">
            <div class="btn-group" role="group">
                <button type="button" class="btn btn-outline-primary filter-btn active" data-filter="all">All</button>
                <button type="button" class="btn btn-outline-primary filter-btn" data-filter="upcoming">Upcoming</button>
                <button type="button" class="btn btn-outline-primary filter-btn" data-filter="active">Active</button>
                <button type="button" class="btn btn-outline-primary filter-btn" data-filter="pending">Pending</button>
                <button type="button" class="btn btn-outline-primary filter-btn" data-filter="completed">Completed</button>
            </div>
        </div>
        
        <!-- Bookings List -->
        <?php if (empty($bookings)): ?>
        <div class="card">
            <div class="card-body text-center py-5">
                <i class="fas fa-calendar-times fa-3x text-muted mb-3"></i>
                <h3 class="h4">No Bookings Found</h3>
                <p class="text-muted">You haven't made any bookings yet.</p>
                <a href="vehicles.php" class="btn btn-primary mt-3">
                    <i class="fas fa-motorcycle me-2"></i>Browse Vehicles
                </a>
            </div>
        </div>
        <?php else: ?>
        <div class="row g-4" id="bookingList">
            <?php foreach ($bookings as $booking): 
                $is_upcoming = strtotime($booking['pickup_date']) > time();
                $is_active = $booking['is_active_rental'];
                $is_completed = $booking['is_completed'];
                $status_class = strtolower($booking['status']);
                if ($is_active) $status_class = 'active';
                if ($is_completed && $booking['status'] === 'approved') $status_class = 'completed';
            ?>
            <div class="col-12 <?= ($highlight_id == $booking['booking_id']) ? 'highlight-booking' : '' ?>">
                <div class="card booking-card <?= $status_class ?>">
                    <div class="card-body">
                        <div class="row">
                            <!-- Vehicle Image -->
                            <div class="col-md-3 mb-3 mb-md-0">
                                <img src="<?= htmlspecialchars($booking['image_path'] ?? 'images/default-vehicle.jpg') ?>" 
                                     class="img-fluid rounded" 
                                     alt="<?= htmlspecialchars($booking['make'] . ' ' . $booking['model']) ?>">
                            </div>
                            
                            <!-- Booking Details -->
                            <div class="col-md-6">
                                <div class="d-flex flex-column h-100">
                                    <!-- Vehicle Info -->
                                    <div class="mb-2">
                                        <h3 class="h5 mb-1">
                                            <?= htmlspecialchars($booking['make'] . ' ' . $booking['model']) ?>
                                            <small class="text-muted">(<?= $booking['year'] ?>)</small>
                                        </h3>
                                        <div class="d-flex flex-wrap gap-2 mb-2">
                                            <span class="badge bg-secondary"><?= $booking['vehicle_type'] ?></span>
                                            <span class="badge bg-light text-dark"><?= $booking['color'] ?></span>
                                            <span class="badge bg-light text-dark"><?= $booking['fuel_type'] ?></span>
                                        </div>
                                    </div>
                                    
                                    <!-- Booking Status -->
                                    <div class="mb-2">
                                        <span class="status-badge status-<?= $status_class ?>">
                                            <?= $booking['status_text'] ?>
                                            <?php if ($is_active): ?>
                                            <span class="countdown-timer ms-2" 
                                                  data-end="<?= $booking['return_date'] ?>">
                                                <i class="fas fa-clock"></i>
                                                <span class="countdown-text"></span>
                                            </span>
                                            <?php endif; ?>
                                        </span>
                                        <?php if ($booking['status'] === 'rejected' && !empty($booking['admin_notes'])): ?>
                                        <div class="alert alert-warning mt-2 p-2 small">
                                            <i class="fas fa-info-circle me-2"></i>
                                            <?= htmlspecialchars($booking['admin_notes']) ?>
                                        </div>
                                        <?php endif; ?>
                                    </div>
                                    
                                    <!-- Rental Period -->
                                    <div class="mb-3">
                                        <div class="d-flex align-items-center gap-3">
                                            <div>
                                                <small class="text-muted d-block">Pickup</small>
                                                <strong><?= date('M j, Y', strtotime($booking['pickup_date'])) ?></strong>
                                            </div>
                                            <i class="fas fa-arrow-right text-muted"></i>
                                            <div>
                                                <small class="text-muted d-block">Return</small>
                                                <strong><?= date('M j, Y', strtotime($booking['return_date'])) ?></strong>
                                            </div>
                                            <div class="ms-auto">
                                                <small class="text-muted d-block">Duration</small>
                                                <strong><?= $booking['days'] ?> day<?= $booking['days'] > 1 ? 's' : '' ?></strong>
                                            </div>
                                        </div>
                                    </div>
                                    
                                    <!-- Payment Info -->
                                    <div class="mt-auto">
                                        <div class="d-flex justify-content-between">
                                            <div>
                                                <small class="text-muted d-block">Total Amount</small>
                                                <h4 class="h5 mb-0">₹<?= number_format($booking['total_price'], 2) ?></h4>
                                                <small class="text-muted">@ ₹<?= number_format($booking['daily_rate'], 2) ?>/day</small>
                                            </div>
                                            <div class="text-end">
                                                <small class="text-muted d-block">Payment</small>
                                                <span class="badge bg-<?= $booking['payment_status'] === 'paid' ? 'success' : 'warning' ?>">
                                                    <?= ucfirst($booking['payment_status']) ?>
                                                </span>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                            
                            <!-- Action Buttons -->
                            <div class="col-md-3">
                                <div class="d-flex flex-column h-100">
                                    <div class="booking-actions mt-md-0 mt-3">
                                        <div class="action-buttons d-grid gap-2">
                                            <a href="booking_details.php?id=<?= $booking['booking_id'] ?>" 
                                               class="btn btn-details">
                                               <i class="fas fa-eye me-2"></i>Details
                                            </a>
                                            
                                            <?php if ($booking['status'] === 'approved' && !$is_completed): ?>
                                                <?php if ($booking['payment_status'] !== 'paid'): ?>
                                                <a href="payment.php?booking_id=<?= $booking['booking_id'] ?>" 
                                                   class="btn btn-pay">
                                                   <i class="fas fa-credit-card me-2"></i>Make Payment
                                                </a>
                                                <?php endif; ?>
                                                
                                                <?php if ($is_upcoming): ?>
                                                <a href="cancel_booking.php?id=<?= $booking['booking_id'] ?>" 
                                                   class="btn btn-danger"
                                                   onclick="return confirm('Are you sure? Cancellation fees may apply.')">
                                                   <i class="fas fa-times me-2"></i>Cancel
                                                </a>
                                                <?php elseif ($is_active): ?>
                                                <a href="extend_booking.php?id=<?= $booking['booking_id'] ?>" 
                                                   class="btn btn-extend">
                                                   <i class="fas fa-calendar-plus me-2"></i>Extend
                                                </a>
                                                <?php endif; ?>
                                            <?php elseif ($booking['status'] === 'pending'): ?>
                                                <a href="cancel_booking.php?id=<?= $booking['booking_id'] ?>" 
                                                   class="btn btn-danger"
                                                   onclick="return confirm('Are you sure you want to cancel this booking?')">
                                                   <i class="fas fa-times me-2"></i>Cancel
                                                </a>
                                            <?php endif; ?>
                                            
                                            <?php if ($is_completed): ?>
                                                <a href="review.php?booking_id=<?= $booking['booking_id'] ?>" 
                                                   class="btn btn-primary">
                                                   <i class="fas fa-star me-2"></i>Leave Review
                                                </a>
                                            <?php endif; ?>
                                        </div>
                                    </div>
                                    
                                    <!-- Document Status -->
                                    <?php if ($booking['status'] === 'approved'): ?>
                                    <div class="mt-3 pt-3 border-top">
                                        <small class="text-muted d-block mb-1">Document Verification</small>
                                        <div class="document-status <?= $booking['documents_verified'] ? 'verified' : 'pending' ?>">
                                            <i class="fas <?= $booking['documents_verified'] ? 'fa-check-circle' : 'fa-hourglass-half' ?>"></i>
                                            <?= $booking['documents_verified'] ? 'Verified' : 'Pending Verification' ?>
                                        </div>
                                    </div>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            <?php endforeach; ?>
        </div>
        <?php endif; ?>
    </div>

    <?php include 'includes/footer.php'; ?>

    <script>
        // Filter bookings
        document.querySelectorAll('.filter-btn').forEach(btn => {
            btn.addEventListener('click', function() {
                // Update active button
                document.querySelectorAll('.filter-btn').forEach(b => b.classList.remove('active'));
                this.classList.add('active');
                
                const filter = this.dataset.filter;
                const bookings = document.querySelectorAll('.booking-card');
                
                bookings.forEach(booking => {
                    const status = booking.classList.contains('confirmed') ? 'upcoming' : 
                                 booking.classList.contains('active') ? 'active' :
                                 booking.classList.contains('pending') ? 'pending' :
                                 booking.classList.contains('completed') ? 'completed' : '';
                    
                    if (filter === 'all' || filter === status) {
                        booking.closest('.col-12').style.display = 'block';
                    } else {
                        booking.closest('.col-12').style.display = 'none';
                    }
                });
            });
        });
        
        // Countdown timer for active rentals
        function updateCountdowns() {
            document.querySelectorAll('.countdown-timer').forEach(timer => {
                const endDate = new Date(timer.dataset.end);
                const now = new Date();
                const diff = endDate - now;
                
                if (diff <= 0) {
                    timer.innerHTML = '<i class="fas fa-check-circle"></i> Completed';
                    return;
                }
                
                const days = Math.floor(diff / (1000 * 60 * 60 * 24));
                const hours = Math.floor((diff % (1000 * 60 * 60 * 24)) / (1000 * 60 * 60));
                const minutes = Math.floor((diff % (1000 * 60 * 60)) / (1000 * 60));
                
                timer.querySelector('.countdown-text').textContent = 
                    `${days}d ${hours}h ${minutes}m remaining`;
            });
        }
        
        // Initialize
        if (document.querySelector('.countdown-timer')) {
            updateCountdowns();
            setInterval(updateCountdowns, 60000);
        }
        
        // Highlight specific booking if requested
        <?php if ($highlight_id): ?>
        document.querySelector('.highlight-booking').scrollIntoView({
            behavior: 'smooth',
            block: 'center'
        });
        setTimeout(() => {
            document.querySelector('.highlight-booking').classList.add('animate__animated', 'animate__pulse');
        }, 500);
        <?php endif; ?>
    </script>
</body>
</html>