<?php
session_start();
require_once 'config.php';

// Redirect if not logged in
if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit();
}

// Check if vehicle_id is provided
if (!isset($_GET['vehicle_id'])) {
    header('Location: four_wheelers.php');
    exit();
}

$vehicle_id = $_GET['vehicle_id'];
$vehicle = null;
$user = null;
$error = null;

try {
    $pdo = getDBConnection();
    
    // Get four-wheeler details
    $stmt = $pdo->prepare("SELECT * FROM vehicles WHERE vehicle_id = ? AND vehicle_type = 'four_wheeler'");
    $stmt->execute([$vehicle_id]);
    $vehicle = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$vehicle) {
        throw new Exception("Four-wheeler not found or unavailable");
    }
    
    // Get user details
    $stmt = $pdo->prepare("SELECT * FROM users WHERE user_id = ?");
    $stmt->execute([$_SESSION['user_id']]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$user) {
        throw new Exception("User not found");
    }
    
} catch (PDOException $e) {
    error_log("Database error: " . $e->getMessage());
    $error = "Database error occurred. Please try again later.";
} catch (Exception $e) {
    $error = $e->getMessage();
}

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        // Validate input
        $full_name = $_POST['full_name'];
        $email = $_POST['email'];
        $phone = $_POST['phone'];
        $pickup_date = $_POST['pickup_date'];
        $return_date = $_POST['return_date'];
        $child_seat = isset($_POST['child_seat']) ? 1 : 0;
        $gps_navigation = isset($_POST['gps_navigation']) ? 1 : 0;
        
        if (empty($full_name) || empty($email) || empty($phone) || empty($pickup_date) || empty($return_date)) {
            throw new Exception("Please fill in all required fields");
        }
        
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            throw new Exception("Please enter a valid email address");
        }
        
        if (strtotime($pickup_date) > strtotime($return_date)) {
            throw new Exception("Return date must be after pickup date");
        }
        
        // Check if payment proof was uploaded
        if (!isset($_FILES['payment_proof']) || $_FILES['payment_proof']['error'] !== UPLOAD_ERR_OK) {
    throw new Exception("Please upload payment screenshot");
}
        
        // Validate image file
        $allowed_types = ['image/jpeg', 'image/png', 'image/gif'];
        $file_info = finfo_open(FILEINFO_MIME_TYPE);
        $mime_type = finfo_file($file_info, $_FILES['payment_proof']['tmp_name']);
        
        if (!in_array($mime_type, $allowed_types)) {
            throw new Exception("Only JPG, PNG, or GIF images are allowed");
        }
        
        // Calculate days and total price
        $pickup = new DateTime($pickup_date);
        $return = new DateTime($return_date);
        $interval = $pickup->diff($return);
        $days = $interval->days + 1; // Include both start and end day
        
        // Calculate additional charges for accessories
        $child_seat_charge = $child_seat ? 200 * $days : 0;
        $gps_charge = $gps_navigation ? 150 * $days : 0;
        
        $total_price = ($days * $vehicle['daily_rate']) + $child_seat_charge + $gps_charge;
        
        // Upload payment proof
     $upload_dir = 'uploads/payments/';
if (!is_dir($upload_dir)) {
    if (!mkdir($upload_dir, 0755, true)) {
        throw new Exception("Failed to create upload directory");
    }
}
        
       $file_ext = strtolower(pathinfo($_FILES['payment_proof']['name'], PATHINFO_EXTENSION));
$allowed_ext = ['jpg', 'jpeg', 'png', 'gif'];
if (!in_array($file_ext, $allowed_ext)) {
    throw new Exception("Only JPG, PNG, or GIF images are allowed");
}

$file_name = 'payment_' . $_SESSION['user_id'] . '_' . time() . '.' . $file_ext;
$file_path = $upload_dir . $file_name;

