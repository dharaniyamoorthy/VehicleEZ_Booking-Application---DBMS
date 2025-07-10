<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);
session_start();
require_once 'config.php';

// Verify both user_id and role
if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'admin') {
    header('Location: login.php');
    exit();
}

// Rest of your dashboard code...

require_once 'config.php';
$pdo = getDBConnection();

// Get admin details
try {
    $user_id = $_SESSION['user_id'];
    $stmt = $pdo->prepare("SELECT user_id, username, full_name, email, last_login FROM users WHERE user_id = :user_id");
    $stmt->bindParam(':user_id', $user_id, PDO::PARAM_INT);
    $stmt->execute();
    
    if ($stmt->rowCount() === 0) {
        session_destroy();
        header('Location: login.php?error=account_not_found');
        exit();
    }
    
    $admin = $stmt->fetch(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    error_log("Database error: " . $e->getMessage());
    die("System error. Please try again later.");
}

// Get system statistics
$system_stats = [];
try {
    $sys_stmt = $pdo->query("SELECT 
        (SELECT COUNT(*) FROM users) as total_users,
        (SELECT COUNT(*) FROM vehicles) as total_vehicles,
        (SELECT COUNT(*) FROM bookings) as total_bookings,
        (SELECT COUNT(*) FROM bookings WHERE booking_status = 'pending') as pending_bookings,
        (SELECT COUNT(*) FROM bookings WHERE booking_status = 'confirmed') as confirmed_bookings,
        (SELECT COUNT(*) FROM bookings WHERE booking_status = 'cancelled') as cancelled_bookings,
        (SELECT COUNT(*) FROM payments) as total_payments,
        (SELECT SUM(amount) FROM payments WHERE status = 'completed') as revenue");
    $system_stats = $sys_stmt->fetch(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    error_log("System stats query error: " . $e->getMessage());
}

// Get recent activities
$recent_activities = [];
try {
    $activity_stmt = $pdo->query("SELECT * FROM system_logs ORDER BY created_at DESC LIMIT 5");
    $recent_activities = $activity_stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    error_log("Recent activities query error: " . $e->getMessage());
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Dashboard | VehicleEZ</title>
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
        
        .notification-icon {
            position: relative;
            cursor: pointer;
            color: var(--dark-color);
        }
        
        .notification-badge {
            position: absolute;
            top: -5px;
            right: -5px;
            background: var(--danger-color);
            color: white;
            border-radius: 50%;
            width: 18px;
            height: 18px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 10px;
        }
        
        .notification-dropdown {
            position: absolute;
            top: 40px;
            right: 0;
            width: 300px;
            background: white;
            border-radius: var(--border-radius);
            box-shadow: var(--box-shadow);
            padding: 10px;
            display: none;
            z-index: 1000;
        }
        
        .notification-icon:hover .notification-dropdown {
            display: block;
        }
        
        .notification-item {
            padding: 10px;
            border-bottom: 1px solid #eee;
            font-size: 14px;
        }
        
        .notification-item:last-child {
            border-bottom: none;
        }
        
        .notification-item small {
            display: block;
            color: #7f8c8d;
            font-size: 12px;
            margin-top: 3px;
        }
        
        .text-danger { color: var(--danger-color); }
        .text-success { color: var(--secondary-color); }
        .text-warning { color: var(--warning-color); }
        
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
        
        .quick-nav {
            background-color: white;
            margin: 20px;
            padding: 15px;
            border-radius: var(--border-radius);
            box-shadow: var(--box-shadow);
            display: flex;
            gap: 15px;
        }
        
        .nav-link {
            color: var(--primary-color);
            text-decoration: none;
            font-weight: 500;
            padding: 8px 15px;
            border-radius: var(--border-radius);
            transition: all 0.3s;
        }
        
        .nav-link:hover {
            background-color: rgba(52, 152, 219, 0.1);
        }
        
        .admin-nav-link {
            color: var(--admin-color);
        }
        
        .admin-nav-link:hover {
            background-color: rgba(108, 92, 231, 0.1);
        }
        
        .welcome-section {
            background-color: white;
            margin: 0 20px 20px 20px;
            padding: 30px;
            border-radius: var(--border-radius);
            box-shadow: var(--box-shadow);
        }
        
        .welcome-message {
            font-size: 28px;
            font-weight: 600;
            color: var(--dark-color);
            margin-bottom: 15px;
            display: flex;
            align-items: center;
            gap: 15px;
        }
        
        .stats-container {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(220px, 1fr));
            gap: 20px;
            margin-top: 30px;
        }
        
        .stat-card {
            background-color: white;
            border-radius: var(--border-radius);
            padding: 20px;
            box-shadow: var(--box-shadow);
            border-left: 4px solid var(--primary-color);
            transition: all 0.3s ease;
        }
        
        .stat-card:hover {
            transform: translateY(-3px);
            box-shadow: 0 5px 15px rgba(0,0,0,0.1);
        }
        
        .stat-card h3 {
            margin-top: 0;
            color: var(--dark-color);
            font-size: 16px;
            display: flex;
            align-items: center;
            gap: 10px;
        }
        
        .stat-card .value {
            font-size: 28px;
            font-weight: 700;
            color: var(--primary-color);
            margin: 15px 0 8px;
        }
        
        .stat-card.admin-stat { border-left-color: var(--admin-color); }
        .stat-card.admin-stat .value { color: var(--admin-color); }
        
        .stat-card.success-stat { border-left-color: var(--secondary-color); }
        .stat-card.success-stat .value { color: var(--secondary-color); }
        
        .stat-card.warning-stat { border-left-color: var(--warning-color); }
        .stat-card.warning-stat .value { color: var(--warning-color); }
        
        .stat-card.danger-stat { border-left-color: var(--danger-color); }
        .stat-card.danger-stat .value { color: var(--danger-color); }
        
        .dashboard-section {
            background-color: white;
            margin: 20px;
            padding: 25px;
            border-radius: var(--border-radius);
            box-shadow: var(--box-shadow);
        }
        
        .section-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 20px;
        }
        
        .section-header h2 {
            margin: 0;
            color: var(--dark-color);
            display: flex;
            align-items: center;
            gap: 10px;
        }
        
        .activity-item {
            padding: 15px;
            border-bottom: 1px solid #eee;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        
        .activity-item:last-child {
            border-bottom: none;
        }
        
        .activity-details {
            flex: 1;
        }
        
        .activity-time {
            color: #7f8c8d;
            font-size: 13px;
        }
        
        .btn {
            padding: 6px 12px;
            border-radius: 4px;
            font-size: 13px;
            font-weight: 500;
            cursor: pointer;
            border: none;
            transition: all 0.3s;
        }
        
        .btn-primary {
            background-color: var(--primary-color);
            color: white;
        }
        
        .btn-primary:hover {
            background-color: #2980b9;
        }
        
        .btn-success {
            background-color: var(--secondary-color);
            color: white;
        }
        
        .btn-success:hover {
            background-color: #27ae60;
        }
        
        .btn-danger {
            background-color: var(--danger-color);
            color: white;
        }
        
        .btn-danger:hover {
            background-color: #c0392b;
        }
        
        .quick-actions {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(160px, 1fr));
            gap: 15px;
            margin-top: 20px;
        }
        
        .action-btn {
            background-color: var(--admin-color);
            color: white;
            border: none;
            padding: 12px;
            border-radius: var(--border-radius);
            cursor: pointer;
            text-align: center;
            transition: all 0.3s;
            display: flex;
            flex-direction: column;
            align-items: center;
            gap: 8px;
        }
        
        .action-btn:hover {
            background-color: #5649b0;
            transform: translateY(-2px);
        }
        
        .action-btn i {
            font-size: 24px;
        }
        
        @media (max-width: 768px) {
            .stats-container {
                grid-template-columns: 1fr 1fr;
            }
            
            .dashboard-header {
                flex-direction: column;
                align-items: flex-start;
                gap: 15px;
            }
            
            .quick-nav {
                flex-direction: column;
                gap: 10px;
            }
            
            .quick-actions {
                grid-template-columns: 1fr 1fr;
            }
        }
    </style>
</head>
<body>
    <div class="dashboard-header">
        <div class="brand-title">
            <h1 class="brand-name">VehicleEZ</h1>
            <p class="brand-tagline">Your seamless vehicle booking experience</p>
        </div>
        <div class="header-right">
            <div id="live-date-time">
                <?php echo date('l, F j, Y | H:i:s'); ?>
            </div>
            <div class="notification-icon">
                <i class="fas fa-bell fa-lg"></i>
                <span class="notification-badge">3</span>
                <div class="notification-dropdown">
                    <div class="notification-item">
                        <i class="fas fa-exclamation-circle text-danger"></i>
                        <span>New booking request from John Doe</span>
                        <small>2 mins ago</small>
                    </div>
                    <div class="notification-item">
                        <i class="fas fa-check-circle text-success"></i>
                        <span>Payment completed for booking #1234</span>
                        <small>1 hour ago</small>
                    </div>
                    <div class="notification-item">
                        <i class="fas fa-car text-warning"></i>
                        <span>Vehicle maintenance required for Toyota Camry</span>
                        <small>3 hours ago</small>
                    </div>
                </div>
            </div>
            <div>
                <span><?php echo htmlspecialchars($admin['username']); ?></span>
                <span class="admin-badge"><i class="fas fa-shield-alt"></i> ADMIN</span>
            </div>
        </div>
    </div>
    
    <!-- Admin Navigation -->
    <div class="quick-nav">
        <a href="admin_dashboard.php" class="nav-link admin-nav-link"><i class="fas fa-tachometer-alt"></i> Dashboard</a>
        <a href="manage_vehicles.php" class="nav-link"><i class="fas fa-car"></i> Manage Vehicles</a>
        <a href="reports.php" class="nav-link"><i class="fas fa-chart-bar"></i> Reports</a>
        <a href="approve_bookings.php" class="nav-link"><i class="fas fa-check-circle"></i> Approve Bookings</a>
        <a href="settings.php" class="nav-link"><i class="fas fa-cog"></i> Settings</a>
    </div>
    
    <!-- Welcome Section -->
    <div class="welcome-section">
        <div class="welcome-message">
            <i class="fas fa-user-shield fa-2x" style="color: var(--admin-color);"></i>
            Welcome back, Admin <?php echo htmlspecialchars($admin['full_name'] ?? $admin['username']); ?>!
        </div>
        
        <div class="stats-container">
            <div class="stat-card admin-stat">
                <h3><i class="fas fa-users"></i> Total Users</h3>
                <div class="value">125</div>
                <small>Registered in system</small>
            </div>
            <div class="stat-card">
                <h3><i class="fas fa-car"></i> Total Vehicles</h3>
                <div class="value">42</div>
                <small>Available for booking</small>
            </div>
            <!-- In your dashboard.php, change the Active Bookings card to this: -->
<div class="stat-card success-stat">
    <a href="active_bookings.php" style="text-decoration: none; color: inherit;">
        <h3><i class="fas fa-calendar-check"></i> Active Bookings</h3>
        <div class="value"><?php echo htmlspecialchars($system_stats['confirmed_bookings'] ?? 5); ?></div>
        <small>Confirmed bookings</small>
    </a>
</div>
            <div class="stat-card warning-stat">
                <h3><i class="fas fa-hourglass-half"></i> Pending Bookings</h3>
                <div class="value">5</div>
                <small>Require action</small>
            </div>
            <div class="stat-card">
                <h3><i class="fas fa-money-bill-wave"></i> Total Revenue</h3>
                <div class="value">$1500.00</div>
                <small>From completed payments</small>
            </div>
            <div class="stat-card danger-stat">
                <h3><i class="fas fa-times-circle"></i> Cancelled Bookings</h3>
                <div class="value">4</div>
                <small>Cancelled by users</small>
            </div>
        </div>
    </div>

    <!-- Quick Actions Section -->
    <div class="dashboard-section">
        <div class="section-header">
            <h2><i class="fas fa-bolt"></i> Quick Actions</h2>
        </div>
        <div class="quick-actions">
            <a href="add_vehicle.php" class="action-btn">
                <i class="fas fa-car"></i>
                Add Vehicle
            </a>
            <a href="reports.php" class="action-btn">
                <i class="fas fa-chart-pie"></i>
                Generate Report
            </a>
            <a href="settings.php" class="action-btn">
                <i class="fas fa-cogs"></i>
                System Settings
            </a>
        </div>
    </div>

    <!-- Recent Activities Section -->
    <div class="dashboard-section">
        <div class="section-header">
            <h2><i class="fas fa-history"></i> Recent Activities</h2>
            <a href="logs.php" class="nav-link">View All</a>
        </div>
        
        <div class="activity-item">
            <div class="activity-details">
                Admin logged in to the system
                <div class="activity-time">
                    Just now
                </div>
            </div>
            <i class="fas fa-sign-in-alt" style="color: var(--secondary-color);"></i>
        </div>
        <div class="activity-item">
            <div class="activity-details">
                New vehicle "Toyota Camry" added to inventory
                <div class="activity-time">
                    30 minutes ago
                </div>
            </div>
            <i class="fas fa-plus-circle" style="color: var(--primary-color);"></i>
        </div>
        <div class="activity-item">
            <div class="activity-details">
                User registration for "johndoe@example.com"
                <div class="activity-time">
                    2 hours ago
                </div>
            </div>
            <i class="fas fa-user-plus" style="color: var(--primary-color);"></i>
        </div>
        <div class="activity-item">
            <div class="activity-details">
                Booking #1234 status changed to "Confirmed"
                <div class="activity-time">
                    5 hours ago
                </div>
            </div>
            <i class="fas fa-edit" style="color: var(--warning-color);"></i>
        </div>
    </div>

    <script>
        // Live Clock
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