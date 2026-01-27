

<nav class="navbar navbar-expand-lg sticky-top">
  <div class="container-fluid">
    <button class="burger-btn" id="burgerBtn">
      <i class="fas fa-bars"></i>
    </button>

    <a class="navbar-brand fw-bold" href="#">Smart Track</a>

    <div class="ms-auto d-flex align-items-center gap-3">
    
        </a>
        <ul class="dropdown-menu dropdown-menu-end shadow" aria-labelledby="notificationDropdown">
          <li><h6 class="dropdown-header">Notifications</h6></li>
          <li><a class="dropdown-item" href="#">New trip assigned</a></li>
          <li><a class="dropdown-item" href="#">Vehicle maintenance due</a></li>
          <li><a class="dropdown-item" href="#">Driver check-in</a></li>
          <li><hr class="dropdown-divider"></li>
          <li><a class="dropdown-item text-primary" href="#">View all</a></li>
        </ul>
      </div>

      <!-- Admin Dropdown -->
      <div class="dropdown admin-dropdown">
        <button class="btn d-flex align-items-center gap-2" id="adminDropdown" data-bs-toggle="dropdown" aria-expanded="false">
          <div class="position-relative">
            <i class="fas fa-user-circle fa-lg text-primary"></i>
            <span class="status-indicator"></span>
          </div>
          <span class="fw-semibold text-dark d-none d-md-inline">
            <?php echo htmlspecialchars($_SESSION['full_name'] ?? 'User'); ?>
          </span>
          <i class="fas fa-chevron-down text-muted small d-none d-md-inline"></i>
        </button>
        <ul class="dropdown-menu dropdown-menu-end" aria-labelledby="adminDropdown">
          <li><a class="dropdown-item" href="#"><i class="fas fa-user me-2"></i> Profile</a></li>
          <li><a class="dropdown-item" href="#"><i class="fas fa-cog me-2"></i> Settings</a></li>
          <li><a class="dropdown-item" href="#"><i class="fas fa-bell me-2"></i> Notifications</a></li>
          <li><hr class="dropdown-divider"></li>
          <li><a class="dropdown-item text-danger" href="#"><i class="fas fa-sign-out-alt me-2"></i> Logout</a></li>
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