if (!move_uploaded_file($_FILES['payment_proof']['tmp_name'], $file_path)) {
    throw new Exception("Failed to upload payment screenshot. Please try again.");
}
        // Insert booking into database
        $stmt = $pdo->prepare("INSERT INTO bookings 
                              (user_id, vehicle_id, pickup_date, return_date, 
                               total_price, payment_method, status, payment_screenshot,
                               child_seat, gps_navigation) 
                              VALUES (?, ?, ?, ?, ?, 'upi', 'pending', ?, ?, ?)");
        $stmt->execute([
            $_SESSION['user_id'],
            $vehicle_id,
            $pickup_date,
            $return_date,
            $total_price,
            $file_path,
            $child_seat,
            $gps_navigation
        ]);
        
        
        // Update user information in database
        $stmt = $pdo->prepare("UPDATE users SET full_name = ?, email = ?, phone = ? WHERE user_id = ?");
        $stmt->execute([$full_name, $email, $phone, $_SESSION['user_id']]);
        
        $booking_id = $pdo->lastInsertId();
$_SESSION['last_booking_id'] = $booking_id;
header("Location: booking_status.php?booking_id=".$booking_id);
exit();
        
        
    } catch (Exception $e) {
        $error = $e->getMessage();
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Book Four-Wheeler | Vehicle Rental</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <style>
        :root {
            --primary-color: #3498db;
            --secondary-color: #2ecc71;
            --dark-color: #2c3e50;
            --light-color: #ecf0f1;
            --danger-color: #e74c3c;
            --warning-color: #f39c12;
            --border-radius: 8px;
            --box-shadow: 0 4px 10px rgba(0,0,0,0.1);
            --transition: all 0.3s ease;
        }
        
        body {
            font-family: 'Poppins', sans-serif;
            margin: 0;
            padding: 0;
            background-color: #f5f7fa;
            color: var(--dark-color);
            line-height: 1.6;
        }
        
        .container {
            max-width: 1000px;
            margin: 0 auto;
            padding: 20px;
        }
        
        .header {
            text-align: center;
            margin-bottom: 30px;
        }
        
        .header h1 {
            color: var(--primary-color);
            margin-bottom: 10px;
        }
        
        .header p {
            color: #666;
        }
        
        .back-btn {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            margin-bottom: 20px;
            color: var(--primary-color);
            text-decoration: none;
            font-weight: 600;
            padding: 8px 15px;
            border-radius: var(--border-radius);
            transition: var(--transition);
        }
        
        .back-btn:hover {
            background-color: #f0f7ff;
        }
        
        .booking-container {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 30px;
        }
        
        .vehicle-card {
            background: white;
            border-radius: var(--border-radius);
            box-shadow: var(--box-shadow);
            overflow: hidden;
        }
        
        .vehicle-image-container {
            height: 250px;
            overflow: hidden;
        }
        
        .vehicle-image {
            width: 100%;
            height: 100%;
            object-fit: cover;
        }
        
        .vehicle-details {
            padding: 20px;
        }
        
        .vehicle-title {
            font-size: 1.5rem;
            margin: 0 0 10px 0;
        }
        
        .vehicle-specs {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 15px;
            margin-bottom: 15px;
        }
        
        .spec-item {
            display: flex;
            align-items: center;
            gap: 8px;
        }
        
        .spec-item i {
            color: var(--primary-color);
            width: 20px;
            text-align: center;
        }
        
        .price-display {
            background: #f8f9fa;
            padding: 15px;
            border-radius: var(--border-radius);
            margin-top: 15px;
        }
        
        .price-row {
            display: flex;
            justify-content: space-between;
            margin-bottom: 8px;
        }
        
        .price-label {
            font-weight: 600;
        }
        
        .price-value {
            color: var(--primary-color);
            font-weight: 700;
        }
        
        .booking-form {
            background: white;
            padding: 30px;
            border-radius: var(--border-radius);
            box-shadow: var(--box-shadow);
        }
        
        .form-title {
            font-size: 1.5rem;
            margin: 0 0 20px 0;
            color: var(--dark-color);
        }
        
        .form-group {
            margin-bottom: 20px;
        }
        
        .form-label {
            display: block;
            margin-bottom: 8px;
            font-weight: 600;
        }
        
        .form-control {
            width: 100%;
            padding: 12px 15px;
            border: 1px solid #ddd;
            border-radius: var(--border-radius);
            font-size: 1rem;
            transition: var(--transition);
        }
        
        .form-control:focus {
            border-color: var(--primary-color);
            outline: none;
            box-shadow: 0 0 0 3px rgba(52, 152, 219, 0.2);
        }
        
        .date-inputs {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 15px;
        }
        
        .btn {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            gap: 10px;
            background: var(--primary-color);
            color: white;
            padding: 12px 25px;
            border: none;
            border-radius: var(--border-radius);
            font-size: 1rem;
            font-weight: 600;
            cursor: pointer;
            transition: var(--transition);
            width: 100%;
        }
        
        .btn:hover {
            background: #2980b9;
            transform: translateY(-2px);
        }
        
        .btn i {
            font-size: 1rem;
        }
        
        .error-message {
            color: var(--danger-color);
            background: #fadbd8;
            padding: 15px;
            border-radius: var(--border-radius);
            margin-bottom: 20px;
            display: flex;
            align-items: center;
            gap: 10px;
        }
        
        .error-message i {
            font-size: 1.2rem;
        }
        
        .user-info {
            background: white;
            padding: 20px;
            border-radius: var(--border-radius);
            margin-bottom: 20px;
            border: 1px solid #eee;
        }
        
        .accessories-section {
            margin-bottom: 20px;
        }
        
        .accessory-option {
            display: flex;
            align-items: center;
            gap: 10px;
            margin-bottom: 10px;
            padding: 10px;
            background: #f8f9fa;
            border-radius: var(--border-radius);
        }
        
        .accessory-option input {
            margin: 0;
        }
        
        .accessory-price {
            margin-left: auto;
            color: var(--primary-color);
            font-weight: 600;
        }
        
        .price-summary {
            background: #f8f9fa;
            padding: 20px;
            border-radius: var(--border-radius);
            margin-top: 20px;
        }
        
        .summary-row {
            display: flex;
            justify-content: space-between;
            margin-bottom: 10px;
            padding-bottom: 10px;
            border-bottom: 1px solid #eee;
        }
        
        .summary-row:last-child {
            border-bottom: none;
            margin-bottom: 0;
            padding-bottom: 0;
        }
        
        .total-row {
            font-weight: 700;
            font-size: 1.1rem;
            color: var(--primary-color);
        }
        
        .upi-section {
            background: #f8f9fa;
            padding: 20px;
            border-radius: var(--border-radius);
            margin: 20px 0;
        }
        
        .upi-id {
            display: flex;
            align-items: center;
            gap: 10px;
            background: white;
            padding: 15px;
            border-radius: var(--border-radius);
            border: 1px dashed #ccc;
            margin: 15px 0;
            font-weight: 600;
        }
        
        .upi-id i {
            color: var(--primary-color);
            font-size: 1.5rem;
        }
        .file-upload-container {
    margin-bottom: 20px;
}

.file-upload-box {
    border: 2px dashed #ccc;
    border-radius: var(--border-radius);
    padding: 20px;
    text-align: center;
    cursor: pointer;
    transition: var(--transition);
    position: relative;
    overflow: hidden;
}

.file-upload-box:hover {
    border-color: var(--primary-color);
    background-color: #f8faff;
}

.file-upload-box input[type="file"] {
    position: absolute;
    width: 100%;
    height: 100%;
    top: 0;
    left: 0;
    opacity: 0;
    cursor: pointer;
}

.upload-content {
    pointer-events: none;
}

.upload-content i {
    font-size: 2.5rem;
    color: var(--primary-color);
    margin-bottom: 10px;
}

.upload-content p {
    margin: 5px 0;
    color: #666;
}

.file-requirements {
    font-size: 0.8rem;
    color: #999;
}

.preview-container {
    margin-top: 15px;
    display: none;
}

.preview-container img {
    max-width: 100%;
    max-height: 200px;
    border-radius: var(--border-radius);
    border: 1px solid #eee;
}

.error-text {
    color: var(--danger-color);
    font-size: 0.9rem;
    margin-top: 5px;
    display: none;
}
        
        @media (max-width: 768px) {
            .booking-container {
                grid-template-columns: 1fr;
            }
            
            .date-inputs {
                grid-template-columns: 1fr;
            }
        }
    </style>
</head>
<body>
    <div class="container">
        <a href="four_wheelers.php" class="back-btn">
            <i class="fas fa-arrow-left"></i> Back to Four-Wheelers
        </a>
        
        <div class="header">
            <h1>Complete your booking details and enjoy the ride!</h1>
        </div>
        
        <?php if ($error): ?>
            <div class="error-message">
                <i class="fas fa-exclamation-circle"></i>
                <span><?php echo htmlspecialchars($error); ?></span>
            </div>
        <?php endif; ?>
        
        <div class="booking-container">
            <div class="vehicle-card">
                <div class="vehicle-image-container">
                    <img src="<?php echo htmlspecialchars($vehicle['image_path'] ?? 'images/default-car.jpg'); ?>" 
                         alt="<?php echo htmlspecialchars($vehicle['make'] . ' ' . $vehicle['model']); ?>" 
                         class="vehicle-image">
                </div>
                <div class="vehicle-details">
                    <h2 class="vehicle-title"><?php echo htmlspecialchars($vehicle['make'] . ' ' . $vehicle['model']); ?></h2>
                    <div class="vehicle-specs">
                        <div class="spec-item">
                            <i class="fas fa-calendar-alt"></i>
                            <span>Year: <?php echo htmlspecialchars($vehicle['year']); ?></span>
                        </div>
                        <div class="spec-item">
                            <i class="fas fa-palette"></i>
                            <span>Color: <?php echo htmlspecialchars($vehicle['color']); ?></span>
                        </div>
                        <div class="spec-item">
                            <i class="fas fa-tag"></i>
                            <span>License: <?php echo htmlspecialchars($vehicle['license_plate']); ?></span>
                        </div>
                        <div class="spec-item">
                            <i class="fas fa-gas-pump"></i>
                            <span>Fuel: <?php echo htmlspecialchars($vehicle['fuel_type']); ?></span>
                        </div>
                    </div>
                    <div class="price-display">
                        <div class="price-row">
                            <span class="price-label">Daily Rate:</span>
                            <span class="price-value">₹<?php echo number_format($vehicle['daily_rate'], 2); ?></span>
                        </div>
                        <div class="price-row">
                            <span class="price-label">Availability:</span>
                            <span class="price-value">Available</span>
                        </div>
                    </div>
                </div>
            </div>
            
            <form method="POST" class="booking-form" enctype="multipart/form-data">
                <h2 class="form-title">Booking Details</h2>
                
                <div class="user-info">
                    <h3>Your Information</h3>
                    
                    <div class="form-group">
                        <label for="full_name" class="form-label">Full Name</label>
                        <input type="text" id="full_name" name="full_name" class="form-control" 
                               value="<?php echo htmlspecialchars($user['full_name']); ?>" required>
                    </div>
                    
                    <div class="form-group">
                        <label for="email" class="form-label">Email</label>
                        <input type="email" id="email" name="email" class="form-control" 
                               value="<?php echo htmlspecialchars($user['email']); ?>" required>
                    </div>
                    
                    <div class="form-group">
                        <label for="phone" class="form-label">Phone Number</label>
                        <input type="tel" id="phone" name="phone" class="form-control" 
                               value="<?php echo htmlspecialchars($user['phone']); ?>"
                               pattern="[0-9]{10}" title="Please enter a 10-digit phone number" required>
                    </div>
                </div>
                
                <div class="form-group date-inputs">
                    <div>
                        <label for="pickup_date" class="form-label">Pickup Date</label>
                        <input type="date" id="pickup_date" name="pickup_date" class="form-control" 
                               min="<?php echo date('Y-m-d'); ?>" required>
                    </div>
                    <div>
                        <label for="return_date" class="form-label">Return Date</label>
                        <input type="date" id="return_date" name="return_date" class="form-control" 
                               min="<?php echo date('Y-m-d'); ?>" required>
                    </div>
                </div>
                
                <div class="form-group accessories-section">
                    <label class="form-label">Add Accessories</label>
                    <div class="accessory-option">
                        <input type="checkbox" id="child_seat" name="child_seat" value="1">
                        <label for="child_seat">Child Seat (₹200/day)</label>
                        <span class="accessory-price" id="child-seat-price">₹0</span>
                    </div>
                    <div class="accessory-option">
                        <input type="checkbox" id="gps_navigation" name="gps_navigation" value="1">
                        <label for="gps_navigation">GPS Navigation (₹150/day)</label>
                        <span class="accessory-price" id="gps-price">₹0</span>
                    </div>
                </div>
                
                <div class="upi-section">
                    <h3>Payment Method</h3>
                    <p>Please complete the payment via UPI to confirm your booking.</p>
                    
                    <div class="upi-id">
                        <i class="fas fa-qrcode"></i>
                        <span>dharaniyamoorthy15@okaxis</span>
                    </div>
                    
                    <p>Scan this UPI ID using any UPI app to make payment. After payment, upload the screenshot below.</p>
                    
                   <div class="file-upload-container">
    <label class="form-label">Payment Proof</label>
    <div class="file-upload-box" id="fileUploadBox">
        <input type="file" id="payment_proof" name="payment_proof" accept="image/*" required 
               onchange="handleFileSelect(this)">
        <div class="upload-content">
            <i class="fas fa-cloud-upload-alt"></i>
            <p>Click to upload payment screenshot</p>
            <p class="file-info">or drag and drop image here</p>
            <p class="file-requirements">(JPG, PNG, or GIF, max 5MB)</p>
        </div>
        <div class="preview-container" id="previewContainer"></div>
    </div>
    <div id="file-error" class="error-text"></div>
</div>
                    <div id="file-name" style="margin-top: 10px; font-size: 0.9rem;"></div>
                </div>
                
                <div class="price-summary">
                    <h3>Price Summary</h3>
                    <div class="summary-row">
                        <span>Daily Rate:</span>
                        <span>₹<?php echo number_format($vehicle['daily_rate'], 2); ?></span>
                    </div>
                    <div class="summary-row">
                        <span>Rental Days:</span>
                        <span id="days-display">0</span>
                    </div>
                    <div class="summary-row" id="child-seat-summary" style="display: none;">
                        <span>Child Seat Charge:</span>
                        <span id="child-seat-charge">₹0</span>
                    </div>
                    <div class="summary-row" id="gps-summary" style="display: none;">
                        <span>GPS Navigation Charge:</span>
                        <span id="gps-charge">₹0</span>
                    </div>
                    <div class="summary-row total-row">
                        <span>Total Amount:</span>
                        <span id="total-price">₹0.00</span>
                    </div>
                </div>
                
                <button type="submit" class="btn">
                    <i class="fas fa-lock"></i> Confirm Booking
                </button>
            </form>
        </div>
    </div>

    <script>
        document.addEventListener('DOMContentLoaded', function() {
            const pickupDate = document.getElementById('pickup_date');
            const returnDate = document.getElementById('return_date');
            const daysDisplay = document.getElementById('days-display');
            const totalPrice = document.getElementById('total-price');
            const childSeatCheckbox = document.getElementById('child_seat');
            const gpsCheckbox = document.getElementById('gps_navigation');
            const childSeatPrice = document.getElementById('child-seat-price');
            const gpsPrice = document.getElementById('gps-price');
            const childSeatSummary = document.getElementById('child-seat-summary');
            const childSeatCharge = document.getElementById('child-seat-charge');
            const gpsSummary = document.getElementById('gps-summary');
            const gpsCharge = document.getElementById('gps-charge');
            const paymentProof = document.getElementById('payment_proof');
            const fileNameDisplay = document.getElementById('file-name');
            
            const dailyRate = <?php echo $vehicle['daily_rate']; ?>;
            const childSeatRate = 200;
            const gpsRate = 150;
            
            function calculateTotal() {
                if (pickupDate.value && returnDate.value) {
                    const pickup = new Date(pickupDate.value);
                    const returnD = new Date(returnDate.value);
                    
                    // Calculate difference in days
                    const diffTime = Math.abs(returnD - pickup);
                    const diffDays = Math.ceil(diffTime / (1000 * 60 * 60 * 24)) + 1;
                    
                    daysDisplay.textContent = diffDays;
                    
                    // Calculate accessory charges
                    const childSeatDays = childSeatCheckbox.checked ? diffDays : 0;
                    const gpsDays = gpsCheckbox.checked ? diffDays : 0;
                    
                    const childSeatTotal = childSeatDays * childSeatRate;
                    const gpsTotal = gpsDays * gpsRate;
                    const baseTotal = diffDays * dailyRate;
                    const grandTotal = baseTotal + childSeatTotal + gpsTotal;
                    
                    // Update display
                    childSeatPrice.textContent = '₹' + childSeatTotal;
                    gpsPrice.textContent = '₹' + gpsTotal;
                    totalPrice.textContent = '₹' + grandTotal.toFixed(2);
                    
                    // Show/hide summary rows
                    if (childSeatCheckbox.checked) {
                        childSeatSummary.style.display = 'flex';
                        childSeatCharge.textContent = '₹' + childSeatTotal;
                    } else {
                        childSeatSummary.style.display = 'none';
                    }
                    
                    if (gpsCheckbox.checked) {
                        gpsSummary.style.display = 'flex';
                        gpsCharge.textContent = '₹' + gpsTotal;
                    } else {
                        gpsSummary.style.display = 'none';
                    }
                }
            }
            
            // Event listeners
            pickupDate.addEventListener('change', function() {
                if (this.value) {
                    returnDate.min = this.value;
                    calculateTotal();
                }
            });
            
            returnDate.addEventListener('change', calculateTotal);
            childSeatCheckbox.addEventListener('change', calculateTotal);
            gpsCheckbox.addEventListener('change', calculateTotal);
            
            // File upload display
            paymentProof.addEventListener('change', function() {
                if (this.files.length > 0) {
                    fileNameDisplay.textContent = 'Selected file: ' + this.files[0].name;
                } else {
                    fileNameDisplay.textContent = '';
                }
            });
            
            // Initialize min dates
            const today = new Date().toISOString().split('T')[0];
            pickupDate.min = today;
            returnDate.min = today;
        });
    </script>
</body>
</html>