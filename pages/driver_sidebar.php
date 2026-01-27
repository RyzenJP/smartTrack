<?php
// Check if user is logged in and is a driver
if (!isset($_SESSION['role']) || strtolower($_SESSION['role']) !== 'driver') {
    header("Location: ../index.php");
    exit();
}
?>

<!-- Sidebar -->
<div class="sidebar" id="sidebar">
    <ul class="sidebar-menu">
        <li>
            <a href="driver-dashboard.php" class="sidebar-link <?= basename($_SERVER['PHP_SELF']) == 'driver-dashboard.php' ? 'active' : '' ?>">
                <i class="fas fa-tachometer-alt me-2"></i>
                <span class="link-text">Dashboard</span>
            </a>
        </li>
        

        
        <li>
            <a href="maintenance-request.php" class="sidebar-link <?= basename($_SERVER['PHP_SELF']) == 'maintenance-request.php' ? 'active' : '' ?>">
                <i class="fas fa-wrench me-2"></i>
                <span class="link-text">Maintenance Request</span>
            </a>
        </li>
        
        <li>
            <a href="emergency-maintenance.php" class="sidebar-link <?= basename($_SERVER['PHP_SELF']) == 'emergency-maintenance.php' ? 'active' : '' ?>">
                <i class="fas fa-exclamation-triangle me-2"></i>
                <span class="link-text">Emergency Maintenance</span>
            </a>
        </li>
        
        <li>
            <a href="maintenance-history.php" class="sidebar-link <?= basename($_SERVER['PHP_SELF']) == 'maintenance-history.php' ? 'active' : '' ?>">
                <i class="fas fa-history me-2"></i>
                <span class="link-text">Maintenance History</span>
            </a>
        </li>
        
        <li>
            <a href="navigation.php" class="sidebar-link <?= basename($_SERVER['PHP_SELF']) == 'navigation.php' ? 'active' : '' ?>">
                <i class="fas fa-map-marked-alt me-2"></i>
                <span class="link-text">Navigation</span>
            </a>
        </li>
        
        <li>
            <a href="driver-calendar.php" class="sidebar-link <?= basename($_SERVER['PHP_SELF']) == 'driver-calendar.php' ? 'active' : '' ?>">
                <i class="fas fa-calendar-alt me-2"></i>
                <span class="link-text">My Calendar</span>
            </a>
        </li>
        
        <li>
            <a href="trip-logs.php" class="sidebar-link <?= basename($_SERVER['PHP_SELF']) == 'trip-logs.php' ? 'active' : '' ?>">
                <i class="fas fa-route me-2"></i>
                <span class="link-text">Trip Logs</span>
            </a>
        </li>
        
        <li class="mt-auto">
            <a href="#" id="logoutBtn" class="sidebar-link">
                <i class="fas fa-sign-out-alt me-2"></i>
                <span class="link-text">Logout</span>
            </a>
        </li>
    </ul>
</div>
<style>
/* Sidebar Styling */
.sidebar {
    position: fixed;
    top: 0;
    left: 0;
    width: 250px;
    height: 100vh;
    background-color: var(--primary);
    color: #fff;
    transition: all 0.3s ease;
    z-index: 1000;
    padding-top: 60px;
    overflow-y: auto;
    scrollbar-width: thin;
    scrollbar-color: #ffffff20 #001d3d;
}

.sidebar::-webkit-scrollbar {
    width: 6px;
}

.sidebar::-webkit-scrollbar-thumb {
    background-color: #ffffffcc;
    border-radius: 10px;
}

.sidebar::-webkit-scrollbar-track {
    background: transparent;
}

.sidebar.collapsed {
    width: 70px;
}

.sidebar-menu {
    list-style: none;
    padding: 0;
    margin: 20px 0 0 0;
    display: flex;
    flex-direction: column;
    height: calc(100vh - 80px);
}

.sidebar-menu li {
    margin: 2px 8px;
}

.sidebar-menu li.mt-auto {
    margin-top: auto;
    margin-bottom: 20px;
}

.sidebar-link {
    display: flex;
    align-items: center;
    padding: 14px 20px;
    color: #d9d9d9;
    text-decoration: none;
    transition: all 0.3s ease;
    border-radius: 8px;
    white-space: nowrap;
}

.sidebar-link:hover,
.sidebar-link.active {
    background-color: #001d3d;
    color: var(--accent);
    transform: translateX(5px);
}

.sidebar-link i {
    width: 20px;
    text-align: center;
}

.sidebar.collapsed .link-text {
    display: none;
}

/* Mobile Responsiveness */
@media (max-width: 768px) {
    .sidebar {
        transform: translateX(-100%);
        transition: transform 0.3s ease;
    }
    
    .sidebar.show {
        transform: translateX(0);
    }
}
</style>

