<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);
session_start();

// Verify admin role
if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'admin') {
    header('Location: login.php');
    exit();
}

try {
    require_once 'config.php';
    $pdo = getDBConnection();
    
    // Initialize data arrays
    $popular_vehicles = [];
    $vehicle_type_distribution = [];
    $booking_rate = ['daily' => [], 'weekly' => [], 'monthly' => []];

    // Most booked vehicles
    try {
        $stmt = $pdo->query("SELECT 
            v.vehicle_id,
            v.make,
            v.model,
            v.vehicle_type,
            COUNT(b.booking_id) as booking_count
            FROM vehicles v
            LEFT JOIN bookings b ON v.vehicle_id = b.vehicle_id
            GROUP BY v.vehicle_id
            ORDER BY booking_count DESC
            LIMIT 10");
        $popular_vehicles = $stmt->fetchAll(PDO::FETCH_ASSOC);
    } catch (PDOException $e) {
        error_log("Popular vehicles query error: " . $e->getMessage());
        $popular_vehicles = [];
    }

    // Vehicle type distribution
    try {
        $stmt = $pdo->query("SELECT 
            v.vehicle_type,
            COUNT(b.booking_id) as booking_count
            FROM vehicles v
            LEFT JOIN bookings b ON v.vehicle_id = b.vehicle_id
            GROUP BY v.vehicle_type
            ORDER BY booking_count DESC");
        $vehicle_type_distribution = $stmt->fetchAll(PDO::FETCH_ASSOC);
    } catch (PDOException $e) {
        error_log("Vehicle type distribution error: " . $e->getMessage());
        $vehicle_type_distribution = [];
    }

    // Booking rate data
    try {
        // Daily booking rate (last 30 days)
        $stmt = $pdo->query("SELECT 
            DATE(created_at) as date, 
            COUNT(*) as count 
            FROM bookings 
            WHERE created_at >= DATE_SUB(CURDATE(), INTERVAL 30 DAY) 
            GROUP BY DATE(created_at) 
            ORDER BY DATE(created_at)");
        $booking_rate['daily'] = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        // Weekly booking rate (last 12 weeks)
        $stmt = $pdo->query("SELECT 
            YEARWEEK(created_at) as week, 
            COUNT(*) as count 
            FROM bookings 
            WHERE created_at >= DATE_SUB(CURDATE(), INTERVAL 12 WEEK) 
            GROUP BY YEARWEEK(created_at) 
            ORDER BY YEARWEEK(created_at)");
        $booking_rate['weekly'] = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        // Monthly booking rate (last 12 months)
        $stmt = $pdo->query("SELECT 
            DATE_FORMAT(created_at, '%Y-%m') as month, 
            COUNT(*) as count 
            FROM bookings 
            WHERE created_at >= DATE_SUB(CURDATE(), INTERVAL 12 MONTH) 
            GROUP BY DATE_FORMAT(created_at, '%Y-%m') 
            ORDER BY DATE_FORMAT(created_at, '%Y-%m')");
        $booking_rate['monthly'] = $stmt->fetchAll(PDO::FETCH_ASSOC);
    } catch (PDOException $e) {
        error_log("Booking rate query error: " . $e->getMessage());
        $booking_rate = ['daily' => [], 'weekly' => [], 'monthly' => []];
    }

} catch (Exception $e) {
    error_log("Report page error: " . $e->getMessage());
    $error_message = "System error. Please try again later.";
    if ($_SESSION['role'] === 'admin') {
        $error_message .= " Technical details: " . $e->getMessage();
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Reports | VehicleEZ</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
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
        
        .quick-nav {
            background-color: white;
            margin: 20px;
            padding: 15px;
            border-radius: var(--border-radius);
            box-shadow: var(--box-shadow);
            display: flex;
            gap: 15px;
            flex-wrap: wrap;
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
        
        .nav-link.active {
            background-color: var(--primary-color);
            color: white;
        }
        
        .report-section {
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
        
        .chart-container {
            position: relative;
            height: 400px;
            margin-bottom: 30px;
        }
        
        .report-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(400px, 1fr));
            gap: 20px;
            margin-top: 20px;
        }
        
        .report-table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 20px;
        }
        
        .report-table th, .report-table td {
            padding: 12px 15px;
            text-align: left;
            border-bottom: 1px solid #ddd;
        }
        
        .report-table th {
            background-color: #f8f9fa;
            font-weight: 600;
        }
        
        .time-period-selector {
            display: flex;
            gap: 10px;
            margin-bottom: 20px;
        }
        
        .time-period-btn {
            padding: 8px 15px;
            background-color: #f8f9fa;
            border: 1px solid #ddd;
            border-radius: 4px;
            cursor: pointer;
            transition: all 0.3s;
        }
        
        .time-period-btn.active {
            background-color: var(--primary-color);
            color: white;
            border-color: var(--primary-color);
        }
        
        .error-message {
            color: var(--danger-color);
            padding: 20px;
            background: #ffeeee;
            margin: 20px;
            border-radius: 5px;
        }
        
        @media (max-width: 768px) {
            .quick-nav {
                flex-direction: column;
                gap: 10px;
            }
            
            .report-grid {
                grid-template-columns: 1fr;
            }
        }
    </style>
</head>
<body>
    <div class="dashboard-header">
        <div class="brand-title">
            <h1 class="brand-name">VehicleEZ</h1>
            <p class="brand-tagline">Booking Reports and Analytics</p>
        </div>
    </div>
    
    <!-- Navigation Bar -->
    <div class="quick-nav">
        <a href="admin_dashboard.php" class="nav-link"><i class="fas fa-tachometer-alt"></i> Dashboard</a>
        <a href="manage_vehicles.php" class="nav-link"><i class="fas fa-car"></i> Manage Vehicles</a>
        <a href="reports.php" class="nav-link active"><i class="fas fa-chart-bar"></i> Reports</a>
        <a href="settings.php" class="nav-link"><i class="fas fa-cog"></i> Settings</a>
    </div>
    
    <?php if (isset($error_message)): ?>
        <div class="error-message">
            <h3>System Error</h3>
            <p><?php echo $error_message; ?></p>
        </div>
    <?php endif; ?>

    <!-- Booking Rate Section -->
    <div class="report-section">
        <div class="section-header">
            <h2><i class="fas fa-chart-line"></i> Booking Rate</h2>
        </div>
        
        <div class="time-period-selector">
            <button class="time-period-btn active" data-period="daily">Daily</button>
            <button class="time-period-btn" data-period="weekly">Weekly</button>
            <button class="time-period-btn" data-period="monthly">Monthly</button>
        </div>
        
        <div class="chart-container">
            <canvas id="bookingRateChart"></canvas>
        </div>
        
        <h3>Booking Rate Data</h3>
        <table class="report-table">
            <thead>
                <tr>
                    <th>Time Period</th>
                    <th>Number of Bookings</th>
                </tr>
            </thead>
            <tbody id="bookingRateData">
                <!-- Data will be inserted by JavaScript -->
            </tbody>
        </table>
    </div>
    
    <!-- Most Booked Vehicles Section -->
    <div class="report-section">
        <div class="section-header">
            <h2><i class="fas fa-car"></i> Most Booked Vehicles</h2>
        </div>
        
        <div class="report-grid">
            <div class="chart-container">
                <canvas id="popularVehiclesChart"></canvas>
            </div>
            
            <div>
                <h3>Top 10 Booked Vehicles</h3>
                <table class="report-table">
                    <thead>
                        <tr>
                            <th>Vehicle</th>
                            <th>Type</th>
                            <th>Bookings</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($popular_vehicles as $vehicle): ?>
                        <tr>
                            <td><?php echo htmlspecialchars($vehicle['make'] . ' ' . $vehicle['model']); ?></td>
                            <td><?php echo htmlspecialchars($vehicle['vehicle_type']); ?></td>
                            <td><?php echo htmlspecialchars($vehicle['booking_count']); ?></td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
    
    <!-- Vehicle Type Distribution Section -->
    <div class="report-section">
        <div class="section-header">
            <h2><i class="fas fa-chart-pie"></i> Vehicle Type Distribution</h2>
        </div>
        
        <div class="report-grid">
            <div class="chart-container">
                <canvas id="vehicleTypeChart"></canvas>
            </div>
            
            <div>
                <h3>Bookings by Vehicle Type</h3>
                <table class="report-table">
                    <thead>
                        <tr>
                            <th>Vehicle Type</th>
                            <th>Number of Bookings</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($vehicle_type_distribution as $type): ?>
                        <tr>
                            <td><?php echo htmlspecialchars($type['vehicle_type']); ?></td>
                            <td><?php echo htmlspecialchars($type['booking_count']); ?></td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <script>
        document.addEventListener('DOMContentLoaded', function() {
            // Booking Rate Data
            const bookingRateData = {
                daily: {
                    labels: <?php echo json_encode(array_column($booking_rate['daily'], 'date')); ?>,
                    data: <?php echo json_encode(array_column($booking_rate['daily'], 'count')); ?>,
                    title: 'Daily Booking Rate (Last 30 Days)'
                },
                weekly: {
                    labels: <?php echo json_encode(array_column($booking_rate['weekly'], 'week')); ?>,
                    data: <?php echo json_encode(array_column($booking_rate['weekly'], 'count')); ?>,
                    title: 'Weekly Booking Rate (Last 12 Weeks)'
                },
                monthly: {
                    labels: <?php echo json_encode(array_column($booking_rate['monthly'], 'month')); ?>,
                    data: <?php echo json_encode(array_column($booking_rate['monthly'], 'count')); ?>,
                    title: 'Monthly Booking Rate (Last 12 Months)'
                }
            };
            
            // Booking Rate Chart
            const bookingRateCtx = document.getElementById('bookingRateChart').getContext('2d');
            const bookingRateChart = new Chart(bookingRateCtx, {
                type: 'line',
                data: {
                    labels: bookingRateData.daily.labels,
                    datasets: [{
                        label: 'Number of Bookings',
                        data: bookingRateData.daily.data,
                        backgroundColor: 'rgba(58, 134, 255, 0.2)',
                        borderColor: 'rgba(58, 134, 255, 1)',
                        borderWidth: 2,
                        tension: 0.4,
                        fill: true
                    }]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    plugins: {
                        title: {
                            display: true,
                            text: bookingRateData.daily.title
                        },
                        tooltip: {
                            mode: 'index',
                            intersect: false
                        }
                    },
                    scales: {
                        y: {
                            beginAtZero: true,
                            title: {
                                display: true,
                                text: 'Number of Bookings'
                            }
                        },
                        x: {
                            title: {
                                display: true,
                                text: 'Date'
                            }
                        }
                    }
                }
            });
            
            // Update booking rate table
            function updateBookingRateTable(period) {
                const tableBody = document.getElementById('bookingRateData');
                tableBody.innerHTML = '';
                
                bookingRateData[period].labels.forEach((label, index) => {
                    const row = document.createElement('tr');
                    row.innerHTML = `
                        <td>${label}</td>
                        <td>${bookingRateData[period].data[index]}</td>
                    `;
                    tableBody.appendChild(row);
                });
            }
            
            // Initialize with daily data
            updateBookingRateTable('daily');
            
            // Time period selector
            document.querySelectorAll('.time-period-btn').forEach(btn => {
                btn.addEventListener('click', function() {
                    document.querySelectorAll('.time-period-btn').forEach(b => b.classList.remove('active'));
                    this.classList.add('active');
                    
                    const period = this.dataset.period;
                    bookingRateChart.data.labels = bookingRateData[period].labels;
                    bookingRateChart.data.datasets[0].data = bookingRateData[period].data;
                    bookingRateChart.options.plugins.title.text = bookingRateData[period].title;
                    bookingRateChart.update();
                    
                    updateBookingRateTable(period);
                });
            });
            
            // Most Booked Vehicles Chart
            const popularVehiclesCtx = document.getElementById('popularVehiclesChart').getContext('2d');
            new Chart(popularVehiclesCtx, {
                type: 'bar',
                data: {
                    labels: <?php echo json_encode(array_map(function($v) { 
                        return $v['make'] . ' ' . $v['model']; 
                    }, $popular_vehicles)); ?>,
                    datasets: [{
                        label: 'Number of Bookings',
                        data: <?php echo json_encode(array_column($popular_vehicles, 'booking_count')); ?>,
                        backgroundColor: 'rgba(108, 92, 231, 0.7)',
                        borderColor: 'rgba(108, 92, 231, 1)',
                        borderWidth: 1
                    }]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    plugins: {
                        title: {
                            display: true,
                            text: 'Top 10 Most Booked Vehicles'
                        }
                    },
                    scales: {
                        y: {
                            beginAtZero: true,
                            title: {
                                display: true,
                                text: 'Number of Bookings'
                            }
                        }
                    }
                }
            });
            
            // Vehicle Type Distribution Chart
            const vehicleTypeCtx = document.getElementById('vehicleTypeChart').getContext('2d');
            new Chart(vehicleTypeCtx, {
                type: 'doughnut',
                data: {
                    labels: <?php echo json_encode(array_column($vehicle_type_distribution, 'vehicle_type')); ?>,
                    datasets: [{
                        data: <?php echo json_encode(array_column($vehicle_type_distribution, 'booking_count')); ?>,
                        backgroundColor: [
                            'rgba(52, 152, 219, 0.7)',
                            'rgba(46, 204, 113, 0.7)',
                            'rgba(155, 89, 182, 0.7)',
                            'rgba(241, 196, 15, 0.7)',
                            'rgba(230, 126, 34, 0.7)',
                            'rgba(231, 76, 60, 0.7)'
                        ],
                        borderWidth: 1
                    }]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    plugins: {
                        title: {
                            display: true,
                            text: 'Bookings by Vehicle Type'
                        },
                        legend: {
                            position: 'right'
                        }
                    }
                }
            });
        });
    </script>
</body>
</html>