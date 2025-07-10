<?php
session_start();
require_once 'config.php';

if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit();
}

$pdo = getDBConnection();
$heavyVehicles = [];

try {
    $stmt = $pdo->prepare("SELECT 
        vehicle_id, make, model, year, license_plate, color, 
        daily_rate, status, image_path, vehicle_type
        FROM vehicles 
        WHERE vehicle_type = 'heavy' AND status = 'available'");
    $stmt->execute();
    $heavyVehicles = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    error_log("Database error: " . $e->getMessage());
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Heavy Vehicles | Vehicle Booking System</title>
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
            max-width: 1200px;
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

        .page-header {
            text-align: center;
            margin-bottom: 40px;
        }

        .page-header h1 {
            font-size: 2.5rem;
            color: var(--primary-color);
            margin-bottom: 10px;
        }

        .page-header i {
            margin-right: 10px;
        }

        .page-header p {
            color: var(--dark-gray);
            font-size: 1.1rem;
        }

        .vehicle-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(300px, 1fr));
            gap: 30px;
            margin-top: 30px;
        }

        .vehicle-card {
            background: var(--white);
            border-radius: 10px;
            overflow: hidden;
            box-shadow: var(--shadow);
            transition: var(--transition);
        }

        .vehicle-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 10px 20px rgba(0, 0, 0, 0.1);
        }

        .vehicle-image-container {
            position: relative;
            height: 200px;
            overflow: hidden;
        }

        .vehicle-image {
            width: 100%;
            height: 100%;
            object-fit: cover;
            transition: var(--transition);
        }

        .vehicle-card:hover .vehicle-image {
            transform: scale(1.05);
        }

        .vehicle-type-badge {
            position: absolute;
            top: 15px;
            right: 15px;
            background-color: var(--accent-color);
            color: var(--white);
            padding: 5px 10px;
            border-radius: 20px;
            font-size: 0.8rem;
            font-weight: bold;
        }

        .vehicle-details {
            padding: 20px;
        }

        .vehicle-title {
            font-size: 1.3rem;
            margin-bottom: 10px;
            color: var(--text-color);
        }

        .vehicle-specs {
            display: flex;
            flex-wrap: wrap;
            gap: 15px;
            margin-bottom: 15px;
        }

        .spec-item {
            display: flex;
            align-items: center;
            gap: 5px;
            font-size: 0.9rem;
            color: var(--dark-gray);
        }

        .price-section {
            padding: 15px 20px;
            border-top: 1px solid #eee;
        }

        .price-row {
            display: flex;
            justify-content: space-between;
            margin-bottom: 8px;
        }

        .price-label {
            font-weight: 600;
            color: var(--dark-gray);
        }

        .price-amount {
            font-weight: bold;
            color: var(--primary-color);
        }

        .book-btn {
            display: block;
            width: 100%;
            padding: 12px;
            background-color: var(--primary-color);
            color: var(--white);
            border: none;
            border-radius: 5px;
            font-weight: bold;
            cursor: pointer;
            transition: var(--transition);
            margin-top: 15px;
            text-align: center;
            text-decoration: none;
        }

        .book-btn:hover {
            background-color: var(--secondary-color);
        }

        .no-vehicles {
            text-align: center;
            padding: 50px 20px;
            background-color: var(--white);
            border-radius: 10px;
            box-shadow: var(--shadow);
            grid-column: 1 / -1;
        }

        .no-vehicles i {
            font-size: 2rem;
            color: var(--primary-color);
            margin-bottom: 15px;
        }

        .no-vehicles p {
            font-size: 1.1rem;
            color: var(--dark-gray);
            margin-bottom: 10px;
        }

        @media (max-width: 768px) {
            .vehicle-grid {
                grid-template-columns: repeat(auto-fill, minmax(250px, 1fr));
            }
            
            .page-header h1 {
                font-size: 2rem;
            }
        }

        @media (max-width: 480px) {
            .vehicle-grid {
                grid-template-columns: 1fr;
            }
        }
    </style>
</head>
<body>
    <div class="container">
        <a href="index.php" class="back-btn">
            <i class="fas fa-arrow-left"></i> Back to Vehicle Types
        </a>
        
        <div class="page-header">
            <h1>
                <i class="fas fa-truck"></i> Heavy Vehicles
            </h1>
            <p>Browse our collection of powerful commercial vehicles</p>
        </div>
        
        <?php if (!empty($heavyVehicles)): ?>
            <div class="vehicle-grid">
                <?php foreach ($heavyVehicles as $vehicle): ?>
                    <div class="vehicle-card">
                        <div class="vehicle-image-container">
                            <img src="<?php echo htmlspecialchars($vehicle['image_path'] ?? 'images/default-truck.jpg'); ?>" 
                                 alt="<?php echo htmlspecialchars($vehicle['make'] . ' ' . $vehicle['model']); ?>" 
                                 class="vehicle-image">
                            <span class="vehicle-type-badge">
                                <?php echo ucfirst(htmlspecialchars($vehicle['vehicle_type'])); ?>
                            </span>
                        </div>
                        <div class="vehicle-details">
                            <h3 class="vehicle-title">
                                <?php echo htmlspecialchars($vehicle['make'] . ' ' . $vehicle['model']); ?>
                                <span style="font-size: 0.9rem; color: #666;">(<?php echo htmlspecialchars($vehicle['year']); ?>)</span>
                            </h3>
                            <div class="vehicle-specs">
                                <div class="spec-item">
                                    <i class="fas fa-tag"></i> <?php echo htmlspecialchars($vehicle['license_plate']); ?>
                                </div>
                                <div class="spec-item">
                                    <i class="fas fa-palette"></i> <?php echo htmlspecialchars($vehicle['color']); ?>
                                </div>
                                <div class="spec-item">
                                    <i class="fas fa-check-circle"></i> <?php echo ucfirst(htmlspecialchars($vehicle['status'])); ?>
                                </div>
                            </div>
                        </div>
                        <div class="price-section">
                            <div class="price-row">
                                <span class="price-label">Daily Rate:</span>
                                <span class="price-amount">₹<?php echo number_format($vehicle['daily_rate'], 2); ?></span>
                            </div>
                            <a href="book_hv.php?vehicle_id=<?php echo $vehicle['vehicle_id']; ?>" class="book-btn">
                                <i class="fas fa-calendar-check"></i> Book Now
                            </a>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php else: ?>
            <div class="no-vehicles">
                <i class="fas fa-info-circle"></i>
                <p>No heavy vehicles currently available.</p>
                <p>Please check back later or contact us for special requests.</p>
            </div>
        <?php endif; ?>
    </div>
</body>
</html>