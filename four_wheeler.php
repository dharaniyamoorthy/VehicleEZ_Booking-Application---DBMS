<?php
session_start();
require_once 'config.php';

// Redirect if not logged in
if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit();
}

$pdo = getDBConnection();

// Get all four-wheeler vehicles
$fourWheelers = [];
try {
    $stmt = $pdo->prepare("SELECT 
        vehicle_id, make, model, year, license_plate, color, 
        daily_rate, status, image_path, vehicle_type
        FROM vehicles 
        WHERE vehicle_type = 'four_wheeler' AND status = 'available'");
    $stmt->execute();
    $fourWheelers = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    error_log("Database error: " . $e->getMessage());
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Four Wheeler Vehicles | Vehicle Rental</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <style>
        :root {
            --primary-color: #3498db;
            --secondary-color: #2ecc71;
            --dark-color: #2c3e50;
            --light-color: #ecf0f1;
            --border-radius: 8px;
            --box-shadow: 0 4px 10px rgba(0,0,0,0.1);
        }
        
        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            margin: 0;
            padding: 20px;
            background-color: #f5f7fa;
            color: var(--dark-color);
            line-height: 1.6;
        }
        
        .container {
            max-width: 1200px;
            margin: 0 auto;
            background: white;
            padding: 30px;
            border-radius: var(--border-radius);
            box-shadow: var(--box-shadow);
        }
        
        .page-header {
            text-align: center;
            margin-bottom: 40px;
            padding-bottom: 20px;
            border-bottom: 2px solid #eee;
        }
        
        .page-header h1 {
            color: var(--primary-color);
            margin-bottom: 10px;
            display: flex;
            justify-content: center;
            align-items: center;
            gap: 15px;
        }
        
        .page-header p {
            color: #666;
            font-size: 1.1rem;
            margin-top: 0;
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
            transition: all 0.3s;
        }
        
        .back-btn:hover {
            background-color: #f0f7ff;
        }
        
        .vehicle-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(300px, 1fr));
            gap: 25px;
            margin-top: 30px;
        }
        
        .vehicle-card {
            border: 1px solid #e0e0e0;
            border-radius: var(--border-radius);
            overflow: hidden;
            transition: all 0.3s;
        }
        
        .vehicle-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 10px 20px rgba(0,0,0,0.1);
            border-color: var(--primary-color);
        }
        
        .vehicle-image-container {
            height: 200px;
            overflow: hidden;
            position: relative;
        }
        
        .vehicle-image {
            width: 100%;
            height: 100%;
            object-fit: cover;
            transition: transform 0.5s;
        }
        
        .vehicle-card:hover .vehicle-image {
            transform: scale(1.05);
        }
        
        .vehicle-details {
            padding: 20px;
        }
        
        .vehicle-title {
            font-size: 1.3rem;
            margin: 0 0 10px 0;
            color: var(--dark-color);
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
            color: #666;
        }
        
        .price-section {
            background: #f8f9fa;
            padding: 15px;
            border-top: 1px solid #eee;
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
        
        .btn-book {
            display: block;
            text-align: center;
            background: var(--primary-color);
            color: white;
            padding: 12px;
            border-radius: var(--border-radius);
            text-decoration: none;
            font-weight: 600;
            margin-top: 15px;
            transition: background 0.3s;
        }
        
        .btn-book:hover {
            background: #2980b9;
        }
        
        .vehicle-type-badge {
            position: absolute;
            top: 10px;
            right: 10px;
            background: rgba(0,0,0,0.7);
            color: white;
            padding: 5px 10px;
            border-radius: 20px;
            font-size: 0.8rem;
            font-weight: 600;
        }
        
        .no-vehicles {
            text-align: center;
            padding: 40px;
            background: #f8f9fa;
            border-radius: var(--border-radius);
            color: #666;
        }
        
        .no-vehicles i {
            font-size: 2rem;
            margin-bottom: 15px;
            color: #666;
        }
        
        .no-vehicles p {
            font-size: 1.1rem;
            margin: 0;
        }
        
        @media (max-width: 768px) {
            .container {
                padding: 20px;
            }
            
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
                <i class="fas fa-car"></i> Four Wheeler Vehicles
            </h1>
            <p>Browse our collection of comfortable and spacious vehicles</p>
        </div>
        
        <?php if (!empty($fourWheelers)): ?>
            <div class="vehicle-grid">
                <?php foreach ($fourWheelers as $vehicle): ?>
                    <div class="vehicle-card">
                        <div class="vehicle-image-container">
                            <img src="<?php echo htmlspecialchars($vehicle['image_path'] ?? 'images/default-car.jpg'); ?>" 
                                 alt="<?php echo htmlspecialchars($vehicle['make'] . ' ' . $vehicle['model']); ?>" 
                                 class="vehicle-image">
                            <span class="vehicle-type-badge">
                                <?php echo ucfirst(str_replace('_', ' ', $vehicle['vehicle_type'])); ?>
                            </span>
                        </div>
                        <div class="vehicle-details">
                            <h3 class="vehicle-title">
                                <?php echo htmlspecialchars($vehicle['make'] . ' ' . $vehicle['model']); ?>
                                <span style="font-size: 0.9rem; color: #666;">(<?php echo htmlspecialchars($vehicle['year']); ?>)</span>
                            </h3>
                            <div class="vehicle-specs">
                                <div class="spec-item">
                                    <i class="fas fa-tag"></i>
                                    <?php echo htmlspecialchars($vehicle['license_plate']); ?>
                                </div>
                                <div class="spec-item">
                                    <i class="fas fa-palette"></i>
                                    <?php echo htmlspecialchars($vehicle['color']); ?>
                                </div>
                                <div class="spec-item">
                                    <i class="fas fa-check-circle"></i>
                                    <?php echo ucfirst(htmlspecialchars($vehicle['status'])); ?>
                                </div>
                            </div>
                        </div>
                        <div class="price-section">
                            <div class="price-row">
                                <span class="price-label">Daily Rate:</span>
                                <span class="price-value">₹<?php echo number_format($vehicle['daily_rate'], 2); ?></span>
                            </div>
                            <a href="book_fw.php?vehicle_id=<?php echo $vehicle['vehicle_id']; ?>" class="btn-book">
                                <i class="fas fa-calendar-check"></i> Book Now
                            </a>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php else: ?>
            <div class="no-vehicles">
                <i class="fas fa-info-circle"></i>
                <p>No four-wheeler vehicles currently available.</p>
                <p>Please check back later or contact us for special requests.</p>
            </div>
        <?php endif; ?>
    </div>
</body>
</html>