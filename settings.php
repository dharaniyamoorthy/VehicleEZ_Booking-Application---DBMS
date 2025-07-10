<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);
session_start();

// Verify admin role
if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'admin') {
    header('Location: login.php');
    exit();
}

// Configuration
$settings_file = __DIR__ . '/system_settings.json';
$backup_file = __DIR__ . '/system_settings_backup.json';

// Default settings (complete set matching your original DB structure)
$default_settings = [
    // General Settings
    'company_name' => 'VehicleEZ',
    'company_email' => '',
    'company_phone' => '',
    'currency' => 'USD',
    'timezone' => 'UTC',
    'maintenance_mode' => '0',
    'maintenance_message' => 'We are currently undergoing maintenance. Please check back later.',
    
    // Booking Settings
    'min_booking_hours' => '2',
    'max_booking_days' => '30',
    'advance_booking_days' => '90',
    'booking_buffer_minutes' => '30',
    'cancellation_fee_percent' => '10',
    'free_cancellation_hours' => '24',
    
    // Vehicle Settings
    'default_availability' => 'available',
    'min_fuel_level' => '20',
    'mileage_alert' => '5000',
    'maintenance_interval' => '90',
    
    // Payment Settings
    'payment_mode' => 'test',
    'stripe_publishable_key' => '',
    'stripe_secret_key' => '',
    'tax_rate' => '0',
    
    // Notification Settings
    'email_from' => 'noreply@vehicleez.com',
    'email_from_name' => 'VehicleEZ',
    'booking_confirmation_email' => '1',
    'booking_reminder_email' => '1',
    'admin_email' => '',
    'alert_new_booking' => '1',
    'alert_cancellation' => '1',
    
    // Security Settings
    'enable_2fa' => '0',
    'password_min_length' => '8',
    'password_require_special' => '1',
    'session_timeout' => '30',
    'login_attempts' => '5',
    'login_lockout' => '15'
];

// Initialize messages
$success_message = '';
$error_message = '';

