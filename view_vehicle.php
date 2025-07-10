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
    header('Location: manage_vehicles.php');
    exit();
}

$vehicle_id = $_GET['id'];

try {
    $pdo = getDBConnection();
    $stmt = $pdo->prepare("SELECT * FROM vehicles WHERE vehicle_id = :id");
    $stmt->execute([':id' => $vehicle_id]);
    $vehicle = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$vehicle) {
        throw new Exception("Vehicle not found");
    }
} catch (Exception $e) {
    $_SESSION['error_message'] = $e->getMessage();
    header('Location: manage_vehicles.php');
    exit();
}

// Display success message if exists
if (isset($_SESSION['success_message'])) {
    $success_message = $_SESSION['success_message'];
    unset($_SESSION['success_message']);
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>View Vehicle | Admin Dashboard</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <!-- Include your CSS styles here -->
</head>
<body>
    <?php include 'admin_header.php'; ?>
    
    <div class="container">
        <?php if (isset($success_message)): ?>
            <div class="alert alert-success">
                <?php echo htmlspecialchars($success_message); ?>
            </div>
        <?php endif; ?>
        
        <h1>Vehicle Details</h1>
        
        <div class="vehicle-details">
            <?php if ($vehicle['image_path']): ?>
                <img src="<?php echo $vehicle['image_path']; ?>" alt="Vehicle Image" class="vehicle-image">
            <?php endif; ?>
            
            <h2><?php echo htmlspecialchars($vehicle['make'] . ' ' . $vehicle['model']); ?></h2>
            <p>Type: <?php echo ucfirst(str_replace('_', ' ', $vehicle['type'])); ?></p>
            <p>Year: <?php echo $vehicle['year']; ?></p>
            <p>Registration: <?php echo htmlspecialchars($vehicle['registration_number']); ?></p>
            <p>Color: <?php echo htmlspecialchars($vehicle['color']); ?></p>
            <p>Fuel Type: <?php echo ucfirst($vehicle['fuel_type']); ?></p>
            <p>Daily Rate: ₹<?php echo number_format($vehicle['daily_rate'], 2); ?></p>
            
            <h3>Description</h3>
            <p><?php echo nl2br(htmlspecialchars($vehicle['description'])); ?></p>
            
            <a href="manage_vehicles.php" class="btn btn-primary">
                <i class="fas fa-arrow-left"></i> Back to Vehicles
            </a>
        </div>
    </div>
</body>
</html>