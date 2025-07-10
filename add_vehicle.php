<?php
session_start();
require_once 'config.php';

// Verify admin is logged in
if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'admin') {
    header('Location: login.php');
    exit();
}

// Process form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        $pdo = getDBConnection();
        
        // Handle file upload
        $image_path = '';
        if (isset($_FILES['vehicle_image']) && $_FILES['vehicle_image']['error'] === UPLOAD_ERR_OK) {
            $upload_dir = 'uploads/vehicles/';
            if (!is_dir($upload_dir)) {
                mkdir($upload_dir, 0755, true);
            }
            
            $file_name = time() . '_' . basename($_FILES['vehicle_image']['name']);
            $target_file = $upload_dir . $file_name;
            
            // Check if image file is actual image
            $check = getimagesize($_FILES['vehicle_image']['tmp_name']);
            if ($check !== false) {
                if (move_uploaded_file($_FILES['vehicle_image']['tmp_name'], $target_file)) {
                    $image_path = $target_file;
                } else {
                    $error = "Sorry, there was an error uploading your file.";
                }
            } else {
                $error = "File is not an image.";
            }
        }
        
        // Insert vehicle data with all fields
        $stmt = $pdo->prepare("INSERT INTO vehicles (
    make, model, year, license_plate, color, daily_rate, hourly_rate, weekly_rate,
    status, seating_capacity, mileage, transmission, fuel_type, image_path, description, vehicle_type
) VALUES (
    :make, :model, :year, :license_plate, :color, :daily_rate, :hourly_rate, :weekly_rate,
    'available', :seating_capacity, :mileage, :transmission, :fuel_type, :image_path, :description, :vehicle_type
)");
        
        $stmt->execute([
            ':make' => $_POST['make'],
            ':model' => $_POST['model'],
            ':year' => $_POST['year'],
            ':license_plate' => $_POST['license_plate'],
            ':color' => $_POST['color'],
            ':daily_rate' => $_POST['daily_rate'],
            ':hourly_rate' => $_POST['hourly_rate'] ?: null,
            ':weekly_rate' => $_POST['weekly_rate'] ?: null,
            ':seating_capacity' => $_POST['seating_capacity'] ?: null,
            ':mileage' => $_POST['mileage'] ?: null,
            ':transmission' => $_POST['transmission'],
            ':fuel_type' => $_POST['fuel_type'],
            ':vehicle_type' => $_POST['vehicle_type'],
            ':image_path' => $image_path,
            ':description' => $_POST['description'] ?? null
        ]);
        
        $_SESSION['success_message'] = "Vehicle added successfully!";
        header('Location: manage_vehicles.php');
        exit();
    } catch (PDOException $e) {
        $error = "Error: " . $e->getMessage();
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Add New Vehicle | Admin Dashboard</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
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
        
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
        }
        
        body {
            background-color: #f9f9f9;
            color: var(--text-color);
            line-height: 1.6;
        }
        
        .dashboard-container {
            display: flex;
            min-height: 100vh;
        }
        
        /* Sidebar Styles */
        .sidebar {
            width: 250px;
            background: white;
            box-shadow: var(--shadow);
            position: fixed;
            height: 100vh;
            transition: all 0.3s;
            z-index: 100;
        }
        
        .sidebar-header {
            padding: 20px;
            background: var(--primary-color);
            color: white;
            text-align: center;
        }
        
        .sidebar-menu {
            list-style: none;
            padding: 20px 0;
        }
        
        .sidebar-menu li a {
            display: flex;
            align-items: center;
            padding: 12px 20px;
            color: var(--text-color);
            text-decoration: none;
            transition: all 0.2s;
        }
        
        .sidebar-menu li a i {
            margin-right: 10px;
            width: 20px;
            text-align: center;
        }
        
        .sidebar-menu li a:hover {
            background: var(--light-gray);
            color: var(--primary-dark);
        }
        
        .sidebar-menu li a.active {
            background: rgba(108, 92, 231, 0.1);
            color: var(--primary-dark);
            border-left: 3px solid var(--primary-color);
        }
        
        /* Main Content Styles */
        .main-content {
            flex: 1;
            margin-left: 250px;
            padding: 20px;
        }
        
        .page-header {
            display: flex;
            align-items: center;
            justify-content: space-between;
            margin-bottom: 30px;
            padding-bottom: 15px;
            border-bottom: 1px solid var(--border-color);
        }
        
        .page-title {
            font-size: 24px;
            color: var(--primary-dark);
            display: flex;
            align-items: center;
        }
        
        .page-title i {
            margin-right: 10px;
            color: var(--primary-color);
        }
        
        /* Form Container */
        .form-container {
            background: white;
            border-radius: 8px;
            box-shadow: var(--shadow);
            padding: 30px;
            margin-bottom: 30px;
        }
        
        .form-group {
            margin-bottom: 25px;
        }
        
        .form-group label {
            display: block;
            margin-bottom: 8px;
            font-weight: 600;
            color: var(--dark-gray);
        }
        
        .form-group label.required:after {
            content: ' *';
            color: var(--danger-color);
        }
        
        .form-group input[type="text"],
        .form-group input[type="number"],
        .form-group input[type="email"],
        .form-group input[type="password"],
        .form-group input[type="date"],
        .form-group input[type="file"],
        .form-group select,
        .form-group textarea {
            width: 100%;
            padding: 12px 15px;
            border: 1px solid var(--border-color);
            border-radius: 4px;
            font-size: 16px;
            transition: border 0.3s;
        }
        
        .form-group input:focus,
        .form-group select:focus,
        .form-group textarea:focus {
            border-color: var(--primary-color);
            outline: none;
            box-shadow: 0 0 0 3px rgba(108, 92, 231, 0.1);
        }
        
        .form-group textarea {
            min-height: 120px;
            resize: vertical;
        }
        
        /* Details Grid */
        .details-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(250px, 1fr));
            gap: 15px;
        }
        
        .details-grid > div {
            margin-bottom: 0;
        }
        
        /* Price Fields */
        .price-fields {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(200px, 1fr));
            gap: 15px;
        }
        
        .price-field {
            background: var(--light-gray);
            padding: 15px;
            border-radius: 4px;
        }
        
        .price-field label {
            font-size: 14px;
            color: var(--text-light);
            margin-bottom: 5px;
            display: block;
        }
        
        /* File Upload */
        .file-upload {
            border: 2px dashed var(--border-color);
            border-radius: 4px;
            padding: 30px;
            text-align: center;
            cursor: pointer;
            transition: all 0.3s;
            margin-bottom: 15px;
        }
        
        .file-upload:hover {
            border-color: var(--primary-color);
            background: rgba(108, 92, 231, 0.05);
        }
        
        .file-upload p {
            margin: 10px 0;
            font-weight: 500;
        }
        
        .file-info {
            font-size: 12px;
            color: var(--text-light);
        }
        
        #vehicle_image {
            display: none;
        }
        
        .image-preview {
            max-width: 100%;
            max-height: 300px;
            display: none;
            margin-top: 15px;
            border-radius: 4px;
            border: 1px solid var(--border-color);
        }
        
        /* Divider */
        .divider {
            height: 1px;
            background: var(--border-color);
            margin: 25px 0;
        }
        
        /* Form Actions */
        .form-actions {
            display: flex;
            justify-content: flex-end;
            gap: 15px;
            margin-top: 30px;
        }
        
        .btn {
            padding: 10px 20px;
            border-radius: 4px;
            font-weight: 600;
            cursor: pointer;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            transition: all 0.3s;
            text-decoration: none;
            border: none;
        }
        
        .btn i {
            margin-right: 8px;
        }
        
        .btn-primary {
            background: var(--primary-color);
            color: white;
        }
        
        .btn-primary:hover {
            background: var(--primary-dark);
        }
        
        .btn-secondary {
            background: var(--medium-gray);
            color: var(--text-color);
        }
        
        .btn-secondary:hover {
            background: #d0d0d0;
        }
        
        /* Responsive */
        @media (max-width: 768px) {
            .sidebar {
                width: 100%;
                height: auto;
                position: relative;
            }
            
            .main-content {
                margin-left: 0;
            }
            
            .details-grid,
            .price-fields {
                grid-template-columns: 1fr;
            }
        }
    </style>
