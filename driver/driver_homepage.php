<?php
session_start();
if (!isset($_SESSION['role']) || strtolower($_SESSION['role']) !== 'driver') {
    header("Location: index.php");
    exit;
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0"/>
  <title>Dashboard | Smart Track</title>

  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
  <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css" rel="stylesheet">
  <style>
    :root {
      --primary: #003566;
      --accent: #00b4d8;
      --bg: #f8f9fa;
    }

    body {
      font-family: 'Segoe UI', sans-serif;
      background-color: var(--bg);
    }

    /* Sidebar */
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

    /* Hide chevrons when sidebar is collapsed */
    .sidebar.collapsed .dropdown-chevron {
      display: none;
    }


    .sidebar a {
      display: block;
      padding: 14px 20px;
      color: #d9d9d9;
      text-decoration: none;
      transition: background 0.2s;
      white-space: nowrap;
    }

    .sidebar a:hover,
    .sidebar a.active {
      background-color: #001d3d;
      color: var(--accent);
    }

    /* Dropdown submenu links design */
    .sidebar .collapse a {
      color: #d9d9d9;
      font-size: 0.95rem;
      padding: 10px 16px;
      margin: 4px 8px;
      border-radius: 0.35rem;
      display: block;
      text-decoration: none;
      transition: background 0.2s, color 0.2s;
    }

    .sidebar .collapse a:hover {
      background-color: #002855;
      color: var(--accent);
    }

    /* Custom chevron icon for dropdown */
    .dropdown-chevron {
    color: #ffffff;
    transition: transform 0.3s ease, color 0.2s ease;
    }

    .dropdown-chevron:hover {
    color: var(--accent);
    }

    /* Rotate chevron when dropdown is expanded */
    .dropdown-toggle[aria-expanded="true"] .dropdown-chevron {
      transform: rotate(90deg);
    }

    .dropdown-toggle::after {
      display: none;
    }

    .main-content {
      margin-left: 250px;
      padding: 20px;
      transition: margin-left 0.3s ease;
    }

    .main-content.collapsed {
      margin-left: 70px;
    }

    .navbar {
      background-color: #fff;
      box-shadow: 0 2px 5px rgba(0,0,0,0.05);
      border-bottom: 1px solid #dee2e6;
      z-index: 1100;
    }

    /* Admin Dropdown Menu Styling */
    .dropdown-menu {
    border-radius: 0.5rem;
    border: none;
    box-shadow: 0 8px 25px rgba(0, 0, 0, 0.08);
    min-width: 190px;
    padding: 0.4rem 0;
    background-color: #ffffff;
    animation: fadeIn 0.25s ease-in-out;
    }

    @keyframes fadeIn {
    from { opacity: 0; transform: translateY(5px); }
    to { opacity: 1; transform: translateY(0); }
    }

    /* Dropdown items */
    .dropdown-menu .dropdown-item {
    display: flex;
    align-items: center;
    padding: 10px 16px;
    font-size: 0.95rem;
    color: #343a40;
    transition: all 0.3s ease;
    border-radius: 0.35rem;
    }

    /* Hover effect */
    .dropdown-menu .dropdown-item:hover {
    background-color: #001d3d; /* deep navy like sidebar */
    color: var(--accent); /* aqua blue */
    box-shadow: inset 2px 0 0 var(--accent); /* accent highlight */
    }

    /* Icon transition */
    .dropdown-menu .dropdown-item i {
    margin-right: 10px;
    color: #6c757d;
    transition: color 0.3s ease;
    }

    .dropdown-menu .dropdown-item:hover i {
    color: var(--accent);
    }

    .burger-btn {
      font-size: 1.5rem;
      background: none;
      border: none;
      color: var(--primary);
      margin-right: 1rem;
    }

    .card-icon {
      font-size: 2rem;
      color: var(--accent);
    }

    .card h6 {
      font-weight: 500;
    }

    .card h4 {
      font-weight: bold;
    }

    #map {
      width: 100%;
      height: 400px;
      background-color: #e9ecef;
      border-radius: 0.5rem;
    }
  </style>
</head>
<body>

  <!-- Sidebar -->
<?php include __DIR__ . '/../pages/sidebar.php'; ?>
  <!-- Navbar -->
