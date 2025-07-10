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
    header('Location: two_wheelers.php');
    exit();
}

$vehicle_id = $_GET['vehicle_id'];
$vehicle = null;
$user = null;
$error = null;

try {
    $pdo = getDBConnection();
    
    // Get two-wheeler details
    $stmt = $pdo->prepare("SELECT * FROM vehicles WHERE vehicle_id = ? AND vehicle_type = 'two_wheeler'");
    $stmt->execute([$vehicle_id]);
    $vehicle = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$vehicle) {
        throw new Exception("Two-wheeler not found or unavailable");
    }
    
    // Get user details
    $stmt = $pdo->prepare("SELECT * FROM users WHERE user_id = ?");
    $stmt->execute([$_SESSION['user_id']]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);
    $default_image = 'images/default-vehicle.jpg'; // Fallback image
    $vehicle_image = !empty($vehicle['image_path']) ? $vehicle['image_path'] : $default_image;
    
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
        $pickup_date = $_POST['pickup_date'];
        $return_date = $_POST['return_date'];
        $payment_method = 'upi'; // Only UPI now
        $helmet_request = isset($_POST['helmet_request']) ? 1 : 0;
        $riding_gear_request = isset($_POST['riding_gear_request']) ? 1 : 0;
        $full_name = $_POST['full_name'];
        $email = $_POST['email'];
        $phone = $_POST['phone'];

        if (empty($full_name) || empty($email)) {
            throw new Exception("Please fill in all required user information");
        }
        
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            throw new Exception("Please enter a valid email address");
        }
        
        if (empty($pickup_date) || empty($return_date)) {
            throw new Exception("Please select both pickup and return dates");
        }
        
        if (strtotime($pickup_date) > strtotime($return_date)) {
            throw new Exception("Return date must be after pickup date");
        }
    
        // Handle file upload
        $screenshot_path = null;
        if (isset($_FILES['payment_screenshot']) && $_FILES['payment_screenshot']['error'] === UPLOAD_ERR_OK) {
            $upload_dir = 'uploads/payments/';
            if (!is_dir($upload_dir)) {
                mkdir($upload_dir, 0755, true);
            }
            
            $file_name = uniqid() . '_' . basename($_FILES['payment_screenshot']['name']);
            $target_path = $upload_dir . $file_name;
            
            // Check file type (allow only images)
            $allowed_types = ['image/jpeg', 'image/png', 'image/gif'];
            $file_type = $_FILES['payment_screenshot']['type'];
            
            if (in_array($file_type, $allowed_types)) {
                if (move_uploaded_file($_FILES['payment_screenshot']['tmp_name'], $target_path)) {
                    $screenshot_path = $target_path;
                } else {
                    throw new Exception("Failed to upload payment screenshot");
                }
            } else {
                throw new Exception("Only JPG, PNG, and GIF files are allowed for payment screenshot");
            }
        } else {
            throw new Exception("Payment screenshot is required");
        }

        $stmt = $pdo->prepare("UPDATE users SET full_name = ?, email = ?, phone = ? WHERE user_id = ?");
        $stmt->execute([$full_name, $email, $phone, $_SESSION['user_id']]);
        
        // Calculate days and total price
        $pickup = new DateTime($pickup_date);
        $return = new DateTime($return_date);
        $interval = $pickup->diff($return);
        $days = $interval->days + 1; // Include both start and end day
        
        // Calculate additional charges for accessories
        $helmet_charge = $helmet_request ? 100 * $days : 0;
        $gear_charge = $riding_gear_request ? 300 * $days : 0;
        
        $total_price = ($days * $vehicle['daily_rate']) + $helmet_charge + $gear_charge;

        // Insert booking into database
        $stmt = $pdo->prepare("INSERT INTO bookings 
                            (user_id, vehicle_id, pickup_date, return_date, 
                             total_price, payment_method, status,
                             helmet_request, riding_gear_request, payment_screenshot) 
                            VALUES (?, ?, ?, ?, ?, ?, 'pending', ?, ?, ?)");
        
        $success = $stmt->execute([
            $_SESSION['user_id'],
            $vehicle_id,
            $pickup_date,
            $return_date,
            $total_price,
            $payment_method,
            $helmet_request,
            $riding_gear_request,
            $screenshot_path
        ]);
        
        if (!$success) {
            throw new Exception("Failed to create booking");
        }
        
        $booking_id = $pdo->lastInsertId();
        
        if (!$booking_id) {
            throw new Exception("Failed to get booking ID");
        }
        
        // Store booking info in session
        $_SESSION['last_booking_id'] = $booking_id;
        $_SESSION['user_email'] = $email;
        
        // Redirect to booking status page
        header("Location: booking_status.php?booking_id=".$booking_id);
        exit();
        
    } catch (Exception $e) {
        $error = $e->getMessage();
        error_log("Booking error: ".$error);
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Book <?php echo htmlspecialchars($vehicle['vehicle_name'] ?? 'Vehicle'); ?> | Vehicle Rental</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <style>
        :root {
            --primary: #4a6bff;
            --primary-dark: #3a56d4;
            --secondary: #f8f9fa;
            --dark: #343a40;
            --light: #f8f9fa;
            --success: #28a745;
            --danger: #dc3545;
            --warning: #ffc107;
            --info: #17a2b8;
        }
        
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
        }
        
        body {
            background-color: #f5f7fa;
            color: #333;
            line-height: 1.6;
        }
        
        .container {
            max-width: 1200px;
            margin: 0 auto;
            padding: 20px;
        }
        
        .back-btn {
            display: inline-flex;
            align-items: center;
            color: var(--primary);
            text-decoration: none;
            margin-bottom: 20px;
            font-weight: 500;
            transition: all 0.3s ease;
        }
        
        .back-btn:hover {
            color: var(--primary-dark);
            transform: translateX(-3px);
        }
        
        .back-btn i {
            margin-right: 8px;
        }
        
        .header {
            text-align: center;
            margin-bottom: 30px;
        }
        
        .header h1 {
            font-size: 2.2rem;
            color: var(--dark);
            margin-bottom: 10px;
        }
        
        .header p {
            color: #666;
            font-size: 1.1rem;
        }
        
        .header i {
            color: var(--primary);
            margin-right: 10px;
        }
        
        .error-message {
            background-color: #ffebee;
            color: var(--danger);
            padding: 15px;
            border-radius: 5px;
            margin-bottom: 25px;
            display: flex;
            align-items: center;
            border-left: 4px solid var(--danger);
        }
        
        .error-message i {
            margin-right: 10px;
            font-size: 1.2rem;
        }
        
        .booking-container {
            display: flex;
            flex-wrap: wrap;
            gap: 30px;
        }
        
        .vehicle-card {
            flex: 1;
            min-width: 300px;
            background: white;
            border-radius: 10px;
            box-shadow: 0 5px 15px rgba(0, 0, 0, 0.05);
            overflow: hidden;
            position: relative;
            transition: transform 0.3s ease;
        }
        
        .vehicle-card:hover {
            transform: translateY(-5px);
        }
        
        .vehicle-badge {
            position: absolute;
            top: 15px;
            right: 15px;
            background-color: var(--primary);
            color: white;
            padding: 5px 10px;
            border-radius: 20px;
            font-size: 0.8rem;
            font-weight: bold;
            z-index: 1;
        }
        
        .vehicle-badge i {
            margin-right: 5px;
        }
        
        .vehicle-image-container {
            height: 250px;
            overflow: hidden;
        }
        
        .vehicle-image {
            width: 100%;
            height: 100%;
            object-fit: cover;
            transition: transform 0.5s ease;
        }
        
        .vehicle-card:hover .vehicle-image {
            transform: scale(1.05);
        }
        
        .vehicle-details {
            padding: 20px;
        }
        
        .vehicle-title {
            font-size: 1.5rem;
            margin-bottom: 15px;
            color: var(--dark);
        }
        
        .vehicle-specs {
            display: grid;
            grid-template-columns: repeat(2, 1fr);
            gap: 15px;
            margin-bottom: 20px;
        }
        
        .spec-item {
            display: flex;
            align-items: center;
        }
        
        .spec-item i {
            color: var(--primary);
            margin-right: 10px;
            width: 20px;
            text-align: center;
        }
        
        .price-display {
            background-color: var(--secondary);
            padding: 15px;
            border-radius: 5px;
            margin-top: 15px;
        }
        
        .price-row {
            display: flex;
            justify-content: space-between;
            margin-bottom: 8px;
        }
        
        .price-row:last-child {
            margin-bottom: 0;
        }
        
        .price-label {
            font-weight: 500;
        }
        
        .price-value {
            font-weight: bold;
            color: var(--primary);
        }
        
        .booking-form {
            flex: 1;
            min-width: 300px;
            background: white;
            border-radius: 10px;
            box-shadow: 0 5px 15px rgba(0, 0, 0, 0.05);
            padding: 25px;
        }
        
        .form-title {
            font-size: 1.5rem;
            color: var(--dark);
            margin-bottom: 20px;
            padding-bottom: 10px;
            border-bottom: 1px solid #eee;
        }
        
        .user-info h3, .accessories-section .form-label {
            font-size: 1.1rem;
            color: var(--dark);
            margin-bottom: 15px;
            display: block;
        }
        
        .form-group {
            margin-bottom: 20px;
        }
        
        .date-inputs {
            display: flex;
            gap: 15px;
        }
        
        .date-inputs > div {
            flex: 1;
        }
        
        .form-label {
            display: block;
            margin-bottom: 8px;
            font-weight: 500;
            color: #555;
        }
        
        .form-control {
            width: 100%;
            padding: 12px 15px;
            border: 1px solid #ddd;
            border-radius: 5px;
            font-size: 1rem;
            transition: border 0.3s ease;
        }
        
        .form-control:focus {
            outline: none;
            border-color: var(--primary);
            box-shadow: 0 0 0 3px rgba(74, 107, 255, 0.1);
        }
        
        .accessory-option {
            display: flex;
            align-items: center;
            margin-bottom: 10px;
            padding: 10px;
            background-color: #f9f9f9;
            border-radius: 5px;
            transition: all 0.3s ease;
        }
        
        .accessory-option:hover {
            background-color: #f0f0f0;
        }
        
        .accessory-option input[type="checkbox"] {
            margin-right: 10px;
            accent-color: var(--primary);
            width: 18px;
            height: 18px;
        }
        
        .accessory-price {
            margin-left: auto;
            font-weight: bold;
            color: var(--primary);
        }
        
        .upi-info {
            background-color: #f0f7ff;
            padding: 15px;
            border-radius: 5px;
            margin-top: 10px;
        }
        
        .upi-info p {
            margin-bottom: 10px;
            color: #333;
        }
        
        .upi-info i {
            color: var(--primary);
            margin-right: 5px;
        }
        
        .upi-id {
            display: flex;
            align-items: center;
            background-color: white;
            padding: 10px 15px;
            border-radius: 5px;
            font-weight: bold;
            color: var(--primary);
            margin: 10px 0;
            border: 1px dashed var(--primary);
        }
        
        .upi-id i {
            font-size: 1.5rem;
            margin-right: 10px;
        }
        
        .file-upload-wrapper {
            margin-top: 10px;
        }
        
        .file-upload-label {
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;
            padding: 30px;
            border: 2px dashed #ddd;
            border-radius: 5px;
            background-color: #fafafa;
            cursor: pointer;
            transition: all 0.3s ease;
            text-align: center;
        }
        
        .file-upload-label:hover {
            border-color: var(--primary);
            background-color: #f0f7ff;
        }
        
        .file-upload-label i {
            font-size: 2rem;
            color: var(--primary);
            margin-bottom: 10px;
        }
        
        .file-upload-label span {
            display: block;
            margin-bottom: 5px;
            color: #666;
        }
        
        .file-upload-label input[type="file"] {
            display: none;
        }
        
        .file-name {
            margin-top: 10px;
            font-size: 0.9rem;
            color: #666;
            word-break: break-all;
        }
        
        .screenshot-preview {
            max-width: 100%;
            max-height: 200px;
            margin-top: 15px;
            border-radius: 5px;
            display: none;
        }
        
        .price-summary {
            background-color: #f9f9f9;
            padding: 20px;
            border-radius: 5px;
            margin: 25px 0;
        }
        
        .price-summary h3 {
            margin-bottom: 15px;
            color: var(--dark);
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
            font-weight: bold;
            font-size: 1.1rem;
            color: var(--dark);
        }
        
        .btn {
            display: flex;
            align-items: center;
            justify-content: center;
            width: 100%;
            padding: 15px;
            background-color: var(--primary);
            color: white;
            border: none;
            border-radius: 5px;
            font-size: 1rem;
            font-weight: 500;
            cursor: pointer;
            transition: all 0.3s ease;
        }
        
        .btn:hover {
            background-color: var(--primary-dark);
            transform: translateY(-2px);
            box-shadow: 0 5px 10px rgba(0, 0, 0, 0.1);
        }
        
        .btn:disabled {
            background-color: #cccccc;
            cursor: not-allowed;
            transform: none;
            box-shadow: none;
        }
        
        .spinner {
            display: none;
            width: 20px;
            height: 20px;
            border: 3px solid rgba(255, 255, 255, 0.3);
            border-radius: 50%;
            border-top-color: white;
            animation: spin 1s ease-in-out infinite;
            margin-left: 10px;
        }
        
        @keyframes spin {
            to { transform: rotate(360deg); }
        }
        
        /* Responsive adjustments */
        @media (max-width: 768px) {
            .booking-container {
                flex-direction: column;
            }
            
            .date-inputs {
                flex-direction: column;
                gap: 15px;
            }
            
            .vehicle-image-container {
                height: 200px;
            }
        }
        
        /* Animation for form elements */
        @keyframes fadeIn {
            from { opacity: 0; transform: translateY(10px); }
            to { opacity: 1; transform: translateY(0); }
        }
        
        .booking-form > * {
            animation: fadeIn 0.5s ease-out forwards;
        }
        
        .booking-form > *:nth-child(1) { animation-delay: 0.1s; }
        .booking-form > *:nth-child(2) { animation-delay: 0.2s; }
        .booking-form > *:nth-child(3) { animation-delay: 0.3s; }
        .booking-form > *:nth-child(4) { animation-delay: 0.4s; }
        .booking-form > *:nth-child(5) { animation-delay: 0.5s; }
        .booking-form > *:nth-child(6) { animation-delay: 0.6s; }
        .booking-form > *:nth-child(7) { animation-delay: 0.7s; }
        .booking-form > *:nth-child(8) { animation-delay: 0.8s; }
    </style>
