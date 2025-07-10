<?php
session_start();
require_once 'config.php';

// Security headers
header("X-Frame-Options: DENY");
header("X-Content-Type-Options: nosniff");
header("X-XSS-Protection: 1; mode=block");

$pdo = getDBConnection();
$error = '';

// Redirect if already logged in as admin
if (isset($_SESSION['user_id']) && isset($_SESSION['role']) && $_SESSION['role'] === 'admin') {
    header('Location: admin_dashboard.php');
    exit();
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $user_id = trim($_POST['user_id']);
    $password = $_POST['password'];
    
    // Input validation
    if (empty($user_id) || empty($password)) {
        $error = "Please fill all fields";
    } else {
        try {
            // Check if admin user exists and is active
            $stmt = $pdo->prepare("SELECT * FROM users WHERE user_id = :user_id AND role = 'admin' AND is_active = 1");
            $stmt->bindParam(':user_id', $user_id, PDO::PARAM_INT);
            $stmt->execute();
            
            if ($stmt->rowCount() === 1) {
                $user = $stmt->fetch(PDO::FETCH_ASSOC);
                
                if (password_verify($password, $user['password'])) {
                    // Regenerate session ID to prevent fixation
                    session_regenerate_id(true);
                    
                    // Set session variables
                    $_SESSION['user_id'] = $user['user_id'];
                    $_SESSION['username'] = $user['username'];
                    $_SESSION['role'] = $user['role'];
                    $_SESSION['last_activity'] = time();
                    $_SESSION['ip_address'] = $_SERVER['REMOTE_ADDR'];
                    $_SESSION['user_agent'] = $_SERVER['HTTP_USER_AGENT'];
                    
                    // Update last login
                    $update = $pdo->prepare("UPDATE users SET last_login = NOW(), login_attempts = 0 WHERE user_id = :user_id");
                    $update->execute([':user_id' => $user['user_id']]);
                    
                    // Redirect to admin dashboard
                    header('Location: admin_dashboard.php');
                    exit();
                } else {
                    // Increment failed login attempts
                    $update = $pdo->prepare("UPDATE users SET login_attempts = login_attempts + 1 WHERE user_id = :user_id");
                    $update->execute([':user_id' => $user['user_id']]);
                    
                    $error = "Invalid credentials";
                }
            } else {
                $error = "Invalid credentials";
            }
        } catch (PDOException $e) {
            error_log("Admin login error: " . $e->getMessage());
            $error = "System error. Please try again later.";
        }
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Login | Vehicle Booking System</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/toastify-js/src/toastify.min.css">
    <style>
        :root {
            --admin-primary: #3498db;
            --admin-dark: #2980b9;
            --error-color: #e74c3c;
            --text-color: #333;
            --light-gray: #f5f5f5;
            --transition-speed: 0.3s;
        }
        
        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            background: var(--light-gray);
            display: flex;
            justify-content: center;
            align-items: center;
            min-height: 100vh;
            margin: 0;
            background-image: linear-gradient(135deg, #f5f7fa 0%, #c3cfe2 100%);
        }
        
        .login-container {
            width: 380px;
            padding: 40px;
            background: white;
            border-radius: 10px;
            box-shadow: 0 5px 20px rgba(0,0,0,0.1);
            position: relative;
            overflow: hidden;
            border-top: 5px solid var(--admin-primary);
        }
        
        .login-container::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            height: 5px;
            background: linear-gradient(90deg, var(--admin-primary), var(--admin-dark));
        }
        
        .login-title {
            text-align: center;
            margin-bottom: 30px;
        }
        
        .login-title i {
            font-size: 50px;
            margin-bottom: 15px;
            color: var(--admin-primary);
            background: rgba(52, 152, 219, 0.1);
            width: 80px;
            height: 80px;
            line-height: 80px;
            border-radius: 50%;
            display: inline-block;
        }
        
        .login-title h2 {
            margin: 10px 0 5px;
            color: var(--text-color);
        }
        
        .login-title p {
            color: #777;
            font-size: 14px;
        }
        
        .form-group {
            margin-bottom: 20px;
            position: relative;
        }
        
        .form-group label {
            display: block;
            margin-bottom: 8px;
            font-weight: 600;
            color: var(--text-color);
        }
        
        .form-control {
            width: 100%;
            padding: 12px 12px 12px 40px;
            border: 1px solid #ddd;
            border-radius: 6px;
            font-size: 15px;
            transition: all var(--transition-speed);
        }
        
        .form-control:focus {
            border-color: var(--admin-primary);
            box-shadow: 0 0 0 3px rgba(52, 152, 219, 0.2);
        }
        
        .input-icon {
            position: absolute;
            left: 15px;
            top: 38px;
            color: #999;
        }
        
        .btn {
            width: 100%;
            padding: 14px;
            border: none;
            border-radius: 6px;
            font-size: 16px;
            font-weight: 600;
            cursor: pointer;
            transition: all var(--transition-speed);
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 10px;
            background: var(--admin-primary);
            color: white;
        }
        
        .btn:hover {
            background: var(--admin-dark);
            transform: translateY(-2px);
        }
        
        .btn:active {
            transform: translateY(0);
        }
        
        .error-message {
            padding: 12px;
            background: #f8d7da;
            color: var(--error-color);
            border-radius: 6px;
            margin-bottom: 20px;
            display: flex;
            align-items: center;
            gap: 10px;
        }
        
        .password-container {
            position: relative;
        }
        
        .password-toggle {
            position: absolute;
            right: 15px;
            top: 12px;
            cursor: pointer;
            color: #999;
        }
        
        /* Animations */
        @keyframes shake {
            0%, 100% { transform: translateX(0); }
            20%, 60% { transform: translateX(-10px); }
            40%, 80% { transform: translateX(10px); }
        }
        
        .shake {
            animation: shake 0.5s;
        }
        
        @keyframes fadeIn {
            from { opacity: 0; transform: translateY(-10px); }
            to { opacity: 1; transform: translateY(0); }
        }
        
        .fade-in {
            animation: fadeIn 0.3s;
        }
        
        /* Responsive adjustments */
        @media (max-width: 480px) {
            .login-container {
                width: 90%;
                padding: 30px 20px;
            }
        }
    </style>
</head>
<body>
    <div class="login-container <?php echo ($error) ? 'shake' : ''; ?>">
        <div class="login-title">
            <i class="fas fa-user-shield"></i>
            <h2>Admin Portal</h2>
            <p>Sign in to access the admin dashboard</p>
        </div>
        
        <?php if ($error): ?>
            <div class="error-message fade-in">
                <i class="fas fa-exclamation-circle"></i>
                <span><?php echo htmlspecialchars($error); ?></span>
            </div>
        <?php endif; ?>
        
        <form method="POST" id="loginForm">
            <div class="form-group">
                <label for="user_id">Admin ID</label>
                <input type="text" id="user_id" name="user_id" class="form-control" placeholder="Enter your admin ID" required>
                <i class="fas fa-user input-icon"></i>
            </div>
            
            <div class="form-group">
                <label for="password">Password</label>
                <div class="password-container">
                    <input type="password" id="password" name="password" class="form-control" placeholder="Enter your password" required>
                    <i class="fas fa-lock input-icon"></i>
                    <i class="fas fa-eye password-toggle" id="togglePassword"></i>
                </div>
            </div>
            
            <button type="submit" class="btn" id="loginBtn">
                <i class="fas fa-sign-in-alt"></i> Sign In
            </button>
        </form>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/toastify-js"></script>
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            // Password toggle
            const togglePassword = document.getElementById('togglePassword');
            if (togglePassword) {
                togglePassword.addEventListener('click', function() {
                    const password = document.getElementById('password');
                    const type = password.getAttribute('type') === 'password' ? 'text' : 'password';
                    password.setAttribute('type', type);
                    this.classList.toggle('fa-eye');
                    this.classList.toggle('fa-eye-slash');
                });
            }
            
            // Form submission
            const loginForm = document.getElementById('loginForm');
            if (loginForm) {
                loginForm.addEventListener('submit', function(e) {
                    const loginBtn = document.getElementById('loginBtn');
                    if (loginBtn) {
                        loginBtn.disabled = true;
                        loginBtn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Authenticating...';
                    }
                });
            }
            
            // Show error toast if there's an error
            <?php if ($error): ?>
                Toastify({
                    text: "<?php echo addslashes($error); ?>",
                    duration: 5000,
                    close: true,
                    gravity: "top",
                    position: "center",
                    backgroundColor: "linear-gradient(to right, #e74c3c, #c0392b)",
                    stopOnFocus: true
                }).showToast();
            <?php endif; ?>
        });
    </script>
</body>
</html>