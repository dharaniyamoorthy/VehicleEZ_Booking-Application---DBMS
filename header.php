<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);

// Only start session if it hasn't been started already
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Dashboard | VehicleEZ</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
</head>
<body>
    <div class="dashboard-header">
        <div class="brand-title">
            <h1 class="brand-name">VehicleEZ</h1>
            <p class="brand-tagline">Admin Dashboard</p>
        </div>
        <div class="header-right">
            <div id="live-date-time">
                <?php echo date('l, F j, Y | H:i:s'); ?>
            </div>
            <div>
                <span><?php echo htmlspecialchars($_SESSION['username'] ?? 'Admin'); ?></span>
                <span class="admin-badge"><i class="fas fa-shield-alt"></i> ADMIN</span>
            </div>
        </div>
    </div>