</head>
<body>
    <div class="container">
        <a href="two_wheelers.php" class="back-btn">
            <i class="fas fa-arrow-left"></i> Back to Two-Wheelers
        </a>
        
        <div class="header">
            <h1><i class="fas fa-motorcycle"></i> Book Your <?php echo htmlspecialchars($vehicle['vehicle_name'] ?? 'Vehicle'); ?></h1>
            <p>Complete your booking details and enjoy the ride!</p>
        </div>
        
        <?php if ($error): ?>
            <div class="error-message">
                <i class="fas fa-exclamation-circle"></i>
                <span><?php echo htmlspecialchars($error); ?></span>
            </div>
        <?php endif; ?>
        
        <div class="booking-container">
            <div class="vehicle-card">
                <?php if ($vehicle && $vehicle['daily_rate'] < 500): ?>
                    <div class="vehicle-badge">
                        <i class="fas fa-bolt"></i> Great Deal
                    </div>
                <?php endif; ?>
                <div class="vehicle-image-container">
                    <img src="<?php echo htmlspecialchars($vehicle_image); ?>" alt="<?php echo htmlspecialchars($vehicle['vehicle_name'] ?? 'Vehicle Image'); ?>" class="vehicle-image">
                </div>
                <div class="vehicle-details">
                    <h2 class="vehicle-title"><?php echo htmlspecialchars($vehicle['vehicle_name'] ?? 'Yamaha RX 100'); ?></h2>
                    <div class="vehicle-specs">
                        <div class="spec-item">
                            <i class="fas fa-calendar-alt"></i>
                            <span>Year: <?php echo htmlspecialchars($vehicle['year'] ?? '2019'); ?></span>
                        </div>
                        <div class="spec-item">
                            <i class="fas fa-tag"></i>
                            <span>License: <?php echo htmlspecialchars($vehicle['license_plate'] ?? 'TN 30 NM 7146'); ?></span>
                        </div>
                        <div class="spec-item">
                            <i class="fas fa-palette"></i>
                            <span>Color: <?php echo htmlspecialchars($vehicle['color'] ?? 'Red'); ?></span>
                        </div>
                        <div class="spec-item">
                            <i class="fas fa-gas-pump"></i>
                            <span>Fuel: <?php echo htmlspecialchars($vehicle['fuel_type'] ?? 'Petrol'); ?></span>
                        </div>
                    </div>
                    <div class="price-display">
                        <div class="price-row">
                            <span class="price-label">Daily Rate:</span>
                            <span class="price-value">₹<?php echo htmlspecialchars($vehicle['daily_rate'] ?? '400.00'); ?></span>
                        </div>
                        <div class="price-row">
                            <span class="price-label">Availability:</span>
                            <span class="price-value"><?php echo ($vehicle && $vehicle['status'] === 'available') ? 'Available' : 'Booked'; ?></span>
                        </div>
                    </div>
                </div>
            </div>
            
            <form method="POST" class="booking-form" enctype="multipart/form-data">
                action="<?php echo htmlspecialchars($_SERVER['PHP_SELF']) . '?vehicle_id=' . $vehicle_id; ?>">
                <h2 class="form-title">Booking Details</h2>
                
                <div class="user-info">
                    <h3>Your Information</h3>
                    <div class="form-group">
                        <label for="full_name" class="form-label">Full Name</label>
                        <input type="text" id="full_name" name="full_name" class="form-control" 
                               value="<?php echo htmlspecialchars($user['full_name'] ?? ''); ?>" required>
                    </div>
                    <div class="form-group">
                        <label for="email" class="form-label">Email</label>
                        <input type="email" id="email" name="email" class="form-control" 
                               value="<?php echo htmlspecialchars($user['email'] ?? ''); ?>" required>
                    </div>
                    <div class="form-group">
                        <label for="phone" class="form-label">Phone Number</label>
                        <input type="tel" id="phone" name="phone" class="form-control" 
                               value="<?php echo htmlspecialchars($user['phone'] ?? ''); ?>" maxlength="10" required
                               pattern="[0-9]{10}" title="Please enter a 10-digit phone number">
                    </div>
                </div>
                
                <div class="form-group date-inputs">
                    <div>
                        <label for="pickup_date" class="form-label">Pickup Date</label>
                        <input type="date" id="pickup_date" name="pickup_date" class="form-control" required>
                    </div>
                    <div>
                        <label for="return_date" class="form-label">Return Date</label>
                        <input type="date" id="return_date" name="return_date" class="form-control" required>
                    </div>
                </div>
                
                <div class="form-group accessories-section">
                    <label class="form-label">Add Accessories</label>
                    <div class="accessory-option">
                        <input type="checkbox" id="helmet_request" name="helmet_request" value="1">
                        <label for="helmet_request">Helmet (₹100/day)</label>
                        <span class="accessory-price" id="helmet-price">₹0</span>
                    </div>
                    <div class="accessory-option">
                        <input type="checkbox" id="riding_gear_request" name="riding_gear_request" value="1">
                        <label for="riding_gear_request">Riding Gear (₹300/day)</label>
                        <span class="accessory-price" id="gear-price">₹0</span>
                    </div>
                </div>
                
                <div class="form-group">
                    <label class="form-label">Payment Method</label>
                    <div class="upi-info">
                        <p><i class="fas fa-info-circle"></i> Please complete the payment via UPI to confirm your booking</p>
                        <div class="upi-id">
                            <i class="fas fa-qrcode"></i> dharaniyamoorthy15@okaxis
                        </div>
                        <p><small>Scan this UPI ID using any UPI app to make payment</small></p>
                        <p><small>After payment, upload the screenshot below</small></p>
                    </div>
                </div>
                
                <div class="form-group screenshot-upload">
                    <label class="form-label">Payment Proof</label>
                    <div class="file-upload-wrapper">
                        <label class="file-upload-label">
                            <i class="fas fa-cloud-upload-alt"></i>
                            <span>Click to upload payment screenshot</span>
                            <span>or drag and drop</span>
                            <input type="file" id="payment_screenshot" name="payment_screenshot" accept="image/*" required>
                        </label>
                        <div id="file-name" class="file-name"></div>
                    </div>
                    <img id="screenshot-preview" class="screenshot-preview" src="#" alt="Payment Screenshot Preview">
                </div>
                
                <div class="price-summary">
                    <h3>Price Summary</h3>
                    <div class="summary-row">
                        <span>Daily Rate:</span>
                        <span>₹<?php echo htmlspecialchars($vehicle['daily_rate'] ?? '400.00'); ?></span>
                    </div>
                    <div class="summary-row">
                        <span>Rental Days:</span>
                        <span id="days-display">0</span>
                    </div>
                    <div class="summary-row" id="helmet-summary" style="display: none;">
                        <span>Helmet Charge:</span>
                        <span id="helmet-charge">₹0</span>
                    </div>
                    <div class="summary-row" id="gear-summary" style="display: none;">
                        <span>Riding Gear Charge:</span>
                        <span id="gear-charge">₹0</span>
                    </div>
                    <div class="summary-row total-row">
                        <span>Total Amount:</span>
                        <span id="total-price">₹0.00</span>
                    </div>
                </div>
                
                <button type="submit" class="btn" id="submit-btn" <?php echo ($vehicle && $vehicle['status'] !== 'available') ? 'disabled' : ''; ?>>
                    <span id="btn-text"><?php echo ($vehicle && $vehicle['status'] === 'available') ? 'Confirm Booking' : 'Currently Unavailable'; ?></span>
                    <div class="spinner" id="spinner"></div>
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
            const helmetCheckbox = document.getElementById('helmet_request');
            const gearCheckbox = document.getElementById('riding_gear_request');
            const helmetPrice = document.getElementById('helmet-price');
            const gearPrice = document.getElementById('gear-price');
            const helmetSummary = document.getElementById('helmet-summary');
            const helmetCharge = document.getElementById('helmet-charge');
            const gearSummary = document.getElementById('gear-summary');
            const gearCharge = document.getElementById('gear-charge');
            const screenshotInput = document.getElementById('payment_screenshot');
            const screenshotPreview = document.getElementById('screenshot-preview');
            const fileNameDisplay = document.getElementById('file-name');
            const submitBtn = document.getElementById('submit-btn');
            const btnText = document.getElementById('btn-text');
            const spinner = document.getElementById('spinner');
            
            const dailyRate = <?php echo $vehicle['daily_rate'] ?? 400; ?>;
            const helmetRate = 100;
            const gearRate = 300;
            
            // Set minimum dates
            const today = new Date().toISOString().split('T')[0];
            pickupDate.min = today;
            returnDate.min = today;
            
            // Phone number validation
            const phoneInput = document.getElementById('phone');
            phoneInput.addEventListener('input', function() {
                this.value = this.value.replace(/[^\d]/g, '').slice(0, 10);
            });
            
            // File upload handling
            screenshotInput.addEventListener('change', function() {
                const file = this.files[0];
                if (file) {
                    fileNameDisplay.textContent = file.name;
                    
                    const reader = new FileReader();
                    reader.onload = function(e) {
                        screenshotPreview.src = e.target.result;
                        screenshotPreview.style.display = 'block';
                    }
                    reader.readAsDataURL(file);
                } else {
                    fileNameDisplay.textContent = '';
                    screenshotPreview.style.display = 'none';
                }
            });
            
            // Drag and drop functionality
            const uploadLabel = document.querySelector('.file-upload-label');
            
            uploadLabel.addEventListener('dragover', (e) => {
                e.preventDefault();
                uploadLabel.style.borderColor = 'var(--primary)';
                uploadLabel.style.backgroundColor = '#f0f8ff';
            });
            
            uploadLabel.addEventListener('dragleave', () => {
                uploadLabel.style.borderColor = '#e0e0e0';
                uploadLabel.style.backgroundColor = 'white';
            });
            
            uploadLabel.addEventListener('drop', (e) => {
                e.preventDefault();
                uploadLabel.style.borderColor = '#e0e0e0';
                uploadLabel.style.backgroundColor = 'white';
                
                if (e.dataTransfer.files.length) {
                    screenshotInput.files = e.dataTransfer.files;
                    const event = new Event('change');
                    screenshotInput.dispatchEvent(event);
                }
            });
            
            // Calculate total price
            function calculateTotal() {
                if (pickupDate.value && returnDate.value) {
                    const pickup = new Date(pickupDate.value);
                    const returnD = new Date(returnDate.value);
                    
                    // Calculate difference in days
                    const diffTime = Math.abs(returnD - pickup);
                    const diffDays = Math.ceil(diffTime / (1000 * 60 * 60 * 24)) + 1;
                    
                    daysDisplay.textContent = diffDays;
                    
                    // Calculate accessory charges
                    const helmetDays = helmetCheckbox.checked ? diffDays : 0;
                    const gearDays = gearCheckbox.checked ? diffDays : 0;
                    
                    const helmetTotal = helmetDays * helmetRate;
                    const gearTotal = gearDays * gearRate;
                    const baseTotal = diffDays * dailyRate;
                    const grandTotal = baseTotal + helmetTotal + gearTotal;
                    
                    // Update display
                    helmetPrice.textContent = '₹' + helmetTotal.toFixed(2);
                    gearPrice.textContent = '₹' + gearTotal.toFixed(2);
                    totalPrice.textContent = '₹' + grandTotal.toFixed(2);
                    
                    // Show/hide summary rows
                    helmetSummary.style.display = helmetCheckbox.checked ? 'flex' : 'none';
                    gearSummary.style.display = gearCheckbox.checked ? 'flex' : 'none';
                    
                    if (helmetCheckbox.checked) {
                        helmetCharge.textContent = '₹' + helmetTotal.toFixed(2);
                    }
                    
                    if (gearCheckbox.checked) {
                        gearCharge.textContent = '₹' + gearTotal.toFixed(2);
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
            helmetCheckbox.addEventListener('change', calculateTotal);
            gearCheckbox.addEventListener('change', calculateTotal);
            
            // Form submission
            const form = document.querySelector('.booking-form');
            form.addEventListener('submit', function(e) {
                btnText.textContent = 'Processing...';
                spinner.style.display = 'block';
                submitBtn.disabled = true;
                if (form.checkValidity()) {
        btnText.textContent = 'Processing...';
        spinner.style.display = 'block';
        submitBtn.disabled = true;
    }
            });
            
            // Initialize calculation if dates are pre-filled
            calculateTotal();
        });
    </script>
</body>
</html>