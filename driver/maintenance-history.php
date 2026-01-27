<?php
session_start();
require_once __DIR__ . '/../db_connection.php';

if (!isset($_SESSION['user_id']) || strtolower($_SESSION['role']) !== 'driver') {
    header("Location: ../index.php");
    exit();
}

$driverId = $_SESSION['user_id'];

// Fetch maintenance history for vehicles assigned to this driver
$stmt = $conn->prepare("
    SELECT ms.*, fv.article, fv.unit, fv.plate_number
    FROM maintenance_schedules ms
    JOIN fleet_vehicles fv ON ms.vehicle_id = fv.id
    JOIN vehicle_assignments va ON va.vehicle_id = fv.id
    WHERE va.driver_id = ?
    ORDER BY COALESCE(ms.scheduled_date, ms.request_date) DESC
");
$stmt->bind_param("i", $driverId);
$stmt->execute();
$maintenanceHistory = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
$stmt->close();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Maintenance History | Driver</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css" rel="stylesheet">
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <style>
        body { font-family: 'Segoe UI', sans-serif; background-color: #f8f9fa; }
        .timeline { position: relative; margin: 20px 0; padding: 0; list-style: none; }
        /* Make history list scrollable */
        .timeline { max-height: 480px; overflow-y: auto; scrollbar-width: thin; scrollbar-color: #cbd5e1 #f1f5f9; }
        .timeline::-webkit-scrollbar { width: 8px; height: 8px; }
        .timeline::-webkit-scrollbar-thumb { background-color: #cbd5e1; border-radius: 8px; }
        .timeline::-webkit-scrollbar-track { background: #f1f5f9; }
        .timeline-item { position: relative; margin-bottom: 20px; padding-left: 40px; }
        .timeline-item::before {
            content: '';
            position: absolute;
            top: 0;
            left: 15px;
            width: 10px;
            height: 10px;
            background-color: #00b4d8;
            border-radius: 50%;
        }
        .timeline-item h6 { font-weight: 600; margin-bottom: 4px; }
        .timeline-item p { margin-bottom: 2px; }
        :root {
      --primary: #003566;
      --accent: #00b4d8;
      --bg: #f8f9fa;
    }

    body {
      font-family: 'Segoe UI', sans-serif;
      background-color: var(--bg);
      margin: 0;
      padding: 0;
      overflow-x: hidden;
    }

    /* Main content positioning */
    .main-content {
      margin-left: 250px;
      margin-top: 60px;
      padding: 20px;
      transition: margin-left 0.3s ease;
      min-height: calc(100vh - 60px);
    }

    .main-content.collapsed {
      margin-left: 70px;
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

    /* Navbar */
    .navbar {
      position: fixed;
      top: 0;
      left: 0;
      width: 100%;
      background-color: #fff;
      box-shadow: 0 2px 5px rgba(0,0,0,0.05);
      border-bottom: 1px solid #dee2e6;
      z-index: 1100;
      display: block;
    }

    .burger-btn {
      font-size: 1.5rem;
      background: none;
      border: none;
      color: var(--primary);
      margin-right: 1rem;
    }

    /* Print styles */
    @media print {
      .no-print, .navbar, .sidebar { display: none !important; }
      body { background: #fff; }
      .main-content { margin: 0; padding: 0; }
      .card { box-shadow: none; border: none; }
      .timeline { display: none; }
      .print-only { display: block !important; }
    }

    .print-only { display: none; }

    /* Print header/table styling */
    .print-container { max-width: 720px; margin: 0 auto; }
    .print-header { text-align: center; margin-bottom: 12px; }
    .print-header h1 { font-size: 22px; margin: 0; letter-spacing: 1px; }
    .print-header h2 { font-size: 16px; margin: 4px 0 0 0; font-weight: 600; }
    .print-header .meta { font-size: 12px; color: #6c757d; margin-top: 2px; }
    .print-table { width: 100%; border-collapse: collapse; font-size: 12px; }
    .print-table th, .print-table td { border: 1px solid #dee2e6; padding: 6px 8px; vertical-align: top; }
    .print-table thead th { background: #f1f3f5; font-weight: 700; }
    .nowrap { white-space: nowrap; }

    @page { size: A4; margin: 12mm; }
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
      margin-top: 60px;
      padding: 20px;
      transition: margin-left 0.3s ease;
    }

    .main-content.collapsed {
      margin-left: 70px;
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
    </style>
</head>
<body>
    <!-- Desktop Sidebar and Navbar -->
    <?php include __DIR__ . '/../pages/driver_sidebar.php'; ?>
    <?php include __DIR__ . '/../pages/driver_navbar.php'; ?>

    <div class="main-content" id="mainContent">
        <div class="container mt-4">
    <div class="d-flex justify-content-between align-items-center mb-3 no-print">
        <h3 class="mb-0">Maintenance History</h3>
        <div>
            <button type="button" class="btn btn-outline-secondary btn-sm" onclick="window.print()">
                <i class="fas fa-file-pdf me-1"></i> Export / Print PDF
            </button>
        </div>
    </div>
    <div class="card shadow-sm">
        <div class="card-body">
            <!-- Printable header + table -->
            <div class="print-only print-container">
                <div class="print-header">
                    <h1>CGSO</h1>
                    <h2>Maintenance History</h2>
                    <div class="meta">Generated: <?= date('M j, Y g:i A') ?> | Driver ID: <?= (int)$driverId ?></div>
                </div>
                <table class="print-table">
                    <thead>
                        <tr>
                            <th class="nowrap">Vehicle</th>
                            <th class="nowrap">Plate</th>
                            <th class="nowrap">Task</th>
                            <th>Status</th>
                            <th class="nowrap">Scheduled</th>
                            <th class="nowrap">Start</th>
                            <th class="nowrap">End</th>
                            <th>Notes</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (!empty($maintenanceHistory)): ?>
                            <?php foreach($maintenanceHistory as $m): ?>
                                <tr>
                                    <td><?= htmlspecialchars($m['article']) ?></td>
                                    <td><?= htmlspecialchars($m['plate_number']) ?></td>
                                    <td><?= ucfirst(str_replace('_', ' ', $m['maintenance_type'])) ?></td>
                                    <td><?= ucfirst(str_replace('_', ' ', $m['status'])) ?></td>
                                    <td><?= $m['scheduled_date'] ? date('M j, Y', strtotime($m['scheduled_date'])) : 'N/A' ?></td>
                                    <td><?= $m['start_time'] ? date('g:i A', strtotime($m['start_time'])) : '—' ?></td>
                                    <td><?= $m['end_time'] ? date('g:i A', strtotime($m['end_time'])) : '—' ?></td>
                                    <td><?= htmlspecialchars($m['notes'] ?? '') ?></td>
                                </tr>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <tr><td colspan="8" style="text-align:center">No maintenance history found.</td></tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
            <?php if (!empty($maintenanceHistory)): ?>
                <div class="timeline">
                    <?php foreach($maintenanceHistory as $maintenance): ?>
                        <div class="timeline-item">
                            <h6><?= htmlspecialchars($maintenance['article']) ?> (<?= htmlspecialchars($maintenance['plate_number']) ?>)</h6>
                            <p class="mb-1">Task: <?= ucfirst(str_replace('_', ' ', $maintenance['maintenance_type'])) ?></p>
                            <p class="mb-1">
                                <span class="badge bg-<?= 
                                    $maintenance['status'] == 'completed' ? 'success' : 
                                    ($maintenance['status'] == 'in progress' ? 'primary' : 'warning') ?>">
                                    <?= ucfirst(str_replace('_', ' ', $maintenance['status'])) ?>
                                </span>
                            </p>
                            <p class="text-muted small mb-1">
                                <i class="far fa-calendar me-1"></i>
                                Scheduled: <?= $maintenance['scheduled_date'] ? date('M j, Y g:i a', strtotime($maintenance['scheduled_date'])) : 'N/A' ?>
                                <?php if ($maintenance['start_time']): ?>
                                    • Start: <?= date('g:i a', strtotime($maintenance['start_time'])) ?>
                                <?php endif; ?>
                                <?php if ($maintenance['end_time']): ?>
                                    • End: <?= date('g:i a', strtotime($maintenance['end_time'])) ?>
                                <?php endif; ?>
                            </p>
                            <?php if ($maintenance['notes']): ?>
                                <p class="mb-0"><?= htmlspecialchars($maintenance['notes']) ?></p>
                            <?php endif; ?>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php else: ?>
                <div class="alert alert-info">No maintenance history found.</div>
            <?php endif; ?>
        </div>
    </div>
        </div>
    </div>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
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
<script>
  // Sidebar toggle functionality
  const burgerBtn = document.getElementById('burgerBtn');
  const sidebar = document.getElementById('sidebar');
  const mainContent = document.getElementById('mainContent');
  const linkTexts = document.querySelectorAll('.link-text');
  const dropdownToggles = document.querySelectorAll('.dropdown-toggle');

  if (burgerBtn) {
    burgerBtn.addEventListener('click', () => {
      // Check if we're on mobile (sidebar is hidden off-screen)
      if (window.innerWidth <= 768) {
        sidebar.classList.toggle('show');
      } else {
        // Desktop behavior - collapse/expand sidebar
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
            toggle.setAttribute('data-bs-toggle', ''); // disable collapse
          } else {
            chevron.classList.remove('disabled-chevron');
            chevron.style.cursor = 'pointer';
            chevron.removeAttribute('title');
            toggle.setAttribute('data-bs-toggle', 'collapse'); // enable collapse
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
  }

  // Sidebar active class handler
  const allSidebarLinks = document.querySelectorAll('.sidebar a:not(.dropdown-toggle)');
  allSidebarLinks.forEach(link => {
    link.addEventListener('click', function() {
      allSidebarLinks.forEach(l => l.classList.remove('active'));
      this.classList.add('active');
      const parentCollapse = this.closest('.collapse');
      if (parentCollapse) {
        const bsCollapse = bootstrap.Collapse.getInstance(parentCollapse);
        if (bsCollapse) {
          bsCollapse.show();
        }
      }
    });
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
          color: '#212529',
          customClass: {
            popup: 'rounded-4 shadow',
            confirmButton: 'swal-btn',
            cancelButton: 'swal-btn'
          },
          didRender: () => {
            const buttons = document.querySelectorAll('.swal-btn');
            buttons.forEach(btn => {
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
                window.location.href = '/tracking/logout.php';
              }
            });
          }
        });
      });
    }

    // Initialize tooltips
    const tooltipTriggerList = [].slice.call(document.querySelectorAll('[data-bs-toggle="tooltip"]'));
    tooltipTriggerList.map(function(tooltipTriggerEl) {
      return new bootstrap.Tooltip(tooltipTriggerEl);
    });
  });
</script>

<script>
  document.addEventListener("DOMContentLoaded", function () {
    const logoutBtn = document.getElementById("logoutBtn");
    if (logoutBtn) {
      logoutBtn.addEventListener("click", function (e) {
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
          color: '#212529',
          customClass: {
            popup: 'rounded-4 shadow',
            confirmButton: 'swal-btn',
            cancelButton: 'swal-btn'
          },
          didRender: () => {
            const buttons = document.querySelectorAll('.swal-btn');
            buttons.forEach(btn => {
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
                window.location.href = '../logout.php';
              }
            });
          }
        });
      });
    }
  });
</script>
<script>
  // Only initialize map if vehicle exists and has coordinates
  <?php if($vehicle && $vehicle['current_latitude'] && $vehicle['current_longitude']): ?>
  const map = L.map("map").setView([<?= $vehicle['current_latitude'] ?>, <?= $vehicle['current_longitude'] ?>], 15);
  L.tileLayer("https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png", {
    attribution: '&copy; OpenStreetMap contributors'
  }).addTo(map);

  // Add vehicle marker
  const vehicleMarker = L.marker([<?= $vehicle['current_latitude'] ?>, <?= $vehicle['current_longitude'] ?>])
    .addTo(map)
    .bindPopup("<b>Your Vehicle</b><br><?= htmlspecialchars($vehicle['article']) ?>")
    .openPopup();

  // Add recent locations as a polyline if available
  <?php if(!empty($recentLocations)): ?>
    const recentPath = [
      <?php foreach($recentLocations as $loc): ?>
        [<?= $loc['latitude'] ?>, <?= $loc['longitude'] ?>],
      <?php endforeach; ?>
    ];
    const pathLine = L.polyline(recentPath, {color: 'blue'}).addTo(map);
    map.fitBounds(pathLine.getBounds());
  <?php endif; ?>
  <?php endif; ?>
</script>
</body>
</html>
