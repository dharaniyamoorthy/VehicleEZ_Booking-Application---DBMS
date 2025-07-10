<?php
session_start();
require_once 'config.php';

// Redirect if not logged in
if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit();
}

$pdo = getDBConnection();
$error = '';
$success = '';

// Get available vehicles with all fields from your database
$vehicles = [];
try {
    $stmt = $pdo->prepare("SELECT 
        vehicle_id, make, model, year, license_plate, color, 
        daily_rate, status, seating_capacity, transmission, 
        fuel_type, image_url, vehicle_type 
        FROM vehicles WHERE status = 'available'");
    $stmt->execute();
    $vehicles = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    error_log("Database error: " . $e->getMessage());
}

// Filter vehicles by type
$twoWheelers = array_filter($vehicles, function($v) { return $v['vehicle_type'] == 'two_wheeler'; });
$fourWheelers = array_filter($vehicles, function($v) { return $v['vehicle_type'] == 'four_wheeler'; });
$heavyVehicles = array_filter($vehicles, function($v) { return $v['vehicle_type'] == 'heavy_vehicle'; });

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $vehicle_id = $_POST['vehicle_id'] ?? null;
    $start_date = $_POST['start_date'] ?? null;
    $end_date = $_POST['end_date'] ?? null;
    $pickup_location = $_POST['pickup_location'] ?? null;
    $special_requests = $_POST['special_requests'] ?? '';
    $payment_method = $_POST['payment_method'] ?? null;

    // Validate inputs
    if (empty($vehicle_id) || empty($start_date) || empty($end_date) || empty($pickup_location) || empty($payment_method)) {
        $error = "Please fill all required fields";
    } elseif (strtotime($start_date) >= strtotime($end_date)) {
        $error = "Return date must be after pickup date";
    } else {
        try {
            // Begin transaction
            $pdo->beginTransaction();
            
            // Check vehicle availability again (prevent race condition)
            $stmt = $pdo->prepare("SELECT * FROM vehicles WHERE vehicle_id = ? AND status = 'available' FOR UPDATE");
            $stmt->execute([$vehicle_id]);
            $vehicle = $stmt->fetch();

            if (!$vehicle) {
                $error = "Selected vehicle is no longer available";
                $pdo->rollBack();
            } else {
                // Calculate booking duration and cost
                $days = ceil((strtotime($end_date) - strtotime($start_date)) / 86400);
                $total_cost = $days * $vehicle['daily_rate'];

                // Create booking
                $stmt = $pdo->prepare("INSERT INTO bookings 
                    (user_id, vehicle_id, booking_status, start_date, end_date, 
                    pickup_location, dropoff_location, special_requests, 
                    payment_method, total_cost)
                    VALUES (?, ?, 'pending', ?, ?, ?, ?, ?, ?, ?)");

                $dropoff_location = $_POST['dropoff_location'] ?? $pickup_location;
                
                $stmt->execute([
                    $_SESSION['user_id'],
                    $vehicle_id,
                    $start_date,
                    $end_date,
                    $pickup_location,
                    $dropoff_location,
                    $special_requests,
                    $payment_method,
                    $total_cost
                ]);

                // Update vehicle status
                $pdo->prepare("UPDATE vehicles SET status = 'booked' WHERE vehicle_id = ?")
                   ->execute([$vehicle_id]);

                // Commit transaction
                $pdo->commit();

                // Redirect to confirmation
                header("Location: booking_confirmation.php?id=" . $pdo->lastInsertId());
                exit();
            }
        } catch (PDOException $e) {
            $pdo->rollBack();
            $error = "Database error. Please try again.";
            error_log("Booking error: " . $e->getMessage());
        }
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Vehicle Availability | Vehicle Pre-Booking</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/flatpickr/dist/flatpickr.min.css">
    <style>
        :root {
            --primary-color: #3498db;
            --secondary-color: #2ecc71;
            --danger-color: #e74c3c;
            --dark-color: #2c3e50;
            --light-color: #ecf0f1;
            --border-radius: 12px;
            --box-shadow: 0 4px 15px rgba(0,0,0,0.1);
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
            max-width: 1200px;
            margin: 0 auto;
            padding: 20px;
        }
        
        .header {
            text-align: center;
            margin-bottom: 40px;
            padding: 30px 0;
            background: linear-gradient(135deg, #3498db, #2c3e50);
            color: white;
            border-radius: var(--border-radius);
            box-shadow: var(--box-shadow);
        }
        
        .header h1 {
            font-size: 2.5rem;
            margin: 0;
            padding: 0;
            text-transform: uppercase;
            letter-spacing: 1px;
        }
        
        .header p {
            font-size: 1.2rem;
            opacity: 0.9;
            margin-top: 10px;
        }
        
        .vehicle-types {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(350px, 1fr));
            gap: 25px;
            margin-bottom: 40px;
        }
        
        .vehicle-type-card {
            background: white;
            border-radius: var(--border-radius);
            overflow: hidden;
            box-shadow: var(--box-shadow);
            transition: var(--transition);
            cursor: pointer;
            position: relative;
        }
        
        .vehicle-type-card:hover {
            transform: translateY(-10px);
            box-shadow: 0 10px 25px rgba(0,0,0,0.15);
        }
        
        .vehicle-type-card.active {
            border: 4px solid var(--primary-color);
        }
        
        .vehicle-type-image {
            width: 100%;
            height: 200px;
            object-fit: cover;
        }
        
        .vehicle-type-content {
            padding: 20px;
        }
        
        .vehicle-type-title {
            font-size: 1.8rem;
            margin: 0 0 10px 0;
            color: var(--dark-color);
            display: flex;
            align-items: center;
            gap: 10px;
        }
        
        .vehicle-type-count {
            font-size: 1rem;
            background: var(--primary-color);
            color: white;
            padding: 3px 10px;
            border-radius: 20px;
            margin-left: auto;
        }
        
        .vehicle-type-description {
            color: #666;
            margin-bottom: 15px;
        }
        
        .vehicle-type-icon {
            font-size: 2rem;
            color: var(--primary-color);
        }
        
        .vehicles-container {
            margin-top: 30px;
            display: none;
        }
        
        .vehicles-container.active {
            display: block;
            animation: fadeIn 0.5s ease;
        }
        
        @keyframes fadeIn {
            from { opacity: 0; transform: translateY(20px); }
            to { opacity: 1; transform: translateY(0); }
        }
        
        .section-title {
            font-size: 1.8rem;
            margin-bottom: 20px;
            color: var(--dark-color);
            position: relative;
            padding-bottom: 10px;
        }
        
        .section-title:after {
            content: '';
            position: absolute;
            bottom: 0;
            left: 0;
            width: 60px;
            height: 4px;
            background: var(--primary-color);
            border-radius: 2px;
        }
        
        .vehicle-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(280px, 1fr));
            gap: 20px;
        }
        
        .vehicle-card {
            background: white;
            border-radius: var(--border-radius);
            overflow: hidden;
            box-shadow: 0 3px 10px rgba(0,0,0,0.1);
            transition: var(--transition);
        }
        
        .vehicle-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 10px 20px rgba(0,0,0,0.15);
        }
        
        .vehicle-image {
            width: 100%;
            height: 180px;
            object-fit: cover;
        }
        
        .vehicle-details {
            padding: 15px;
        }
        
        .vehicle-title {
            font-size: 1.2rem;
            font-weight: 600;
            margin: 0 0 5px 0;
        }
        
        .vehicle-price {
            font-size: 1.3rem;
            font-weight: 700;
            color: var(--primary-color);
            margin: 10px 0;
        }
        
        .vehicle-meta {
            display: flex;
            flex-wrap: wrap;
            gap: 10px;
            margin: 10px 0;
            font-size: 0.9rem;
            color: #666;
        }
        
        .vehicle-meta span {
            display: flex;
            align-items: center;
            gap: 5px;
        }
        
        .btn {
            display: inline-block;
            background: var(--primary-color);
            color: white;
            padding: 12px 25px;
            border-radius: var(--border-radius);
            text-decoration: none;
            font-weight: 600;
            transition: var(--transition);
            border: none;
            cursor: pointer;
            width: 100%;
            text-align: center;
            margin-top: 10px;
        }
        
        .btn:hover {
            background: #2980b9;
            transform: translateY(-2px);
        }
        
        .btn-secondary {
            background: var(--dark-color);
        }
        
        .btn-secondary:hover {
            background: #1a252f;
        }
        
        .error-message {
            background: #f8d7da;
            color: var(--danger-color);
            padding: 15px;
            border-radius: var(--border-radius);
            margin-bottom: 20px;
            display: flex;
            align-items: center;
            gap: 10px;
        }
        
        .no-vehicles {
            text-align: center;
            padding: 40px;
            background: #f8f9fa;
            border-radius: var(--border-radius);
            color: #666;
            font-size: 1.1rem;
        }
        
        .booking-form {
            background: white;
            padding: 30px;
            border-radius: var(--border-radius);
            box-shadow: var(--box-shadow);
            margin-top: 40px;
            display: none;
        }
        
        .booking-form.active {
            display: block;
            animation: fadeIn 0.5s ease;
        }
        
        .form-group {
            margin-bottom: 20px;
        }
        
        .form-group label {
            display: block;
            margin-bottom: 8px;
            font-weight: 600;
        }
        
        .form-control {
            width: 100%;
            padding: 12px;
            border: 1px solid #ddd;
            border-radius: var(--border-radius);
            font-size: 1rem;
        }
        
        .form-grid {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 20px;
        }
        
        @media (max-width: 768px) {
            .vehicle-types {
                grid-template-columns: 1fr;
            }
            
            .form-grid {
                grid-template-columns: 1fr;
            }
            
            .header h1 {
                font-size: 2rem;
            }
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <h1>Availability of Vehicles</h1>
            <p>Browse and book from our wide selection of available vehicles</p>
        </div>
        
        <?php if ($error): ?>
            <div class="error-message">
                <i class="fas fa-exclamation-circle"></i> <?php echo htmlspecialchars($error); ?>
            </div>
        <?php endif; ?>
        
        <h2 class="section-title">Type of Vehicle</h2>
        
        <div class="vehicle-types">
        <div class="vehicle-type-card" onclick="window.location.href='two_wheeler.php'">
                <img src="https://images.unsplash.com/photo-1558981806-ec527fa84c39?ixlib=rb-1.2.1&auto=format&fit=crop&w=1350&q=80" 
                     alt="Two Wheeler" class="vehicle-type-image">
                <div class="vehicle-type-content">
                    <h3 class="vehicle-type-title">
                        <i class="fas fa-motorcycle vehicle-type-icon"></i> TWO WHEELER
                        <span class="vehicle-type-count"><?php echo count($twoWheelers); ?> 12 available</span>
                    </h3>
                    <p class="vehicle-type-description">
                        Perfect for quick city commutes and solo rides. Our two-wheelers offer great fuel efficiency and easy parking.
                    </p>
                </div>
            </div>
            
            <div class="vehicle-type-card" onclick="window.location.href='four_wheeler.php'">
                <img src="https://images.unsplash.com/photo-1503376780353-7e6692767b70?ixlib=rb-1.2.1&auto=format&fit=crop&w=1350&q=80" 
                     alt="Four Wheeler" class="vehicle-type-image">
                <div class="vehicle-type-content">
                    <h3 class="vehicle-type-title">
                        <i class="fas fa-car vehicle-type-icon"></i> FOUR WHEELER
                        <span class="vehicle-type-count"><?php echo count($fourWheelers); ?> 14  available</span>
                    </h3>
                    <p class="vehicle-type-description">
                        Comfortable and spacious vehicles for families and groups. Choose from sedans, SUVs, and luxury models.
                    </p>
                </div>
            </div>
            
            <div class="vehicle-type-card" onclick="window.location.href='heavy_vehicle.php'">
                <img src="https://images.unsplash.com/photo-1568605117036-5fe5e7bab0b7?ixlib=rb-1.2.1&auto=format&fit=crop&w=1350&q=80" 
                     alt="Heavy Vehicle" class="vehicle-type-image">
                <div class="vehicle-type-content">
                    <h3 class="vehicle-type-title">
                        <i class="fas fa-truck vehicle-type-icon"></i> HEAVY VEHICLE
                        <span class="vehicle-type-count"><?php echo count($heavyVehicles); ?> 10 available</span>
                    </h3>
                    <p class="vehicle-type-description">
                        Powerful vehicles for commercial and industrial use. Includes trucks, buses, and specialized equipment.
                    </p>
                </div>
            </div>
        </div>
        
        <!-- Two Wheeler Vehicles -->
        <div id="two-wheeler-vehicles" class="vehicles-container">
            <h2 class="section-title">Two Wheeler Vehicles</h2>
            <?php if (empty($twoWheelers)): ?>
                <div class="no-vehicles">
                    <i class="fas fa-info-circle" style="font-size: 2rem; margin-bottom: 15px;"></i>
                    <p>No two-wheeler vehicles currently available for booking.</p>
                </div>
            <?php else: ?>
                <div class="vehicle-grid">
                    <?php foreach ($twoWheelers as $vehicle): ?>
                        <div class="vehicle-card" onclick="selectVehicle(<?php echo $vehicle['vehicle_id']; ?>)">
                            <img src="<?php echo htmlspecialchars($vehicle['image_url'] ?? 'images/default-bike.jpg'); ?>" 
                                 alt="<?php echo htmlspecialchars($vehicle['make'] . ' ' . $vehicle['model']); ?>" 
                                 class="vehicle-image">
                            <div class="vehicle-details">
                                <h3 class="vehicle-title"><?php echo htmlspecialchars($vehicle['make'] . ' ' . $vehicle['model']); ?></h3>
                                <div class="vehicle-meta">
                                    <span><i class="fas fa-calendar-alt"></i> <?php echo htmlspecialchars($vehicle['year']); ?></span>
                                    <span><i class="fas fa-gas-pump"></i> <?php echo htmlspecialchars($vehicle['fuel_type']); ?></span>
                                    <span><i class="fas fa-tachometer-alt"></i> <?php echo htmlspecialchars($vehicle['transmission']); ?></span>
                                </div>
                                <div class="vehicle-price">$<?php echo number_format($vehicle['daily_rate'], 2); ?>/day</div>
                                <button class="btn" onclick="event.stopPropagation(); showBookingForm(<?php echo $vehicle['vehicle_id']; ?>)">
                                    <i class="fas fa-calendar-check"></i> Book Now
                                </button>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </div>
        
        <!-- Four Wheeler Vehicles -->
        <div id="four-wheeler-vehicles" class="vehicles-container">
            <h2 class="section-title">Four Wheeler Vehicles</h2>
            <?php if (empty($fourWheelers)): ?>
                <div class="no-vehicles">
                    <i class="fas fa-info-circle" style="font-size: 2rem; margin-bottom: 15px;"></i>
                    <p>No four-wheeler vehicles currently available for booking.</p>
                </div>
            <?php else: ?>
                <div class="vehicle-grid">
                    <?php foreach ($fourWheelers as $vehicle): ?>
                        <div class="vehicle-card" onclick="window.location.href='four_wheeler.php'">
                            <img src="<?php echo htmlspecialchars($vehicle['image_url'] ?? 'images/default-car.jpg'); ?>" 
                                 alt="<?php echo htmlspecialchars($vehicle['make'] . ' ' . $vehicle['model']); ?>" 
                                 class="vehicle-image">
                            <div class="vehicle-details">
                                <h3 class="vehicle-title"><?php echo htmlspecialchars($vehicle['make'] . ' ' . $vehicle['model']); ?></h3>
                                <div class="vehicle-meta">
                                    <span><i class="fas fa-calendar-alt"></i> <?php echo htmlspecialchars($vehicle['year']); ?></span>
                                    <span><i class="fas fa-gas-pump"></i> <?php echo htmlspecialchars($vehicle['fuel_type']); ?></span>
                                    <span><i class="fas fa-users"></i> <?php echo htmlspecialchars($vehicle['seating_capacity']); ?> seats</span>
                                </div>
                                <div class="vehicle-price">$<?php echo number_format($vehicle['daily_rate'], 2); ?>/day</div>
                                <button class="btn" onclick="event.stopPropagation(); showBookingForm(<?php echo $vehicle['vehicle_id']; ?>)">
                                    <i class="fas fa-calendar-check"></i> Book Now
                                </button>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </div>
        
        <!-- Heavy Vehicles -->
        <div id="heavy-vehicle-vehicles" class="vehicles-container">
            <h2 class="section-title">Heavy Vehicles</h2>
            <?php if (empty($heavyVehicles)): ?>
                <div class="no-vehicles">
                    <i class="fas fa-info-circle" style="font-size: 2rem; margin-bottom: 15px;"></i>
                    <p>No heavy vehicles currently available for booking.</p>
                </div>
            <?php else: ?>
                <div class="vehicle-grid">
                    <?php foreach ($heavyVehicles as $vehicle): ?>
                        <div class="vehicle-card" onclick="selectVehicle(<?php echo $vehicle['vehicle_id']; ?>)">
                            <img src="<?php echo htmlspecialchars($vehicle['image_url'] ?? 'images/default-truck.jpg'); ?>" 
                                 alt="<?php echo htmlspecialchars($vehicle['make'] . ' ' . $vehicle['model']); ?>" 
                                 class="vehicle-image">
                            <div class="vehicle-details">
                                <h3 class="vehicle-title"><?php echo htmlspecialchars($vehicle['make'] . ' ' . $vehicle['model']); ?></h3>
                                <div class="vehicle-meta">
                                    <span><i class="fas fa-calendar-alt"></i> <?php echo htmlspecialchars($vehicle['year']); ?></span>
                                    <span><i class="fas fa-gas-pump"></i> <?php echo htmlspecialchars($vehicle['fuel_type']); ?></span>
                                    <span><i class="fas fa-weight"></i> <?php echo htmlspecialchars($vehicle['seating_capacity']); ?> ton</span>
                                </div>
                                <div class="vehicle-price">$<?php echo number_format($vehicle['daily_rate'], 2); ?>/day</div>
                                <button class="btn" onclick="event.stopPropagation(); showBookingForm(<?php echo $vehicle['vehicle_id']; ?>)">
                                    <i class="fas fa-calendar-check"></i> Book Now
                                </button>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </div>
        
        <!-- Booking Form (hidden by default) -->
        <div id="booking-form" class="booking-form">
            <h2 class="section-title">Complete Your Booking</h2>
            <form method="POST" id="bookingForm">
                <input type="hidden" name="vehicle_id" id="form_vehicle_id">
                
                <div class="form-grid">
                    <div class="form-group">
                        <label for="start_date">Pickup Date & Time</label>
                        <input type="datetime-local" id="start_date" name="start_date" 
                               class="form-control" required min="<?php echo date('Y-m-d\TH:i'); ?>">
                    </div>
                    
                    <div class="form-group">
                        <label for="end_date">Return Date & Time</label>
                        <input type="datetime-local" id="end_date" name="end_date" 
                               class="form-control" required min="<?php echo date('Y-m-d\TH:i'); ?>">
                    </div>
                </div>
                
                <div class="form-grid">
                    <div class="form-group">
                        <label for="pickup_location">Pickup Location</label>
                        <select id="pickup_location" name="pickup_location" class="form-control" required>
                            <option value="">-- Select Location --</option>
                            <option value="Main Office - 123 Center St">Main Office - 123 Center St</option>
                            <option value="Downtown Branch - 456 Main Ave">Downtown Branch - 456 Main Ave</option>
                            <option value="Airport Location - 789 Terminal Rd">Airport Location - 789 Terminal Rd</option>
                        </select>
                    </div>
                    
                    <div class="form-group">
                        <label for="dropoff_location">Return Location</label>
                        <select id="dropoff_location" name="dropoff_location" class="form-control">
                            <option value="">Same as pickup location</option>
                            <option value="Main Office - 123 Center St">Main Office - 123 Center St</option>
                            <option value="Downtown Branch - 456 Main Ave">Downtown Branch - 456 Main Ave</option>
                            <option value="Airport Location - 789 Terminal Rd">Airport Location - 789 Terminal Rd</option>
                        </select>
                    </div>
                </div>
                
                <div class="form-group">
                    <label for="special_requests">Special Requests</label>
                    <textarea id="special_requests" name="special_requests" class="form-control" 
                              placeholder="Child seats, GPS, additional driver, etc."></textarea>
                </div>
                
                <div class="form-group">
                    <label>Payment Method</label>
                    <div style="display: flex; gap: 20px;">
                        <label style="display: flex; align-items: center; gap: 8px;">
                            <input type="radio" name="payment_method" value="credit_card" required checked> 
                            <i class="far fa-credit-card"></i> Credit Card
                        </label>
                        <label style="display: flex; align-items: center; gap: 8px;">
                            <input type="radio" name="payment_method" value="debit_card"> 
                            <i class="fas fa-credit-card"></i> Debit Card
                        </label>
                        <label style="display: flex; align-items: center; gap: 8px;">
                            <input type="radio" name="payment_method" value="paypal"> 
                            <i class="fab fa-paypal"></i> PayPal
                        </label>
                    </div>
                </div>
                
                <div class="form-group" style="margin-top: 30px;">
                    <button type="submit" class="btn">
                        <i class="fas fa-paper-plane"></i> Confirm Booking
                    </button>
                    <button type="button" class="btn btn-secondary" onclick="hideBookingForm()" style="margin-top: 10px;">
                        <i class="fas fa-arrow-left"></i> Back to Vehicles
                    </button>
                </div>
            </form>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/flatpickr"></script>
    <script>
        // Initialize date pickers
        flatpickr("#start_date", {
            enableTime: true,
            minDate: "today",
            dateFormat: "Y-m-d H:i",
            onChange: function(selectedDates, dateStr, instance) {
                const endDatePicker = document.querySelector("#end_date")._flatpickr;
                if (selectedDates.length > 0) {
                    const minEndDate = new Date(selectedDates[0].getTime() + 60 * 60 * 1000);
                    endDatePicker.set("minDate", minEndDate);
                    
                    if (endDatePicker.selectedDates[0] && endDatePicker.selectedDates[0] < minEndDate) {
                        endDatePicker.setDate(minEndDate);
                    }
                }
            }
        });
        
        flatpickr("#end_date", {
            enableTime: true,
            minDate: new Date().fp_incr(3600),
            dateFormat: "Y-m-d H:i"
        });

        // Show vehicles of selected type
        function showVehicles(type) {
            // Hide all vehicle containers
            document.querySelectorAll('.vehicles-container').forEach(container => {
                container.classList.remove('active');
            });
            
            // Show selected vehicle type
            document.getElementById(`${type}-vehicles`).classList.add('active');
            
            // Scroll to the vehicles section
            document.getElementById(`${type}-vehicles`).scrollIntoView({
                behavior: 'smooth'
            });
        }
        
        // Show booking form for selected vehicle
        function showBookingForm(vehicleId) {
            document.getElementById('form_vehicle_id').value = vehicleId;
            document.getElementById('booking-form').classList.add('active');
            
            // Scroll to the form
            document.getElementById('booking-form').scrollIntoView({
                behavior: 'smooth'
            });
        }
        
        // Hide booking form
        function hideBookingForm() {
            document.getElementById('booking-form').classList.remove('active');
        }
        
        // Select vehicle (for visual feedback)
        function selectVehicle(vehicleId) {
            // Remove any existing selection
            document.querySelectorAll('.vehicle-card').forEach(card => {
                card.style.border = 'none';
            });
            
            // Highlight selected vehicle (visual only)
            event.currentTarget.style.border = '3px solid var(--primary-color)';
            
            // You could also pre-fill the form if you want
            // showBookingForm(vehicleId);
        }
        
        // Show two-wheelers by default
        document.addEventListener('DOMContentLoaded', function() {
            showVehicles('two-wheeler');
        });
    </script>
</body>
</html>