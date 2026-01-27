<?php
// sidebar.php
$currentPage = basename($_SERVER['PHP_SELF']);
?>

<div class="sidebar" id="sidebar">
  <!-- Dashboard -->
  <a href="admin_homepage.php" class="<?= $currentPage == 'admin_homepage.php' ? 'active' : '' ?>">
    <i class="fas fa-tachometer-alt me-2"></i><span class="link-text">Dashboard</span>
  </a>


  <!-- Vehicles Dropdown -->
  <div class="dropdown">
    <a href="#" class="dropdown-toggle d-flex align-items-center justify-content-between text-decoration-none px-3 py-2 text-white"
       data-bs-toggle="collapse"
       data-bs-target="#vehicleMenu"
       aria-expanded="<?= in_array($currentPage, ['fleet.php', 'assignment.php']) ? 'true' : 'false' ?>">
      <div><i class="fas fa-car me-2"></i><span class="link-text">Vehicles</span></div>
      <i class="fas fa-chevron-right dropdown-chevron"></i>
    </a>
    <div class="collapse ps-2 <?= in_array($currentPage, ['fleet.php']) ? 'show' : '' ?>" id="vehicleMenu">
      <a href="fleet.php" class="<?= $currentPage == 'fleet.php' ? 'active' : '' ?>">
        <i class="fas fa-car-side me-2"></i> Fleet Vehicles
      </a>
    </div>
  </div>

  <!-- Maintenance Focused Links -->
  <a href="geofencing.php" class="<?= $currentPage == 'geofencing.php' ? 'active' : '' ?>">
    <i class="fas fa-map-pin me-2"></i><span class="link-text">Geofencing</span>
  </a>
  <a href="maintenance.php" class="<?= $currentPage == 'maintenance.php' ? 'active' : '' ?>">
    <i class="fas fa-tools me-2"></i><span class="link-text">Maintenance History</span>
  </a>
  <a href="reports.php" class="<?= $currentPage == 'reports.php' ? 'active' : '' ?>">
    <i class="fas fa-chart-bar me-2"></i><span class="link-text">Reports</span>
  </a>
  <a href="fuel_management.php" class="<?= $currentPage == 'fuel_management.php' ? 'active' : '' ?>">
    <i class="fas fa-gas-pump me-2"></i><span class="link-text">Fuel Management</span>
  </a>
  <a href="emergency_history.php" class="<?= $currentPage == 'emergency_history.php' ? 'active' : '' ?>">
    <i class="fas fa-clock-rotate-left me-2"></i><span class="link-text">Emergency History</span>
  </a>
  <a href="predictive_maintenance.php" class="<?= $currentPage == 'predictive_maintenance.php' ? 'active' : '' ?>">
    <i class="fas fa-brain me-2"></i><span class="link-text">Predictive Maintenance</span>
  </a>

    <!-- GPS Devices -->
    <a href="gps.php" class="<?= $currentPage == 'gps.php' ? 'active' : '' ?>">
    <i class="fas fa-satellite-dish"></i>
    <span class="link-text">GPS Devices</span>
  </a>
  
  <a href="#" id="logoutBtn">
    <i class="fas fa-sign-out-alt me-2"></i><span class="link-text">Logout</span>
  </a>
</div>