</head>
<body>
    <div class="dashboard-container">
        <!-- Sidebar Navigation -->
        <div class="sidebar">
            <div class="sidebar-header">
                <h3>Admin Dashboard</h3>
            </div>
            <ul class="sidebar-menu">
                <li><a href="admin_dashboard.php"><i class="fas fa-tachometer-alt"></i> Dashboard</a></li>
                <li><a href="manage_vehicles.php"><i class="fas fa-car"></i> Manage Vehicles</a></li>
                <li><a href="add_vehicle.php" class="active"><i class="fas fa-plus-circle"></i> Add Vehicle</a></li>
                <li><a href="reports.php"><i class="fas fa-chart-bar"></i> Reports</a></li>
                <li><a href="settings.php"><i class="fas fa-cog"></i> Settings</a></li>
                <li><a href="logout.php"><i class="fas fa-sign-out-alt"></i> Logout</a></li>
            </ul>
        </div>
        
        <!-- Main Content Area -->
        
    <div class="main-content">
        <div class="page-header">
            <h1 class="page-title"><i class="fas fa-plus-circle"></i> Add New Vehicle</h1>
        </div>
        
        <?php if (isset($error)): ?>
            <div class="error-message" style="color: var(--danger-color); padding: 15px; background: #f8d7da; border-radius: 4px; margin-bottom: 20px;">
                <?php echo htmlspecialchars($error); ?>
            </div>
        <?php endif; ?>
        
        <div class="form-container">
            <form action="add_vehicle.php" method="POST" enctype="multipart/form-data">
                <!-- Basic Information Section -->
                <div class="form-group">
                    <label for="vehicle_name">Vehicle Display Name</label>
                    <input type="text" id="vehicle_name" name="vehicle_name" placeholder="e.g., Honda Civic, Royal Enfield Classic">
                </div>
                
                <div class="divider"></div>
                
                <!-- Vehicle Details Section -->
                <div class="form-group">
                    <label class="required">Vehicle Details</label>
                    <div class="details-grid">
                        <div>
                            <label for="make">Make/Brand</label>
                            <input type="text" id="make" name="make" required>
                        </div>
                        <div>
                            <label for="model">Model</label>
                            <input type="text" id="model" name="model" required>
                        </div>
                        <div>
                            <label for="year">Year</label>
                            <input type="number" id="year" name="year" min="1900" max="<?php echo date('Y'); ?>" required>
                        </div>
                        <div>
                            <label for="license_plate">License Plate</label>
                            <input type="text" id="license_plate" name="license_plate" required>
                        </div>
                        <div>
                            <label for="color">Color</label>
                            <input type="text" id="color" name="color" required>
                        </div>
                        <div>
                            <label for="mileage">Mileage (km)</label>
                            <input type="number" id="mileage" name="mileage" min="0">
                        </div>
                        <div>
                            <label for="seating_capacity">Seating Capacity</label>
                            <input type="number" id="seating_capacity" name="seating_capacity" min="1">
                        </div>
                        <div>
                            <label for="transmission">Transmission</label>
                            <select id="transmission" name="transmission" required>
                                <option value="automatic">Automatic</option>
                                <option value="manual">Manual</option>
                            </select>
                        </div>

                        <div>
                            <label for="fuel_type">Fuel Type</label>
                            <select id="fuel_type" name="fuel_type" required>
                                <option value="petrol">Petrol</option>
                                <option value="diesel">Diesel</option>
                                <option value="electric">Electric</option>
                                <option value="hybrid">Hybrid</option>
                                <option value="cng">CNG</option>
                            </select>
                        </div>
                        <div>
    <label for="vehicle_type">Vehicle Type</label>
    <select id="vehicle_type" name="vehicle_type" required>
        <option value="two_wheeler">Two Wheeler</option>
        <option value="four_wheeler" selected>Four Wheeler</option>
        <option value="heavy">Heavy Vehicle</option>
    </select>
