<?php
session_start();
require_once 'config.php';

$pdo = getDBConnection();
$error = '';
$role_error = '';
$is_customer_login = false;

if (isset($_GET['role']) && $_GET['role'] === 'customer') {
    $is_customer_login = true;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $user_id = trim($_POST['user_id']);
    $password = $_POST['password'];
    $role = trim($_POST['role']);
    
    if ($role === 'admin' && $user_id === '1' && $password === '12345') {
        $_SESSION['user_id'] = 1;
        $_SESSION['username'] = 'Admin';
        $_SESSION['role'] = 'admin';
        header('Location: admin_dashboard.php');
        exit();
    }
    
    try {
        $stmt = $pdo->prepare("SELECT * FROM users WHERE user_id = :user_id AND role = :role AND is_active = 1");
        $stmt->bindParam(':user_id', $user_id, PDO::PARAM_INT);
        $stmt->bindParam(':role', $role, PDO::PARAM_STR);
        $stmt->execute();
        
        if ($stmt->rowCount() === 1) {
            $user = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if (password_verify($password, $user['password'])) {
                $_SESSION['user_id'] = $user['user_id'];
                $_SESSION['username'] = $user['username'];
                $_SESSION['role'] = $user['role'];
                
                $update = $pdo->prepare("UPDATE users SET last_login = NOW() WHERE user_id = :user_id");
                $update->execute([':user_id' => $user['user_id']]);
                
                $_SESSION['login_success'] = true;
                
                if ($user['role'] === 'admin') {
                    header('Location: admin_dashboard.php');
                } else {
                    header('Location: dashboard.php');
                }
                exit();
            } else {
                $role_error = "Invalid password";
                echo '<script>showErrorAnimation("'.htmlspecialchars($role).'");</script>';
            }
        } else {
            $role_error = "Invalid credentials";
            echo '<script>showErrorAnimation("'.htmlspecialchars($role).'");</script>';
        }
    } catch (PDOException $e) {
        $error = "System error. Please try again later.";
        error_log("Login error: " . $e->getMessage());
    }
}
?>

