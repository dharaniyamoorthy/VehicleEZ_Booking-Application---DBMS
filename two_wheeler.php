<?php
session_start();
require_once 'config.php';

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit();
}

// Initialize variables
$twoWheelers = [];
$error = null;
$info = null;

try {
    // Get database connection
    $pdo = getDBConnection();
    
    if (!$pdo) {
        throw new Exception("Database connection failed");
    }

    // Query to fetch available two-wheelers from the vehicles table
    $query = "SELECT 
                vehicle_id, make, model, year, license_plate, color, 
                daily_rate, status, 
                image_path AS image_url, vehicle_type
              FROM vehicles 
              WHERE vehicle_type = 'two_wheeler' 
              AND status = 'available'";
    
    $stmt = $pdo->query($query);
    $twoWheelers = $stmt->fetchAll(PDO::FETCH_ASSOC);

    if (empty($twoWheelers)) {
        $info = "No two-wheeler vehicles currently available. Please check back later.";
    }

    // Get unique filters
    $makes = $twoWheelers ? array_values(array_unique(array_column($twoWheelers, 'make'))) : [];
    $colors = $twoWheelers ? array_values(array_unique(array_column($twoWheelers, 'color'))) : [];

} catch (PDOException $e) {
    error_log("Database error: " . $e->getMessage());
    $error = "Unable to load vehicles. Please try again later.";
} catch (Exception $e) {
    error_log("Error: " . $e->getMessage());
    $error = $e->getMessage();
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Two Wheeler Vehicles | Vehicle Rental</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <style>
        :root {
            --primary-color: #3498db;
            --secondary-color: #2ecc71;
            --dark-color: #2c3e50;
            --light-color: #ecf0f1;
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
            max-width: 1200px;
            margin: 0 auto;
            padding: 20px;
        }
        
        .header {
            text-align: center;
            margin-bottom: 30px;
            padding: 30px 0;
            background: linear-gradient(135deg, #3498db, #2c3e50);
            color: white;
            border-radius: var(--border-radius);
            box-shadow: var(--box-shadow);
        }
        
        .header h1 {
            font-size: 2.5rem;
            margin: 0;
        }
        
        .header p {
            font-size: 1.2rem;
            opacity: 0.9;
        }
        
        .back-btn {
            display: inline-flex;
            align-items: center;
            gap: 10px;
            margin-bottom: 20px;
            padding: 10px 20px;
            background-color: white;
            color: var(--primary-color);
            text-decoration: none;
            font-weight: 600;
            border-radius: var(--border-radius);
            box-shadow: var(--box-shadow);
            transition: var(--transition);
        }
        
        .back-btn:hover {
            transform: translateX(-5px);
        }
        
        .filter-section {
            background: white;
            padding: 20px;
            border-radius: var(--border-radius);
            box-shadow: var(--box-shadow);
            margin-bottom: 30px;
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 15px;
        }
        
        .filter-group {
            margin-bottom: 0;
        }
        
        .filter-group label {
            display: block;
            margin-bottom: 8px;
            font-weight: 600;
        }
        
        .filter-control {
            width: 100%;
            padding: 10px 15px;
            border: 1px solid #ddd;
            border-radius: var(--border-radius);
            font-size: 1rem;
        }
        
        .search-box {
            position: relative;
            grid-column: 1 / -1;
        }
        
        .search-box input {
            padding-left: 40px;
        }
        
        .search-icon {
            position: absolute;
            left: 15px;
            top: 50%;
            transform: translateY(-50%);
            color: #777;
        }
        
        .vehicle-list {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(300px, 1fr));
            gap: 25px;
        }
        
        .vehicle-item {
            background: white;
            border-radius: var(--border-radius);
            overflow: hidden;
            box-shadow: var(--box-shadow);
            transition: var(--transition);
            position: relative;
        }
        
        .vehicle-item:hover {
            transform: translateY(-5px);
            box-shadow: 0 10px 20px rgba(0,0,0,0.15);
        }
        
        .vehicle-badge {
            position: absolute;
            top: 15px;
            right: 15px;
            background: var(--primary-color);
            color: white;
            padding: 5px 10px;
            border-radius: 20px;
            font-size: 0.8rem;
            font-weight: 600;
            z-index: 1;
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
            transition: transform 0.5s ease;
        }
        
        .vehicle-item:hover .vehicle-image {
            transform: scale(1.05);
        }
        
        .vehicle-details {
            padding: 20px;
        }
        
        .vehicle-title {
            font-size: 1.4rem;
            margin: 0 0 10px 0;
        }
        
        .vehicle-meta {
            display: flex;
            flex-wrap: wrap;
            gap: 10px;
            margin-bottom: 15px;
        }
        
        .meta-item {
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
        
        .price-value {
            color: var(--primary-color);
            font-weight: 700;
        }
        
        .btn {
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 10px;
            background: var(--primary-color);
            color: white;
            padding: 12px;
            border-radius: var(--border-radius);
            text-decoration: none;
            font-weight: 600;
            margin-top: 15px;
            transition: var(--transition);
        }
        
        .btn:hover {
            background: #2980b9;
            transform: translateY(-2px);
        }
        
        .no-vehicles {
            text-align: center;
            padding: 40px;
            background: #f8f9fa;
            border-radius: var(--border-radius);
            color: #666;
            font-size: 1.1rem;
            grid-column: 1 / -1;
        }
        
        .compare-section {
            position: fixed;
            bottom: 20px;
            right: 20px;
            background: white;
            padding: 15px;
            border-radius: var(--border-radius);
            box-shadow: 0 5px 15px rgba(0,0,0,0.2);
            z-index: 1000;
            display: none;
        }
        
        .compare-btn {
            background: var(--secondary-color);
            color: white;
            border: none;
            padding: 10px 20px;
            border-radius: var(--border-radius);
            cursor: pointer;
            font-weight: 600;
            transition: var(--transition);
        }
        
        .compare-btn:hover {
            background: #27ae60;
        }
        
        .compare-count {
            display: inline-block;
            width: 24px;
            height: 24px;
            background: var(--primary-color);
            color: white;
            border-radius: 50%;
            text-align: center;
            line-height: 24px;
            margin-left: 5px;
        }
        
        .favorite-btn {
            position: absolute;
            top: 15px;
            left: 15px;
            background: rgba(255,255,255,0.8);
            border: none;
            width: 36px;
            height: 36px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            cursor: pointer;
            z-index: 1;
            transition: var(--transition);
        }
        
        .favorite-btn:hover {
            background: rgba(255,255,255,1);
        }
        
        .favorite-btn i {
            color: #e74c3c;
            font-size: 18px;
        }
        
        .favorite-btn.active i {
            color: #c0392b;
        }
        
        .alert {
            padding: 15px;
            margin-bottom: 20px;
            border: 1px solid transparent;
            border-radius: var(--border-radius);
        }
        
        .alert-danger {
            color: #721c24;
            background-color: #f8d7da;
            border-color: #f5c6cb;
        }
    </style>
</head>
<body>
    <div class="container">
        <a href="index.php" class="back-btn">
            <i class="fas fa-arrow-left"></i> Back to Vehicle Types
        </a>
        
        <div class="header">
            <h1><i class="fas fa-motorcycle"></i> Two Wheeler Vehicles</h1>
            <p>Browse and book from our premium collection of two-wheelers</p>
        </div>
        
        <?php if ($error): ?>
            <div class="alert alert-danger">
                <?php echo htmlspecialchars($error); ?>
            </div>
        <?php endif; ?>
        
        <div class="filter-section">
            <div class="search-box filter-group">
                <i class="fas fa-search search-icon"></i>
                <input type="text" id="search-input" class="filter-control" placeholder="Search by make or model...">
            </div>
            
            <?php if (!empty($makes)): ?>
            <div class="filter-group">
                <label for="make-filter">Make</label>
                <select id="make-filter" class="filter-control">
                    <option value="">All Makes</option>
                    <?php foreach ($makes as $make): ?>
                        <option value="<?php echo htmlspecialchars($make); ?>"><?php echo htmlspecialchars($make); ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <?php endif; ?>
            
            <?php if (!empty($colors)): ?>
            <div class="filter-group">
                <label for="color-filter">Color</label>
                <select id="color-filter" class="filter-control">
                    <option value="">All Colors</option>
                    <?php foreach ($colors as $color): ?>
                        <option value="<?php echo htmlspecialchars($color); ?>"><?php echo htmlspecialchars($color); ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <?php endif; ?>
        </div>
        
        <div class="vehicle-list" id="vehicle-list">
            <?php if (!empty($twoWheelers)): ?>
                <?php foreach ($twoWheelers as $vehicle): ?>
                    <div class="vehicle-item" 
                         data-make="<?php echo htmlspecialchars($vehicle['make']); ?>"
                         data-color="<?php echo htmlspecialchars($vehicle['color']); ?>"
                         data-search="<?php echo htmlspecialchars(strtolower($vehicle['make'] . ' ' . $vehicle['model'])); ?>"
                         data-id="<?php echo $vehicle['vehicle_id']; ?>">
                        
                        <button class="favorite-btn" data-id="<?php echo $vehicle['vehicle_id']; ?>">
                            <i class="far fa-heart"></i>
                        </button>
                        
                        <div class="vehicle-image-container">
                            <img src="<?php echo htmlspecialchars($vehicle['image_url'] ?? 'images/default-bike.jpg'); ?>" 
                                 alt="<?php echo htmlspecialchars($vehicle['make'] . ' ' . $vehicle['model']); ?>" 
                                 class="vehicle-image">
                        </div>
                        
                        <div class="vehicle-details">
                            <h3 class="vehicle-title"><?php echo htmlspecialchars($vehicle['make'] . ' ' . $vehicle['model']); ?></h3>
                            <div class="vehicle-meta">
                                <span class="meta-item">
                                    <i class="fas fa-calendar-alt"></i> <?php echo htmlspecialchars($vehicle['year']); ?>
                                </span>
                                <span class="meta-item">
                                    <i class="fas fa-tag"></i> <?php echo htmlspecialchars($vehicle['license_plate']); ?>
                                </span>
                                <span class="meta-item">
                                    <i class="fas fa-palette"></i> <?php echo htmlspecialchars($vehicle['color']); ?>
                                </span>
                            </div>
                        </div>
                        
                        <div class="price-section">
                            <div class="price-row">
                                <span>Daily Rate:</span>
                                <span class="price-value">₹<?php echo number_format($vehicle['daily_rate'], 2); ?></span>
                            </div>
                            <a href="book_tw.php?vehicle_id=<?php echo $vehicle['vehicle_id']; ?>" class="btn">
                                <i class="fas fa-calendar-check"></i> Book Now
                            </a>
                        </div>
                    </div>
                <?php endforeach; ?>
            <?php else: ?>
                <div class="no-vehicles">
                    <i class="fas fa-info-circle"></i>
                    <p><?php echo htmlspecialchars($info ?? 'No two-wheeler vehicles currently available.'); ?></p>
                    <p>Please check back later or contact us for special requests.</p>
                </div>
            <?php endif; ?>
        </div>
    </div>

    <div class="compare-section" id="compare-section">
        <button class="compare-btn" id="compare-action-btn">
            Compare Vehicles (<span id="compare-count" class="compare-count">0</span>)
        </button>
    </div>

    <script>
        document.addEventListener('DOMContentLoaded', function() {
            // Favorite functionality
            const favoriteBtns = document.querySelectorAll('.favorite-btn');
            favoriteBtns.forEach(btn => {
                btn.addEventListener('click', function() {
                    const heartIcon = this.querySelector('i');
                    heartIcon.classList.toggle('far');
                    heartIcon.classList.toggle('fas');
                    this.classList.toggle('active');
                    
                    const vehicleId = this.dataset.id;
                    // Here you would typically send an AJAX request to save the favorite status
                    console.log(`Vehicle ${vehicleId} favorite status toggled`);
                });
            });
            
            // Compare functionality
            const compareSection = document.getElementById('compare-section');
            const compareCount = document.getElementById('compare-count');
            const compareActionBtn = document.getElementById('compare-action-btn');
            const compareAddBtns = document.querySelectorAll('.compare-add-btn');
            let compareItems = [];
            
            // Filter functionality
            const filters = {
                search: '',
                make: '',
                color: ''
            };
            
            const filterElements = {
                search: document.getElementById('search-input'),
                make: document.getElementById('make-filter'),
                color: document.getElementById('color-filter')
            };
            
            // Add event listeners to all filters
            Object.keys(filterElements).forEach(key => {
                if (filterElements[key]) {
                    filterElements[key].addEventListener('input', function() {
                        filters[key] = this.value.toLowerCase();
                        filterVehicles();
                    });
                }
            });
            
            function filterVehicles() {
                const vehicles = document.querySelectorAll('.vehicle-item');
                let visibleCount = 0;
                
                vehicles.forEach(vehicle => {
                    const matchesSearch = vehicle.dataset.search.includes(filters.search);
                    const matchesMake = !filters.make || vehicle.dataset.make.toLowerCase() === filters.make;
                    const matchesColor = !filters.color || vehicle.dataset.color.toLowerCase() === filters.color;
                    
                    if (matchesSearch && matchesMake && matchesColor) {
                        vehicle.style.display = 'block';
                        visibleCount++;
                    } else {
                        vehicle.style.display = 'none';
                    }
                });
                
                // Show no results message if needed
                const noResults = document.getElementById('no-results');
                if (visibleCount === 0 && vehicles.length > 0) {
                    if (!noResults) {
                        const noResultsDiv = document.createElement('div');
                        noResultsDiv.id = 'no-results';
                        noResultsDiv.className = 'no-vehicles';
                        noResultsDiv.innerHTML = `
                            <i class="fas fa-search"></i>
                            <p>No vehicles match your search criteria.</p>
                            <button onclick="resetFilters()" class="btn" style="margin-top: 15px;">
                                <i class="fas fa-times"></i> Reset Filters
                            </button>
                        `;
                        document.getElementById('vehicle-list').appendChild(noResultsDiv);
                    }
                } else if (noResults) {
                    noResults.remove();
                }
            }
            
            // Reset filters function
            window.resetFilters = function() {
                Object.keys(filterElements).forEach(key => {
                    if (filterElements[key]) {
                        filterElements[key].value = '';
                        filters[key] = '';
                    }
                });
                filterVehicles();
            };
        });
    </script>
</body>
</html>