// Load or initialize settings
try {
    if (file_exists($settings_file)) {
        $settings_data = file_get_contents($settings_file);
        $settings = json_decode($settings_data, true);
        
        // Validate JSON and merge with defaults if incomplete
        if (json_last_error() !== JSON_ERROR_NONE || !is_array($settings)) {
            throw new Exception('Invalid settings file');
        }
        $settings = array_merge($default_settings, $settings);
    } else {
        $settings = $default_settings;
        // Create initial settings file
        file_put_contents($settings_file, json_encode($settings, JSON_PRETTY_PRINT));
    }
} catch (Exception $e) {
    $error_message = "Settings error: " . $e->getMessage();
    $settings = $default_settings;
}

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        // Create backup before modifying
        if (file_exists($settings_file)) {
            copy($settings_file, $backup_file);
        }
        
        // Process each setting
        foreach ($_POST as $key => $value) {
            if (strpos($key, 'setting_') === 0) {
                $setting_key = substr($key, 8);
                $settings[$setting_key] = trim($value);
            }
        }
        
        // Save to file
        file_put_contents($settings_file, json_encode($settings, JSON_PRETTY_PRINT));
        $success_message = "Settings updated successfully!";
        
    } catch (Exception $e) {
        // Restore from backup if error occurs
        if (file_exists($backup_file)) {
            copy($backup_file, $settings_file);
        }
        $error_message = "Failed to update settings: " . $e->getMessage();
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>System Settings | VehicleEZ</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <style>
        :root {
            --primary-color: #3498db;
            --secondary-color: #2ecc71;
            --danger-color: #e74c3c;
            --warning-color: #f39c12;
            --dark-color: #2c3e50;
            --light-color: #ecf0f1;
            --text-color: #333;
            --border-radius: 8px;
            --box-shadow: 0 2px 10px rgba(0,0,0,0.1);
            --admin-color: #6c5ce7;
            --brand-color: #3a86ff;
        }
        
        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            margin: 0;
            padding: 0;
            background-color: #f5f7fa;
            color: var(--text-color);
            line-height: 1.6;
        }
        
        .dashboard-header {
            background-color: white;
            padding: 20px;
            box-shadow: var(--box-shadow);
            display: flex;
            justify-content: space-between;
            align-items: center;
            position: sticky;
            top: 0;
            z-index: 100;
        }
        
        .quick-nav {
            background-color: white;
            margin: 20px;
            padding: 15px;
            border-radius: var(--border-radius);
            box-shadow: var(--box-shadow);
            display: flex;
            gap: 15px;
            flex-wrap: wrap;
        }
        
        .nav-link {
            color: var(--primary-color);
            text-decoration: none;
            font-weight: 500;
            padding: 8px 15px;
            border-radius: var(--border-radius);
            transition: all 0.3s;
        }
        
        .nav-link:hover {
            background-color: rgba(52, 152, 219, 0.1);
        }
        
        .nav-link.active {
            background-color: var(--primary-color);
            color: white;
        }
        
        .settings-container {
            margin: 20px;
        }
        
        .settings-tabs {
            display: flex;
            border-bottom: 1px solid #ddd;
            margin-bottom: 20px;
        }
        
        .settings-tab {
            padding: 10px 20px;
            cursor: pointer;
            border: 1px solid transparent;
            border-bottom: none;
            border-radius: 5px 5px 0 0;
            margin-right: 5px;
            background-color: #f8f9fa;
        }
        
        .settings-tab.active {
            background-color: white;
            border-color: #ddd;
            border-bottom: 1px solid white;
            margin-bottom: -1px;
            font-weight: 600;
        }
        
        .settings-tab-content {
            display: none;
            background-color: white;
            padding: 25px;
            border-radius: 0 var(--border-radius) var(--border-radius) var(--border-radius);
            box-shadow: var(--box-shadow);
        }
        
        .settings-tab-content.active {
            display: block;
        }
        
        .settings-group {
            margin-bottom: 30px;
        }
        
        .settings-group h3 {
            border-bottom: 1px solid #eee;
            padding-bottom: 10px;
            margin-bottom: 20px;
        }
        
        .form-row {
            display: flex;
            margin-bottom: 15px;
            align-items: center;
        }
        
        .form-row label {
            width: 250px;
            font-weight: 500;
        }
        
        .form-row input[type="text"],
        .form-row input[type="number"],
        .form-row input[type="email"],
        .form-row input[type="password"],
        .form-row select,
        .form-row textarea {
            flex: 1;
            padding: 8px 12px;
            border: 1px solid #ddd;
            border-radius: 4px;
        }
        
        .form-row input[type="checkbox"] {
            margin-right: 10px;
        }
        
        .form-actions {
            margin-top: 30px;
            text-align: right;
        }
        
        .btn {
            padding: 10px 20px;
            border-radius: 4px;
            font-weight: 500;
            cursor: pointer;
            border: none;
            transition: all 0.3s;
        }
        
        .btn-primary {
            background-color: var(--primary-color);
            color: white;
        }
        
        .btn-primary:hover {
            background-color: #2980b9;
        }
        
        .alert {
            padding: 15px;
            margin-bottom: 20px;
            border-radius: 4px;
        }
        
        .alert-success {
            background-color: #d4edda;
            color: #155724;
        }
        
        .alert-danger {
            background-color: #f8d7da;
            color: #721c24;
        }
        
        .toggle-switch {
            position: relative;
            display: inline-block;
            width: 60px;
            height: 30px;
        }
        
        .toggle-switch input {
            opacity: 0;
            width: 0;
            height: 0;
        }
        
        .toggle-slider {
            position: absolute;
            cursor: pointer;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background-color: #ccc;
            transition: .4s;
            border-radius: 34px;
        }
        
        .toggle-slider:before {
            position: absolute;
            content: "";
            height: 22px;
            width: 22px;
            left: 4px;
            bottom: 4px;
            background-color: white;
            transition: .4s;
            border-radius: 50%;
        }
        
        input:checked + .toggle-slider {
            background-color: var(--secondary-color);
        }
        
        input:checked + .toggle-slider:before {
            transform: translateX(30px);
        }
        
        @media (max-width: 768px) {
            .quick-nav {
                flex-direction: column;
                gap: 10px;
            }
            
            .form-row {
                flex-direction: column;
                align-items: flex-start;
            }
            
            .form-row label {
                width: 100%;
                margin-bottom: 5px;
            }
        }
    </style>
