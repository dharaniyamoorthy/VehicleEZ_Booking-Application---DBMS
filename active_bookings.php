<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);

// Only start session if not already started
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once 'config.php';

// Verify admin role
if (!isset($_SESSION['user_id']) || !isset($_SESSION['role']) || $_SESSION['role'] !== 'admin') {
    header('Location: login.php');
    exit();
}

// Get active bookings with error handling
$active_bookings = [];
$error = null;

try {
    $pdo = getDBConnection();
    
    // Verify connection
    if (!$pdo) {
        throw new Exception("Could not connect to database");
    }

    $stmt = $pdo->prepare("
        SELECT b.booking_id, b.start_date, b.end_date, b.total_cost, b.booking_status,
               u.user_id, u.full_name as customer_name, u.email, u.phone,
               v.vehicle_id, v.make, v.model, v.license_plate, v.image_path,
               DATEDIFF(b.end_date, b.start_date) as duration_days
        FROM bookings b
        JOIN users u ON b.user_id = u.user_id
        JOIN vehicles v ON b.vehicle_id = v.vehicle_id
        WHERE b.booking_status = 'confirmed'
        ORDER BY b.start_date DESC
    ");
    
    if (!$stmt->execute()) {
        throw new Exception("Failed to execute query");
    }
    
    $active_bookings = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
} catch (PDOException $e) {
    $error = "Database error: " . $e->getMessage();
    error_log($error);
} catch (Exception $e) {
    $error = "System error: " . $e->getMessage();
    error_log($error);
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Active Bookings | VehicleEZ</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/flatpickr/dist/flatpickr.min.css">
    <style>
        :root {
            --primary-color: #4361ee;
            --secondary-color: #3f37c9;
            --success-color: #4cc9f0;
            --danger-color: #f72585;
            --warning-color: #f8961e;
            --light-color: #f8f9fa;
            --dark-color: #212529;
        }
        
        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            background-color: #f5f7fa;
            margin: 0;
            padding: 0;
            color: #333;
        }
        
        .content-container {
            padding: 2rem;
            max-width: 1400px;
            margin: 0 auto;
        }
        
        .page-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 2rem;
            flex-wrap: wrap;
            gap: 1rem;
        }
        
        .page-title {
            font-size: 1.8rem;
            color: var(--dark-color);
            margin: 0;
            display: flex;
            align-items: center;
            gap: 0.8rem;
        }
        
        .btn {
            padding: 0.6rem 1.2rem;
            border-radius: 6px;
            font-weight: 500;
            cursor: pointer;
            transition: all 0.3s ease;
            text-decoration: none;
            display: inline-flex;
            align-items: center;
            gap: 0.5rem;
            border: none;
        }
        
        .btn-primary {
            background-color: var(--primary-color);
            color: white;
        }
        
        .btn-primary:hover {
            background-color: var(--secondary-color);
            transform: translateY(-2px);
            box-shadow: 0 4px 8px rgba(0,0,0,0.1);
        }
        
        .error-message {
            color: var(--danger-color);
            background-color: #fde8e8;
            padding: 1rem;
            border-radius: 6px;
            margin-bottom: 2rem;
            border-left: 4px solid var(--danger-color);
            display: flex;
            align-items: center;
            gap: 0.8rem;
        }
        
        .bookings-table {
            width: 100%;
            border-collapse: collapse;
            background-color: white;
            border-radius: 8px;
            overflow: hidden;
            box-shadow: 0 2px 10px rgba(0,0,0,0.05);
        }
        
        .bookings-table th {
            background-color: var(--primary-color);
            color: white;
            padding: 1rem;
            text-align: left;
        }
        
        .bookings-table td {
            padding: 1rem;
            border-bottom: 1px solid #eee;
            vertical-align: middle;
        }
        
        .bookings-table tr:last-child td {
            border-bottom: none;
        }
        
        .bookings-table tr:hover {
            background-color: #f8f9fa;
        }
        
        .status-badge {
            padding: 0.4rem 0.8rem;
            border-radius: 20px;
            font-size: 0.85rem;
            font-weight: 500;
        }
        
        .status-confirmed {
            background-color: #e3fafc;
            color: #15aabf;
        }
        
        .action-btn {
            padding: 0.4rem 0.8rem;
            border-radius: 4px;
            font-size: 0.85rem;
            cursor: pointer;
            transition: all 0.2s ease;
            text-decoration: none;
            display: inline-flex;
            align-items: center;
            gap: 0.3rem;
            margin-right: 0.5rem;
        }
        
        .btn-view {
            background-color: #e3fafc;
            color: #15aabf;
            border: 1px solid #99e9f2;
        }
        
        .btn-view:hover {
            background-color: #c5f6fa;
        }
        
        .btn-cancel {
            background-color: #fff5f5;
            color: #fa5252;
            border: 1px solid #ffc9c9;
        }
        
        .btn-cancel:hover {
            background-color: #ffe3e3;
        }
        
        .no-bookings {
            text-align: center;
            padding: 3rem;
            background-color: white;
            border-radius: 8px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.05);
        }
        
        .no-bookings h3 {
            color: var(--dark-color);
            margin-bottom: 0.5rem;
        }
        
        .no-bookings p {
            color: #6c757d;
            margin-bottom: 1.5rem;
        }
        
        .vehicle-image {
            width: 60px;
            height: 40px;
            object-fit: cover;
            border-radius: 4px;
            margin-right: 0.8rem;
        }
        
        .customer-info {
            display: flex;
            flex-direction: column;
        }
        
        .customer-email {
            font-size: 0.85rem;
            color: #6c757d;
        }
        
        .filters-container {
            display: flex;
            gap: 1rem;
            margin-bottom: 1.5rem;
            flex-wrap: wrap;
        }
        
        .filter-group {
            display: flex;
            flex-direction: column;
            min-width: 200px;
        }
        
        .filter-label {
            font-size: 0.85rem;
            margin-bottom: 0.3rem;
            color: #6c757d;
        }
        
        .filter-input {
            padding: 0.5rem;
            border: 1px solid #ddd;
            border-radius: 4px;
            font-size: 0.9rem;
        }
        
        .badge {
            display: inline-block;
            padding: 0.25rem 0.5rem;
            border-radius: 4px;
            font-size: 0.75rem;
            font-weight: 500;
        }
        
        .badge-info {
            background-color: #e3fafc;
            color: #15aabf;
        }
        
        .badge-warning {
            background-color: #fff3bf;
            color: #f08c00;
        }
        
        @media (max-width: 768px) {
            .bookings-table {
                display: block;
                overflow-x: auto;
            }
            
            .page-header {
                flex-direction: column;
                align-items: flex-start;
            }
        }
        
        /* Modal styles */
        .modal {
            display: none;
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background-color: rgba(0,0,0,0.5);
            z-index: 1000;
            justify-content: center;
            align-items: center;
        }
        
        .modal-content {
            background-color: white;
            padding: 2rem;
            border-radius: 8px;
            width: 90%;
            max-width: 500px;
            max-height: 80vh;
            overflow-y: auto;
        }
        
        .modal-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 1.5rem;
        }
        
        .modal-title {
            font-size: 1.5rem;
            margin: 0;
        }
        
        .close-modal {
            background: none;
            border: none;
            font-size: 1.5rem;
            cursor: pointer;
            color: #6c757d;
        }
        
        .modal-footer {
            display: flex;
            justify-content: flex-end;
            gap: 1rem;
            margin-top: 1.5rem;
        }
        
        /* Loading spinner */
        .spinner {
            display: inline-block;
            width: 1.5rem;
            height: 1.5rem;
            border: 3px solid rgba(0,0,0,0.1);
            border-radius: 50%;
            border-top-color: var(--primary-color);
            animation: spin 1s ease-in-out infinite;
        }
        
        @keyframes spin {
            to { transform: rotate(360deg); }
        }
    </style>
