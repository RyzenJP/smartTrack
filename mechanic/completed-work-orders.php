<?php
session_start();
require_once __DIR__ . '/../db_connection.php';

// Make sure only mechanics can access
if (!isset($_SESSION['role']) || strtolower($_SESSION['role']) !== 'mechanic') {
    header("Location: ../index.php");
    exit;
}

$mechanicId = $_SESSION['user_id'];

// Fetch completed and cancelled work orders for current mechanic
$stmt = $conn->prepare("
    SELECT w.id, w.maintenance_type, w.status, w.start_time, w.end_time, w.days_taken, w.notes,
           v.article, v.unit, v.plate_number, u.username AS mechanic_name
    FROM maintenance_schedules w
    JOIN fleet_vehicles v ON w.vehicle_id = v.id
    LEFT JOIN user_table u ON w.assigned_mechanic = u.user_id
    WHERE w.status IN ('completed', 'cancelled') AND w.assigned_mechanic = ?
    ORDER BY w.end_time DESC
");
$stmt->bind_param("i", $mechanicId);
$stmt->execute();
$result = $stmt->get_result();
$completedOrders = $result->fetch_all(MYSQLI_ASSOC);
$stmt->close();

// Debug information removed - functionality is working correctly
?>

<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
  <title>Completed & Cancelled Work Orders | Smart Track</title>
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
      overflow-x: hidden;
    }
    
    /* Force mobile-first approach */
    * {
      box-sizing: border-box;
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

    /* Fix dropdown toggle layout - position chevron at far right edge */
    .sidebar .dropdown-toggle {
      justify-content: space-between !important;
      align-items: center !important;
      cursor: pointer !important;
      pointer-events: auto !important;
    }

    .sidebar .dropdown-toggle > div {
      display: flex;
      align-items: center;
      flex: 1;
    }

    .sidebar .dropdown-toggle .link-text {
      margin-left: 12px;
    }

    .sidebar .dropdown-chevron {
      margin-left: auto;
      flex-shrink: 0;
      position: absolute;
      right: 15px;
    }

    /* Smooth collapse animation */
    .sidebar .collapse {
      transition: height 0.3s ease, opacity 0.3s ease;
      overflow: hidden;
    }

    .sidebar .collapse:not(.show) {
      height: 0 !important;
      opacity: 0;
    }

    .sidebar .collapse.show {
      height: auto !important;
      opacity: 1;
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

    /* Print/PDF styles */
    @media print {
      .no-print, .navbar, .sidebar { display: none !important; }
      body { background: #fff; }
      .main-content { margin: 0; padding: 0; }
      .table-responsive { max-height: none !important; overflow: visible !important; border: none !important; }
      .card, .card-body { box-shadow: none; border: none; }
      .print-header { display: block !important; margin-bottom: 12px; text-align: center; }
    }

    .print-header { display: none; }
    .print-header h1 { font-size: 22px; margin: 0; letter-spacing: 1px; }
    .print-header h2 { font-size: 16px; margin: 4px 0 0 0; font-weight: 600; }
    .print-header .meta { font-size: 12px; color: #6c757d; margin-top: 2px; }
    @page { size: A4; margin: 12mm; }

    /* Table Scrollable Styling */
    .table-responsive::-webkit-scrollbar {
      width: 8px;
      height: 8px;
    }

    .table-responsive::-webkit-scrollbar-track {
      background: #f1f1f1;
      border-radius: 4px;
    }

    .table-responsive::-webkit-scrollbar-thumb {
      background: #c1c1c1;
      border-radius: 4px;
    }

    .table-responsive::-webkit-scrollbar-thumb:hover {
      background: #a8a8a8;
    }

    /* Sticky header styling */
    .sticky-top {
      position: sticky;
      top: 0;
      z-index: 10;
      background-color: #f8f9fa;
    }

    /* Filter controls styling */
    .input-group-text {
      background-color: var(--primary);
      color: white;
      border-color: var(--primary);
    }

    #searchInput:focus {
      border-color: var(--accent);
      box-shadow: 0 0 0 0.2rem rgba(0, 180, 216, 0.25);
    }

    #statusFilter:focus {
      border-color: var(--accent);
      box-shadow: 0 0 0 0.2rem rgba(0, 180, 216, 0.25);
    }

    /* Mobile responsive tweaks */
    @media (max-width: 991.98px) {
      .sidebar { 
        width: 260px !important; 
        transform: translateX(-100%) !important; 
        position: fixed !important; 
        top: 0 !important; 
        left: 0 !important; 
        height: 100vh !important; 
        z-index: 1101 !important; 
        transition: transform 0.3s ease !important;
      }
      .sidebar.show, .sidebar.open { transform: translateX(0) !important; }
      .main-content, .main-content.collapsed { 
        margin-left: 0 !important; 
        padding: 16px !important; 
        margin-top: 60px !important;
        width: 100% !important;
      }
      
      /* Force mobile layout */
      .container-fluid {
        padding-left: 0 !important;
        padding-right: 0 !important;
        max-width: 100% !important;
      }
      
      /* Force single column layout */
      .row.g-4 > * {
        margin-bottom: 1rem !important;
        width: 100% !important;
        flex: 0 0 100% !important;
        max-width: 100% !important;
      }
      
      /* Stack all columns on mobile */
      .col-lg-3, .col-lg-6, .col-lg-8, .col-lg-4 {
        margin-bottom: 1.5rem !important;
        width: 100% !important;
        flex: 0 0 100% !important;
        max-width: 100% !important;
      }
      
      /* Mobile dropdown animations */
      .sidebar .collapse {
        transition: height 0.3s ease !important;
      }

      .sidebar .collapse.show {
        display: block !important;
      }

      .sidebar .dropdown-chevron {
        transition: transform 0.3s ease !important;
      }

      .sidebar .dropdown-chevron.rotated {
        transform: rotate(90deg) !important;
      }
    }

    /* Phone tweaks for more native feel */
    @media (max-width: 575.98px) {
      .main-content { 
        padding: 12px !important; 
        margin-top: 60px !important;
      }
      
      /* Header adjustments for mobile */
      .d-flex.justify-content-between {
        flex-direction: column !important;
        align-items: flex-start !important;
        gap: 0.5rem !important;
      }
      
      /* Force mobile layout for all elements */
      .row {
        margin-left: 0 !important;
        margin-right: 0 !important;
      }
      
      .col-lg-3, .col-lg-6, .col-lg-8, .col-lg-4,
      .col-md-3, .col-md-6, .col-md-8, .col-md-4 {
        padding-left: 0 !important;
        padding-right: 0 !important;
      }
      
      /* Ensure cards are full width on mobile */
      .card {
        width: 100% !important;
        margin-bottom: 1rem !important;
      }
      
      /* Force mobile navbar */
      .navbar {
        padding-left: 15px !important;
        padding-right: 15px !important;
      }
      
      /* Table responsive */
      .table-responsive {
        font-size: 0.8rem;
      }
      
      .table th, .table td {
        padding: 8px 4px;
        font-size: 0.75rem;
      }
      
      /* Button adjustments */
      .btn {
        font-size: 0.8rem !important;
        padding: 6px 12px !important;
      }
    }
  </style>
</head>
<body>

<?php include __DIR__ . '/../pages/mechanic_sidebar.php'; ?>
<?php include __DIR__ . '/../pages/mechanic_navbar.php'; ?>

<div class="main-content" id="mainContent">
  <div class="container-fluid">
    <div class="card shadow-sm">
        <div class="card-body">
            <div class="d-flex justify-content-between align-items-center mb-3 no-print">
                <h5 class="card-title text-success fw-bold mb-0">Completed & Cancelled Work Orders</h5>
                <button type="button" class="btn btn-outline-secondary btn-sm" onclick="window.print()">
                  <i class="fas fa-file-pdf me-1"></i> Export / Print PDF
                </button>
            </div>

            <div class="print-header">
              <h1>CGSO</h1>
              <h2>Completed & Cancelled Work Orders</h2>
              <div class="meta">Generated: <?= date('M j, Y g:i A') ?></div>
            </div>

            <!-- Search and Filter Controls -->
            <div class="row mb-3">
                <div class="col-md-6">
                    <div class="input-group">
                        <span class="input-group-text"><i class="fas fa-search"></i></span>
                        <input type="text" id="searchInput" class="form-control" placeholder="Search by vehicle, type, or notes...">
                    </div>
                </div>
                <div class="col-md-3">
                    <select id="statusFilter" class="form-select">
                        <option value="all">All Status</option>
                        <option value="completed">Completed</option>
                        <option value="cancelled">Cancelled</option>
                    </select>
                </div>
                <div class="col-md-3">
                    <button id="clearFilters" class="btn btn-outline-secondary">
                        <i class="fas fa-times me-1"></i>Clear Filters
                    </button>
                </div>
            </div>

            <?php if (empty($completedOrders)): ?>
                <div class="alert alert-info">No completed or cancelled work orders found.</div>
            <?php else: ?>
                <!-- Results Counter -->
                <div class="mb-2">
                    <small class="text-muted">
                        Showing <span id="resultCount"><?= count($completedOrders) ?></span> of <?= count($completedOrders) ?> work orders
                    </small>
                </div>

                <!-- Scrollable Table Container (expands in print) -->
                <div class="table-responsive" style="max-height: 500px; overflow-y: auto; border: 1px solid #dee2e6; border-radius: 0.375rem;">
                    <table class="table table-hover align-middle mb-0" id="workOrdersTable">
                        <thead class="table-light sticky-top">
                            <tr>
                                <th>ID</th>
                                <th>Vehicle</th>
                                <th>Maintenance Type</th>
                                <th>Status</th>
                                <th>Start Date</th>
                                <th>Completed At</th>
                                <th>Days Taken</th>
                                <th>Completed By</th>
                                <th>Notes</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($completedOrders as $order): ?>
                                <tr class="work-order-row" data-status="<?= $order['status'] ?>">
                                    <td><?= htmlspecialchars($order['id']) ?></td>
                                    <td>
                                        <strong><?= htmlspecialchars($order['article']) ?></strong>
                                        (<?= htmlspecialchars($order['unit']) ?>) - 
                                        <?= htmlspecialchars($order['plate_number']) ?>
                                    </td>
                                    <td><?= htmlspecialchars($order['maintenance_type']) ?></td>
                                    <td>
                                        <?php if ($order['status'] === 'completed'): ?>
                                            <span class="badge bg-success">
                                                <?= ucfirst($order['status']) ?>
                                            </span>
                                        <?php else: ?>
                                            <span class="badge bg-danger">
                                                <?= ucfirst($order['status']) ?>
                                            </span>
                                        <?php endif; ?>
                                    </td>
                                    <td><?= htmlspecialchars($order['start_time']) ?></td>
                                    <td><?= htmlspecialchars($order['end_time']) ?></td>
                                    <td>
                                        <?php if ($order['status'] === 'completed'): ?>
                                            <?php if ($order['days_taken'] && $order['days_taken'] > 0): ?>
                                                <span class="badge bg-success"><?= $order['days_taken'] ?> days</span>
                                            <?php else: ?>
                                                <span class="badge bg-warning">Same day</span>
                                            <?php endif; ?>
                                        <?php elseif ($order['status'] === 'cancelled'): ?>
                                            <span class="badge bg-danger">Cancelled</span>
                                        <?php else: ?>
                                            <span class="badge bg-secondary">N/A</span>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <span class="badge bg-info">
                                            <?= htmlspecialchars($order['mechanic_name'] ?? 'Unknown') ?>
                                        </span>
                                    </td>
                                    <td><?= htmlspecialchars($order['notes']) ?></td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
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

<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
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

  // Search and Filter Functionality
  document.addEventListener('DOMContentLoaded', function() {
    const searchInput = document.getElementById('searchInput');
    const statusFilter = document.getElementById('statusFilter');
    const clearFilters = document.getElementById('clearFilters');
    const resultCount = document.getElementById('resultCount');
    const workOrderRows = document.querySelectorAll('.work-order-row');
    const totalRows = workOrderRows.length;

    function filterTable() {
      const searchTerm = searchInput.value.toLowerCase();
      const statusValue = statusFilter.value;
      let visibleCount = 0;

      workOrderRows.forEach(row => {
        const vehicle = row.cells[1].textContent.toLowerCase();
        const maintenanceType = row.cells[2].textContent.toLowerCase();
        const notes = row.cells[8].textContent.toLowerCase();
        const status = row.getAttribute('data-status');

        const matchesSearch = vehicle.includes(searchTerm) || 
                            maintenanceType.includes(searchTerm) || 
                            notes.includes(searchTerm);
        
        const matchesStatus = statusValue === 'all' || status === statusValue;

        if (matchesSearch && matchesStatus) {
          row.style.display = '';
          visibleCount++;
        } else {
          row.style.display = 'none';
        }
      });

      // Update result count
      if (resultCount) {
        resultCount.textContent = visibleCount;
      }
    }

    // Event listeners
    if (searchInput) {
      searchInput.addEventListener('input', filterTable);
    }

    if (statusFilter) {
      statusFilter.addEventListener('change', filterTable);
    }

    if (clearFilters) {
      clearFilters.addEventListener('click', function() {
        searchInput.value = '';
        statusFilter.value = 'all';
        filterTable();
      });
    }
  });
</script>

<script>
  document.addEventListener('DOMContentLoaded', function() {
    const burgerBtn = document.getElementById('burgerBtn');
    const sidebar = document.getElementById('sidebar');
    const mainContent = document.getElementById('mainContent');
    const linkTexts = document.querySelectorAll('.link-text');
    const dropdownToggles = document.querySelectorAll('.dropdown-toggle');

    // Function to check if device is mobile
    function isMobile() {
      return window.innerWidth < 992;
    }

    // Function to handle desktop sidebar behavior
    function handleDesktopSidebar() {
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
          toggle.setAttribute('data-bs-toggle', '');
        } else {
          chevron.classList.remove('disabled-chevron');
          chevron.style.cursor = 'pointer';
          chevron.removeAttribute('title');
          toggle.setAttribute('data-bs-toggle', 'collapse');
        }
      });

      // Collapse all sidebar dropdowns when sidebar is collapsed
      if (isCollapsed) {
        const openMenus = sidebar.querySelectorAll('.collapse.show');
        openMenus.forEach(menu => {
          const collapseInstance = bootstrap.Collapse.getInstance(menu) || new bootstrap.Collapse(menu, { toggle: false });
          collapseInstance.hide();
        });
      }
    }

    // Add event listener to burger button (only for desktop - navbar handles mobile)
    if (burgerBtn && sidebar && mainContent && !isMobile()) {
      burgerBtn.addEventListener('click', () => {
        if (!isMobile()) {
          // Desktop behavior - toggle collapsed state
          handleDesktopSidebar();
        }
      });
    }

    // Handle window resize
    window.addEventListener('resize', () => {
      if (window.innerWidth > 991.98) {
        sidebar.classList.remove('show', 'open');
        sidebar.classList.remove('collapsed');
        mainContent.classList.remove('collapsed');
        linkTexts.forEach(text => {
          text.style.display = 'inline';
        });
      } else {
        sidebar.classList.remove('collapsed');
        mainContent.classList.remove('collapsed');
        linkTexts.forEach(text => {
          text.style.display = 'inline';
        });
      }
    });

    // Comprehensive dropdown toggle fix
    dropdownToggles.forEach(toggle => {
      // Remove any existing event listeners by cloning the element
      const newToggle = toggle.cloneNode(true);
      toggle.parentNode.replaceChild(newToggle, toggle);
      
      // Add comprehensive click handler
      newToggle.addEventListener('click', (e) => {
        e.preventDefault();
        e.stopPropagation();
        
        const targetId = newToggle.getAttribute('data-bs-target');
        const targetElement = document.querySelector(targetId);
        const chevron = newToggle.querySelector('.dropdown-chevron');
        
        if (targetElement && chevron) {
          const isCurrentlyOpen = targetElement.classList.contains('show');
          
          // Close all other dropdowns first
          const allDropdowns = document.querySelectorAll('.collapse');
          allDropdowns.forEach(dropdown => {
            if (dropdown !== targetElement && dropdown.classList.contains('show')) {
              dropdown.classList.remove('show');
              // Update other chevrons
              const otherToggle = document.querySelector(`[data-bs-target="#${dropdown.id}"]`);
              if (otherToggle) {
                const otherChevron = otherToggle.querySelector('.dropdown-chevron');
                if (otherChevron) {
                  otherChevron.style.transform = 'rotate(0deg)';
                  otherToggle.setAttribute('aria-expanded', 'false');
                }
              }
            }
          });
          
          // Toggle current dropdown with smooth animation
          if (isCurrentlyOpen) {
            // Close dropdown
            targetElement.classList.remove('show');
            targetElement.style.height = '0px';
            targetElement.style.overflow = 'hidden';
            chevron.style.transform = 'rotate(0deg)';
            newToggle.setAttribute('aria-expanded', 'false');
          } else {
            // Open dropdown
            targetElement.style.height = 'auto';
            targetElement.style.overflow = 'visible';
            targetElement.classList.add('show');
            chevron.style.transform = 'rotate(90deg)';
            newToggle.setAttribute('aria-expanded', 'true');
          }
        }
      });
      
      // Ensure chevron starts in correct position
      const targetId = newToggle.getAttribute('data-bs-target');
      const targetElement = document.querySelector(targetId);
      const chevron = newToggle.querySelector('.dropdown-chevron');
      
      if (targetElement && chevron) {
        const isOpen = targetElement.classList.contains('show');
        chevron.style.transform = isOpen ? 'rotate(90deg)' : 'rotate(0deg)';
        newToggle.setAttribute('aria-expanded', isOpen);
      }
    });

    // Prevent sub-menu clicks from interfering with dropdown toggle
    const subMenuLinks = document.querySelectorAll('.collapse a');
    subMenuLinks.forEach(link => {
      link.addEventListener('click', (e) => {
        // Don't prevent navigation, but ensure dropdown state is maintained
        e.stopPropagation();
      });
    });

    // Global event listener for all dropdown state changes
    const observer = new MutationObserver((mutations) => {
      mutations.forEach((mutation) => {
        if (mutation.type === 'attributes' && mutation.attributeName === 'class') {
          const target = mutation.target;
          if (target.classList.contains('collapse')) {
            const toggle = document.querySelector(`[data-bs-target="#${target.id}"]`);
            if (toggle) {
              const chevron = toggle.querySelector('.dropdown-chevron');
              if (chevron) {
                const isOpen = target.classList.contains('show');
                chevron.style.transform = isOpen ? 'rotate(90deg)' : 'rotate(0deg)';
              }
            }
          }
        }
      });
    });

    // Observe all collapse elements
    const collapseElements = document.querySelectorAll('.collapse');
    collapseElements.forEach(element => {
      observer.observe(element, { attributes: true, attributeFilter: ['class'] });
    });
  });
</script>

</body>
</html>
