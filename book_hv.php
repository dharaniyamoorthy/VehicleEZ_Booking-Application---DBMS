<?php
session_start();
require_once 'config.php';

if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit();
}

// Check if vehicle_id is provided
if (!isset($_GET['vehicle_id'])) {
    header('Location: heavy_vehicles.php');
    exit();
}

$vehicle_id = $_GET['vehicle_id'];
$vehicle = [];
$user = [];
$pdo = getDBConnection();

try {
    // Get vehicle details
    $stmt = $pdo->prepare("SELECT * FROM vehicles WHERE vehicle_id = ?");
    $stmt->execute([$vehicle_id]);
    $vehicle = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$vehicle) {
        header('Location: heavy_vehicles.php');
        exit();
    }
    
    // Get user details
    $stmt = $pdo->prepare("SELECT full_name, email, phone FROM users WHERE user_id = ?");
    $stmt->execute([$_SESSION['user_id']]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);
    
} catch (PDOException $e) {
    error_log("Database error: " . $e->getMessage());
    header('Location: heavy_vehicles.php');
    exit();
}

// Process form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $pickup_date = $_POST['pickup_date'] ?? '';
    $return_date = $_POST['return_date'] ?? '';
    $payment_proof = $_FILES['payment_proof'] ?? null;
    
    try {
        // Calculate rental days and total amount
        $pickup = new DateTime($pickup_date);
        $return = new DateTime($return_date);
        $interval = $return->diff($pickup);
        $rental_days = $interval->days;
        
        if ($rental_days < 1) {
            $rental_days = 1;
        }
        
        $total_amount = $vehicle['daily_rate'] * $rental_days;
        
        // Handle file upload
        $payment_proof_path = '';
        if ($payment_proof && $payment_proof['error'] === UPLOAD_ERR_OK) {
            $upload_dir = 'uploads/payments/';
            if (!is_dir($upload_dir)) {
                mkdir($upload_dir, 0755, true);
            }
            
            $file_ext = pathinfo($payment_proof['name'], PATHINFO_EXTENSION);
            $file_name = 'payment_' . $_SESSION['user_id'] . '_' . time() . '.' . $file_ext;
            $payment_proof_path = $upload_dir . $file_name;
            
            move_uploaded_file($payment_proof['tmp_name'], $payment_proof_path);
        }
        
        // Insert booking into database
        $stmt = $pdo->prepare("INSERT INTO bookings (
            user_id, vehicle_id, pickup_date, return_date, 
            total_amount, payment_proof, status
        ) VALUES (?, ?, ?, ?, ?, ?, 'pending')");
        
        $stmt->execute([
            $_SESSION['user_id'],
            $vehicle_id,
            $pickup_date,
            $return_date,
            $total_amount,
            $payment_proof_path
        ]);
        
        // Update vehicle status
        $stmt = $pdo->prepare("UPDATE vehicles SET status = 'booked' WHERE vehicle_id = ?");
        $stmt->execute([$vehicle_id]);
        
        // Redirect to confirmation page
        header('Location: booking_confirmation.php?id=' . $pdo->lastInsertId());
        exit();
        
    } catch (Exception $e) {
        error_log("Booking error: " . $e->getMessage());
        $error_message = "An error occurred during booking. Please try again.";
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Book Vehicle | Vehicle Booking System</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <style>
        :root {
            --primary-color: #3498db;
            --secondary-color: #2980b9;
            --accent-color: #e74c3c;
            --text-color: #333;
            --light-gray: #f5f5f5;
            --dark-gray: #777;
            --white: #fff;
            --shadow: 0 4px 6px rgba(0, 0, 0, 0.1);
            --transition: all 0.3s ease;
        }

        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
        }

        body {
            background-color: var(--light-gray);
            color: var(--text-color);
            line-height: 1.6;
        }

        .container {
            max-width: 1000px;
            margin: 0 auto;
            padding: 20px;
        }

        .back-btn {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            color: var(--secondary-color);
            text-decoration: none;
            margin-bottom: 20px;
            transition: var(--transition);
        }

        .back-btn:hover {
            color: var(--primary-color);
            transform: translateX(-3px);
        }

        .booking-container {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 30px;
            background-color: var(--white);
            border-radius: 10px;
            box-shadow: var(--shadow);
            overflow: hidden;
        }

        .vehicle-info {
            padding: 30px;
            background-color: #f9f9f9;
        }

        .vehicle-image {
            width: 100%;
            height: 200px;
            object-fit: cover;
            border-radius: 8px;
            margin-bottom: 20px;
        }

        .vehicle-title {
            font-size: 1.5rem;
            margin-bottom: 10px;
            color: var(--primary-color);
        }

        .vehicle-specs {
            margin-bottom: 20px;
        }

        .spec-item {
            display: flex;
            margin-bottom: 8px;
        }

        .spec-label {
            font-weight: 600;
            width: 120px;
            color: var(--dark-gray);
        }

        .spec-value {
            flex: 1;
        }

        .availability {
            display: inline-block;
            padding: 5px 10px;
            background-color: #2ecc71;
            color: white;
            border-radius: 4px;
            font-weight: bold;
            margin-top: 10px;
        }

        .booking-form {
            padding: 30px;
        }

        .section-title {
            font-size: 1.3rem;
            margin-bottom: 20px;
            color: var(--primary-color);
            padding-bottom: 10px;
            border-bottom: 1px solid #eee;
        }

        .form-group {
            margin-bottom: 20px;
        }

        label {
            display: block;
            margin-bottom: 8px;
            font-weight: 600;
            color: var(--dark-gray);
        }

        input[type="text"],
        input[type="email"],
        input[type="tel"],
        input[type="date"],
        input[type="file"] {
            width: 100%;
            padding: 12px;
            border: 1px solid #ddd;
            border-radius: 5px;
            font-size: 1rem;
            transition: var(--transition);
        }

        input:focus {
            border-color: var(--primary-color);
            outline: none;
            box-shadow: 0 0 0 3px rgba(52, 152, 219, 0.2);
        }

        .payment-method {
            background-color: #f5f5f5;
            padding: 15px;
            border-radius: 5px;
            margin: 20px 0;
        }

        .payment-instructions {
            margin-top: 15px;
            font-size: 0.9rem;
            color: var(--dark-gray);
        }

        .upi-id {
            font-weight: bold;
            color: var(--primary-color);
            margin: 10px 0;
            padding: 10px;
            background-color: #eaf2f8;
            border-radius: 5px;
            text-align: center;
        }

        .file-upload {
            border: 2px dashed #ddd;
            padding: 20px;
            text-align: center;
            border-radius: 5px;
            margin: 15px 0;
            cursor: pointer;
            transition: var(--transition);
        }

        .file-upload:hover {
            border-color: var(--primary-color);
            background-color: #f8f9fa;
        }

        .file-upload i {
            font-size: 2rem;
            color: var(--primary-color);
            margin-bottom: 10px;
        }

        .price-summary {
            background-color: #f5f5f5;
            padding: 20px;
            border-radius: 5px;
            margin-top: 20px;
        }

        .price-row {
            display: flex;
            justify-content: space-between;
            margin-bottom: 10px;
        }

        .price-label {
            font-weight: 600;
            color: var(--dark-gray);
        }

        .price-amount {
            font-weight: bold;
            color: var(--primary-color);
        }

        .total-row {
            border-top: 1px solid #ddd;
            padding-top: 10px;
            margin-top: 10px;
            font-size: 1.1rem;
        }

        .submit-btn {
            width: 100%;
            padding: 15px;
            background-color: var(--primary-color);
            color: white;
            border: none;
            border-radius: 5px;
            font-size: 1.1rem;
            font-weight: bold;
            cursor: pointer;
            transition: var(--transition);
            margin-top: 20px;
        }

        .submit-btn:hover {
            background-color: var(--secondary-color);
        }

        .error-message {
            color: var(--accent-color);
            margin-bottom: 15px;
            text-align: center;
        }

        @media (max-width: 768px) {
            .booking-container {
                grid-template-columns: 1fr;
            }
        }
    </style>
