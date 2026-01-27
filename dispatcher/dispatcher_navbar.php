<nav class="navbar navbar-expand-lg px-3">
  <div class="container-fluid">
    <button class="burger-btn" id="burgerBtn">
      <i class="fas fa-bars"></i>
    </button>
    <a class="navbar-brand fw-bold text-dark" href="#">Smart Track</a>
    <div class="ms-auto">
      <div class="dropdown">
        <button class="btn btn-light border-0 d-flex align-items-center gap-2" id="dispatcherDropdown" data-bs-toggle="dropdown" aria-expanded="false">
          <i class="fas fa-user-circle fa-lg text-primary"></i>
          <span class="fw-semibold text-dark d-none d-md-inline">Dispatcher</span>
          <i class="fas fa-chevron-down text-muted small d-none d-md-inline"></i>
        </button>
        <?php
        // Determine correct path based on current location
        $currentPath = $_SERVER['PHP_SELF'];
        $isProfilePage = (strpos($currentPath, 'profile.php') !== false);
        $isInSubfolder = (strpos($currentPath, '/super_admin/') !== false || 
                         strpos($currentPath, '/motorpool_admin/') !== false || 
                         strpos($currentPath, '/dispatcher/') !== false || 
                         strpos($currentPath, '/driver/') !== false || 
                         strpos($currentPath, '/mechanic/') !== false || 
                         strpos($currentPath, '/user/') !== false);
        
        $profilePath = $isProfilePage ? 'profile.php' : ($isInSubfolder ? '../profile.php' : 'profile.php');
        $logoutPath = $isProfilePage ? 'logout.php' : ($isInSubfolder ? '../logout.php' : 'logout.php');
        ?>
        <ul class="dropdown-menu dropdown-menu-end shadow-sm" aria-labelledby="dispatcherDropdown">
          <li><a class="dropdown-item" href="<?= $profilePath ?>"><i class="fas fa-user me-2"></i> My Profile</a></li>
          <li><a class="dropdown-item" href="#"><i class="fas fa-bell me-2"></i> Notifications</a></li>
          <li><hr class="dropdown-divider"></li>
          <li><a class="dropdown-item text-danger" href="<?= $logoutPath ?>"><i class="fas fa-sign-out-alt me-2"></i> Logout</a></li>
        </ul>
      </div>
    </div>
  </div>
</nav>