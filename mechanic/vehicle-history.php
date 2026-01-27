<?php
session_start();

// ✅ Check if session role is set and user is a mechanic
if (!isset($_SESSION['role']) || strtolower($_SESSION['role']) !== 'mechanic') {
    header("Location: ../index.php");
    exit();
}

require_once __DIR__ . '/../db_connection.php';

// Fetch all vehicles - use prepared statement for consistency
$vehicles_stmt = $conn->prepare("SELECT id, article, plate_number, unit FROM fleet_vehicles ORDER BY article ASC");
$vehicles_stmt->execute();
$vehiclesResult = $vehicles_stmt->get_result();

// Get current logged-in mechanic ID
$currentMechanicId = $_SESSION['user_id'];

// Handle filters
$selectedVehicle = $_GET['vehicle_id'] ?? '';
$selectedStatus = $_GET['status'] ?? '';

$whereConditions = [];
$params = [];
$paramTypes = '';

// Always filter by current mechanic
$whereConditions[] = "ms.assigned_mechanic = ?";
$params[] = $currentMechanicId;
$paramTypes .= 'i';

if ($selectedVehicle) {
    $selectedVehicle = intval($selectedVehicle);
    $whereConditions[] = "ms.vehicle_id = ?";
    $params[] = $selectedVehicle;
    $paramTypes .= 'i';
}

if ($selectedStatus) {
    $whereConditions[] = "ms.status = ?";
    $params[] = $selectedStatus;
    $paramTypes .= 's';
}

$whereClause = !empty($whereConditions) ? 'WHERE ' . implode(' AND ', $whereConditions) : '';

// Fetch maintenance history with prepared statement
$historyQuery = "
    SELECT ms.id, fv.article, fv.unit, fv.plate_number, ms.maintenance_type, ms.notes, 
           ms.scheduled_date, ms.start_time, ms.end_time, ms.status, u.username AS mechanic
    FROM maintenance_schedules ms
    JOIN fleet_vehicles fv ON ms.vehicle_id = fv.id
    LEFT JOIN user_table u ON ms.assigned_mechanic = u.user_id
    $whereClause
    ORDER BY ms.scheduled_date DESC
";