</head>
<body>
    <div class="container">
        <a href="heavy_vehicles.php" class="back-btn">
            <i class="fas fa-arrow-left"></i> Back to Heavy Vehicles
        </a>
        
        <div class="booking-container">
            <div class="vehicle-info">
                <h2 class="vehicle-title"><?php echo htmlspecialchars($vehicle['make'] . ' ' . $vehicle['model']); ?></h2>
                <img src="<?php echo htmlspecialchars($vehicle['image_path'] ?? 'images/default-truck.jpg'); ?>" 
                     alt="<?php echo htmlspecialchars($vehicle['make'] . ' ' . $vehicle['model']); ?>" 
                     class="vehicle-image">
                
                <div class="vehicle-specs">
                    <div class="spec-item">
                        <span class="spec-label">Year:</span>
                        <span class="spec-value"><?php echo htmlspecialchars($vehicle['year']); ?></span>
                    </div>
                    <div class="spec-item">
                        <span class="spec-label">Color:</span>
                        <span class="spec-value"><?php echo htmlspecialchars($vehicle['color']); ?></span>
                    </div>
                    <div class="spec-item">
                        <span class="spec-label">License:</span>
                        <span class="spec-value"><?php echo htmlspecialchars($vehicle['license_plate']); ?></span>
                    </div>
                    <div class="spec-item">
                        <span class="spec-label">Fuel:</span>
                        <span class="spec-value"><?php echo htmlspecialchars($vehicle['fuel_type']); ?></span>
                    </div>
                    <div class="spec-item">
                        <span class="spec-label">Daily Rate:</span>
                        <span class="spec-value">₹<?php echo number_format($vehicle['daily_rate'], 2); ?></span>
                    </div>
                </div>
                
                <span class="availability">Available</span>
            </div>
            
            <div class="booking-form">
                <h2 class="section-title">Complete your booking details</h2>
                
                <?php if (isset($error_message)): ?>
                    <div class="error-message">
                        <i class="fas fa-exclamation-circle"></i> <?php echo $error_message; ?>
                    </div>
                <?php endif; ?>
                
                <form action="" method="POST" enctype="multipart/form-data">
                    <h3 class="section-title">Your Information</h3>
                    
                    <div class="form-group">
                        <label for="full_name">Full Name</label>
                        <input type="text" id="full_name" name="full_name" 
                               value="<?php echo htmlspecialchars($user['full_name'] ?? ''); ?>" readonly>
                    </div>
                    
                    <div class="form-group">
                        <label for="email">Email</label>
                        <input type="email" id="email" name="email" 
                               value="<?php echo htmlspecialchars($user['email'] ?? ''); ?>" readonly>
                    </div>
                    
                    <div class="form-group">
                        <label for="phone">Phone Number</label>
                        <input type="tel" id="phone" name="phone" 
                               value="<?php echo htmlspecialchars($user['phone'] ?? ''); ?>" readonly>
                    </div>
                    
                    <h3 class="section-title">Booking Dates</h3>
                    
                    <div class="form-group">
                        <label for="pickup_date">Pickup Date</label>
                        <input type="date" id="pickup_date" name="pickup_date" required>
                    </div>
                    
                    <div class="form-group">
                        <label for="return_date">Return Date</label>
                        <input type="date" id="return_date" name="return_date" required>
                    </div>
                    
                    <h3 class="section-title">Payment Method</h3>
                    
                    <div class="payment-method">
                        <p>Please complete the payment via UPI to confirm your booking</p>
                        <div class="upi-id">dharaniyamoorthy15@okaxis</div>
                        <p class="payment-instructions">Scan this UPI ID using any UPI app to make payment</p>
                        <p class="payment-instructions">After payment, upload the screenshot below</p>
                    </div>
                    
                    <div class="form-group">
                        <label>Payment Proof</label>
                        <div class="file-upload">
                            <i class="fas fa-cloud-upload-alt"></i>
                            <p>Click to upload payment screenshot or drag and drop</p>
                            <input type="file" id="payment_proof" name="payment_proof" accept="image/*" required style="display: none;">
                        </div>
                    </div>
                    
                    <div class="price-summary">
                        <h3 class="section-title">Price Summary</h3>
                        
                        <div class="price-row">
                            <span class="price-label">Daily Rate:</span>
                            <span class="price-amount">₹<?php echo number_format($vehicle['daily_rate'], 2); ?></span>
                        </div>
                        
                        <div class="price-row">
                            <span class="price-label">Rental Days:</span>
                            <span class="price-amount" id="rental-days">0</span>
                        </div>
                        
                        <div class="price-row total-row">
                            <span class="price-label">Total Amount:</span>
                            <span class="price-amount" id="total-amount">₹0.00</span>
                        </div>
                    </div>
                    
                    <button type="submit" class="submit-btn">Confirm Booking</button>
                </form>
            </div>
        </div>
    </div>

    <script>
        // Calculate rental days and total amount when dates change
        document.getElementById('pickup_date').addEventListener('change', calculateTotal);
        document.getElementById('return_date').addEventListener('change', calculateTotal);
        
        // File upload click handler
        document.querySelector('.file-upload').addEventListener('click', function() {
            document.getElementById('payment_proof').click();
        });
        
        // Show selected file name
        document.getElementById('payment_proof').addEventListener('change', function() {
            if (this.files.length > 0) {
                document.querySelector('.file-upload p').textContent = this.files[0].name;
            }
        });
        
        function calculateTotal() {
            const pickupDate = new Date(document.getElementById('pickup_date').value);
            const returnDate = new Date(document.getElementById('return_date').value);
            
            if (pickupDate && returnDate && returnDate > pickupDate) {
                const diffTime = Math.abs(returnDate - pickupDate);
                const diffDays = Math.ceil(diffTime / (1000 * 60 * 60 * 24));
                document.getElementById('rental-days').textContent = diffDays;
                
                // Calculate total
                const dailyRate = <?php echo $vehicle['daily_rate']; ?>;
                const totalAmount = dailyRate * diffDays;
                document.getElementById('total-amount').textContent = '₹' + totalAmount.toFixed(2);
            } else {
                document.getElementById('rental-days').textContent = '0';
                document.getElementById('total-amount').textContent = '₹0.00';
            }
        }
    </script>
</body>
</html>