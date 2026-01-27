<?php
session_start();
if (!isset($_SESSION['role']) || strtolower($_SESSION['role']) !== 'dispatcher') {
    header("Location: ../index.php");
    exit;
}
require_once __DIR__ . '/../db_connection.php';

// Fetch all fleet vehicles and their most recently assigned driver from trip logs
// Filter out synthetic vehicles - use prepared statement for consistency
$fleetVehicles_stmt = $conn->prepare("
    SELECT
    fv.unit,
    fv.article,
    fv.plate_number,
    fv.status,
    ut.full_name AS driver_name
FROM fleet_vehicles fv
LEFT JOIN vehicle_assignments va 
    ON fv.id = va.vehicle_id AND va.status = 'active'
LEFT JOIN user_table ut 
    ON va.driver_id = ut.user_id
WHERE fv.article NOT LIKE '%Synthetic%' 
    AND fv.plate_number NOT LIKE 'SYN-%'
    AND fv.plate_number NOT LIKE '%SYN%'
ORDER BY fv.unit
");
$fleetVehicles_stmt->execute();
$fleetVehicles = $fleetVehicles_stmt->get_result();

?>

<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0"/>
  <title>Fleet Overview | Smart Track</title>
  <link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css" />
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

    .sidebar a.active i {
    color: var(--accent) !important;
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
      margin-top: 20px;
      padding: 20px;
      transition: margin-left 0.3s ease;
    }

    .main-content.collapsed {
      margin-left: 70px;
    }

    /* Mobile responsiveness */
    @media (max-width: 768px) {
      .sidebar {
        transform: translateX(-100%);
        transition: transform 0.3s ease;
        z-index: 1050;
      }
      
      .sidebar.show {
        transform: translateX(0);
      }
      
      .sidebar-backdrop {
        position: fixed;
        top: 0;
        left: 0;
        width: 100%;
        height: 100%;
        background-color: rgba(0, 0, 0, 0.5);
        z-index: 1040;
        display: none;
      }
      
      .sidebar-backdrop.show {
        display: block;
      }
      
      .main-content {
        margin-left: 0;
        margin-top: 60px;
      }
      
      .main-content.collapsed {
        margin-left: 0;
      }
    }

    .navbar {
      position: fixed;      
      top: 0;               
      left: 0;      
      width: 100%;
      background-color: #fff;
      box-shadow: 0 2px 5px rgba(0,0,0,0.05);
      border-bottom: 1px solid #dee2e6;
      z-index: 1100;
    }
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
    
    /* Add modal styles that match your UI */
    .modal-content {
      border-radius: 0.5rem;
      border: none;
      box-shadow: 0 5px 15px rgba(0,0,0,0.1);
    }
    .modal-header {
      background-color: var(--primary);
      color: white;
      border-bottom: none;
    }
    .modal-footer {
      border-top: none;
    }
    .alert-item {
      transition: all 0.3s;
      border-left: 4px solid var(--accent);
    }
    
    /* Card styles */
    .card {
      border: none;
      border-radius: 0.5rem;
      transition: transform 0.2s, box-shadow 0.2s;
    }
    .card:hover {
      transform: translateY(-2px);
      box-shadow: 0 10px 20px rgba(0,0,0,0.1);
    }
    .card-icon {
      font-size: 1.75rem;
      color: var(--accent);
      width: 50px;
      height: 50px;
      display: flex;
      align-items: center;
      justify-content: center;
      border-radius: 50%;
      background-color: rgba(0, 180, 216, 0.1);
    }
    .border-primary { border-color: var(--primary) !important; }
    .border-success { border-color: #28a745 !important; }
    .border-info { border-color: #17a2b8 !important; }
    .border-warning { border-color: #ffc107 !important; }
    
    /* Slightly lower the Fleet Overview title */
    .fleet-title { margin-top: 12px; }
    
    /* Scrollable fleet table */
    #fleetTableContainer {
      max-height: 520px;
      overflow-y: auto;
      scrollbar-width: thin;
      scrollbar-color: #cbd5e1 #f1f5f9;
    }
    #fleetTableContainer::-webkit-scrollbar { width: 8px; height: 8px; }
    #fleetTableContainer::-webkit-scrollbar-thumb { background-color: #cbd5e1; border-radius: 8px; }
    #fleetTableContainer::-webkit-scrollbar-track { background: #f1f5f9; }
    /* Keep header visible while scrolling */
    #fleetTableContainer thead th { position: sticky; top: 0; background: #ffffff; z-index: 2; }
  </style>
</head>
<body>

<?php include __DIR__ . '/../pages/dispatcher_sidebar.php'; ?>
<?php include __DIR__ . '/../pages/dispatcher_navbar.php'; ?>

<!-- Mobile sidebar backdrop -->
<div class="sidebar-backdrop" id="sidebarBackdrop"></div>

<div class="main-content" id="mainContent">
  <div class="container-fluid py-2">
    <div class="row">
      <div class="col-12">
        <h3 class="mb-4 text-primary fleet-title">Fleet Overview</h3>
        <div class="card shadow-sm">
          <div class="card-body">
            <div class="table-responsive" id="fleetTableContainer">
              <table class="table table-hover table-striped">
                <thead>
                  <tr>
                    <th>Unit ID</th>
                    <th>Vehicle Description</th>
                    <th>Plate Number</th>
                    <th>Status</th>
                    <th>Assigned Driver</th>
                  </tr>
                </thead>
                <tbody>
                  <?php while($vehicle = $fleetVehicles->fetch_assoc()): ?>
                    <tr>
                      <td><?= htmlspecialchars($vehicle['unit']) ?></td>
                      <td><?= htmlspecialchars($vehicle['article']) ?></td>
                      <td><?= htmlspecialchars($vehicle['plate_number']) ?></td>
                      <td>
                        <?php 
                          $status = strtolower($vehicle['status']);
                          $badge_class = '';
                          switch ($status) {
                            case 'active':
                              $badge_class = 'bg-success';
                              break;
                            case 'inactive':
                              $badge_class = 'bg-danger';
                              break;
                            case 'maintenance':
                              $badge_class = 'bg-warning';
                              break;
                            default:
                              $badge_class = 'bg-secondary';
                              break;
                          }
                        ?>
                        <span class="badge <?= $badge_class ?>"><?= htmlspecialchars(ucfirst($status)) ?></span>
                      </td>
                      <td><?= htmlspecialchars($vehicle['driver_name'] ?: 'N/A') ?></td>
                    </tr>
                  <?php endwhile; ?>
                </tbody>
              </table>
            </div>
          </div>
        </div>
      </div>
    </div>
  </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
<script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js"></script>
<script src="https://unpkg.com/leaflet-routing-machine@3.2.12/dist/leaflet-routing-machine.js"></script>

<script>
// Sidebar toggle functionality
const burgerBtn = document.getElementById('burgerBtn');
const sidebar = document.getElementById('sidebar');
const mainContent = document.getElementById('mainContent');
const sidebarBackdrop = document.getElementById('sidebarBackdrop');
const linkTexts = document.querySelectorAll('.link-text');
const dropdownToggles = document.querySelectorAll('.dropdown-toggle');

burgerBtn.addEventListener('click', () => {
  const isMobile = window.innerWidth <= 768;
  
  if (isMobile) {
    // Mobile: toggle sidebar visibility
    const isShowing = sidebar.classList.toggle('show');
    sidebarBackdrop.classList.toggle('show', isShowing);
  } else {
    // Desktop: toggle sidebar collapse
    const isCollapsed = sidebar.classList.toggle('collapsed');
    mainContent.classList.toggle('collapsed');
    
    linkTexts.forEach(text => {
      text.style.display = isCollapsed ? 'none' : 'inline';
    });
    
    dropdownToggles.forEach(toggle => {
      const chevron = toggle.querySelector('.dropdown-chevron');
      if (isCollapsed) {
        chevron.classList.add('disabled-chevron');
        chevron.style.cursor = 'not-allowed';
        chevron.setAttribute('title', 'Expand sidebar to activate');
        toggle.setAttribute('data-bs-toggle', '');
      } else {
        chevron.classList.remove('disabled-chevron');
        chevron.style.cursor = 'pointer';
        chevron.removeAttribute('title');
        toggle.setAttribute('data-bs-toggle', 'collapse');
      }
    });
    
    if (isCollapsed) {
      const openMenus = sidebar.querySelectorAll('.collapse.show');
      openMenus.forEach(menu => {
        const collapseInstance = bootstrap.Collapse.getInstance(menu) || new bootstrap.Collapse(menu, { toggle: false });
        collapseInstance.hide();
      });
    }
  }
});

// Close sidebar when clicking backdrop on mobile
sidebarBackdrop.addEventListener('click', () => {
  const isMobile = window.innerWidth <= 768;
  if (isMobile) {
    sidebar.classList.remove('show');
    sidebarBackdrop.classList.remove('show');
  }
});

// Close sidebar when clicking outside on mobile
document.addEventListener('click', (e) => {
  const isMobile = window.innerWidth <= 768;
  if (isMobile && sidebar.classList.contains('show')) {
    if (!sidebar.contains(e.target) && !burgerBtn.contains(e.target)) {
      sidebar.classList.remove('show');
      sidebarBackdrop.classList.remove('show');
    }
  }
});

// Handle window resize
window.addEventListener('resize', () => {
  const isMobile = window.innerWidth <= 768;
  if (isMobile) {
    sidebar.classList.remove('collapsed');
    mainContent.classList.remove('collapsed');
    sidebar.classList.remove('show');
    sidebarBackdrop.classList.remove('show');
  } else {
    sidebar.classList.remove('show');
    sidebarBackdrop.classList.remove('show');
  }
});

// Logout functionality
document.addEventListener("DOMContentLoaded", function() {
  const logoutBtn = document.getElementById("logoutBtn");
  if (logoutBtn) {
    logoutBtn.addEventListener("click", function(e) {
      e.preventDefault();
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
        customClass: {
          popup: 'rounded-4 shadow',
          confirmButton: 'swal-btn',
          cancelButton: 'swal-btn'
        }
      }).then((result) => {
        if (result.isConfirmed) {
          window.location.href = '../logout.php';
        }
      });
    });
  }
});
</script>
</body>
</html>