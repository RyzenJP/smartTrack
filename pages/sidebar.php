<?php
// sidebar.php
$currentPage = basename($_SERVER['PHP_SELF']);
// Determine prefix so links work regardless of where this file is included from
$scriptPath = str_replace('\\', '/', $_SERVER['PHP_SELF']);
$isSuperAdmin = strpos($scriptPath, '/super_admin/') !== false;
$saPrefix = $isSuperAdmin ? '' : 'super_admin/';
?>

<div class="sidebar" id="sidebar">
  <!-- Dashboard -->
   <a href="<?= $saPrefix ?>homepage.php" class="<?= $currentPage == 'homepage.php' ? 'active' : '' ?>">
    <i class="fas fa-tachometer-alt"></i>
    <span class="link-text">Dashboard</span>
  </a>

  <!-- Users Dropdown -->
  <div class="dropdown">
    <a href="#" class="dropdown-toggle d-flex align-items-center text-decoration-none"
       data-bs-toggle="collapse"
       data-bs-target="#userMenu"
       aria-expanded="<?= in_array($currentPage, ['admin.php', 'dispatcher.php', 'driver.php', 'mechanic.php', 'reservation_users.php']) ? 'true' : 'false' ?>">
      <div><i class="fas fa-users"></i><span class="link-text">Users</span></div>
      <i class="fas fa-chevron-right dropdown-chevron"></i>
    </a>
    <div class="collapse ps-2 <?= in_array($currentPage, ['admin.php', 'dispatcher.php', 'driver.php', 'mechanic.php', 'reservation_users.php']) ? 'show' : '' ?>" id="userMenu">
      <a href="<?= $saPrefix ?>admin.php" class="<?= $currentPage == 'admin.php' ? 'active' : '' ?>">
        <i class="fas fa-user-shield"></i> Admin
      </a>
      <a href="<?= $saPrefix ?>dispatcher.php" class="<?= $currentPage == 'dispatcher.php' ? 'active' : '' ?>">
        <i class="fas fa-headset"></i> Dispatcher
      </a>
      <a href="<?= $saPrefix ?>driver.php" class="<?= $currentPage == 'driver.php' ? 'active' : '' ?>">
        <i class="fas fa-id-badge"></i> Driver
      </a>
      <a href="<?= $saPrefix ?>mechanic.php" class="<?= $currentPage == 'mechanic.php' ? 'active' : '' ?>">
        <i class="fas fa-tools"></i> Mechanic
      </a>
      <a href="<?= $saPrefix ?>reservation_users.php" class="<?= $currentPage == 'reservation_users.php' ? 'active' : '' ?>">
        <i class="fas fa-user-check"></i> Reservation Users
      </a>
    </div>
  </div>

  <!-- Reservations Dropdown -->
  <div class="dropdown">
    <a href="#" class="dropdown-toggle d-flex align-items-center text-decoration-none"
       data-bs-toggle="collapse"
       data-bs-target="#reservationMenu"
       aria-expanded="<?= in_array($currentPage, ['reservation_management.php', 'reservation_approval.php']) ? 'true' : 'false' ?>">
      <div><i class="fas fa-calendar-check"></i><span class="link-text">Reservations</span></div>
      <i class="fas fa-chevron-right dropdown-chevron"></i>
    </a>
    <div class="collapse ps-2 <?= in_array($currentPage, ['reservation_management.php', 'reservation_approval.php']) ? 'show' : '' ?>" id="reservationMenu">
      <a href="<?= $saPrefix ?>reservation_management.php" class="<?= $currentPage == 'reservation_management.php' ? 'active' : '' ?>">
        <i class="fas fa-list"></i> All Reservations
      </a>
      <a href="<?= $saPrefix ?>reservation_approval.php" class="<?= $currentPage == 'reservation_approval.php' ? 'active' : '' ?>">
        <i class="fas fa-clipboard-check"></i> Approval Queue
      </a>
    </div>
  </div>

  

  
  <a href="<?= $saPrefix ?>reports.php" class="<?= $currentPage == 'reports.php' ? 'active' : '' ?>">
    <i class="fas fa-chart-bar"></i>
    <span class="link-text">Reports</span>
  </a>
  
  
  <!-- Geofencing Dropdown -->
  <div class="dropdown">
    <a href="#" class="dropdown-toggle d-flex align-items-center text-decoration-none"
       data-bs-toggle="collapse"
       data-bs-target="#geofencingMenu"
       aria-expanded="<?= in_array($currentPage, ['geofencing.php', 'geofence_analytics.php']) ? 'true' : 'false' ?>">
      <div><i class="fas fa-draw-polygon"></i><span class="link-text">Geofencing</span></div>
      <i class="fas fa-chevron-right dropdown-chevron"></i>
    </a>
    <div class="collapse ps-2 <?= in_array($currentPage, ['geofencing.php', 'geofence_analytics.php']) ? 'show' : '' ?>" id="geofencingMenu">
      <a href="<?= $saPrefix ?>geofencing.php" class="<?= $currentPage == 'geofencing.php' ? 'active' : '' ?>">
        <i class="fas fa-draw-polygon"></i> Geofencing
      </a>
      <a href="<?= $saPrefix ?>geofence_analytics.php" class="<?= $currentPage == 'geofence_analytics.php' ? 'active' : '' ?>">
        <i class="fas fa-chart-line"></i> Statistics
      </a>
    </div>
  </div>
  
  <!-- Security Dashboard -->
  <a href="<?= $isSuperAdmin ? '../security_dashboard.php' : 'security_dashboard.php' ?>" class="<?= $currentPage == 'security_dashboard.php' ? 'active' : '' ?>">
    <i class="fas fa-shield-alt"></i>
    <span class="link-text">Security Dashboard</span>
  </a>

  <!-- Quick Backup -->
  <a href="<?= $isSuperAdmin ? '../quick_backup.php' : 'quick_backup.php' ?>" class="<?= $currentPage == 'quick_backup.php' ? 'active' : '' ?>">
    <i class="fas fa-database"></i>
    <span class="link-text">Quick Backup</span>
  </a>

  <!-- Help Guide -->
  <a href="<?= $saPrefix ?>help.php" class="<?= $currentPage == 'help.php' ? 'active' : '' ?>">
    <i class="fas fa-question-circle"></i>
    <span class="link-text">Help Guide</span>
  </a>

  
  <a href="#" id="logoutBtn">
    <i class="fas fa-sign-out-alt"></i>
    <span class="link-text">Logout</span>
  </a>
</div>