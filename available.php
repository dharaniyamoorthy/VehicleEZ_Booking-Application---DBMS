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

// Get available vehicles with better query
try {
    $stmt = $pdo->prepare("SELECT * FROM vehicles 
                          WHERE status = 'available' 
                          AND vehicle_id NOT IN (
                              SELECT vehicle_id FROM bookings 
                              WHERE booking_status IN ('confirmed', 'pending')
                              AND (
                                  (start_date BETWEEN ? AND ?)
                                  OR (end_date BETWEEN ? AND ?)
                                  OR (start_date <= ? AND end_date >= ?)
                              )
                          ");
    
    // Set date range to check availability (next 30 days as example)
    $checkStart = date('Y-m-d H:i:s');
    $checkEnd = date('Y-m-d H:i:s', strtotime('+30 days'));
    
    $stmt->execute([$checkStart, $checkEnd, $checkStart, $checkEnd, $checkStart, $checkEnd]);
    $vehicles = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    $error = "Error loading vehicles. Please try again later.";
    error_log("Vehicle query error: " . $e->getMessage());
    $vehicles = [];
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Book a Vehicle</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/flatpickr/dist/flatpickr.min.css">
    <style>
        /* Your existing styles */
        .vehicle-container {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(300px, 1fr));
            gap: 20px;
            margin: 20px 0;
        }
        
        .vehicle-card {
            border: 1px solid #ddd;
            border-radius: 8px;
            padding: 15px;
            transition: all 0.3s;
        }
        
        .vehicle-card:hover {
            box-shadow: 0 4px 8px rgba(0,0,0,0.1);
            transform: translateY(-5px);
        }
        
        .vehicle-image {
            width: 100%;
            height: 180px;
            object-fit: cover;
            border-radius: 5px;
        }
        
        .vehicle-details {
            margin-top: 10px;
        }
        
        .vehicle-title {
            font-size: 1.2rem;
            margin: 0 0 5px;
            color: #2c3e50;
        }
        
        .vehicle-specs {
            display: flex;
            flex-wrap: wrap;
            gap: 10px;
            margin: 10px 0;
        }
        
        .spec-item {
            display: flex;
            align-items: center;
            gap: 5px;
            font-size: 0.9rem;
            color: #555;
        }
        
        .vehicle-price {
            font-size: 1.3rem;
            font-weight: bold;
            color: #2ecc71;
            margin: 10px 0;
        }
        
        .select-btn {
            background-color: #3498db;
            color: white;
            border: none;
            padding: 8px 15px;
            border-radius: 4px;
            cursor: pointer;
            width: 100%;
        }
        
        .no-vehicles {
            text-align: center;
            padding: 30px;
            background-color: #f8f9fa;
            border-radius: 8px;
            margin: 20px 0;
        }
    </style>
</head>
<body>
    <?php include 'navbar.php'; ?>
    
    <div class="container">
        <div class="booking-header">
            <h1><i class="fas fa-car"></i> Book a Vehicle</h1>
            <p>Fill out the form below to reserve your vehicle</p>
        </div>
        
        <?php if ($error): ?>
            <div class="error-message">
                <i class="fas fa-exclamation-circle"></i> <?php echo htmlspecialchars($error); ?>
            </div>
        <?php endif; ?>
        
        <form method="POST" class="booking-form">
            <h2><i class="fas fa-car-side"></i> Select Vehicle</h2>
            
            <?php if (empty($vehicles)): ?>
                <div class="no-vehicles">
                    <i class="fas fa-car-crash" style="font-size: 3rem; color: #95a5a6; margin-bottom: 15px;"></i>
                    <h3>No vehicles currently available for booking</h3>
                    <p>Please check back later or contact support for assistance.</p>
                </div>
            <?php else: ?>
                <div class="vehicle-container">
                    <?php foreach ($vehicles as $vehicle): ?>
                        <div class="vehicle-card">
                            <img src="<?php echo htmlspecialchars($vehicle['image_url'] ?? 'images/default-car.jpg'); ?>" 
                                 alt="<?php echo htmlspecialchars($vehicle['make'].' '.$vehicle['model']); ?>" 
                                 class="vehicle-image">
                            <div class="vehicle-details">
                                <h3 class="vehicle-title"><?php echo htmlspecialchars($vehicle['make'].' '.$vehicle['model']); ?></h3>
                                <div class="vehicle-specs">
                                    <span class="spec-item"><i class="fas fa-calendar-alt"></i> <?php echo htmlspecialchars($vehicle['year']); ?></span>
                                    <span class="spec-item"><i class="fas fa-users"></i> <?php echo htmlspecialchars($vehicle['seating_capacity']); ?> seats</span>
                                    <span class="spec-item"><i class="fas fa-gas-pump"></i> <?php echo htmlspecialchars($vehicle['fuel_type']); ?></span>
                                    <span class="spec-item"><i class="fas fa-cog"></i> <?php echo htmlspecialchars($vehicle['transmission']); ?></span>
                                </div>
                                <div class="vehicle-price">
                                    $<?php echo number_format($vehicle['daily_rate'], 2); ?>/day
                                </div>
                                <button type="button" class="select-btn" 
                                        onclick="selectVehicle(<?php echo htmlspecialchars(json_encode($vehicle)); ?>)">
                                    <i class="fas fa-check"></i> Select This Vehicle
                                </button>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
                
                <input type="hidden" id="selected_vehicle_id" name="vehicle_id" required>
            <?php endif; ?>
            
            <!-- Rest of your form (date pickers, etc.) -->
            <div class="form-group required">
                <label for="start_date"><i class="fas fa-calendar-plus"></i> Pickup Date & Time *</label>
                <input type="datetime-local" id="start_date" name="start_date" 
                       class="form-control" required min="<?php echo date('Y-m-d\TH:i'); ?>">
            </div>
            
            <div class="form-group required">
                <label for="end_date"><i class="fas fa-calendar-minus"></i> Return Date & Time *</label>
                <input type="datetime-local" id="end_date" name="end_date" 
                       class="form-control" required min="<?php echo date('Y-m-d\TH:i', strtotime('+1 hour')); ?>">
            </div>
            
            <div class="form-group">
                <button type="submit" class="btn btn-block" <?php echo empty($vehicles) ? 'disabled' : ''; ?>>
                    <i class="fas fa-paper-plane"></i> Submit Booking Request
                </button>
            </div>
        </form>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/flatpickr"></script>
    <script>
        // Function to handle vehicle selection
        function selectVehicle(vehicle) {
            document.getElementById('selected_vehicle_id').value = vehicle.vehicle_id;
            
            // Highlight selected vehicle
            document.querySelectorAll('.vehicle-card').forEach(card => {
                card.style.border = '1px solid #ddd';
            });
            event.target.closest('.vehicle-card').style.border = '2px solid #3498db';
            
            // Update button text
            document.querySelectorAll('.select-btn').forEach(btn => {
                btn.innerHTML = '<i class="fas fa-check"></i> Select This Vehicle';
            });
            event.target.innerHTML = '<i class="fas fa-check-circle"></i> Selected';
            
            // Enable submit button if it was disabled
            document.querySelector('button[type="submit"]').disabled = false;
        }
        
        // Initialize date pickers
        flatpickr("#start_date", {
            enableTime: true,
            minDate: "today",
            dateFormat: "Y-m-d H:i",
            onChange: function(selectedDates) {
                if (selectedDates.length > 0) {
                    const endDatePicker = document.querySelector("#end_date")._flatpickr;
                    const minEndDate = new Date(selectedDates[0].getTime() + 60 * 60 * 1000);
                    endDatePicker.set("minDate", minEndDate);
                    
                    // Adjust end date if it's now before the new min date
                    if (endDatePicker.selectedDates[0] && endDatePicker.selectedDates[0] < minEndDate) {
                        endDatePicker.setDate(minEndDate);
                    }
                }
            }
        });
        
        flatpickr("#end_date", {
            enableTime: true,
            minDate: new Date().fp_incr(3600), // 1 hour from now
            dateFormat: "Y-m-d H:i"
        });
    </script>
</body>
</html>