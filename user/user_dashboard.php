<?php
session_start();
require_once '../db_connection.php';

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    header('Location: ../login.php');
    exit();
}

// Get user's reservations
$sql = "SELECT vr.*, fv.article, fv.unit, fv.plate_number, ut.full_name as dispatcher_name
        FROM vehicle_reservations vr 
        LEFT JOIN fleet_vehicles fv ON vr.vehicle_id = fv.id 
        LEFT JOIN user_table ut ON vr.assigned_dispatcher_id = ut.user_id
        WHERE vr.created_by = ? 
        ORDER BY vr.created_at DESC";
$stmt = $conn->prepare($sql);
$stmt->bind_param("i", $_SESSION['user_id']);
$stmt->execute();
$result = $stmt->get_result();
?>

<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0"/>
  <title>User Dashboard | Smart Track</title>
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
      z-index: 2000; /* Keep navbar above any modals/backdrops */
    }
    /* Ensure modals do not cover the fixed navbar */
    .modal { z-index: 1500; }
    .modal-backdrop { z-index: 1400; }
    
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

    /* Modern stat cards */
    .stat-card {
      border: 0;
      color: #fff;
      border-radius: 16px;
      overflow: hidden;
      box-shadow: 0 10px 25px rgba(0,0,0,0.08);
      transition: transform .15s ease, box-shadow .15s ease;
      position: relative;
      isolation: isolate;
      background: linear-gradient(135deg, #6c757d, #343a40);
    }
    .stat-card:hover { transform: translateY(-2px); box-shadow: 0 14px 30px rgba(0,0,0,0.12); }
    .stat-card .card-body { padding: 22px; display: flex; align-items: center; justify-content: space-between; }
    .stat-icon { font-size: 34px; opacity: .9; }
    .stat-content { text-align: right; }
    .stat-value { font-size: 32px; font-weight: 800; line-height: 1; }
    .stat-label { margin: 0; opacity: .95; font-weight: 500; letter-spacing: .3px; }

    .stat-pending { background: linear-gradient(135deg, #f6c667, #f39c12); }
    .stat-assigned { background: linear-gradient(135deg, #21b2d6, #0aa1c9); }
    .stat-completed { background: linear-gradient(135deg, #4a67ff, #2a47e6); }

    /* Mobile Responsive Styles */
    @media (max-width: 991.98px) {
      .sidebar {
        width: 250px;
        transform: translateX(-100%);
        transition: transform 0.3s ease;
      }
      
      .sidebar.show {
        transform: translateX(0);
      }
      
      .main-content {
        margin-left: 0;
        padding: 15px;
      }
      
      .main-content.collapsed {
        margin-left: 0;
      }
      
      .navbar {
        z-index: 2000;
      }
      
      .burger-btn {
        display: block;
      }
      
      /* Mobile header adjustments */
      .d-flex.justify-content-between {
        flex-direction: column;
        gap: 15px;
        align-items: stretch !important;
      }
      
      .d-flex.justify-content-between .btn {
        width: 100%;
        margin-bottom: 10px;
      }
      
      /* Mobile stats cards */
      .col-md-4 {
        margin-bottom: 15px;
      }
      
      .stat-card .card-body {
        padding: 18px;
        flex-direction: column;
        text-align: center;
      }
      
      .stat-content {
        text-align: center;
        margin-top: 10px;
      }
      
      .stat-icon {
        font-size: 28px;
        margin-bottom: 8px;
      }
      
      .stat-value {
        font-size: 24px;
      }
      
      .stat-label {
        font-size: 12px;
      }
    }

    @media (max-width: 575.98px) {
      .main-content {
        padding: 10px;
      }
      
      h2 {
        font-size: 1.5rem;
      }
      
      .stat-card .card-body {
        padding: 15px;
      }
      
      .stat-icon {
        font-size: 24px;
      }
      
      .stat-value {
        font-size: 20px;
      }
      
      /* Mobile table responsive */
      .table-responsive {
        border-radius: 8px;
        overflow-x: auto;
        -webkit-overflow-scrolling: touch;
      }
      
      .table {
        min-width: 600px;
      }
      
      .table th,
      .table td {
        padding: 8px 6px;
        font-size: 12px;
      }
      
      .btn-sm {
        padding: 4px 8px;
        font-size: 11px;
        margin: 2px;
      }
      
      /* Mobile modal adjustments */
      .modal-dialog {
        margin: 10px;
        max-width: calc(100% - 20px);
      }
      
      .modal-body {
        padding: 15px;
      }
      
      .modal-body .row .col-md-6 {
        margin-bottom: 15px;
      }
    }

    @media (max-width: 375px) {
      .stat-card .card-body {
        padding: 12px;
      }
      
      .stat-icon {
        font-size: 20px;
      }
      
      .stat-value {
        font-size: 18px;
      }
      
      .table th,
      .table td {
        padding: 6px 4px;
        font-size: 11px;
      }
      
      .btn-sm {
        padding: 3px 6px;
        font-size: 10px;
      }
    }
  </style>
</head>
<body>

<?php include __DIR__ . '/../pages/user_sidebar.php'; ?>
<?php include __DIR__ . '/../pages/user_navbar.php'; ?>

 <!-- Main Content -->
<div class="main-content" id="mainContent">
  <div class="container-fluid">
                <div class="d-flex justify-content-between align-items-center mb-4">
                    <h2 class="mb-0"><i class="fas fa-gauge"></i> Dashboard</h2>
                    <div class="d-flex gap-2">
                        <a href="vehicle_reservation.php" class="btn btn-primary">
                            <i class="fas fa-plus"></i> New Reservation
                        </a>
                        <a href="my_reservations.php" class="btn btn-outline-secondary">
                            <i class="fas fa-calendar-check"></i> My Reservations
                        </a>
                    </div>
                </div>

                <!-- Quick Stats -->
                <div class="row mb-4">
                    <?php
                    $stats = [
                        'pending' => 0,
                        'assigned' => 0,
                        'completed' => 0,
                        'cancelled' => 0
                    ];
                    
                    $result->data_seek(0); // Reset result pointer
                    while ($row = $result->fetch_assoc()) {
                        if (isset($stats[$row['status']])) {
                            $stats[$row['status']]++;
                        }
                    }
                    ?>
                    
                    <div class="col-md-4">
                      <div class="stat-card stat-pending">
                        <div class="card-body">
                          <div class="stat-icon"><i class="fas fa-hourglass-half"></i></div>
                          <div class="stat-content">
                            <div class="stat-value"><?php echo $stats['pending']; ?></div>
                            <p class="stat-label">Pending</p>
                          </div>
                        </div>
                      </div>
                    </div>
                    <div class="col-md-4">
                      <div class="stat-card stat-assigned">
                        <div class="card-body">
                          <div class="stat-icon"><i class="fas fa-user-tag"></i></div>
                          <div class="stat-content">
                            <div class="stat-value"><?php echo $stats['assigned']; ?></div>
                            <p class="stat-label">Assigned</p>
                          </div>
                        </div>
                      </div>
                    </div>
                    <div class="col-md-4">
                      <div class="stat-card stat-completed">
                        <div class="card-body">
                          <div class="stat-icon"><i class="fas fa-flag-checkered"></i></div>
                          <div class="stat-content">
                            <div class="stat-value"><?php echo $stats['completed']; ?></div>
                            <p class="stat-label">Completed</p>
                          </div>
                        </div>
                      </div>
                    </div>
                </div>

                

                <?php if ($result->num_rows == 0): ?>
                    <div class="card">
                        <div class="card-body text-center py-5">
                            <i class="fas fa-calendar-times fa-3x text-muted mb-3"></i>
                            <h4 class="text-muted">No Reservations Found</h4>
                            <p class="text-muted">You haven't made any vehicle reservations yet.</p>
                            <a href="vehicle_reservation.php" class="btn btn-primary">
                                <i class="fas fa-plus"></i> Make Your First Reservation
                            </a>
                        </div>
                    </div>
                <?php else: ?>
                    <div class="card">
                        <div class="card-body">
                            <div class="table-responsive">
                                <table class="table table-striped table-hover">
                                    <thead class="table-dark">
                                        <tr>
                                            <th>ID</th>
                                            <th>Purpose</th>
                                            <th>Route</th>
                                            <th>Date/Time</th>
                                            <th>Vehicle</th>
                                            <th>Dispatcher</th>
                                            <th>Status</th>
                                            <th>Actions</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php 
                                        $result->data_seek(0); // Reset result pointer
                                        while ($row = $result->fetch_assoc()): 
                                        ?>
                                            <tr>
                                                <td><?php echo $row['id']; ?></td>
                                                <td>
                                                    <div style="max-width: 200px;">
                                                        <?php echo htmlspecialchars(substr($row['purpose'], 0, 50)) . (strlen($row['purpose']) > 50 ? '...' : ''); ?>
                                                    </div>
                                                </td>
                                                <td>
                                                    <small>
                                                        <strong>From:</strong> <?php echo htmlspecialchars($row['origin']); ?><br>
                                                        <strong>To:</strong> <?php echo htmlspecialchars($row['destination']); ?>
                                                    </small>
                                                </td>
                                                <td>
                                                    <small>
                                                        <strong>Start:</strong> <?php echo date('M j, Y g:i A', strtotime($row['start_datetime'])); ?><br>
                                                        <strong>End:</strong> <?php echo date('M j, Y g:i A', strtotime($row['end_datetime'])); ?>
                                                    </small>
                                                </td>
                                                <td>
                                                    <?php if ($row['vehicle_id']): ?>
                                                        <span class="badge bg-info">
                                                            <?php echo htmlspecialchars($row['article'] . ' - ' . $row['plate_number']); ?>
                                                        </span>
                                                    <?php else: ?>
                                                        <span class="text-muted">Not assigned</span>
                                                    <?php endif; ?>
                                                </td>
                                                <td>
                                                    <?php if ($row['dispatcher_name']): ?>
                                                        <span class="badge bg-success">
                                                            <?php echo htmlspecialchars($row['dispatcher_name']); ?>
                                                        </span>
                                                    <?php else: ?>
                                                        <span class="text-muted">Not assigned</span>
                                                    <?php endif; ?>
                                                </td>
                                                <td>
                                                    <?php
                                                    $status_colors = [
                                                        'pending' => 'warning',
                                                        'assigned' => 'info',
                                                        'completed' => 'primary',
                                                        'cancelled' => 'danger'
                                                    ];
                                                    $color = $status_colors[$row['status']] ?? 'secondary';
                                                    ?>
                                                    <span class="badge bg-<?php echo $color; ?>">
                                                        <?php echo ucfirst($row['status']); ?>
                                                    </span>
                                                </td>
                                                <td>
                                                    <button type="button" class="btn btn-info btn-sm" data-bs-toggle="modal" data-bs-target="#detailsModal<?php echo $row['id']; ?>">
                                                        <i class="fas fa-eye"></i> Details
                                                    </button>
                                                    
                                                    <?php if ($row['status'] === 'pending'): ?>
                                                        <button type="button" class="btn btn-danger btn-sm" onclick="cancelReservation(<?php echo $row['id']; ?>)">
                                                            <i class="fas fa-times"></i> Cancel
                                                        </button>
                                                    <?php endif; ?>
                                                </td>
                                            </tr>
                                            
                                            <!-- Details Modal -->
                                            <div class="modal fade" id="detailsModal<?php echo $row['id']; ?>" tabindex="-1">
                                                <div class="modal-dialog modal-lg">
                                                    <div class="modal-content">
                                                        <div class="modal-header">
                                                            <h5 class="modal-title">Reservation Details #<?php echo $row['id']; ?></h5>
                                                            <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                                                        </div>
                                                        <div class="modal-body">
                                                            <div class="row">
                                                                <div class="col-md-6">
                                                                    <h6>Requester Information</h6>
                                                                    <p><strong>Name:</strong> <?php echo htmlspecialchars($row['requester_name']); ?></p>
                                                                    <p><strong>Department:</strong> <?php echo htmlspecialchars($row['department']); ?></p>
                                                                    <p><strong>Contact:</strong> <?php echo htmlspecialchars($row['contact']); ?></p>
                                                                    <p><strong>Passengers:</strong> <?php echo $row['passengers']; ?></p>
                                                                </div>
                                                                <div class="col-md-6">
                                                                    <h6>Trip Details</h6>
                                                                    <p><strong>Purpose:</strong> <?php echo htmlspecialchars($row['purpose']); ?></p>
                                                                    <p><strong>Origin:</strong> <?php echo htmlspecialchars($row['origin']); ?></p>
                                                                    <p><strong>Destination:</strong> <?php echo htmlspecialchars($row['destination']); ?></p>
                                                                    <p><strong>Start:</strong> <?php echo date('M j, Y g:i A', strtotime($row['start_datetime'])); ?></p>
                                                                    <p><strong>End:</strong> <?php echo date('M j, Y g:i A', strtotime($row['end_datetime'])); ?></p>
                                                                </div>
                                                            </div>
                                                            <?php if ($row['notes']): ?>
                                                                <h6>Notes</h6>
                                                                <p><?php echo htmlspecialchars($row['notes']); ?></p>
                                                            <?php endif; ?>
                                                        </div>
                                                    </div>
                                                </div>
                                            </div>
                                        <?php endwhile; ?>
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    </div>
                <?php endif; ?>
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
    // Check if we're on mobile
    const isMobile = window.innerWidth <= 991.98;
    
    if (isMobile) {
      // Mobile behavior - toggle sidebar visibility
      sidebar.classList.toggle('show');
    } else {
      // Desktop behavior - toggle sidebar collapse
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

  // Close sidebar when clicking outside on mobile
  document.addEventListener('click', (e) => {
    const isMobile = window.innerWidth <= 991.98;
    if (isMobile && sidebar.classList.contains('show')) {
      if (!sidebar.contains(e.target) && !burgerBtn.contains(e.target)) {
        sidebar.classList.remove('show');
      }
    }
  });

  // Handle window resize
  window.addEventListener('resize', () => {
    const isMobile = window.innerWidth <= 991.98;
    if (!isMobile) {
      // Reset mobile sidebar state when switching to desktop
      sidebar.classList.remove('show');
    }
  });
</script>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
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
    function viewDetails(reservationId) {
        // Create a modal or redirect to details page
        // For now, show an alert with reservation ID
        alert('Viewing details for reservation ID: ' + reservationId);
        // You can replace this with a modal or redirect to a details page
        // window.location.href = 'reservation_details.php?id=' + reservationId;
    }

    function cancelReservation(reservationId) {
        // Enhanced confirmation with SweetAlert2
        Swal.fire({
            title: 'Cancel Reservation',
            html: `
                <div class="text-center">
                    <i class="fas fa-exclamation-triangle text-warning mb-3" style="font-size: 3rem;"></i>
                    <p class="mb-2">Are you sure you want to cancel this reservation?</p>
                    <p class="text-muted small mb-0">This action cannot be undone.</p>
                </div>
            `,
            showCancelButton: true,
            confirmButtonText: '<i class="fas fa-times me-2"></i>Yes, Cancel',
            cancelButtonText: '<i class="fas fa-arrow-left me-2"></i>Keep Reservation',
            confirmButtonColor: '#dc3545',
            cancelButtonColor: '#6c757d',
            reverseButtons: true,
            focusCancel: true,
            customClass: {
                popup: 'rounded-4 shadow-lg',
                confirmButton: 'btn btn-danger',
                cancelButton: 'btn btn-secondary'
            }
        }).then((result) => {
            if (result.isConfirmed) {
                fetch(`../api/reservation_api.php?id=${reservationId}`, { 
                    method:'DELETE', 
                    headers:{'Content-Type':'application/json'} 
                })
                .then(response => response.json())
                .then(data => { 
                    if(data.error){ 
                        // Show error with SweetAlert2
                        Swal.fire({
                            icon: 'error',
                            title: 'Cancellation Failed',
                            text: data.error,
                            confirmButtonColor: '#dc3545',
                            customClass: {
                                popup: 'rounded-4 shadow-lg'
                            }
                        });
                    } else { 
                        // Show success with SweetAlert2
                        Swal.fire({
                            icon: 'success',
                            title: 'Reservation Cancelled',
                            text: 'Your reservation has been cancelled successfully.',
                            confirmButtonColor: '#198754',
                            customClass: {
                                popup: 'rounded-4 shadow-lg'
                            }
                        }).then(() => {
                            location.reload();
                        });
                    } 
                })
                .catch(error => {
                    console.error('Error:', error);
                    // Show error with SweetAlert2
                    Swal.fire({
                        icon: 'error',
                        title: 'Error',
                        text: 'An error occurred while cancelling the reservation.',
                        confirmButtonColor: '#dc3545',
                        customClass: {
                            popup: 'rounded-4 shadow-lg'
                        }
                    });
                });
            }
        });
    }
</script>
</body>
</html>
