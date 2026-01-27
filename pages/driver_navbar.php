<?php
// Get unread notifications count (using prepared statement for security)
$user_id = isset($_SESSION['user_id']) ? (int)$_SESSION['user_id'] : 0;
$stmt = $conn->prepare("SELECT COUNT(*) AS unread_count FROM notifications WHERE user_id = ? AND is_read = 0");
$stmt->bind_param("i", $user_id);
$stmt->execute();
$countRes = $stmt->get_result();
$unreadCount = $countRes->fetch_assoc()['unread_count'] ?? 0;
?>

<nav class="navbar navbar-expand-lg px-3">
  <div class="container-fluid">
    <button class="burger-btn" id="burgerBtn">
      <i class="fas fa-bars"></i>
    </button>
    <a class="navbar-brand fw-bold text-dark" href="../driver/driver-dashboard.php">Smart Track</a>

    <div class="ms-auto d-flex align-items-center gap-3">
      
      <!-- Notification Bell -->
      <a href="../driver/notifications.php" class="position-relative text-dark" style="text-decoration: none;">
        <i class="fas fa-bell fa-lg"></i>
        <?php if ($unreadCount > 0): ?>
          <span class="position-absolute top-0 start-100 translate-middle badge rounded-pill bg-danger">
            <?= $unreadCount ?>
          </span>
        <?php endif; ?>
      </a>

      <!-- Profile Dropdown -->
      <div class="dropdown driver-dropdown">
        <button class="btn dropdown-toggle d-flex align-items-center gap-2" id="driverDropdown" type="button" data-bs-toggle="dropdown" aria-expanded="false">
          <div class="position-relative">
            <i class="fas fa-user-circle fa-lg text-primary"></i>
            <span class="status-indicator"></span>
          </div>
          <span class="fw-semibold text-dark d-none d-md-inline">
            <?php echo htmlspecialchars($_SESSION['full_name'] ?? 'User'); ?>
          </span>
          <i class="fas fa-chevron-down text-muted small d-none d-md-inline"></i>
        </button>
        <ul class="dropdown-menu dropdown-menu-end" aria-labelledby="driverDropdown">
          <li><a class="dropdown-item" href="../driver/driver-dashboard.php"><i class="fas fa-tachometer-alt me-2"></i> Dashboard</a></li>
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
          <li><a class="dropdown-item" href="<?= $profilePath ?>"><i class="fas fa-user me-2"></i> My Profile</a></li>
          <li><a class="dropdown-item" href="../driver/notifications.php"><i class="fas fa-bell me-2"></i> Notifications</a></li>
          <li><hr class="dropdown-divider"></li>
          <li><a class="dropdown-item text-danger" href="<?= $logoutPath ?>" id="logoutBtnDropdown"><i class="fas fa-sign-out-alt me-2"></i> Logout</a></li>
        </ul>
      </div>
    </div>
  </div>
</nav>
<style>
 .navbar { z-index: 1202 !important; }
@media (max-width: 991.98px) {
  .sidebar { width: 260px; transform: translateX(-100%); position: fixed; top: 0; left: 0; height: 100vh; z-index: 1101; }
  .sidebar.open { transform: translateX(0); }
  .main-content { margin-left: 0 !important; padding: 16px; }
}
</style>
<script>
(function(){
  const burger = document.getElementById('burgerBtn');
  const sidebar = document.getElementById('sidebar');
  if (!burger || !sidebar) return;
  function isMobile(){ return window.innerWidth < 992; }
  let backdrop;
  function ensureBackdrop(){
    if (backdrop) return backdrop;
    backdrop = document.createElement('div');
    backdrop.style.position = 'fixed';
    backdrop.style.top = '0';
    backdrop.style.left = '0';
    backdrop.style.right = '0';
    backdrop.style.bottom = '0';
    backdrop.style.background = 'rgba(0,0,0,0.25)';
    backdrop.style.zIndex = '1100';
    backdrop.style.display = 'none';
    document.body.appendChild(backdrop);
    backdrop.addEventListener('click', closeSidebar);
    return backdrop;
  }
  function openSidebar(){ sidebar.classList.add('open'); ensureBackdrop().style.display = 'block'; }
  function closeSidebar(){ sidebar.classList.remove('open'); if (backdrop) backdrop.style.display = 'none'; }
  function toggle(){ if(!isMobile()) return; if(sidebar.classList.contains('open')) closeSidebar(); else openSidebar(); }
  if (!burger.dataset.bound){
    burger.addEventListener('click', function(e){ e.preventDefault(); e.stopPropagation(); toggle(); });
    burger.addEventListener('touchstart', function(e){ e.preventDefault(); e.stopPropagation(); toggle(); }, { passive: false });
    burger.dataset.bound = '1';
  }
  sidebar.addEventListener('click', function(e){ e.stopPropagation(); });
  sidebar.addEventListener('touchstart', function(e){ e.stopPropagation(); }, { passive: true });
  document.addEventListener('click', function(e){ if(!isMobile()) return; if(!sidebar.contains(e.target) && !burger.contains(e.target)) closeSidebar(); });
  window.addEventListener('resize', function(){ if(!isMobile()) closeSidebar(); });
})();
</script>