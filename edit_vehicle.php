<?php
session_start();
require_once 'config.php';

// Verify admin is logged in
if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'admin') {
    header('Location: login.php');
    exit();
}

// Check if vehicle ID is provided
if (!isset($_GET['id'])) {
    $_SESSION['error_message'] = "Vehicle ID not provided!";
    header('Location: manage_vehicles.php');
    exit();
}

$vehicle_id = $_GET['id'];
$vehicle = null;
$error = null;

// Fetch vehicle data
try {
    $pdo = getDBConnection();
    
    // First try with 'id' as primary key
    try {
        $stmt = $pdo->prepare("SELECT * FROM vehicles WHERE id = ?");
        $stmt->execute([$vehicle_id]);
        $vehicle = $stmt->fetch(PDO::FETCH_ASSOC);
    } catch (PDOException $e) {
        // If that fails, try with 'vehicle_id' as primary key
        $stmt = $pdo->prepare("SELECT * FROM vehicles WHERE vehicle_id = ?");
        $stmt->execute([$vehicle_id]);
        $vehicle = $stmt->fetch(PDO::FETCH_ASSOC);
    }
    
    if (!$vehicle) {
        $_SESSION['error_message'] = "Vehicle not found!";
        header('Location: manage_vehicles.php');
        exit();
    }
} catch (PDOException $e) {
    $error = "Error fetching vehicle: " . $e->getMessage();
}