if (!empty($params)) {
    $stmt = $conn->prepare($historyQuery);
    if ($stmt) {
        $stmt->bind_param($paramTypes, ...$params);
        $stmt->execute();
        $historyResult = $stmt->get_result();
    } else {
        // Fallback: use prepared statement even without params
        $fallback_stmt = $conn->prepare($historyQuery);
        $fallback_stmt->execute();
        $historyResult = $fallback_stmt->get_result();
    }
} else {
    // Use prepared statement for consistency even when no params
    $stmt = $conn->prepare($historyQuery);
    $stmt->execute();
    $historyResult = $stmt->get_result();
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
  <title>My Maintenance History | Smart Track</title>
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
  <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css" rel="stylesheet">
  <style>
    :root {
      --primary: #003566;
      --accent: #00b4d8;
      --bg: #f8f9fa;
    }

    /* Force mobile-first approach */
    * {
      box-sizing: border-box;
    }

    body {
      font-family: 'Segoe UI', sans-serif;
      background-color: var(--bg);
      margin: 0;
      padding: 0;
      overflow-x: hidden;
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
      min-height: calc(100vh - 60px);
      overflow-y: auto;
      max-height: calc(100vh - 60px);
    }

    .main-content.collapsed {
      margin-left: 70px;
    }

    /* Custom scrollbar for main content */
    .main-content::-webkit-scrollbar {
      width: 8px;
    }

    .main-content::-webkit-scrollbar-track {
      background: #f1f1f1;
      border-radius: 4px;
    }

    .main-content::-webkit-scrollbar-thumb {
      background: #c1c1c1;
      border-radius: 4px;
    }

    .main-content::-webkit-scrollbar-thumb:hover {
      background: #a8a8a8;
    }

    /* Table scrollbar styling */
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

    /* Filter section styling */
    .form-label {
      font-weight: 600;
      color: var(--primary);
      margin-bottom: 0.5rem;
    }

    .form-select:focus {
      border-color: var(--accent);
      box-shadow: 0 0 0 0.2rem rgba(0, 180, 216, 0.25);
    }

    .btn-primary {
      background-color: var(--primary);
      border-color: var(--primary);
    }

    .btn-primary:hover {
      background-color: var(--accent);
      border-color: var(--accent);
    }

    /* Fix dropdown toggle layout - position chevron next to text */
    .sidebar .dropdown-toggle {
      justify-content: flex-start !important;
      align-items: center !important;
      gap: 8px;
    }

    .sidebar .dropdown-toggle > div {
      display: flex;
      align-items: center;
    }

    .sidebar .dropdown-toggle .link-text {
      margin-left: 12px;
    }

    .sidebar .dropdown-chevron {
      margin-left: auto;
      flex-shrink: 0;
    }

    /* Mobile responsiveness */
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
      .container-fluid {
        padding-left: 0 !important;
        padding-right: 0 !important;
        max-width: 100% !important;
      }
      .row.g-4 > * {
        margin-bottom: 1rem !important;
        width: 100% !important;
        flex: 0 0 100% !important;
        max-width: 100% !important;
      }
      .col-lg-3, .col-lg-6, .col-lg-8, .col-lg-4 {
        margin-bottom: 1.5rem !important;
        width: 100% !important;
        flex: 0 0 100% !important;
        max-width: 100% !important;
      }
      .sidebar .collapse { transition: height 0.3s ease !important; }
      .sidebar .collapse.show { display: block !important; }
      .sidebar .dropdown-chevron { transition: transform 0.3s ease !important; }
      .sidebar .dropdown-chevron.rotated { transform: rotate(90deg) !important; }
    }
    @media (max-width: 575.98px) {
      .main-content { 
        padding: 12px !important; 
        margin-top: 60px !important;
      }
      .d-flex.justify-content-between {
        flex-direction: column !important;
        align-items: flex-start !important;
        gap: 0.5rem !important;
      }
      .row {
        margin-left: 0 !important;
        margin-right: 0 !important;
      }
      .col-lg-3, .col-lg-6, .col-lg-8, .col-lg-4,
      .col-md-3, .col-md-6, .col-md-8, .col-md-4 {
        padding-left: 0 !important;
        padding-right: 0 !important;
      }
      .card {
        width: 100% !important;
        margin-bottom: 1rem !important;
      }
      .navbar {
        padding-left: 15px !important;
        padding-right: 15px !important;
      }
      .table-responsive { font-size: 0.8rem; }
      .table th, .table td {
        padding: 8px 4px;
        font-size: 0.75rem;
      }
      .btn {
        font-size: 0.8rem !important;
        padding: 6px 12px !important;
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
    .badge-status {
        font-size:0.8rem;
        padding: 3px 6px;
    }
    .bg-pending { background-color: #6c757d; }
    .bg-in_progress { background-color: #ffc107; color:#212529; }
    .bg-completed { background-color: #198754; }
    .bg-cancelled { background-color: #dc3545; }
  </style>
</head>
<body>

<?php include __DIR__ . '/../pages/mechanic_sidebar.php'; ?>
<?php include __DIR__ . '/../pages/mechanic_navbar.php'; ?>

<div class="main-content" id="mainContent">
  <div class="container-fluid">
    <h3 class="mb-4">My Maintenance History</h3>

    <form method="GET" class="mb-3">
        <div class="row g-3 align-items-end">
            <!-- Vehicle Filter -->
            <div class="col-md-4">
                <label for="vehicleSelect" class="form-label">Filter by Vehicle:</label>
                <select id="vehicleSelect" name="vehicle_id" class="form-select">
                    <option value="">-- All Vehicles --</option>
                    <?php 
                    // Reset the result pointer for vehicles
                    $vehiclesResult->data_seek(0);
                    while($v = $vehiclesResult->fetch_assoc()): ?>
                        <option value="<?= $v['id'] ?>" <?= $selectedVehicle==$v['id'] ? 'selected':'' ?>>
                            <?= htmlspecialchars($v['article'].' ('.$v['plate_number'].')') ?>
                        </option>
                    <?php endwhile; ?>
                </select>
            </div>

            <!-- Status Filter -->
            <div class="col-md-3">
                <label for="statusSelect" class="form-label">Filter by Status:</label>
                <select id="statusSelect" name="status" class="form-select">
                    <option value="">-- All Status --</option>
                    <option value="pending" <?= $selectedStatus=='pending' ? 'selected':'' ?>>Pending</option>
                    <option value="in_progress" <?= $selectedStatus=='in_progress' ? 'selected':'' ?>>In Progress</option>
                    <option value="completed" <?= $selectedStatus=='completed' ? 'selected':'' ?>>Completed</option>
                    <option value="cancelled" <?= $selectedStatus=='cancelled' ? 'selected':'' ?>>Cancelled</option>
                </select>
            </div>

            <!-- Filter Buttons -->
            <div class="col-md-5">
                <div class="d-flex gap-2">
                    <button type="submit" class="btn btn-primary">
                        <i class="fas fa-filter me-1"></i>Apply Filters
                    </button>
                    <a href="vehicle-history.php" class="btn btn-outline-secondary">
                        <i class="fas fa-times me-1"></i>Clear All
                    </a>
                </div>
            </div>
        </div>
    </form>

    <?php if ($historyResult->num_rows == 0): ?>
        <div class="alert alert-info">No maintenance history found.</div>
    <?php else: ?>
        <!-- Results Counter -->
        <div class="mb-2">
            <small class="text-muted">
                Showing <?= $historyResult->num_rows ?> maintenance record(s)
            </small>
        </div>

        <!-- Scrollable Table Container -->
        <div class="table-responsive" style="max-height: 60vh; overflow-y: auto; border: 1px solid #dee2e6; border-radius: 0.375rem;">
            <table class="table table-hover align-middle mb-0">
                <thead class="table-light sticky-top">
                    <tr>
                        <th>ID</th>
                        <th>Vehicle</th>
                        <th>Type</th>
                        <th>Notes</th>
                        <th>Scheduled</th>
                        <th>Start</th>
                        <th>End</th>
                        <th>Mechanic</th>
                        <th>Status</th>
                    </tr>
                </thead>
                <tbody>
                    <?php while($row = $historyResult->fetch_assoc()): ?>
                        <tr>
                            <td><?= $row['id'] ?></td>
                            <td><?= htmlspecialchars($row['article'].' ('.$row['plate_number'].')') ?></td>
                            <td><?= htmlspecialchars(ucwords(str_replace('_',' ',$row['maintenance_type']))) ?></td>
                            <td><?= htmlspecialchars($row['notes']) ?></td>
                            <td><?= $row['scheduled_date'] ? date('M j, Y', strtotime($row['scheduled_date'])) : '-' ?></td>
                            <td><?= $row['start_time'] ? date('M j, Y H:i', strtotime($row['start_time'])) : '-' ?></td>
                            <td><?= $row['end_time'] ? date('M j, Y H:i', strtotime($row['end_time'])) : '-' ?></td>
                            <td><?= htmlspecialchars($row['mechanic'] ?? 'Unassigned') ?></td>
                            <td>
                                <span class="badge badge-status bg-<?= str_replace('_', '', $row['status']) ?>">
                                    <?= ucfirst(str_replace('_', ' ', $row['status'])) ?>
                                </span>
                            </td>
                        </tr>
                    <?php endwhile; ?>
                </tbody>
            </table>
        </div>
    <?php endif; ?>
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

    // Initialize chevron rotation based on current state
    dropdownToggles.forEach(toggle => {
      const targetId = toggle.getAttribute('data-bs-target');
      const targetElement = document.querySelector(targetId);
      const chevron = toggle.querySelector('.dropdown-chevron');
      
      if (targetElement && chevron) {
        const isOpen = targetElement.classList.contains('show');
        chevron.style.transform = isOpen ? 'rotate(90deg)' : 'rotate(0deg)';
      }
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

    // Handle mobile dropdown behavior
    dropdownToggles.forEach(toggle => {
      toggle.addEventListener('click', (e) => {
        if (isMobile()) {
          // On mobile, prevent default Bootstrap behavior and handle manually
          e.preventDefault();
          const targetId = toggle.getAttribute('data-bs-target');
          const targetElement = document.querySelector(targetId);
          
          if (targetElement) {
            const isCollapsed = targetElement.classList.contains('show');
            
            // Close all other dropdowns first
            const allDropdowns = document.querySelectorAll('.collapse');
            allDropdowns.forEach(dropdown => {
              if (dropdown !== targetElement) {
                dropdown.classList.remove('show');
              }
            });
            
            // Toggle current dropdown
            if (isCollapsed) {
              targetElement.classList.remove('show');
            } else {
              targetElement.classList.add('show');
            }
            
            // Update aria-expanded
            toggle.setAttribute('aria-expanded', !isCollapsed);
            
            // Rotate chevron
            const chevron = toggle.querySelector('.dropdown-chevron');
            if (chevron) {
              chevron.style.transform = !isCollapsed ? 'rotate(90deg)' : 'rotate(0deg)';
            }
          }
        }
      });
    });
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
</script>

</body>
</html>
