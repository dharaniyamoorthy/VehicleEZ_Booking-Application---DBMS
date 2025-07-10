<?php
// Start session if not already started
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
?>

<nav class="navbar">
    <div class="navbar-brand">
        <a href="dashboard.php" class="logo-link">
            <i class="fas fa-car"></i>
            <span>Vehicle Booking</span>
        </a>
        <button class="navbar-toggle" id="navbarToggle">
            <i class="fas fa-bars"></i>
        </button>
    </div>
    
    <div class="navbar-links" id="navbarLinks">
        <ul>
            <li>
                <a href="dashboard.php" class="<?php echo basename($_SERVER['PHP_SELF']) === 'dashboard.php' ? 'active' : ''; ?>">
                    <i class="fas fa-home"></i>
                    <span>Dashboard</span>
                </a>
            </li>
            <li>
                <a href="book.php" class="<?php echo basename($_SERVER['PHP_SELF']) === 'book.php' ? 'active' : ''; ?>">
                    <i class="fas fa-car"></i>
                    <span>Book Vehicle</span>
                </a>
            </li>
            <li>
                <a href="my_bookings.php" class="<?php echo basename($_SERVER['PHP_SELF']) === 'my_bookings.php' ? 'active' : ''; ?>">
                    <i class="fas fa-calendar-alt"></i>
                    <span>My Bookings</span>
                </a>
            </li>
            
            <?php if (isset($_SESSION['user_role']) && $_SESSION['user_role'] === 'admin'): ?>
                <li class="menu-dropdown">
                    <a href="#" class="<?php echo in_array(basename($_SERVER['PHP_SELF']), ['vehicles.php', 'approvals.php']) ? 'active' : ''; ?>">
                        <i class="fas fa-cog"></i>
                        <span>Admin</span>
                        <i class="fas fa-chevron-down dropdown-icon"></i>
                    </a>
                    <ul class="dropdown-menu">
                        <li>
                            <a href="admin/vehicles.php" class="<?php echo basename($_SERVER['PHP_SELF']) === 'vehicles.php' ? 'active' : ''; ?>">
                                <i class="fas fa-truck-pickup"></i>
                                <span>Manage Vehicles</span>
                            </a>
                        </li>
                        <li>
                            <a href="admin/approvals.php" class="<?php echo basename($_SERVER['PHP_SELF']) === 'approvals.php' ? 'active' : ''; ?>">
                                <i class="fas fa-check-circle"></i>
                                <span>Approve Requests</span>
                            </a>
                        </li>
                    </ul>
                </li>
            <?php endif; ?>
            
            <li class="menu-dropdown user-menu">
                <a href="#">
                    <i class="fas fa-user-circle"></i>
                    <span><?php echo isset($_SESSION['username']) ? htmlspecialchars($_SESSION['username']) : 'Account'; ?></span>
                    <i class="fas fa-chevron-down dropdown-icon"></i>
                </a>
                <ul class="dropdown-menu">
                    <li>
                        <a href="profile.php" class="<?php echo basename($_SERVER['PHP_SELF']) === 'profile.php' ? 'active' : ''; ?>">
                            <i class="fas fa-user"></i>
                            <span>Profile</span>
                        </a>
                    </li>
                    <li>
                        <a href="logout.php">
                            <i class="fas fa-sign-out-alt"></i>
                            <span>Logout</span>
                        </a>
                    </li>
                </ul>
            </li>
        </ul>
    </div>
</nav>

<style>
/* Navbar Styles */
.navbar {
    background-color: #2c3e50;
    color: white;
    display: flex;
    justify-content: space-between;
    align-items: center;
    padding: 0 20px;
    height: 60px;
    position: sticky;
    top: 0;
    z-index: 1000;
    box-shadow: 0 2px 10px rgba(0,0,0,0.1);
}

.navbar-brand {
    display: flex;
    align-items: center;
}

.logo-link {
    color: white;
    text-decoration: none;
    font-size: 1.25rem;
    font-weight: bold;
    display: flex;
    align-items: center;
    gap: 10px;
}

.logo-link i {
    font-size: 1.5rem;
}

.navbar-toggle {
    background: none;
    border: none;
    color: white;
    font-size: 1.5rem;
    cursor: pointer;
    display: none;
}

.navbar-links ul {
    display: flex;
    list-style: none;
    margin: 0;
    padding: 0;
    align-items: center;
    height: 100%;
}

.navbar-links li {
    position: relative;
    height: 100%;
    display: flex;
    align-items: center;
}

.navbar-links a {
    color: white;
    text-decoration: none;
    padding: 0 15px;
    display: flex;
    align-items: center;
    gap: 8px;
    height: 100%;
    transition: background-color 0.3s;
}

.navbar-links a:hover {
    background-color: #34495e;
}

.navbar-links a.active {
    background-color: #3498db;
}

/* Dropdown Menus */
.menu-dropdown > a {
    padding-right: 10px;
}

.dropdown-menu {
    position: absolute;
    top: 100%;
    left: 0;
    background-color: #34495e;
    min-width: 200px;
    box-shadow: 0 8px 16px rgba(0,0,0,0.2);
    display: none;
    z-index: 1;
    list-style: none;
    padding: 0;
    border-radius: 0 0 4px 4px;
    overflow: hidden;
}

.menu-dropdown:hover .dropdown-menu {
    display: block;
}

.dropdown-menu a {
    padding: 12px 15px;
    height: auto;
    white-space: nowrap;
}

.dropdown-menu a:hover {
    background-color: #2c3e50;
}

.user-menu {
    margin-left: 10px;
}

.user-menu .dropdown-menu {
    left: auto;
    right: 0;
}

.dropdown-icon {
    font-size: 0.8rem;
    margin-left: 5px;
    transition: transform 0.3s;
}

.menu-dropdown:hover .dropdown-icon {
    transform: rotate(180deg);
}

/* Mobile Responsiveness */
@media (max-width: 768px) {
    .navbar {
        flex-direction: column;
        height: auto;
        padding: 10px;
    }
    
    .navbar-brand {
        width: 100%;
        justify-content: space-between;
    }
    
    .navbar-toggle {
        display: block;
    }
    
    .navbar-links {
        width: 100%;
        display: none;
    }
    
    .navbar-links.active {
        display: block;
    }
    
    .navbar-links ul {
        flex-direction: column;
        align-items: stretch;
    }
    
    .navbar-links li {
        height: auto;
    }
    
    .navbar-links a {
        padding: 12px 15px;
    }
    
    .dropdown-menu {
        position: static;
        box-shadow: none;
        display: none;
        background-color: rgba(0,0,0,0.1);
    }
    
    .menu-dropdown:hover .dropdown-menu {
        display: none;
    }
    
    .menu-dropdown.active .dropdown-menu {
        display: block;
    }
}
</style>

<script>
// Mobile menu toggle
document.getElementById('navbarToggle')?.addEventListener('click', function() {
    const navbarLinks = document.getElementById('navbarLinks');
    navbarLinks.classList.toggle('active');
});

// Mobile dropdown toggle
document.querySelectorAll('.menu-dropdown > a').forEach(dropdown => {
    dropdown.addEventListener('click', function(e) {
        if (window.innerWidth <= 768) {
            e.preventDefault();
            const parent = this.parentElement;
            parent.classList.toggle('active');
        }
    });
});
</script>