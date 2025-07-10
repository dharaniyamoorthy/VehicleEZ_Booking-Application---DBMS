<?php
session_start();

if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit();
}

require_once 'config.php';
$pdo = getDBConnection();

try {
    $user_id = $_SESSION['user_id'];
    $stmt = $pdo->prepare("SELECT user_id, username, full_name, role, email FROM users WHERE user_id = :user_id");
    $stmt->bindParam(':user_id', $user_id, PDO::PARAM_INT);
    $stmt->execute();
    
    if ($stmt->rowCount() === 0) {
        session_destroy();
        header('Location: login.php?error=account_not_found');
        exit();
    }
    
    $user = $stmt->fetch(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    error_log("Database error: " . $e->getMessage());
    die("System error. Please try again later.");
}

$featured_vehicles = [];
try {
    $stmt = $pdo->query("SELECT * FROM vehicles WHERE featured = 1 LIMIT 3");
    $featured_vehicles = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    error_log("Featured vehicles error: " . $e->getMessage());
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dashboard | VehicleEZ</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <style>
        :root {
            --primary-color: #6c5ce7;
            --primary-dark: #5649b0;
            --secondary-color: #00b894;
            --danger-color: #d63031;
            --warning-color: #fdcb6e;
            --dark-color: #2d3436;
            --light-color: #f5f6fa;
            --text-color: #2d3436;
            --border-radius: 10px;
            --box-shadow: 0 5px 15px rgba(0,0,0,0.1);
            --transition: all 0.3s ease;
        }
        
        body {
            font-family: 'Poppins', sans-serif;
            margin: 0;
            padding: 0;
            background-color: var(--light-color);
            color: var(--text-color);
            line-height: 1.6;
        }
        
        .hero-header {
            background: linear-gradient(135deg, var(--primary-color) 0%, #0984e3 100%);
            color: white;
            padding: 60px 0;
            text-align: center;
            margin-bottom: 40px;
            box-shadow: 0 4px 12px rgba(0,0,0,0.1);
            position: relative;
            overflow: hidden;
        }
        
        .hero-header::before {
            content: "";
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            height: 5px;
            background: linear-gradient(90deg, #00b894, #fdcb6e, #d63031, #6c5ce7);
        }
        
        .brand-logo {
            font-size: 3.5rem;
            font-weight: 800;
            margin-bottom: 15px;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 15px;
            animation: fadeInDown 1s;
        }
        
        .tagline {
            font-size: 1.6rem;
            opacity: 0.9;
            margin-bottom: 0;
            animation: fadeInUp 1s;
        }
        
        .container {
            max-width: 1200px;
            margin: 0 auto;
            padding: 0 20px;
        }
        
        .cta-section {
            text-align: center;
            margin: 60px 0;
            padding: 80px 40px;
            background: linear-gradient(135deg, rgba(108, 92, 231, 0.1) 0%, rgba(0, 184, 148, 0.1) 100%);
            border-radius: var(--border-radius);
            position: relative;
            overflow: hidden;
            border: 2px dashed var(--primary-color);
        }
        
        .cta-section h2 {
            font-size: 3rem;
            margin-bottom: 25px;
            color: var(--primary-color);
            font-weight: 800;
        }
        
        .cta-section p {
            font-size: 1.4rem;
            color: var(--dark-color);
            max-width: 700px;
            margin: 0 auto 40px;
            line-height: 1.8;
        }
        
        .book-now-btn {
            display: inline-block;
            background: linear-gradient(to right, var(--primary-color), var(--primary-dark));
            color: white;
            padding: 22px 50px;
            border-radius: 50px;
            text-decoration: none;
            font-weight: 700;
            font-size: 1.5rem;
            box-shadow: 0 15px 30px rgba(108, 92, 231, 0.4);
            transition: var(--transition);
            position: relative;
            overflow: hidden;
            border: none;
            cursor: pointer;
        }
        
        .book-now-btn:hover {
            transform: translateY(-5px) scale(1.05);
            box-shadow: 0 20px 40px rgba(108, 92, 231, 0.5);
            background: linear-gradient(to right, var(--primary-dark), var(--primary-color));
        }
        
        .book-now-btn::after {
            content: "";
            position: absolute;
            top: -50%;
            left: -50%;
            width: 200%;
            height: 200%;
            background: linear-gradient(45deg, transparent, rgba(255,255,255,0.3), transparent);
            transform: rotate(45deg);
            transition: var(--transition);
            animation: shine 3s infinite;
        }
        
        /* Animations */
        @keyframes fadeInDown {
            from {
                opacity: 0;
                transform: translateY(-30px);
            }
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }
        
        @keyframes fadeInUp {
            from {
                opacity: 0;
                transform: translateY(30px);
            }
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }
        
        @keyframes shine {
            0% {
                left: -100%;
            }
            20% {
                left: 100%;
            }
            100% {
                left: 100%;
            }
        }
        
        @keyframes pulse {
            0% { transform: scale(1); }
            50% { transform: scale(1.05); }
            100% { transform: scale(1); }
        }
        
        .pulse {
            animation: pulse 2s infinite;
        }
        
        /* Responsive */
        @media (max-width: 768px) {
            .brand-logo {
                font-size: 2.5rem;
            }
            
            .tagline {
                font-size: 1.3rem;
            }
            
            .cta-section {
                padding: 50px 20px;
            }
            
            .cta-section h2 {
                font-size: 2.2rem;
            }
            
            .cta-section p {
                font-size: 1.2rem;
            }
            
            .book-now-btn {
                padding: 18px 35px;
                font-size: 1.3rem;
            }
        }
    </style>
</head>
<body>
    <!-- Hero Header with Branding -->
    <div class="hero-header">
        <div class="container">
            <div class="brand-logo">
                <i class="fas fa-car"></i>
                VehicleEZ
            </div>
            <div class="tagline">Your seamless vehicle booking experience</div>
        </div>
    </div>
    
    <div class="container">
        <!-- Big CTA Section - Now the main focus -->
        <div class="cta-section">
            <h2>Ready for your next adventure?</h2>
            <p>Discover and book the perfect vehicle in just a few clicks. Whether you need a car for a road trip or a bike for city exploration, we've got you covered.</p>
            <button class="book-now-btn pulse" onclick="window.location.href='book.php'">
                <i class="fas fa-car"></i> BOOK MY VEHICLE NOW
            </button>
        </div>
    </div>

    <script>
        // Add click effect to buttons
        document.querySelectorAll('.book-now-btn').forEach(button => {
            button.addEventListener('mousedown', function() {
                this.style.transform = 'scale(0.95)';
            });
            
            button.addEventListener('mouseup', function() {
                this.style.transform = '';
            });
            
            button.addEventListener('mouseleave', function() {
                this.style.transform = '';
            });
        });
    </script>
</body>
</html>