</div>
                    </div>
                </div>
                
                <div class="divider"></div>
                
                <!-- Description Section -->
                <div class="form-group">
                    <label for="description">Description</label>
                    <textarea id="description" name="description" placeholder="Describe the vehicle features, condition, and specifications"></textarea>
                </div>
                
                <div class="divider"></div>
                
                <!-- Pricing Section -->
                <div class="form-group">
                    <label class="required">Pricing</label>
                    <div class="price-fields">
                        <div class="price-field">
                            <label>Daily Rate (₹)</label>
                            <input type="number" name="daily_rate" min="0" step="0.01" required>
                        </div>
                        <div class="price-field">
                            <label>Hourly Rate (₹)</label>
                            <input type="number" name="hourly_rate" min="0" step="0.01">
                        </div>
                        <div class="price-field">
                            <label>Weekly Rate (₹)</label>
                            <input type="number" name="weekly_rate" min="0" step="0.01">
                        </div>
                    </div>
                </div>
                
                <div class="divider"></div>
                
                <!-- Image Upload Section -->
                <div class="form-group">
                    <label>Vehicle Image</label>
                    <div class="file-upload" onclick="document.getElementById('vehicle_image').click()">
                        <i class="fas fa-cloud-upload-alt" style="font-size: 24px; color: #666;"></i>
                        <p>Click to upload or drag and drop</p>
                        <div class="file-info">Accepted formats: JPG, PNG (Max 2MB)</div>
                    </div>
                    <input type="file" id="vehicle_image" name="vehicle_image" accept="image/jpeg,image/png" onchange="previewImage(this)">
                    <img id="imagePreview" src="#" alt="Preview" class="image-preview">
                </div>
                
                <!-- Form Actions -->
                <div class="form-actions">
                    <a href="admin_dashboard.php" class="btn btn-secondary">
                        <i class="fas fa-times"></i> Cancel
                    </a>
                    <button type="submit" class="btn btn-primary">
                        <i class="fas fa-plus"></i> Add Vehicle
                    </button>
                </div>
            </form>
        </div>
    </div>

    <script>
        // Image preview functionality
        function previewImage(input) {
            const preview = document.getElementById('imagePreview');
            const file = input.files[0];
            
            if (file) {
                const reader = new FileReader();
                
                reader.onload = function(e) {
                    preview.src = e.target.result;
                    preview.style.display = 'block';
                }
                
                reader.readAsDataURL(file);
            }
        }
        
        // Validate file size before upload
        document.getElementById('vehicle_image').addEventListener('change', function() {
            const file = this.files[0];
            if (file && file.size > 2 * 1024 * 1024) { // 2MB
                alert('File size must be less than 2MB');
                this.value = '';
                document.getElementById('imagePreview').style.display = 'none';
            }
        });
    </script>
</body>
</html>