<!DOCTYPE html>
<html>
<head>
    <title>VehicleEZ - Login</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/toastify-js/src/toastify.min.css">
    <style>
        :root {
            --primary-color: #3498db;
            --primary-dark: #2980b9;
            --customer-color: #9b59b6;
            --customer-dark: #8e44ad;
            --error-color: #d9534f;
            --success-color: #5cb85c;
            --text-color: #333;
            --transition-speed: 0.3s;
        }
        
        body { 
            font-family: 'Poppins', sans-serif;
            background: #0f2027;
            background: linear-gradient(to right, #0f2027, #203a43, #2c5364);
            display: flex;
            flex-direction: column;
            justify-content: center;
            align-items: center;
            min-height: 100vh;
            margin: 0;
            overflow: hidden;
        }
        
        .brand-header {
            text-align: center;
            margin-bottom: 20px;
            animation: fadeInDown 1s;
            z-index: 10;
        }
        
        .brand-header h1 {
            color: white;
            font-size: 2.8rem;
            margin: 0;
            font-weight: 700;
            letter-spacing: 1px;
            background: linear-gradient(to right, #fff, #c3cfe2);
            -webkit-background-clip: text;
            background-clip: text;
            -webkit-text-fill-color: transparent;
        }
        
        .brand-header p {
            color: rgba(255,255,255,0.8);
            font-size: 1rem;
            margin-top: 8px;
            font-weight: 300;
        }
        
        .login-container {
            width: 350px;
            background: rgba(255, 255, 255, 0.95);
            border-radius: 15px;
            box-shadow: 0 15px 30px rgba(0,0,0,0.15);
            overflow: hidden;
            position: relative;
            z-index: 5;
            border: 1px solid rgba(255,255,255,0.2);
        }
        
        .login-header {
            background: linear-gradient(135deg, #3498db 0%, #2c3e50 100%);
            color: white;
            padding: 20px;
            text-align: center;
            position: relative;
        }
        
        .customer-version .login-header {
            background: linear-gradient(135deg, #9b59b6 0%, #34495e 100%);
        }
        
        .login-header h3 {
            margin: 0;
            font-size: 1.5rem;
            font-weight: 600;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 10px;
        }
        
        .login-header::after {
            content: '';
            position: absolute;
            bottom: -15px;
            left: 0;
            right: 0;
            height: 30px;
            background: white;
            transform: skewY(-3deg);
            z-index: 1;
        }
        
        .login-content {
            padding: 25px;
        }
        
        .form-group {
            margin-bottom: 20px;
            position: relative;
        }
        
        input {
            width: 100%;
            padding: 12px 12px 12px 45px;
            border: 2px solid #eee;
            border-radius: 8px;
            font-size: 15px;
            transition: all 0.3s;
            background: rgba(245, 245, 245, 0.8);
        }
        
        input:focus {
            border-color: var(--primary-color);
            box-shadow: 0 0 0 3px rgba(52, 152, 219, 0.2);
            background: white;
        }
        
        .customer-version input:focus {
            border-color: var(--customer-color);
            box-shadow: 0 0 0 3px rgba(155, 89, 182, 0.2);
        }
        
        .input-icon {
            position: absolute;
            left: 15px;
            top: 13px;
            color: #777;
            font-size: 18px;
        }
        
        button {
            width: 100%;
            padding: 14px;
            color: white;
            border: none;
            border-radius: 8px;
            font-size: 16px;
            font-weight: 600;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 8px;
            background: var(--primary-color);
            box-shadow: 0 4px 10px rgba(52, 152, 219, 0.3);
            transition: all 0.3s;
            cursor: pointer;
        }
        
        button:hover {
            transform: translateY(-2px);
            box-shadow: 0 6px 15px rgba(52, 152, 219, 0.4);
        }
        
        .customer-version button {
            background: var(--customer-color);
            box-shadow: 0 4px 10px rgba(155, 89, 182, 0.3);
        }
        
        .customer-version button:hover {
            box-shadow: 0 6px 15px rgba(155, 89, 182, 0.4);
        }
        
        .vehicle-features {
            display: flex;
            justify-content: center;
            flex-wrap: wrap;
            margin: 20px 0;
            gap: 10px;
        }
        
        .vehicle-icon {
            font-size: 24px;
            transition: all 0.3s;
        }
        
        .vehicle-icon:hover {
            transform: translateY(-5px);
        }
        
        .admin-icons .vehicle-icon {
            color: var(--primary-dark);
        }
        
        .customer-icons .vehicle-icon {
            color: var(--customer-dark);
        }
        
        .switch-role {
            text-align: center;
            margin-top: 20px;
        }
        
        .switch-role a {
            color: var(--primary-color);
            text-decoration: none;
            font-weight: 600;
            display: inline-flex;
            align-items: center;
            gap: 6px;
            font-size: 14px;
        }
        
        .customer-version .switch-role a {
            color: var(--customer-color);
        }
        
        .register-link {
            text-align: center;
            margin-top: 15px;
        }
        
        .register-link a {
            color: var(--customer-color);
            text-decoration: none;
            font-weight: 600;
            display: inline-flex;
            align-items: center;
            gap: 6px;
            font-size: 14px;
        }
        
        /* Bubble background */
        .bubbles {
            position: absolute;
            width: 100%;
            height: 100%;
            top: 0;
            left: 0;
            z-index: 1;
            overflow: hidden;
        }
        
        .bubble {
            position: absolute;
            bottom: -100px;
            background: rgba(255, 255, 255, 0.1);
            border-radius: 50%;
            animation: rise 15s infinite ease-in;
        }
        
        .bubble:nth-child(1) {
            width: 40px;
            height: 40px;
            left: 10%;
            animation-duration: 12s;
        }
        
        .bubble:nth-child(2) {
            width: 20px;
            height: 20px;
            left: 20%;
            animation-duration: 15s;
            animation-delay: 1s;
        }
        
        .bubble:nth-child(3) {
            width: 50px;
            height: 50px;
            left: 35%;
            animation-duration: 18s;
            animation-delay: 2s;
        }
        
        .bubble:nth-child(4) {
            width: 30px;
            height: 30px;
            left: 50%;
            animation-duration: 14s;
            animation-delay: 0.5s;
        }
        
        .bubble:nth-child(5) {
            width: 25px;
            height: 25px;
            left: 70%;
            animation-duration: 16s;
            animation-delay: 3s;
        }
        
        .bubble:nth-child(6) {
            width: 45px;
            height: 45px;
            left: 85%;
            animation-duration: 13s;
            animation-delay: 1.5s;
        }
        
        @keyframes rise {
            0% {
                bottom: -100px;
                transform: translateX(0);
            }
            50% {
                transform: translateX(50px);
            }
            100% {
                bottom: 100vh;
                transform: translateX(0);
            }
        }
        
        @keyframes fadeInDown {
            from { opacity: 0; transform: translateY(-20px); }
            to { opacity: 1; transform: translateY(0); }
        }
    </style>
</head>
<body>
    <div class="bubbles">
        <div class="bubble"></div>
        <div class="bubble"></div>
        <div class="bubble"></div>
        <div class="bubble"></div>
        <div class="bubble"></div>
        <div class="bubble"></div>
    </div>
    
    <div class="brand-header">
        <h1>VehicleEZ</h1>
        <p>Your seamless vehicle booking experience</p>
    </div>
    
    <div class="login-container <?php echo $is_customer_login ? 'customer-version' : ''; ?>">
        <div class="login-header">
            <h3>
                <?php if ($is_customer_login): ?>
                    <i class="fas fa-user"></i> Customer Portal
                <?php else: ?>
                    <i class="fas fa-user-shield"></i> Admin Portal
                <?php endif; ?>
            </h3>
        </div>
        
        <div class="login-content">
            <?php if ($error): ?>
                <div class="error">
                    <i class="fas fa-exclamation-circle"></i>
                    <?php echo htmlspecialchars($error); ?>
                </div>
            <?php endif; ?>
            
            <form method="POST">
                <input type="hidden" name="role" value="<?php echo $is_customer_login ? 'customer' : 'admin'; ?>">
                
                <div class="form-group">
                    <input type="text" id="user_id" name="user_id" placeholder="<?php echo $is_customer_login ? 'Customer ID' : 'Admin ID'; ?>" required>
                    <i class="fas fa-id-card input-icon"></i>
                </div>
                
                <div class="form-group">
                    <input type="password" id="password" name="password" placeholder="Password" required>
                    <i class="fas fa-lock input-icon"></i>
                    <i class="fas fa-eye password-toggle" id="togglePassword"></i>
                </div>
                
                <button type="submit" id="loginButton">
                    <i class="fas fa-sign-in-alt"></i> Login
                </button>
            </form>
            
            <div class="vehicle-features <?php echo $is_customer_login ? 'customer-icons' : 'admin-icons'; ?>">
                <?php if ($is_customer_login): ?>
                    <div class="vehicle-icon" title="Cars">🚗</div>
                    <div class="vehicle-icon" title="Bikes">🏍️</div>
                    <div class="vehicle-icon" title="Scooters">🛵</div>
                    <div class="vehicle-icon" title="Trucks">🚚</div>
                <?php else: ?>
                    <div class="vehicle-icon" title="Fleet">🚘</div>
                    <div class="vehicle-icon" title="Bookings">📅</div>
                    <div class="vehicle-icon" title="Analytics">📊</div>
                    <div class="vehicle-icon" title="Users">👥</div>
                <?php endif; ?>
            </div>
            
            <div class="switch-role">
                <?php if ($is_customer_login): ?>
                    <a href="login.php"><i class="fas fa-shield-alt"></i> Admin Login</a>
                <?php else: ?>
                    <a href="login.php?role=customer"><i class="fas fa-user"></i> Customer Login</a>
                <?php endif; ?>
            </div>
            
            <?php if ($is_customer_login): ?>
                <div class="register-link">
                    <a href="register.php"><i class="fas fa-user-plus"></i> Create Account</a>
                </div>
            <?php endif; ?>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/toastify-js"></script>
    <script>
        // Password toggle
        document.getElementById('togglePassword').addEventListener('click', function() {
            const password = document.getElementById('password');
            const type = password.getAttribute('type') === 'password' ? 'text' : 'password';
            password.setAttribute('type', type);
            this.classList.toggle('fa-eye');
            this.classList.toggle('fa-eye-slash');
        });
        
        // Form submission
        document.querySelector('form').addEventListener('submit', function(e) {
            const loginBtn = document.getElementById('loginButton');
            loginBtn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Authenticating...';
            loginBtn.disabled = true;
        });
        
        // Add hover effect to vehicle icons
        const vehicleIcons = document.querySelectorAll('.vehicle-icon');
        vehicleIcons.forEach(icon => {
            icon.addEventListener('mouseover', () => {
                icon.style.transform = 'translateY(-5px)';
            });
            icon.addEventListener('mouseout', () => {
                icon.style.transform = 'translateY(0)';
            });
        });
    </script>
</body>
</html>