</head>
<body>
    <?php include 'header.php'; ?>
    
    <div class="content-container">
        <div class="page-header">
            <h1 class="page-title"><i class="fas fa-calendar-check"></i> Active Bookings</h1>
            <div>
                <a href="bookings.php?action=add" class="btn btn-primary">
                    <i class="fas fa-plus"></i> Create New Booking
                </a>
                <button id="export-btn" class="btn" style="background-color: #e9ecef; color: var(--dark-color); margin-left: 0.5rem;">
                    <i class="fas fa-file-export"></i> Export
                </button>
            </div>
        </div>
        
        <div class="filters-container">
            <div class="filter-group">
                <label class="filter-label">Search</label>
                <input type="text" id="search-input" class="filter-input" placeholder="Search bookings...">
            </div>
            <div class="filter-group">
                <label class="filter-label">Date Range</label>
                <input type="text" id="date-range" class="filter-input" placeholder="Select date range">
            </div>
            <div class="filter-group">
                <label class="filter-label">Status</label>
                <select id="status-filter" class="filter-input">
                    <option value="">All Statuses</option>
                    <option value="confirmed" selected>Confirmed</option>
                    <option value="completed">Completed</option>
                    <option value="cancelled">Cancelled</option>
                </select>
            </div>
        </div>
        
        <?php if ($error): ?>
            <div class="error-message">
                <i class="fas fa-exclamation-circle"></i> <?php echo htmlspecialchars($error); ?>
            </div>
        <?php elseif (count($active_bookings) > 0): ?>
            <div class="table-responsive">
                <table class="bookings-table">
                    <thead>
                        <tr>
                            <th>Booking ID</th>
                            <th>Customer</th>
                            <th>Vehicle</th>
                            <th>Dates</th>
                            <th>Duration</th>
                            <th>Total Cost</th>
                            <th>Status</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($active_bookings as $booking): ?>
                            <tr>
                                <td>#<?php echo htmlspecialchars($booking['booking_id']); ?></td>
                                <td>
                                    <div class="customer-info">
                                        <strong><?php echo htmlspecialchars($booking['customer_name']); ?></strong>
                                        <span class="customer-email"><?php echo htmlspecialchars($booking['email']); ?></span>
                                    </div>
                                </td>
                                <td>
                                    <div style="display: flex; align-items: center;">
                                        <?php if (!empty($booking['image_path'])): ?>
                                            <img src="<?php echo htmlspecialchars($booking['image_path']); ?>" alt="Vehicle" class="vehicle-image">
                                        <?php else: ?>
                                            <div class="vehicle-image" style="background-color: #eee; display: flex; align-items: center; justify-content: center;">
                                                <i class="fas fa-car" style="color: #aaa;"></i>
                                            </div>
                                        <?php endif; ?>
                                        <div>
                                            <?php echo htmlspecialchars($booking['make'] . ' ' . $booking['model']); ?>
                                            <div><small class="badge badge-info"><?php echo htmlspecialchars($booking['license_plate']); ?></small></div>
                                        </div>
                                    </div>
                                </td>
                                <td>
                                    <?php echo date('M j, Y', strtotime($booking['start_date'])); ?> - 
                                    <?php echo date('M j, Y', strtotime($booking['end_date'])); ?>
                                </td>
                                <td>
                                    <span class="badge badge-warning"><?php echo htmlspecialchars($booking['duration_days']); ?> days</span>
                                </td>
                                <td>$<?php echo number_format($booking['total_cost'], 2); ?></td>
                                <td>
                                    <span class="status-badge status-confirmed">
                                        <?php echo ucfirst($booking['booking_status']); ?>
                                    </span>
                                </td>
                                <td>
                                    <a href="booking_details.php?id=<?php echo $booking['booking_id']; ?>" class="action-btn btn-view">
                                        <i class="fas fa-eye"></i> View
                                    </a>
                                    <button class="action-btn btn-cancel" onclick="showCancelModal(<?php echo $booking['booking_id']; ?>, '<?php echo htmlspecialchars($booking['customer_name']); ?>')">
                                        <i class="fas fa-times"></i> Cancel
                                    </button>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
            
            <div class="pagination" style="margin-top: 1.5rem; display: flex; justify-content: center;">
                <button class="btn" style="margin-right: 0.5rem;"><i class="fas fa-chevron-left"></i></button>
                <span style="padding: 0.5rem 1rem;">Page 1 of 1</span>
                <button class="btn"><i class="fas fa-chevron-right"></i></button>
            </div>
        <?php else: ?>
            <div class="no-bookings">
                <i class="fas fa-calendar-times fa-3x" style="color: #ddd; margin-bottom: 15px;"></i>
                <h3>No Active Bookings Found</h3>
                <p>There are currently no confirmed bookings in the system.</p>
                <a href="bookings.php?action=add" class="btn btn-primary">
                    <i class="fas fa-plus"></i> Create New Booking
                </a>
            </div>
        <?php endif; ?>
    </div>
    
    <!-- Cancel Booking Modal -->
    <div id="cancel-modal" class="modal">
        <div class="modal-content">
            <div class="modal-header">
                <h3 class="modal-title">Cancel Booking</h3>
                <button class="close-modal" onclick="closeModal()">&times;</button>
            </div>
            <div id="modal-body">
                <p>Are you sure you want to cancel booking for <strong id="customer-name"></strong>?</p>
                <div class="filter-group" style="margin-top: 1rem;">
                    <label class="filter-label">Reason for cancellation</label>
                    <select id="cancel-reason" class="filter-input">
                        <option value="">Select a reason</option>
                        <option value="customer_request">Customer Request</option>
                        <option value="vehicle_unavailable">Vehicle Unavailable</option>
                        <option value="payment_issue">Payment Issue</option>
                        <option value="other">Other</option>
                    </select>
                </div>
                <div class="filter-group" style="margin-top: 1rem; display: none;" id="other-reason-container">
                    <label class="filter-label">Please specify</label>
                    <textarea id="other-reason" class="filter-input" rows="3"></textarea>
                </div>
            </div>
            <div class="modal-footer">
                <button class="btn" onclick="closeModal()" style="background-color: #e9ecef; color: var(--dark-color);">Close</button>
                <button id="confirm-cancel-btn" class="btn btn-primary" onclick="confirmCancel()">
                    <span id="cancel-btn-text">Confirm Cancellation</span>
                    <span id="cancel-spinner" class="spinner" style="display: none;"></span>
                </button>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/flatpickr"></script>
    <script>
        // Initialize date range picker
        flatpickr("#date-range", {
            mode: "range",
            dateFormat: "Y-m-d",
            allowInput: true
        });
        
        // Search functionality
        document.getElementById('search-input').addEventListener('input', function(e) {
            const searchTerm = e.target.value.toLowerCase();
            const rows = document.querySelectorAll('.bookings-table tbody tr');
            
            rows.forEach(row => {
                const text = row.textContent.toLowerCase();
                row.style.display = text.includes(searchTerm) ? '' : 'none';
            });
        });
        
        // Status filter
        document.getElementById('status-filter').addEventListener('change', function(e) {
            const status = e.target.value;
            const rows = document.querySelectorAll('.bookings-table tbody tr');
            
            rows.forEach(row => {
                const rowStatus = row.querySelector('.status-badge').textContent.toLowerCase().trim();
                row.style.display = (status === '' || rowStatus === status) ? '' : 'none';
            });
        });
        
        // Export button
        document.getElementById('export-btn').addEventListener('click', function() {
            // In a real implementation, this would export the data
            alert('Export functionality would be implemented here');
        });
        
        // Modal functions
        let currentBookingId = null;
        
        function showCancelModal(bookingId, customerName) {
            currentBookingId = bookingId;
            document.getElementById('customer-name').textContent = customerName;
            document.getElementById('cancel-modal').style.display = 'flex';
        }
        
        function closeModal() {
            document.getElementById('cancel-modal').style.display = 'none';
            document.getElementById('cancel-reason').value = '';
            document.getElementById('other-reason').value = '';
            document.getElementById('other-reason-container').style.display = 'none';
        }
        
        // Show/hide other reason field
        document.getElementById('cancel-reason').addEventListener('change', function(e) {
            const otherContainer = document.getElementById('other-reason-container');
            otherContainer.style.display = e.target.value === 'other' ? 'block' : 'none';
        });
        
        function confirmCancel() {
            const reason = document.getElementById('cancel-reason').value;
            const otherReason = document.getElementById('other-reason').value;
            
            if (!reason) {
                alert('Please select a cancellation reason');
                return;
            }
            
            if (reason === 'other' && !otherReason) {
                alert('Please specify the cancellation reason');
                return;
            }
            
            const btnText = document.getElementById('cancel-btn-text');
            const spinner = document.getElementById('cancel-spinner');
            
            btnText.style.display = 'none';
            spinner.style.display = 'inline-block';
            
            // Simulate API call
            setTimeout(() => {
                fetch('cancel_booking.php', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/x-www-form-urlencoded',
                    },
                    body: `booking_id=${currentBookingId}&reason=${encodeURIComponent(reason)}&other_reason=${encodeURIComponent(otherReason)}`
                })
                .then(response => {
                    if (!response.ok) {
                        throw new Error('Network response was not ok');
                    }
                    return response.json();
                })
                .then(data => {
                    if (data.success) {
                        alert('Booking cancelled successfully');
                        window.location.reload();
                    } else {
                        alert('Error: ' + (data.message || 'Unknown error occurred'));
                    }
                })
                .catch(error => {
                    console.error('Error:', error);
                    alert('An error occurred while cancelling the booking');
                })
                .finally(() => {
                    btnText.style.display = 'inline-block';
                    spinner.style.display = 'none';
                    closeModal();
                });
            }, 1000);
        }
        
        // Close modal when clicking outside
        window.addEventListener('click', function(event) {
            if (event.target === document.getElementById('cancel-modal')) {
                closeModal();
            }
        });
    </script>
</body>
</html>