// Process form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        $pdo = getDBConnection();
        
        // Initialize image path with existing value
        $image_path = $vehicle['image_path'] ?? '';
        
        // Handle image removal if checkbox is checked
        if (isset($_POST['remove_image']) && $_POST['remove_image'] === 'on') {
            if (!empty($image_path) && file_exists($image_path)) {
                unlink($image_path);
            }
            $image_path = '';
        }
        
        // Handle new file upload
        if (isset($_FILES['vehicle_image']) && $_FILES['vehicle_image']['error'] === UPLOAD_ERR_OK) {
            $upload_dir = 'uploads/vehicles/';
            if (!is_dir($upload_dir)) {
                mkdir($upload_dir, 0755, true);
            }
            
            // Delete old image if it exists
            if (!empty($image_path) && file_exists($image_path)) {
                unlink($image_path);
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
        
        // Determine the primary key column name
        $primary_key = isset($vehicle['id']) ? 'id' : 'vehicle_id';
        
        // Update vehicle data
        // In the UPDATE statement, remove the monthly_rate line
$stmt = $pdo->prepare("UPDATE vehicles SET
    make = :make,
    model = :model,
    year = :year,
    license_plate = :license_plate,
    color = :color,
    daily_rate = :daily_rate,
    hourly_rate = :hourly_rate,
    weekly_rate = :weekly_rate,
    status = :status,
    seating_capacity = :seating_capacity,
    mileage = :mileage,
    transmission = :transmission,
    fuel_type = :fuel_type,
    image_path = :image_path,
    description = :description
    WHERE $primary_key = :id");
        
        $stmt->execute([
            ':make' => $_POST['make'],
            ':model' => $_POST['model'],
            ':year' => $_POST['year'],
            ':license_plate' => $_POST['license_plate'],
            ':color' => $_POST['color'],
            ':daily_rate' => $_POST['daily_rate'],
            ':hourly_rate' => $_POST['hourly_rate'] ?: null,
            ':weekly_rate' => $_POST['weekly_rate'] ?: null,
            ':status' => $_POST['status'],
            ':seating_capacity' => $_POST['seating_capacity'] ?: null,
            ':mileage' => $_POST['mileage'] ?: null,
            ':transmission' => $_POST['transmission'],
            ':fuel_type' => $_POST['fuel_type'],
            ':image_path' => $image_path,
            ':description' => $_POST['description'] ?? null,
            ':id' => $vehicle_id
        ]);
        
        $_SESSION['success_message'] = "Vehicle updated successfully!";
        header('Location: manage_vehicles.php');
        exit();
    } catch (PDOException $e) {
        $error = "Error updating vehicle: " . $e->getMessage();
    }
}

// If we get here and $vehicle is still null, redirect
if ($vehicle === null) {
    $_SESSION['error_message'] = "Vehicle data could not be loaded!";
    header('Location: manage_vehicles.php');
    exit();
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Edit Vehicle | Admin Dashboard</title>
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
        }
        
        .card {
            border-radius: 10px;
            box-shadow: var(--shadow);
            border: none;
        }
        
        .form-control, .form-select {
            border-radius: 5px;
            padding: 10px 15px;
        }
        
        .form-control:focus, .form-select:focus {
            border-color: var(--primary-color);
            box-shadow: 0 0 0 0.25rem rgba(108, 92, 231, 0.25);
        }
        
        .btn-primary {
            background-color: var(--primary-color);
            border-color: var(--primary-color);
        }
        
        .btn-primary:hover {
            background-color: var(--primary-dark);
            border-color: var(--primary-dark);
        }
        
        .btn-outline-secondary {
            border-radius: 5px;
        }
        
        .file-upload {
            border: 2px dashed var(--border-color);
            border-radius: 5px;
            padding: 30px;
            text-align: center;
            cursor: pointer;
            transition: all 0.3s;
        }
        
        .file-upload:hover {
            border-color: var(--primary-color);
            background-color: rgba(108, 92, 231, 0.05);
        }
        
        .image-preview {
            max-width: 100%;
            max-height: 300px;
            border-radius: 5px;
            border: 1px solid var(--border-color);
            margin-top: 15px;
        }
        
        .price-field {
            background-color: var(--light-gray);
            padding: 15px;
            border-radius: 5px;
            margin-bottom: 15px;
        }
        
        .price-field label {
            font-size: 14px;
            color: var(--text-light);
        }
        
        .page-title {
            color: var(--primary-dark);
            font-weight: 600;
        }
        
        .status-badge {
            display: inline-block;
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
    </style>
</head>
<body>
    <div class="container py-4">
        <div class="row mb-4">
            <div class="col-12">
                <h2 class="page-title">
                    <i class="fas fa-edit me-2"></i>Edit Vehicle
                </h2>
            </div>
        </div>
        
        <?php if (isset($error)): ?>
            <div class="alert alert-danger alert-dismissible fade show">
                <?php echo $error; ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
            </div>
        <?php endif; ?>
        
        <div class="card">
            <div class="card-body">
                <form action="edit_vehicle.php?id=<?php echo $vehicle_id; ?>" method="POST" enctype="multipart/form-data">
                    <div class="row">
                        <div class="col-md-8">
                            <!-- Basic Information -->
                            <div class="mb-4">
                                <h5 class="mb-3">Basic Information</h5>
                                <div class="row g-3">
                                    <div class="col-md-6">
                                        <label for="make" class="form-label">Make/Brand</label>
                                        <input type="text" class="form-control" id="make" name="make" value="<?php echo htmlspecialchars($vehicle['make'] ?? ''); ?>" required>
                                    </div>
                                    <div class="col-md-6">
                                        <label for="model" class="form-label">Model</label>
                                        <input type="text" class="form-control" id="model" name="model" value="<?php echo htmlspecialchars($vehicle['model'] ?? ''); ?>" required>
                                    </div>
                                    <div class="col-md-4">
                                        <label for="year" class="form-label">Year</label>
                                        <input type="number" class="form-control" id="year" name="year" min="1900" max="<?php echo date('Y'); ?>" value="<?php echo htmlspecialchars($vehicle['year'] ?? ''); ?>" required>
                                    </div>
                                    <div class="col-md-4">
                                        <label for="license_plate" class="form-label">License Plate</label>
                                        <input type="text" class="form-control" id="license_plate" name="license_plate" value="<?php echo htmlspecialchars($vehicle['license_plate'] ?? ''); ?>" required>
                                    </div>
                                    <div class="col-md-4">
                                        <label for="color" class="form-label">Color</label>
                                        <input type="text" class="form-control" id="color" name="color" value="<?php echo htmlspecialchars($vehicle['color'] ?? ''); ?>" required>
                                    </div>
                                </div>
                            </div>
                            
                            <!-- Vehicle Details -->
                            <div class="mb-4">
                                <h5 class="mb-3">Vehicle Details</h5>
                                <div class="row g-3">
                                    <div class="col-md-4">
                                        <label for="transmission" class="form-label">Transmission</label>
                                        <select class="form-select" id="transmission" name="transmission" required>
                                            <option value="automatic" <?php echo ($vehicle['transmission'] ?? '') === 'automatic' ? 'selected' : ''; ?>>Automatic</option>
                                            <option value="manual" <?php echo ($vehicle['transmission'] ?? '') === 'manual' ? 'selected' : ''; ?>>Manual</option>
                                        </select>
                                    </div>
                                    <div class="col-md-4">
                                        <label for="fuel_type" class="form-label">Fuel Type</label>
                                        <select class="form-select" id="fuel_type" name="fuel_type" required>
                                            <option value="petrol" <?php echo ($vehicle['fuel_type'] ?? '') === 'petrol' ? 'selected' : ''; ?>>Petrol</option>
                                            <option value="diesel" <?php echo ($vehicle['fuel_type'] ?? '') === 'diesel' ? 'selected' : ''; ?>>Diesel</option>
                                            <option value="electric" <?php echo ($vehicle['fuel_type'] ?? '') === 'electric' ? 'selected' : ''; ?>>Electric</option>
                                            <option value="hybrid" <?php echo ($vehicle['fuel_type'] ?? '') === 'hybrid' ? 'selected' : ''; ?>>Hybrid</option>
                                            <option value="cng" <?php echo ($vehicle['fuel_type'] ?? '') === 'cng' ? 'selected' : ''; ?>>CNG</option>
                                        </select>
                                    </div>
                                    <div class="col-md-4">
                                        <label for="status" class="form-label">Status</label>
                                        <select class="form-select" id="status" name="status" required>
                                            <option value="available" <?php echo ($vehicle['status'] ?? '') === 'available' ? 'selected' : ''; ?>>Available</option>
                                            <option value="booked" <?php echo ($vehicle['status'] ?? '') === 'booked' ? 'selected' : ''; ?>>Booked</option>
                                            <option value="maintenance" <?php echo ($vehicle['status'] ?? '') === 'maintenance' ? 'selected' : ''; ?>>Maintenance</option>
                                            <option value="out_of_service" <?php echo ($vehicle['status'] ?? '') === 'out_of_service' ? 'selected' : ''; ?>>Out of Service</option>
                                        </select>
                                    </div>
                                    <div class="col-md-4">
                                        <label for="seating_capacity" class="form-label">Seating Capacity</label>
                                        <input type="number" class="form-control" id="seating_capacity" name="seating_capacity" min="1" value="<?php echo htmlspecialchars($vehicle['seating_capacity'] ?? ''); ?>">
                                    </div>
                                    <div class="col-md-4">
                                        <label for="mileage" class="form-label">Mileage (km)</label>
                                        <input type="number" class="form-control" id="mileage" name="mileage" min="0" value="<?php echo htmlspecialchars($vehicle['mileage'] ?? ''); ?>">
                                    </div>
                                </div>
                            </div>
                            
                            <!-- Description -->
                            <div class="mb-4">
                                <h5 class="mb-3">Description</h5>
                                <textarea class="form-control" id="description" name="description" rows="4"><?php echo htmlspecialchars($vehicle['description'] ?? ''); ?></textarea>
                            </div>
                        </div>
                        
                        <div class="col-md-4">
                            <!-- Pricing Section -->
                            <div class="mb-4">
                                <h5 class="mb-3">Pricing</h5>
                                
                                <div class="price-field">
                                    <label for="hourly_rate" class="form-label">Hourly Rate (₹)</label>
                                    <div class="input-group">
                                        <span class="input-group-text">₹</span>
                                        <input type="number" class="form-control" id="hourly_rate" name="hourly_rate" min="0" step="0.01" value="<?php echo htmlspecialchars($vehicle['hourly_rate'] ?? ''); ?>">
                                    </div>
                                </div>
                                
                                <div class="price-field">
                                    <label for="daily_rate" class="form-label">Daily Rate (₹)</label>
                                    <div class="input-group">
                                        <span class="input-group-text">₹</span>
                                        <input type="number" class="form-control" id="daily_rate" name="daily_rate" min="0" step="0.01" value="<?php echo htmlspecialchars($vehicle['daily_rate'] ?? ''); ?>" required>
                                    </div>
                                </div>
                                
                                <div class="price-field">
                                    <label for="weekly_rate" class="form-label">Weekly Rate (₹)</label>
                                    <div class="input-group">
                                        <span class="input-group-text">₹</span>
                                        <input type="number" class="form-control" id="weekly_rate" name="weekly_rate" min="0" step="0.01" value="<?php echo htmlspecialchars($vehicle['weekly_rate'] ?? ''); ?>">
                                    </div>
                                </div>
                                
                            
                            <!-- Current Status Display -->
                            <div class="mb-4">
                                <h5 class="mb-3">Current Status</h5>
                                <div class="d-flex align-items-center">
                                    <span class="status-badge status-<?php echo htmlspecialchars($vehicle['status'] ?? ''); ?> me-2">
                                        <?php echo ucfirst(htmlspecialchars($vehicle['status'] ?? '')); ?>
                                    </span>
                                    <?php if (($vehicle['status'] ?? '') === 'available'): ?>
                                        <span class="text-success"><i class="fas fa-check-circle me-1"></i> Ready for booking</span>
                                    <?php elseif (($vehicle['status'] ?? '') === 'booked'): ?>
                                        <span class="text-warning"><i class="fas fa-calendar-times me-1"></i> Currently booked</span>
                                    <?php elseif (($vehicle['status'] ?? '') === 'maintenance'): ?>
                                        <span class="text-danger"><i class="fas fa-tools me-1"></i> Under maintenance</span>
                                    <?php endif; ?>
                                </div>
                            </div>
                            
                            <!-- Image Upload -->
                            <div class="mb-4">
                                <h5 class="mb-3">Vehicle Image</h5>
                                <div class="file-upload" onclick="document.getElementById('vehicle_image').click()">
                                    <i class="fas fa-cloud-upload-alt fa-2x mb-2" style="color: var(--primary-color);"></i>
                                    <p class="mb-1">Click to upload or drag and drop</p>
                                    <small class="text-muted">Accepted formats: JPG, PNG (Max 2MB)</small>
                                </div>
                                <input type="file" id="vehicle_image" name="vehicle_image" accept="image/jpeg,image/png" onchange="previewImage(this)" style="display: none;">
                                
                                <?php if (!empty($vehicle['image_path'])): ?>
                                    <img id="imagePreview" src="<?php echo htmlspecialchars($vehicle['image_path']); ?>" alt="Current Vehicle Image" class="image-preview mt-3">
                                    <div class="form-check mt-2">
                                        <input class="form-check-input" type="checkbox" id="remove_image" name="remove_image">
                                        <label class="form-check-label" for="remove_image">
                                            Remove current image
                                        </label>
                                    </div>
                                <?php else: ?>
                                    <img id="imagePreview" src="#" alt="Preview" class="image-preview mt-3" style="display: none;">
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                    
                    <div class="d-flex justify-content-end mt-4">
                        <a href="manage_vehicles.php" class="btn btn-outline-secondary me-2">
                            <i class="fas fa-times me-1"></i> Cancel
                        </a>
                        <button type="submit" class="btn btn-primary">
                            <i class="fas fa-save me-1"></i> Save Changes
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.2.3/dist/js/bootstrap.bundle.min.js"></script>
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
        
        // Auto-calculate weekly and monthly rates based on daily rate
        document.getElementById('daily_rate').addEventListener('change', function() {
            const dailyRate = parseFloat(this.value);
            if (!isNaN(dailyRate)) {
                // Calculate weekly rate (5 days)
                document.getElementById('weekly_rate').value = (dailyRate * 5).toFixed(2);
                // Calculate monthly rate (20 days)
               
            }
        });
    </script>
</body>
</html>