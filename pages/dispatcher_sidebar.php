<?php
// Session already started in the main file
$currentPage = basename($_SERVER['PHP_SELF']);
?>

<div class="sidebar" id="sidebar">
  <style>
    .dropdown-toggle::after { display: none; }
    .dropdown-chevron { transition: transform 0.3s ease; }
    .dropdown-toggle[aria-expanded="true"] .dropdown-chevron { transform: rotate(90deg); }
  </style>
  <!-- Dashboard -->
  <a href="dispatcher-dashboard.php" class="<?= $currentPage == 'dispatcher-dashboard.php' ? 'active' : '' ?>">
    <i class="fas fa-tachometer-alt me-2"></i><span class="link-text">Dashboard</span>
  </a>

  <!-- Dispatch Center -->
  <div class="dropdown">
    <a href="#" class="dropdown-toggle d-flex align-items-center justify-content-between text-decoration-none px-3 py-2 text-white"
       data-bs-toggle="collapse"
       data-bs-target="#dispatchMenu"
       aria-expanded="<?= in_array($currentPage, ['active-routes.php', 'driver-status.php', 'assign-vehicles.php']) ? 'true' : 'false' ?>">
      <div><i class="fas fa-truck me-2"></i><span class="link-text">Dispatch Center</span></div>
      <i class="fas fa-chevron-right dropdown-chevron"></i>
    </a>
    <div class="collapse ps-2 <?= in_array($currentPage, ['active-routes.php', 'driver-status.php', 'assign-vehicles.php']) ? 'show' : '' ?>" id="dispatchMenu">
      <a href="active-routes.php" class="<?= $currentPage == 'active-routes.php' ? 'active' : '' ?>">
        <i class="fas fa-route me-2"></i> Active Routes
      </a>
      <a href="driver-status.php" class="<?= $currentPage == 'driver-status.php' ? 'active' : '' ?>">
        <i class="fas fa-user-check me-2"></i> Driver Status
      </a>
      <a href="assign-vehicles.php" class="<?= $currentPage == 'assign-vehicles.php' ? 'active' : '' ?>">
        <i class="fas fa-tasks me-2"></i> Assign Vehicles
      </a>
    </div>
  </div>

  

  <!-- My Reservations -->
  <a href="assigned_reservations.php" class="<?= $currentPage == 'assigned_reservations.php' ? 'active' : '' ?>">
    <i class="fas fa-calendar-check me-2"></i><span class="link-text">My Reservations</span>
  </a>

  <!-- Dispatch Calendar -->
  <a href="dispatcher-calendar.php" class="<?= $currentPage == 'dispatcher-calendar.php' ? 'active' : '' ?>">
    <i class="fas fa-calendar-alt me-2"></i><span class="link-text">Dispatch Calendar</span>
  </a>

  <!-- Alerts -->
  <a href="alerts.php" class="<?= $currentPage == 'alerts.php' ? 'active' : '' ?>">
    <i class="fas fa-exclamation-triangle me-2"></i><span class="link-text">Alerts</span>
  </a>

  <!-- Fleet Overview -->
  <a href="fleet-overview.php" class="<?= $currentPage == 'fleet-overview.php' ? 'active' : '' ?>">
    <i class="fas fa-car-side me-2"></i><span class="link-text">Fleet Overview</span>
  </a>

  <!-- Help Guide -->
  <a href="help.php" class="<?= $currentPage == 'help.php' ? 'active' : '' ?>">
    <i class="fas fa-question-circle me-2"></i><span class="link-text">Help Guide</span>
  </a>

  <!-- Logout -->
  <a href="#" id="logoutBtn">
    <i class="fas fa-sign-out-alt me-2"></i><span class="link-text">Logout</span>
  </a>
</div>