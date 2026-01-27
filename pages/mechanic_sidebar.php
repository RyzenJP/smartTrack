<?php
// mechanic_sidebar.php
$currentPage = basename($_SERVER['PHP_SELF']);
?>

<div class="sidebar" id="sidebar">
  <!-- Dashboard -->
  <a href="mechanic_homepage.php" class="<?= $currentPage == 'mechanic-dashboard.php' ? 'active' : '' ?>">
    <i class="fas fa-tachometer-alt me-2"></i>
    <span class="link-text">Dashboard</span>
  </a>

  <!-- Work Orders -->
  <a href="#"
     class="dropdown-toggle d-flex align-items-center text-decoration-none px-3 py-2 <?= in_array($currentPage, ['new-work-orders.php', 'assigned-work-orders.php', 'completed-work-orders.php']) ? 'active' : '' ?>"
     data-bs-toggle="collapse"
     data-bs-target="#workOrderMenu"
     aria-expanded="<?= in_array($currentPage, ['new-work-orders.php', 'assigned-work-orders.php', 'completed-work-orders.php']) ? 'true' : 'false' ?>"
     role="button"
     onclick="return false;">
    <div>
      <i class="fas fa-clipboard-list me-2"></i>
      <span class="link-text">Work Orders</span>
    </div>
    <i class="fas fa-chevron-right dropdown-chevron" style="transition: transform 0.3s ease;"></i>
  </a>
  <div class="collapse ps-2 <?= in_array($currentPage, ['new-work-orders.php', 'assigned-work-orders.php', 'completed-work-orders.php']) ? 'show' : '' ?>" id="workOrderMenu">
    <a href="new-work-orders.php" class="<?= $currentPage == 'new-work-orders.php' ? 'active' : '' ?>">
      <i class="fas fa-plus-circle me-2"></i> <span class="link-text">New Orders</span>
    </a>
    <a href="assigned-work-orders.php" class="<?= $currentPage == 'assigned-work-orders.php' ? 'active' : '' ?>">
      <i class="fas fa-wrench me-2"></i> <span class="link-text">Assigned to Me</span>
    </a>
    <a href="completed-work-orders.php" class="<?= $currentPage == 'completed-work-orders.php' ? 'active' : '' ?>">
      <i class="fas fa-check-circle me-2"></i> <span class="link-text">Completed</span>
    </a>
  </div>

  <!-- Emergency Maintenance -->
  <a href="emergency-maintenance.php" class="<?= $currentPage == 'emergency-maintenance.php' ? 'active' : '' ?>">
    <i class="fas fa-exclamation-triangle me-2"></i>
    <span class="link-text">Emergency Maintenance</span>
  </a>

  <!-- Maintenance Schedule -->
  <a href="maintenance-schedule.php" class="<?= $currentPage == 'maintenance-schedule.php' ? 'active' : '' ?>">
    <i class="fas fa-calendar-alt me-2"></i>
    <span class="link-text">Maintenance Schedule</span>
  </a>

  <!-- Vehicle History -->
  <a href="vehicle-history.php" class="<?= $currentPage == 'vehicle-history.php' ? 'active' : '' ?>">
    <i class="fas fa-history me-2"></i>
    <span class="link-text">Vehicle History</span>
  </a>

  <!-- Logout -->
  <a href="#" id="logoutBtn">
    <i class="fas fa-sign-out-alt me-2"></i>
    <span class="link-text">Logout</span>
  </a>
</div>
