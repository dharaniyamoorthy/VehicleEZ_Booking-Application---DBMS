<?php
session_start();
require_once 'config.php';

// Verify admin is logged in
if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'admin') {
    header('Location: login.php');
    exit();
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        $pdo = getDBConnection();
        
        // Handle file upload
        $image_path = '';
        if (isset($_FILES['vehicle_image']) && $_FILES['vehicle_image']['error'] === UPLOAD_ERR_OK) {
            $upload_dir = 'uploads/vehicles/';
            if (!is_dir($upload_dir)) {
                mkdir($upload_dir, 0755, true);
            }
            
            $file_name = time() . '_' . basename($_FILES['vehicle_image']['name']);
            $target_file = $upload_dir . $file_name;
            
            // Check if image file is actual image
            $check = getimagesize($_FILES['vehicle_image']['tmp_name']);
            if ($check !== false) {
                if (move_uploaded_file($_FILES['vehicle_image']['tmp_name'], $target_file)) {
                    $image_path = $target_file;
                } else {
                    throw new Exception("Sorry, there was an error uploading your file.");
                }
            } else {
                throw new Exception("File is not an image.");
            }
        }
        
        // Insert vehicle data
        $stmt = $pdo->prepare("INSERT INTO vehicles (
            type, make, model, year, registration_number, color, mileage, 
            fuel_type, seating_capacity, daily_rate, hourly_rate, weekly_rate, 
            monthly_rate, description, image_path, is_available
        ) VALUES (
            :type, :make, :model, :year, :registration_number, :color, :mileage,
            :fuel_type, :seating_capacity, :daily_rate, :hourly_rate, :weekly_rate,
            :monthly_rate, :description, :image_path, 1
        )");
        
        $stmt->execute([
            ':type' => $_POST['type'],
            ':make' => $_POST['make'],
            ':model' => $_POST['model'],
            ':year' => $_POST['year'],
            ':registration_number' => $_POST['registration_number'],
            ':color' => $_POST['color'],
            ':mileage' => !empty($_POST['mileage']) ? $_POST['mileage'] : null,
            ':fuel_type' => $_POST['fuel_type'],
            ':seating_capacity' => !empty($_POST['seating_capacity']) ? $_POST['seating_capacity'] : null,
            ':daily_rate' => $_POST['daily_rate'],
            ':hourly_rate' => !empty($_POST['hourly_rate']) ? $_POST['hourly_rate'] : null,
            ':weekly_rate' => !empty($_POST['weekly_rate']) ? $_POST['weekly_rate'] : null,
            ':monthly_rate' => !empty($_POST['monthly_rate']) ? $_POST['monthly_rate'] : null,
            ':description' => $_POST['description'],
            ':image_path' => $image_path
        ]);
        
        // Get the ID of the newly inserted vehicle
        $vehicle_id = $pdo->lastInsertId();
        
        // Redirect to view the newly added vehicle
        $_SESSION['success_message'] = "Vehicle added successfully!";
        header("Location: view_vehicle.php?id=$vehicle_id");
        exit();
        
    } catch (Exception $e) {
        $_SESSION['error_message'] = $e->getMessage();
        header('Location: add_vehicle.php');
        exit();
    }
} else {
    header('Location: add_vehicle.php');
    exit();
}
?>