<?php
session_start();
require_once __DIR__ . '/../db_connection.php';

// Only mechanics can access
if (!isset($_SESSION['role']) || strtolower($_SESSION['role']) !== 'mechanic') {
    header("Location: ../index.php");
    exit();
}

$mechanicId = $_SESSION['user_id'];

// Handle status updates
if (isset($_POST['update_status'])) {
    $orderId = intval($_POST['order_id']);
    $newStatus = $_POST['status'];

    // Only allow valid status
    if (in_array($newStatus, ['completed', 'cancelled'])) {
        if ($newStatus === 'completed') {
            // For completed orders, calculate days taken
            $stmt = $conn->prepare("
                UPDATE maintenance_schedules
                SET status = ?, end_time = NOW(), days_taken = DATEDIFF(NOW(), start_time)
                WHERE id = ? AND assigned_mechanic = ?
            ");
        } else {
            // For cancelled orders, just update status
            $stmt = $conn->prepare("
                UPDATE maintenance_schedules
                SET status = ?, end_time = NOW()
                WHERE id = ? AND assigned_mechanic = ?
            ");
        }
        
        $stmt->bind_param("sii", $newStatus, $orderId, $mechanicId);
        
        if ($stmt->execute()) {
            if ($newStatus === 'completed') {
                // Get the calculated days taken
                $daysStmt = $conn->prepare("SELECT days_taken FROM maintenance_schedules WHERE id = ?");
                $daysStmt->bind_param("i", $orderId);
                $daysStmt->execute();
                $daysResult = $daysStmt->get_result();
                $daysData = $daysResult->fetch_assoc();
                $daysTaken = $daysData['days_taken'] ?? 0;
                $daysStmt->close();
                
                $_SESSION['success'] = "Work order completed successfully! Days taken: $daysTaken";
            } else {
                $_SESSION['success'] = "Work order cancelled successfully!";
            }
        } else {
            $_SESSION['error'] = "Failed to update work order status.";
        }
        
        $stmt->close();
        
        // Redirect to prevent form resubmission
        header("Location: assigned-work-orders.php");
        exit();
    }
}

// Fetch assigned work orders
$stmt = $conn->prepare("
    SELECT 
        ms.id,
        ms.maintenance_type AS type,
        ms.status,
        ms.start_time,
        ms.end_time,
        ms.days_taken,
        v.article,
        v.unit,
        v.plate_number,
        u.username AS mechanic_name
    FROM maintenance_schedules ms
    JOIN fleet_vehicles v ON ms.vehicle_id = v.id
    JOIN user_table u ON ms.assigned_mechanic = u.user_id
    WHERE ms.assigned_mechanic = ? 
      AND ms.status IN ('scheduled','in_progress')
    ORDER BY ms.start_time DESC
");
$stmt->bind_param("i", $mechanicId);
$stmt->execute();
$result = $stmt->get_result();

$assignedOrders = [];
while ($row = $result->fetch_assoc()) {
    $assignedOrders[] = $row;
}
$stmt->close();
?>

<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
  <title>Assigned Work Orders</title>
  <link rel="stylesheet" href="../assets/css/style.css">
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
  <style>
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
      min-height: calc(100vh - 60px);
      position: relative;
      z-index: 1;
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

    /* Mobile responsiveness */
    @media (max-width: 768px) {
      .sidebar {
        transform: translateX(-100%);
      }
      
      .sidebar.show {
        transform: translateX(0);
      }
      
      .main-content {
        margin-left: 0;
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
            <?php if (isset($_SESSION['success'])): ?>
                <div class="alert alert-success alert-dismissible fade show">
                    <?= htmlspecialchars($_SESSION['success']) ?>
                    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                </div>
                <?php unset($_SESSION['success']); ?>
            <?php endif; ?>

            <?php if (isset($_SESSION['error'])): ?>
                <div class="alert alert-danger alert-dismissible fade show">
                    <?= htmlspecialchars($_SESSION['error']) ?>
                    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                </div>
                <?php unset($_SESSION['error']); ?>
            <?php endif; ?>

            <div class="card shadow-sm">
                <div class="card-body">
                    <div class="d-flex justify-content-between align-items-center mb-3">
                        <h5 class="card-title text-primary fw-bold">Assigned Work Orders</h5>
                    </div>

                    <?php if (empty($assignedOrders)): ?>
                        <div class="alert alert-info">No work orders are currently assigned.</div>
                    <?php else: ?>
                        <div class="table-responsive">
                            <table class="table table-hover align-middle">
                                <thead class="table-light">
                                    <tr>
                                        <th>ID</th>
                                        <th>Vehicle</th>
                                        <th>Type</th>
                                        <th>Status</th>
                                        <th>Start Date</th>
                                        <th>Actions</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($assignedOrders as $order): ?>
                                        <tr>
                                            <td><?= htmlspecialchars($order['id']) ?></td>
                                            <td>
                                                <strong><?= htmlspecialchars($order['article']) ?></strong>
                                                (<?= htmlspecialchars($order['unit']) ?>) - 
                                                <?= htmlspecialchars($order['plate_number']) ?>
                                            </td>
                                            <td><?= htmlspecialchars($order['type']) ?></td>
                                            <td>
                                                <span class="badge 
                                                    <?= $order['status'] === 'in_progress' ? 'bg-warning text-dark' : 
                                                        ($order['status'] === 'completed' ? 'bg-success' : 
                                                        ($order['status'] === 'cancelled' ? 'bg-danger' : 'bg-secondary')) ?>">
                                                    <?= htmlspecialchars(ucfirst($order['status'])) ?>
                                                </span>
                                            </td>
                                            <td><?= htmlspecialchars($order['start_time']) ?></td>
                                            <td>
                                                <form method="POST" class="d-inline">
                                                    <input type="hidden" name="order_id" value="<?= $order['id'] ?>">
                                                    <input type="hidden" name="status" value="completed">
                                                    <button type="submit" name="update_status" class="btn btn-sm btn-success me-1">
                                                        ✅ Complete
                                                    </button>
                                                </form>
                                                <form method="POST" class="d-inline">
                                                    <input type="hidden" name="order_id" value="<?= $order['id'] ?>">
                                                    <input type="hidden" name="status" value="cancelled">
                                                    <button type="submit" name="update_status" class="btn btn-sm btn-danger">
                                                        ❌ Cancel
                                                    </button>
                                                </form>
                                            </td>
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

  if (burgerBtn) {
    burgerBtn.addEventListener('click', () => {
      // Check if we're on mobile (sidebar is hidden off-screen)
      if (window.innerWidth <= 768) {
        sidebar.classList.toggle('show');
      } else {
        // Desktop behavior - collapse/expand sidebar
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

        // Collapse all sidebar dropdowns when sidebar is collapsed
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