<?php include __DIR__ . '/../pages/navbar.php'; ?>

  <!-- Main Content -->
  <div class="main-content" id="mainContent">
    <div class="container-fluid">
    <div class="row g-4">
      <div class="col-md-4">
        <div class="card shadow-sm">
          <div class="card-body d-flex align-items-center">
            <div class="me-3 card-icon"><i class="fas fa-car"></i></div>
            <div>
              <h6>Assigned Vehicle</h6>
              <h4>ABC-123 (Toyota Hilux)</h4>
            </div>
          </div>
        </div>
      </div>
      <div class="col-md-4">
        <div class="card shadow-sm">
          <div class="card-body d-flex align-items-center">
            <div class="me-3 card-icon"><i class="fas fa-route"></i></div>
            <div>
              <h6>Current Route</h6>
              <h4>City Hall → District B</h4>
            </div>
          </div>
        </div>
      </div>
      <div class="col-md-4">
        <div class="card shadow-sm">
          <div class="card-body d-flex align-items-center">
            <div class="me-3 card-icon"><i class="fas fa-tools"></i></div>
            <div>
              <h6>Vehicle Status</h6>
              <h4>Operational</h4>
            </div>
          </div>
        </div>
      </div>
    </div>

    <!-- Map Section -->
    <div class="row mt-4">
      <div class="col-12">
        <div class="card shadow-sm">
          <div class="card-body">
            <h5 class="card-title text-primary">My Vehicle Live Location</h5>
            <div id="map">Map showing only this driver’s assigned unit.</div>
          </div>
        </div>
      </div>
    </div>
    </div>
  </div>

  <!-- JS -->
<script>
  const burgerBtn = document.getElementById('burgerBtn');
  const sidebar = document.getElementById('sidebar');
  const mainContent = document.getElementById('mainContent');
  const linkTexts = document.querySelectorAll('.link-text');
  const dropdownToggles = document.querySelectorAll('.dropdown-toggle');

  burgerBtn.addEventListener('click', () => {
    const isCollapsed = sidebar.classList.toggle('collapsed');
    mainContent.classList.toggle('collapsed');

    // Toggle text visibility
    linkTexts.forEach(text => {
      text.style.display = isCollapsed ? 'none' : 'inline';
    });

    // Manage dropdown chevron interactivity
    dropdownToggles.forEach(toggle => {
      const chevron = toggle.querySelector('.dropdown-chevron');
      if (isCollapsed) {
        chevron.classList.add('disabled-chevron');
        chevron.style.cursor = 'not-allowed';
        chevron.setAttribute('title', 'Expand sidebar to activate');
        toggle.setAttribute('data-bs-toggle', ''); // disable collapse
      } else {
        chevron.classList.remove('disabled-chevron');
        chevron.style.cursor = 'pointer';
        chevron.removeAttribute('title');
        toggle.setAttribute('data-bs-toggle', 'collapse'); // enable collapse
      }
    });

    // 🚨 Collapse all sidebar dropdowns when sidebar is collapsed
    if (isCollapsed) {
      const openMenus = sidebar.querySelectorAll('.collapse.show');
      openMenus.forEach(menu => {
        const collapseInstance = bootstrap.Collapse.getInstance(menu) || new bootstrap.Collapse(menu, { toggle: false });
        collapseInstance.hide();
      });
    }
  });
</script>
<?php if (isset($_SESSION['login_success'])): ?>
<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
<script>
    Swal.fire({
        icon: 'success',
        title: 'Login Successful',
        text: '<?= $_SESSION['login_success'] ?>',
        timer: 3000,
        showConfirmButton: false
    });
</script>
<?php unset($_SESSION['login_success']); ?>
<?php endif; ?>

<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
<script>
  document.getElementById("logoutBtn").addEventListener("click", function(e) {
    e.preventDefault();
    const logoutURL = this.getAttribute('data-logout-url') || '../logout.php';

    Swal.fire({
      title: 'Log out?',
      text: "Are you sure you want to log out?",
      icon: 'question',
      iconColor: '#00b4d8',
      showCancelButton: true,
      confirmButtonColor: '#003566',
      cancelButtonColor: '#6c757d',
      confirmButtonText: '<i class="fas fa-check-circle me-1"></i> Yes',
      cancelButtonText: '<i class="fas fa-times-circle me-1"></i> Cancel',
      reverseButtons: true,
      background: '#f8f9fa',
      color: '#212529',
      customClass: {
        popup: 'rounded-4 shadow',
        confirmButton: 'swal-btn',
        cancelButton: 'swal-btn'
      },
      didRender: () => {
        document.querySelectorAll('.swal-btn').forEach(btn => {
          btn.style.minWidth = '120px';
          btn.style.padding = '10px 16px';
          btn.style.fontSize = '15px';
        });
      }
    }).then((result) => {
      if (result.isConfirmed) {
        Swal.fire({
          title: 'Logging out...',
          icon: 'info',
          showConfirmButton: false,
          timer: 1000,
          willClose: () => {
            window.location.href = logoutURL;
          }
        });
      }
    });
  });
</script>

  <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>