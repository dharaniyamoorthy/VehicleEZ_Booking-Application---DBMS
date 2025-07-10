<?php
session_start();
require_once 'config.php';

// Verify admin is logged in
if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'admin') {
    header('Location: login.php');
    exit();
}

// Initialize vehicle type filter (default to 'all')
$vehicle_type = isset($_GET['type']) ? $_GET['type'] : 'all';

// Handle vehicle deletion
if (isset($_GET['delete_id'])) {
    try {
        $pdo = getDBConnection();
        $stmt = $pdo->prepare("DELETE FROM vehicles WHERE vehicle_id = ?");
        $stmt->execute([$_GET['delete_id']]);
        
        $_SESSION['success_message'] = "Vehicle deleted successfully!";
        header('Location: manage_vehicles.php');
        exit();
    } catch (PDOException $e) {
        $error = "Error deleting vehicle: " . $e->getMessage();
    }
}

// Build the base query
$query = "SELECT * FROM vehicles";
$params = [];

// Add vehicle type filter if specified
if ($vehicle_type !== 'all') {
    $query .= " WHERE vehicle_type = ?";
    $params[] = $vehicle_type;
}

$query .= " ORDER BY vehicle_id DESC";

// Initialize vehicles array
$vehicles = [];
$error = null;

// Fetch all vehicles from database
try {
    $pdo = getDBConnection();
    $stmt = $pdo->prepare($query);
    $stmt->execute($params);
    $vehicles = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    $error = "Error fetching vehicles: " . $e->getMessage();
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Manage Vehicles | Admin Dashboard</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.2.3/dist/css/bootstrap.min.css">
    <style>
        :root {
            --primary-color: #6c5ce7;
            --primary-dark: #5649b0;
            --danger-color: #e74c3c;
            --success-color: #2ecc71;
            --warning-color: #f39c12;
            --light-gray: #f5f5f5;
            --medium-gray: #e0e0e0;
            --dark-gray: #333;
            --border-color: #ddd;
            --text-color: #333;
            --text-light: #777;
            --shadow: 0 2px 10px rgba(0, 0, 0, 0.1);
        }
        
        body {
            background-color: #f8f9fa;
            color: var(--text-color);
            display: flex;
            min-height: 100vh;
        }
        
        /* Sidebar styles */
        .sidebar {
            width: 250px;
            background-color: white;
            box-shadow: var(--shadow);
            padding: 20px 0;
            position: sticky;
            top: 0;
            height: 100vh;
        }
        
        .sidebar-header {
            padding: 0 20px 20px;
            border-bottom: 1px solid var(--border-color);
            margin-bottom: 20px;
        }
        
        .sidebar-menu {
            list-style: none;
            padding: 0;
            margin: 0;
        }
        
        .sidebar-menu li a {
            display: block;
            padding: 10px 20px;
            color: var(--text-color);
            text-decoration: none;
            transition: all 0.3s;
        }
        
        .sidebar-menu li a:hover,
        .sidebar-menu li a.active {
            background-color: rgba(108, 92, 231, 0.1);
            color: var(--primary-color);
        }
        
        .sidebar-menu li a i {
            width: 25px;
            text-align: center;
            margin-right: 10px;
        }
        
        .main-content {
            flex: 1;
            padding: 20px;
            overflow-y: auto;
        }
        
        .card {
            border-radius: 10px;
            box-shadow: var(--shadow);
            border: none;
        }
        
        .table-responsive {
            border-radius: 10px;
            overflow: hidden;
        }
        
        .table {
            margin-bottom: 0;
        }
        
        .table th {
            background-color: var(--primary-color);
            color: white;
            font-weight: 500;
            padding: 15px;
        }
        
        .table td {
            vertical-align: middle;
            padding: 12px 15px;
        }
        
        .table tr:nth-child(even) {
            background-color: rgba(108, 92, 231, 0.05);
        }
        
        .table tr:hover {
            background-color: rgba(108, 92, 231, 0.1);
        }
        
        .vehicle-image {
            width: 80px;
            height: 60px;
            object-fit: cover;
            border-radius: 5px;
            border: 1px solid var(--border-color);
        }
        
        .status-badge {
            padding: 5px 10px;
            border-radius: 20px;
            font-size: 12px;
            font-weight: 500;
            text-transform: capitalize;
        }
        
        .status-available {
            background-color: #d4edda;
            color: #155724;
        }
        
        .status-booked {
            background-color: #fff3cd;
            color: #856404;
        }
        
        .status-maintenance {
            background-color: #f8d7da;
            color: #721c24;
        }
        
        .action-btns .btn {
            padding: 5px 10px;
            font-size: 13px;
            margin: 2px;
        }
        
        .page-title {
            color: var(--primary-dark);
            font-weight: 600;
        }
        
        .add-new-btn {
            background: var(--primary-color);
            border: none;
            padding: 10px 20px;
            font-weight: 500;
        }
        
        .add-new-btn:hover {
            background: var(--primary-dark);
        }
        
        .search-box {
            position: relative;
            max-width: 300px;
        }
        
        .search-box input {
            padding-left: 40px;
            border-radius: 20px;
            border: 1px solid var(--border-color);
        }
        
        .search-box i {
            position: absolute;
            left: 15px;
            top: 10px;
            color: var(--text-light);
        }
        
        .pagination .page-item.active .page-link {
            background-color: var(--primary-color);
            border-color: var(--primary-color);
        }
        
        .pagination .page-link {
            color: var(--primary-color);
        }
        
        @media (max-width: 768px) {
            .table-responsive {
                border-radius: 0;
            }
            
            .table th, .table td {
                padding: 8px 5px;
                font-size: 14px;
            }
            
            .action-btns .btn {
                padding: 3px 6px;
                font-size: 12px;
            }
        }
    </style>
</head>
<body>
    <!-- Sidebar -->
    <div class="sidebar">
        <div class="sidebar-header">
            <h3>Admin Panel</h3>
        </div>
        <ul class="sidebar-menu">
            <li><a href="admin_dashboard.php"><i class="fas fa-tachometer-alt"></i> Dashboard</a></li>
            <li><a href="manage_vehicles.php" class="active"><i class="fas fa-car"></i> Vehicles</a></li>
            <li><a href="approve_bookings.php"><i class="fas fa-calendar-alt"></i> Approve Bookings</a></li>
            <li><a href="reports.php"><i class="fas fa-chart-bar"></i> Reports</a></li>
            <li><a href="settings.php"><i class="fas fa-cog"></i> Settings</a></li>
            <li><a href="logout.php"><i class="fas fa-sign-out-alt"></i> Logout</a></li>
        </ul>
    </div>

    <!-- Main Content -->
    <div class="main-content">
        <div class="container-fluid py-4">
            <div class="row mb-4">
                <div class="col-12">
                    <div class="d-flex justify-content-between align-items-center">
                        <h2 class="page-title">
                            <i class="fas fa-car me-2"></i>Manage Vehicles
                        </h2>
                        <a href="add_vehicle.php" class="btn btn-primary add-new-btn">
                            <i class="fas fa-plus me-1"></i> Add New Vehicle
                        </a>
                    </div>
                </div>
            </div>
            
            <?php if (isset($_SESSION['success_message'])): ?>
                <div class="alert alert-success alert-dismissible fade show">
                    <?php echo $_SESSION['success_message']; unset($_SESSION['success_message']); ?>
                    <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                </div>
            <?php endif; ?>
            
            <?php if (isset($error)): ?>
                <div class="alert alert-danger alert-dismissible fade show">
                    <?php echo $error; ?>
                    <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                </div>
            <?php endif; ?>
            
            <div class="card">
                <div class="card-body">
                    <div class="row mb-3">
                        <div class="col-md-6">
                            <div class="search-box">
                                <i class="fas fa-search"></i>
                                <input type="text" class="form-control" placeholder="Search vehicles...">
                            </div>
                        </div>
                        <div class="col-md-6 d-flex justify-content-end">
                            <div class="btn-group me-2" role="group">
                                <a href="manage_vehicles.php" 
                                   class="btn btn-sm <?php echo $vehicle_type === 'all' ? 'btn-primary' : 'btn-outline-primary'; ?>">
                                    All
                                </a>
                                <a href="manage_vehicles.php?type=two_wheeler" 
                                   class="btn btn-sm <?php echo $vehicle_type === 'two_wheeler' ? 'btn-primary' : 'btn-outline-primary'; ?>">
                                    Two Wheelers
                                </a>
                                <a href="manage_vehicles.php?type=four_wheeler" 
                                   class="btn btn-sm <?php echo $vehicle_type === 'four_wheeler' ? 'btn-primary' : 'btn-outline-primary'; ?>">
                                    Four Wheelers
                                </a>
                                <a href="manage_vehicles.php?type=heavy" 
                                   class="btn btn-sm <?php echo $vehicle_type === 'heavy' ? 'btn-primary' : 'btn-outline-primary'; ?>">
                                    Heavy Vehicles
                                </a>
                            </div>
                            <select class="form-select w-auto">
                                <option>All Status</option>
                                <option>Available</option>
                                <option>Booked</option>
                                <option>Maintenance</option>
                            </select>
                        </div>
                    </div>
                    
                    <div class="table-responsive">
                        <table class="table table-hover">
                            <thead>
                                <tr>
                                    <th>ID</th>
                                    <th>Image</th>
                                    <th>Make & Model</th>
                                    <th>Year</th>
                                    <th>License Plate</th>
                                    <th>Type</th>
                                    <th>Color</th>
                                    <th>Daily Rate</th>
                                    <th>Status</th>
                                    <th>Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if (empty($vehicles)): ?>
                                    <tr>
                                        <td colspan="10" class="text-center py-4">No vehicles found. Add your first vehicle!</td>
                                    </tr>
                                <?php else: ?>
                                    <?php foreach ($vehicles as $vehicle): ?>
                                        <tr>
                                            <td><?php echo htmlspecialchars($vehicle['vehicle_id']); ?></td>
                                            <td>
                                                <?php if (!empty($vehicle['image_path'])): ?>
                                                    <img src="<?php echo htmlspecialchars($vehicle['image_path']); ?>" alt="Vehicle Image" class="vehicle-image">
                                                <?php else: ?>
                                                    <div class="d-flex align-items-center justify-content-center vehicle-image" style="background-color: #eee;">
                                                        <i class="fas fa-car text-muted"></i>
                                                    </div>
                                                <?php endif; ?>
                                            </td>
                                            <td>
                                                <strong><?php echo htmlspecialchars($vehicle['make']); ?></strong><br>
                                                <small class="text-muted"><?php echo htmlspecialchars($vehicle['model']); ?></small>
                                            </td>
                                            <td><?php echo htmlspecialchars($vehicle['year']); ?></td>
                                            <td><?php echo htmlspecialchars($vehicle['license_plate']); ?></td>
                                            <td><?php echo ucfirst(str_replace('_', ' ', htmlspecialchars($vehicle['vehicle_type']))); ?></td>
                                            <td><?php echo htmlspecialchars($vehicle['color']); ?></td>
                                            <td>₹<?php echo number_format($vehicle['daily_rate'], 2); ?></td>
                                            <td>
                                                <span class="status-badge status-<?php echo htmlspecialchars($vehicle['status']); ?>">
                                                    <?php echo ucfirst(htmlspecialchars($vehicle['status'])); ?>
                                                </span>
                                            </td>
                                            <td>
                                                <div class="action-btns d-flex">
                                                    <a href="edit_vehicle.php?id=<?php echo $vehicle['vehicle_id']; ?>" class="btn btn-sm btn-warning">
                                                        <i class="fas fa-edit"></i>
                                                    </a>
                                                    <a href="manage_vehicles.php?delete_id=<?php echo $vehicle['vehicle_id']; ?>" class="btn btn-sm btn-danger" onclick="return confirm('Are you sure you want to delete this vehicle?');">
                                                        <i class="fas fa-trash"></i>
                                                    </a>
                                                </div>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                    
                    <div class="row mt-3">
                        <div class="col-12 d-flex justify-content-between align-items-center">
                            <div class="text-muted">
                                <?php if (!empty($vehicles)): ?>
                                    Showing <?php echo count($vehicles); ?> <?php echo $vehicle_type !== 'all' ? str_replace('_', ' ', $vehicle_type) . ' ' : ''; ?>vehicles
                                <?php else: ?>
                                    No vehicles found
                                <?php endif; ?>
                            </div>
                            <nav>
                                <ul class="pagination pagination-sm mb-0">
                                    <li class="page-item disabled">
                                        <a class="page-link" href="#" tabindex="-1">Previous</a>
                                    </li>
                                    <li class="page-item active"><a class="page-link" href="#">1</a></li>
                                    <li class="page-item"><a class="page-link" href="#">2</a></li>
                                    <li class="page-item"><a class="page-link" href="#">3</a></li>
                                    <li class="page-item">
                                        <a class="page-link" href="#">Next</a>
                                    </li>
                                </ul>
                            </nav>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.2.3/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        // Simple search functionality
        document.querySelector('.search-box input').addEventListener('keyup', function() {
            const searchTerm = this.value.toLowerCase();
            const rows = document.querySelectorAll('tbody tr');
            
            rows.forEach(row => {
                const text = row.textContent.toLowerCase();
                row.style.display = text.includes(searchTerm) ? '' : 'none';
            });
        });
    </script>
</body>
</html>