</head>
<body>
    <div class="dashboard-header">
        <div class="brand-title">
            <h1 class="brand-name">VehicleEZ</h1>
            <p class="brand-tagline">System Settings</p>
        </div>
    </div>
    
    <!-- Navigation Bar -->
    <div class="quick-nav">
        <a href="admin_dashboard.php" class="nav-link"><i class="fas fa-tachometer-alt"></i> Dashboard</a>
        <a href="manage_vehicles.php" class="nav-link"><i class="fas fa-car"></i> Manage Vehicles</a>
        <a href="reports.php" class="nav-link"><i class="fas fa-chart-bar"></i> Reports</a>
        <a href="settings.php" class="nav-link active"><i class="fas fa-cog"></i> Settings</a>
    </div>
    
    <div class="settings-container">
        <?php if ($success_message): ?>
            <div class="alert alert-success">
                <?php echo htmlspecialchars($success_message); ?>
            </div>
        <?php endif; ?>
        
        <?php if ($error_message): ?>
            <div class="alert alert-danger">
                <?php echo htmlspecialchars($error_message); ?>
            </div>
        <?php endif; ?>
        
        <form method="POST">
            <div class="settings-tabs">
                <div class="settings-tab active" data-tab="general">General</div>
                <div class="settings-tab" data-tab="bookings">Bookings</div>
                <div class="settings-tab" data-tab="vehicles">Vehicles</div>
                <div class="settings-tab" data-tab="payments">Payments</div>
                <div class="settings-tab" data-tab="notifications">Notifications</div>
                <div class="settings-tab" data-tab="security">Security</div>
            </div>
            
            <!-- General Settings Tab -->
            <div class="settings-tab-content active" id="general-tab">
                <div class="settings-group">
                    <h3><i class="fas fa-info-circle"></i> Business Information</h3>
                    
                    <div class="form-row">
                        <label for="setting_company_name">Company Name</label>
                        <input type="text" id="setting_company_name" name="setting_company_name" 
                               value="<?php echo htmlspecialchars($settings['company_name'] ?? 'VehicleEZ'); ?>">
                    </div>
                    
                    <div class="form-row">
                        <label for="setting_company_email">Contact Email</label>
                        <input type="email" id="setting_company_email" name="setting_company_email" 
                               value="<?php echo htmlspecialchars($settings['company_email'] ?? ''); ?>">
                    </div>
                    
                    <div class="form-row">
                        <label for="setting_company_phone">Contact Phone</label>
                        <input type="text" id="setting_company_phone" name="setting_company_phone" 
                               value="<?php echo htmlspecialchars($settings['company_phone'] ?? ''); ?>">
                    </div>
                    
                    <div class="form-row">
                        <label for="setting_currency">Currency</label>
                        <select id="setting_currency" name="setting_currency">
                            <option value="USD" <?php echo ($settings['currency'] ?? 'USD') === 'USD' ? 'selected' : ''; ?>>US Dollar ($)</option>
                            <option value="EUR" <?php echo ($settings['currency'] ?? 'USD') === 'EUR' ? 'selected' : ''; ?>>Euro (€)</option>
                            <option value="GBP" <?php echo ($settings['currency'] ?? 'USD') === 'GBP' ? 'selected' : ''; ?>>British Pound (£)</option>
                        </select>
                    </div>
                    
                    <div class="form-row">
                        <label for="setting_timezone">Timezone</label>
                        <select id="setting_timezone" name="setting_timezone">
                            <?php
                            $timezones = DateTimeZone::listIdentifiers();
                            $current_tz = $settings['timezone'] ?? 'UTC';
                            foreach ($timezones as $tz) {
                                echo '<option value="' . htmlspecialchars($tz) . '"';
                                if ($tz === $current_tz) echo ' selected';
                                echo '>' . htmlspecialchars($tz) . '</option>';
                            }
                            ?>
                        </select>
                    </div>
                </div>
                
                <div class="settings-group">
                    <h3><i class="fas fa-tools"></i> System Configuration</h3>
                    
                    <div class="form-row">
                        <label for="setting_maintenance_mode">Maintenance Mode</label>
                        <label class="toggle-switch">
                            <input type="checkbox" id="setting_maintenance_mode" name="setting_maintenance_mode" 
                                   value="1" <?php echo ($settings['maintenance_mode'] ?? '0') === '1' ? 'checked' : ''; ?>>
                            <span class="toggle-slider"></span>
                        </label>
                    </div>
                    
                    <div class="form-row">
                        <label for="setting_maintenance_message">Maintenance Message</label>
                        <textarea id="setting_maintenance_message" name="setting_maintenance_message" 
                                  rows="3"><?php echo htmlspecialchars($settings['maintenance_message'] ?? 'We are currently undergoing maintenance. Please check back later.'); ?></textarea>
                    </div>
                </div>
            </div>
            
            <!-- Bookings Settings Tab -->
            <div class="settings-tab-content" id="bookings-tab">
                <div class="settings-group">
                    <h3><i class="fas fa-calendar-check"></i> Booking Rules</h3>
                    
                    <div class="form-row">
                        <label for="setting_min_booking_hours">Minimum Booking Duration (hours)</label>
                        <input type="number" id="setting_min_booking_hours" name="setting_min_booking_hours" 
                               value="<?php echo htmlspecialchars($settings['min_booking_hours'] ?? '2'); ?>" min="1">
                    </div>
                    
                    <div class="form-row">
                        <label for="setting_max_booking_days">Maximum Booking Duration (days)</label>
                        <input type="number" id="setting_max_booking_days" name="setting_max_booking_days" 
                               value="<?php echo htmlspecialchars($settings['max_booking_days'] ?? '30'); ?>" min="1">
                    </div>
                    
                    <div class="form-row">
                        <label for="setting_advance_booking_days">Maximum Advance Booking (days)</label>
                        <input type="number" id="setting_advance_booking_days" name="setting_advance_booking_days" 
                               value="<?php echo htmlspecialchars($settings['advance_booking_days'] ?? '90'); ?>" min="1">
                    </div>
                    
                    <div class="form-row">
                        <label for="setting_booking_buffer_minutes">Buffer Time Between Bookings (minutes)</label>
                        <input type="number" id="setting_booking_buffer_minutes" name="setting_booking_buffer_minutes" 
                               value="<?php echo htmlspecialchars($settings['booking_buffer_minutes'] ?? '30'); ?>" min="0">
                    </div>
                </div>
                
                <div class="settings-group">
                    <h3><i class="fas fa-ban"></i> Cancellation Policy</h3>
                    
                    <div class="form-row">
                        <label for="setting_cancellation_fee_percent">Cancellation Fee (%)</label>
                        <input type="number" id="setting_cancellation_fee_percent" name="setting_cancellation_fee_percent" 
                               value="<?php echo htmlspecialchars($settings['cancellation_fee_percent'] ?? '10'); ?>" min="0" max="100">
                    </div>
                    
                    <div class="form-row">
                        <label for="setting_free_cancellation_hours">Free Cancellation Before (hours)</label>
                        <input type="number" id="setting_free_cancellation_hours" name="setting_free_cancellation_hours" 
                               value="<?php echo htmlspecialchars($settings['free_cancellation_hours'] ?? '24'); ?>" min="0">
                    </div>
                </div>
            </div>
            
            <!-- Vehicles Settings Tab -->
            <div class="settings-tab-content" id="vehicles-tab">
                <div class="settings-group">
                    <h3><i class="fas fa-car"></i> Vehicle Defaults</h3>
                    
                    <div class="form-row">
                        <label for="setting_default_availability">Default Availability Status</label>
                        <select id="setting_default_availability" name="setting_default_availability">
                            <option value="available" <?php echo ($settings['default_availability'] ?? 'available') === 'available' ? 'selected' : ''; ?>>Available</option>
                            <option value="unavailable" <?php echo ($settings['default_availability'] ?? 'available') === 'unavailable' ? 'selected' : ''; ?>>Unavailable</option>
                            <option value="maintenance" <?php echo ($settings['default_availability'] ?? 'available') === 'maintenance' ? 'selected' : ''; ?>>Maintenance</option>
                        </select>
                    </div>
                    
                    <div class="form-row">
                        <label for="setting_min_fuel_level">Minimum Fuel Level (%)</label>
                        <input type="number" id="setting_min_fuel_level" name="setting_min_fuel_level" 
                               value="<?php echo htmlspecialchars($settings['min_fuel_level'] ?? '20'); ?>" min="0" max="100">
                    </div>
                </div>
                
                <div class="settings-group">
                    <h3><i class="fas fa-wrench"></i> Maintenance Alerts</h3>
                    
                    <div class="form-row">
                        <label for="setting_mileage_alert">Mileage Alert (km)</label>
                        <input type="number" id="setting_mileage_alert" name="setting_mileage_alert" 
                               value="<?php echo htmlspecialchars($settings['mileage_alert'] ?? '5000'); ?>" min="0">
                    </div>
                    
                    <div class="form-row">
                        <label for="setting_maintenance_interval">Maintenance Interval (days)</label>
                        <input type="number" id="setting_maintenance_interval" name="setting_maintenance_interval" 
                               value="<?php echo htmlspecialchars($settings['maintenance_interval'] ?? '90'); ?>" min="1">
                    </div>
                </div>
            </div>
            
            <!-- Payments Settings Tab -->
            <div class="settings-tab-content" id="payments-tab">
                <div class="settings-group">
                    <h3><i class="fas fa-credit-card"></i> Payment Gateway</h3>
                    
                    <div class="form-row">
                        <label for="setting_payment_mode">Payment Mode</label>
                        <select id="setting_payment_mode" name="setting_payment_mode">
                            <option value="test" <?php echo ($settings['payment_mode'] ?? 'test') === 'test' ? 'selected' : ''; ?>>Test Mode</option>
                            <option value="live" <?php echo ($settings['payment_mode'] ?? 'test') === 'live' ? 'selected' : ''; ?>>Live Mode</option>
                        </select>
                    </div>
                    
                    <div class="form-row">
                        <label for="setting_stripe_publishable_key">Stripe Publishable Key</label>
                        <input type="text" id="setting_stripe_publishable_key" name="setting_stripe_publishable_key" 
                               value="<?php echo htmlspecialchars($settings['stripe_publishable_key'] ?? ''); ?>">
                    </div>
                    
                    <div class="form-row">
                        <label for="setting_stripe_secret_key">Stripe Secret Key</label>
                        <input type="password" id="setting_stripe_secret_key" name="setting_stripe_secret_key" 
                               value="<?php echo htmlspecialchars($settings['stripe_secret_key'] ?? ''); ?>">
                    </div>
                    
                    <div class="form-row">
                        <label for="setting_tax_rate">Tax Rate (%)</label>
                        <input type="number" id="setting_tax_rate" name="setting_tax_rate" 
                               value="<?php echo htmlspecialchars($settings['tax_rate'] ?? '0'); ?>" min="0" max="100" step="0.01">
                    </div>
                </div>
            </div>
            
            <!-- Notifications Settings Tab -->
            <div class="settings-tab-content" id="notifications-tab">
                <div class="settings-group">
                    <h3><i class="fas fa-envelope"></i> Email Notifications</h3>
                    
                    <div class="form-row">
                        <label for="setting_email_from">From Email Address</label>
                        <input type="email" id="setting_email_from" name="setting_email_from" 
                               value="<?php echo htmlspecialchars($settings['email_from'] ?? 'noreply@vehicleez.com'); ?>">
                    </div>
                    
                    <div class="form-row">
                        <label for="setting_email_from_name">From Name</label>
                        <input type="text" id="setting_email_from_name" name="setting_email_from_name" 
                               value="<?php echo htmlspecialchars($settings['email_from_name'] ?? 'VehicleEZ'); ?>">
                    </div>
                    
                    <div class="form-row">
                        <label for="setting_booking_confirmation_email">Enable Booking Confirmation Emails</label>
                        <label class="toggle-switch">
                            <input type="checkbox" id="setting_booking_confirmation_email" name="setting_booking_confirmation_email" 
                                   value="1" <?php echo ($settings['booking_confirmation_email'] ?? '1') === '1' ? 'checked' : ''; ?>>
                            <span class="toggle-slider"></span>
                        </label>
                    </div>
                    
                    <div class="form-row">
                        <label for="setting_booking_reminder_email">Enable Booking Reminder Emails</label>
                        <label class="toggle-switch">
                            <input type="checkbox" id="setting_booking_reminder_email" name="setting_booking_reminder_email" 
                                   value="1" <?php echo ($settings['booking_reminder_email'] ?? '1') === '1' ? 'checked' : ''; ?>>
                            <span class="toggle-slider"></span>
                        </label>
                    </div>
                </div>
                
                <div class="settings-group">
                    <h3><i class="fas fa-bell"></i> Admin Alerts</h3>
                    
                    <div class="form-row">
                        <label for="setting_admin_email">Admin Notification Email</label>
                        <input type="email" id="setting_admin_email" name="setting_admin_email" 
                               value="<?php echo htmlspecialchars($settings['admin_email'] ?? ''); ?>">
                    </div>
                    
                    <div class="form-row">
                        <label for="setting_alert_new_booking">Alert on New Booking</label>
                        <label class="toggle-switch">
                            <input type="checkbox" id="setting_alert_new_booking" name="setting_alert_new_booking" 
                                   value="1" <?php echo ($settings['alert_new_booking'] ?? '1') === '1' ? 'checked' : ''; ?>>
                            <span class="toggle-slider"></span>
                        </label>
                    </div>
                    
                    <div class="form-row">
                        <label for="setting_alert_cancellation">Alert on Cancellation</label>
                        <label class="toggle-switch">
                            <input type="checkbox" id="setting_alert_cancellation" name="setting_alert_cancellation" 
                                   value="1" <?php echo ($settings['alert_cancellation'] ?? '1') === '1' ? 'checked' : ''; ?>>
                            <span class="toggle-slider"></span>
                        </label>
                    </div>
                </div>
            </div>
            
            <!-- Security Settings Tab -->
            <div class="settings-tab-content" id="security-tab">
                <div class="settings-group">
                    <h3><i class="fas fa-shield-alt"></i> User Security</h3>
                    
                    <div class="form-row">
                        <label for="setting_enable_2fa">Enable Two-Factor Authentication</label>
                        <label class="toggle-switch">
                            <input type="checkbox" id="setting_enable_2fa" name="setting_enable_2fa" 
                                   value="1" <?php echo ($settings['enable_2fa'] ?? '0') === '1' ? 'checked' : ''; ?>>
                            <span class="toggle-slider"></span>
                        </label>
                    </div>
                    
                    <div class="form-row">
                        <label for="setting_password_min_length">Minimum Password Length</label>
                        <input type="number" id="setting_password_min_length" name="setting_password_min_length" 
                               value="<?php echo htmlspecialchars($settings['password_min_length'] ?? '8'); ?>" min="6" max="32">
                    </div>
                    
                    <div class="form-row">
                        <label for="setting_password_require_special">Require Special Character</label>
                        <label class="toggle-switch">
                            <input type="checkbox" id="setting_password_require_special" name="setting_password_require_special" 
                                   value="1" <?php echo ($settings['password_require_special'] ?? '1') === '1' ? 'checked' : ''; ?>>
                            <span class="toggle-slider"></span>
                        </label>
                    </div>
                    
                    <div class="form-row">
                        <label for="setting_session_timeout">Session Timeout (minutes)</label>
                        <input type="number" id="setting_session_timeout" name="setting_session_timeout" 
                               value="<?php echo htmlspecialchars($settings['session_timeout'] ?? '30'); ?>" min="1" max="1440">
                    </div>
                </div>
                
                <div class="settings-group">
                    <h3><i class="fas fa-lock"></i> System Security</h3>
                    
                    <div class="form-row">
                        <label for="setting_login_attempts">Max Login Attempts</label>
                        <input type="number" id="setting_login_attempts" name="setting_login_attempts" 
                               value="<?php echo htmlspecialchars($settings['login_attempts'] ?? '5'); ?>" min="1" max="10">
                    </div>
                    
                    <div class="form-row">
                        <label for="setting_login_lockout">Lockout Duration (minutes)</label>
                        <input type="number" id="setting_login_lockout" name="setting_login_lockout" 
                               value="<?php echo htmlspecialchars($settings['login_lockout'] ?? '15'); ?>" min="1" max="1440">
                    </div>
                </div>
            </div>
            
            <div class="form-actions">
                <button type="submit" class="btn btn-primary">
                    <i class="fas fa-save"></i> Save All Settings
                </button>
            </div>
        </form>
    </div>

    <script>
        // Tab switching functionality
        document.addEventListener('DOMContentLoaded', function() {
            const tabs = document.querySelectorAll('.settings-tab');
            const tabContents = document.querySelectorAll('.settings-tab-content');
            
            tabs.forEach(tab => {
                tab.addEventListener('click', function() {
                    // Remove active class from all tabs and contents
                    tabs.forEach(t => t.classList.remove('active'));
                    tabContents.forEach(c => c.classList.remove('active'));
                    
                    // Add active class to clicked tab and corresponding content
                    this.classList.add('active');
                    const tabId = this.getAttribute('data-tab');
                    document.getElementById(tabId + '-tab').classList.add('active');
                });
            });
            
            // Toggle switch labels
            document.querySelectorAll('.toggle-switch input[type="checkbox"]').forEach(checkbox => {
                checkbox.addEventListener('change', function() {
                    this.value = this.checked ? '1' : '0';
                });
            });
        });
    </script>
</body>
</html>