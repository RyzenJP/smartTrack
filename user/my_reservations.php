<?php
session_start();
if (!isset($_SESSION['user_id']) || ($_SESSION['user_type'] ?? '') !== 'reservation') {
    header('Location: ../login.php');
    exit();
}
require_once __DIR__ . '/../db_connection.php';

// Fetch this user's reservations
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
  <title>My Reservations | Smart Track</title>
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
  <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css" rel="stylesheet">
  <style>
    :root { --primary:#003566; --accent:#00b4d8; --bg:#f8f9fa; }
    body { font-family:'Segoe UI', sans-serif; background-color: var(--bg); }
    .sidebar { position:fixed; top:0; left:0; width:250px; height:100vh; background:#003566; color:#fff; z-index:1000; padding-top:60px; overflow-y:auto; transition:all .3s; }
    .sidebar.collapsed{ width:70px; }
    .sidebar a{ display:block; padding:14px 20px; color:#d9d9d9; text-decoration:none; white-space:nowrap; }
    .sidebar a.active, .sidebar a:hover{ background:#001d3d; color:var(--accent); }
    .main-content{ margin-left:250px; margin-top:60px; padding:20px; transition:margin-left .3s; }
    .main-content.collapsed{ margin-left:70px; }
    .navbar{ position:fixed; top:0; left:0; width:100%; background:#fff; border-bottom:1px solid #dee2e6; z-index:1100; box-shadow:0 2px 5px rgba(0,0,0,.05); }

    .burger-btn {
      font-size: 1.5rem;
      background: none;
      border: none;
      color: var(--primary);
      margin-right: 1rem;
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
    .stat-cancelled { background: linear-gradient(135deg, #e74c3c, #c0392b); }
    .stat-total { background: linear-gradient(135deg, #6c757d, #495057); }

    /* Fit 5 cards better */
    .row.mb-4 .col-md-2 {
      flex: 0 0 20%;
      max-width: 20%;
    }

    /* Modern table styling */
    .table-container {
      background: white;
      border-radius: 16px;
      box-shadow: 0 10px 25px rgba(0,0,0,0.08);
      overflow: hidden;
    }

    .table-header {
      background: linear-gradient(135deg, var(--primary), #001d3d);
      color: white;
      padding: 20px 24px;
      border-radius: 16px 16px 0 0;
    }

    .table-modern {
      margin: 0;
      border: none;
    }

    .table-modern thead th {
      background: #f8f9fa;
      border: none;
      color: var(--primary);
      font-weight: 600;
      padding: 16px 12px;
      font-size: 14px;
      text-transform: uppercase;
      letter-spacing: 0.5px;
    }

    .table-modern tbody td {
      border: none;
      padding: 12px;
      vertical-align: middle;
      border-bottom: 1px solid #f1f3f4;
    }

    /* Fix dispatcher name wrapping */
    .table-modern tbody td:nth-child(6) {
      white-space: nowrap;
      min-width: 120px;
    }

    /* Make table more compact */
    .table-modern th:nth-child(1) { width: 5%; } /* ID */
    .table-modern th:nth-child(2) { width: 12%; } /* Purpose */
    .table-modern th:nth-child(3) { width: 20%; } /* Route */
    .table-modern th:nth-child(4) { width: 18%; } /* Date/Time */
    .table-modern th:nth-child(5) { width: 10%; } /* Vehicle */
    .table-modern th:nth-child(6) { width: 12%; } /* Dispatcher */
    .table-modern th:nth-child(7) { width: 10%; } /* Status */
    .table-modern th:nth-child(8) { width: 13%; } /* Actions */

    .table-modern tbody tr:hover {
      background-color: #f8f9fa;
    }

    .table-modern tbody tr:last-child td {
      border-bottom: none;
    }

    .badge-modern {
      padding: 8px 16px;
      border-radius: 20px;
      font-weight: 600;
      font-size: 12px;
      text-transform: uppercase;
      letter-spacing: 0.5px;
    }

    .btn-modern {
      border-radius: 8px;
      padding: 8px 16px;
      font-weight: 500;
      font-size: 13px;
      transition: all 0.3s ease;
    }

    .btn-modern:hover {
      transform: translateY(-1px);
      box-shadow: 0 4px 12px rgba(0,0,0,0.15);
    }

    /* Actions section styling */
    .d-flex.gap-2.flex-wrap {
      gap: 8px;
      align-items: center;
    }

    .btn-modern.btn-outline-danger {
      border-color: #dc3545;
      color: #dc3545;
    }

    .btn-modern.btn-outline-danger:hover {
      background-color: #dc3545;
      color: white;
      transform: translateY(-1px);
      box-shadow: 0 4px 12px rgba(220, 53, 69, 0.3);
    }

    .text-muted.small {
      font-size: 11px;
      padding: 4px 8px;
      border-radius: 4px;
      background-color: #f8f9fa;
      border: 1px solid #e9ecef;
    }

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
      .col-md-2 {
        margin-bottom: 15px;
        flex: 0 0 50%;
        max-width: 50%;
      }
      
      .stat-card .card-body {
        padding: 15px;
        flex-direction: column;
        text-align: center;
      }
      
      .stat-content {
        text-align: center;
        margin-top: 8px;
      }
      
      .stat-icon {
        font-size: 24px;
        margin-bottom: 6px;
      }
      
      .stat-value {
        font-size: 20px;
      }
      
      .stat-label {
        font-size: 11px;
      }
    }

    @media (max-width: 575.98px) {
      .main-content {
        padding: 10px;
      }
      
      h2 {
        font-size: 1.5rem;
      }
      
      /* Mobile stats - 2 per row */
      .col-md-2 {
        flex: 0 0 50%;
        max-width: 50%;
      }
      
      .stat-card .card-body {
        padding: 12px;
      }
      
      .stat-icon {
        font-size: 20px;
      }
      
      .stat-value {
        font-size: 18px;
      }
      
      /* Mobile table responsive */
      .table-container {
        border-radius: 8px;
        overflow: hidden;
      }
      
      .table-header {
        padding: 15px 16px;
      }
      
      .table-responsive {
        overflow-x: auto;
        -webkit-overflow-scrolling: touch;
      }
      
      .table-modern {
        min-width: 700px;
      }
      
      .table-modern th,
      .table-modern td {
        padding: 8px 6px;
        font-size: 12px;
      }
      
      .btn-modern {
        padding: 6px 12px;
        font-size: 11px;
        margin: 2px;
      }
      
      .badge-modern {
        padding: 6px 12px;
        font-size: 10px;
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
        padding: 10px;
      }
      
      .stat-icon {
        font-size: 18px;
      }
      
      .stat-value {
        font-size: 16px;
      }
      
      .stat-label {
        font-size: 10px;
      }
      
      .table-modern th,
      .table-modern td {
        padding: 6px 4px;
        font-size: 11px;
      }
      
      .btn-modern {
        padding: 4px 8px;
        font-size: 10px;
      }
      
      .badge-modern {
        padding: 4px 8px;
        font-size: 9px;
      }
      
      .table-header {
        padding: 12px 14px;
      }
      
      h5 {
        font-size: 1rem;
      }
    }
  </style>
</head>
<body>

<?php include __DIR__ . '/../pages/user_sidebar.php'; ?>
<?php include __DIR__ . '/../pages/user_navbar.php'; ?>

<div class="main-content" id="mainContent">
  <div class="container-fluid">
    <div class="d-flex justify-content-between align-items-center mb-4">
      <h2><i class="fas fa-calendar-check"></i> My Reservations</h2>
      <a href="vehicle_reservation.php" class="btn btn-primary"><i class="fas fa-plus me-1"></i>New Reservation</a>
    </div>

    <!-- Stats Cards -->
    <div class="row mb-4">
      <?php
        $stats = ['pending'=>0,'assigned'=>0,'completed'=>0,'cancelled'=>0];
        $rows = $result->fetch_all(MYSQLI_ASSOC);
        foreach ($rows as $row) { if (isset($stats[$row['status']])) { $stats[$row['status']]++; } }
      ?>
      <div class="col-md-2">
        <div class="stat-card stat-pending">
          <div class="card-body">
            <div class="stat-icon"><i class="fas fa-hourglass-half"></i></div>
            <div class="stat-content">
              <div class="stat-value"><?= $stats['pending'] ?></div>
              <p class="stat-label">Pending</p>
            </div>
          </div>
        </div>
      </div>
      <div class="col-md-2">
        <div class="stat-card stat-assigned">
          <div class="card-body">
            <div class="stat-icon"><i class="fas fa-user-tag"></i></div>
            <div class="stat-content">
              <div class="stat-value"><?= $stats['assigned'] ?></div>
              <p class="stat-label">Assigned</p>
            </div>
          </div>
        </div>
      </div>
      <div class="col-md-2">
        <div class="stat-card stat-completed">
          <div class="card-body">
            <div class="stat-icon"><i class="fas fa-flag-checkered"></i></div>
            <div class="stat-content">
              <div class="stat-value"><?= $stats['completed'] ?></div>
              <p class="stat-label">Completed</p>
            </div>
          </div>
        </div>
      </div>
      <div class="col-md-2">
        <div class="stat-card stat-cancelled">
          <div class="card-body">
            <div class="stat-icon"><i class="fas fa-times-circle"></i></div>
            <div class="stat-content">
              <div class="stat-value"><?= $stats['cancelled'] ?></div>
              <p class="stat-label">Cancelled</p>
            </div>
          </div>
        </div>
      </div>
      <div class="col-md-2">
        <div class="stat-card stat-total">
          <div class="card-body">
            <div class="stat-icon"><i class="fas fa-chart-bar"></i></div>
            <div class="stat-content">
              <div class="stat-value"><?= array_sum($stats) ?></div>
              <p class="stat-label">Total</p>
            </div>
          </div>
        </div>
      </div>
    </div>

    <!-- Reservations Table -->
    <div class="table-container">
      <div class="table-header">
        <h5 class="mb-0"><i class="fas fa-list me-2"></i>Reservation Details</h5>
        <p class="mb-0 mt-1 opacity-75">Manage your vehicle reservations</p>
      </div>
      <div class="table-responsive">
        <table class="table table-modern">
          <thead>
            <tr>
              <th>ID</th><th>Purpose</th><th>Route</th><th>Date/Time</th><th>Vehicle</th><th>Dispatcher</th><th>Status</th><th>Actions</th>
            </tr>
          </thead>
            <tbody>
              <?php if (count($rows) === 0): ?>
                <tr><td colspan="8" class="text-center text-muted py-4">No reservations yet.</td></tr>
              <?php else: foreach ($rows as $row): ?>
                <tr>
                  <td><?= (int)$row['id'] ?></td>
                  <td><?= htmlspecialchars($row['purpose']) ?></td>
                  <td>
                    <div><small class="text-muted">From:</small> <?= htmlspecialchars($row['origin'] ?? '') ?></div>
                    <div><small class="text-muted">To:</small> <?= htmlspecialchars($row['destination'] ?? '') ?></div>
                  </td>
                  <td>
                    <div><small class="text-muted">Start:</small> <?= date('M j, Y g:i A', strtotime($row['start_datetime'])) ?></div>
                    <div><small class="text-muted">End:</small> <?= $row['end_datetime'] ? date('M j, Y g:i A', strtotime($row['end_datetime'])) : '—' ?></div>
                  </td>
                  <td>
                    <?php if ($row['plate_number']): ?>
                      <span class="badge-modern bg-info"><?= htmlspecialchars($row['plate_number']) ?></span>
                    <?php else: ?>
                      <span class="text-muted">Not assigned</span>
                    <?php endif; ?>
                  </td>
                  <td>
                    <?php if ($row['dispatcher_name']): ?>
                      <span class="badge-modern bg-success"><?= htmlspecialchars($row['dispatcher_name']) ?></span>
                    <?php else: ?>
                      <span class="text-muted">—</span>
                    <?php endif; ?>
                  </td>
                  <td>
                    <?php
                      $status = strtolower($row['status']);
                      $badgeClass = [ 'pending'=>'bg-warning','assigned'=>'bg-info','completed'=>'bg-primary','cancelled'=>'bg-danger' ][$status] ?? 'bg-secondary';
                    ?>
                    <span class="badge-modern <?= $badgeClass ?> text-uppercase"><?= htmlspecialchars($row['status']) ?></span>
                  </td>
                  <td>
                    <div class="d-flex gap-2 flex-wrap">
                      <button class="btn btn-modern btn-outline-primary"
                              onclick="viewDetails(this)"
                              data-id="<?= (int)$row['id'] ?>"
                              data-requester="<?= htmlspecialchars($row['requester_name'] ?? '') ?>"
                              data-department="<?= htmlspecialchars($row['department'] ?? '') ?>"
                              data-contact="<?= htmlspecialchars($row['contact'] ?? '') ?>"
                              data-passengers="<?= htmlspecialchars((string)($row['passengers'] ?? '')) ?>"
                              data-purpose="<?= htmlspecialchars($row['purpose'] ?? '') ?>"
                              data-origin="<?= htmlspecialchars($row['origin'] ?? '') ?>"
                              data-destination="<?= htmlspecialchars($row['destination'] ?? '') ?>"
                              data-start="<?= htmlspecialchars($row['start_datetime'] ?? '') ?>"
                              data-end="<?= htmlspecialchars($row['end_datetime'] ?? '') ?>">
                        <i class="fas fa-eye me-1"></i> Details
                      </button>
                      <?php 
                      $canCancel = in_array($status, ['pending']);
                      if ($canCancel): 
                      ?>
                        <button class="btn btn-modern btn-outline-danger" 
                                onclick="cancelReservation(<?= (int)$row['id'] ?>)"
                                title="Cancel this reservation">
                          <i class="fas fa-times me-1"></i> Cancel
                        </button>
                      <?php else: ?>
                        <span class="text-muted small">
                          <i class="fas fa-lock me-1"></i>Cannot cancel
                        </span>
                      <?php endif; ?>
                    </div>
                  </td>
                </tr>
              <?php endforeach; endif; ?>
            </tbody>
          </table>
        </div>
      </div>
    </div>
  </div>
</div>

<!-- Details Modal -->
<div class="modal fade" id="reservationDetailsModal" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog modal-lg modal-dialog-centered">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title"><i class="fas fa-file-alt me-2"></i>Reservation Details <span id="mdResId" class="text-muted"></span></h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
      </div>
      <div class="modal-body">
        <div class="row g-4">
          <div class="col-md-6">
            <h6 class="text-muted">Requester Information</h6>
            <div><strong>Name:</strong> <span id="mdRequester">—</span></div>
            <div><strong>Department:</strong> <span id="mdDepartment">—</span></div>
            <div><strong>Contact:</strong> <span id="mdContact">—</span></div>
            <div><strong>Passengers:</strong> <span id="mdPassengers">—</span></div>
          </div>
          <div class="col-md-6">
            <h6 class="text-muted">Trip Details</h6>
            <div><strong>Purpose:</strong> <span id="mdPurpose">—</span></div>
            <div><strong>Origin:</strong> <span id="mdOrigin">—</span></div>
            <div><strong>Destination:</strong> <span id="mdDestination">—</span></div>
            <div><strong>Start:</strong> <span id="mdStart">—</span></div>
            <div><strong>End:</strong> <span id="mdEnd">—</span></div>
          </div>
        </div>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal"><i class="fas fa-times me-1"></i>Close</button>
      </div>
    </div>
  </div>
 </div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<script>
  const burgerBtn = document.getElementById('burgerBtn');
  const sidebar = document.getElementById('sidebar');
  const mainContent = document.getElementById('mainContent');
  const linkTexts = document.querySelectorAll('.link-text');
  burgerBtn.addEventListener('click', () => {
    // Check if we're on mobile
    const isMobile = window.innerWidth <= 991.98;
    
    if (isMobile) {
      // Mobile behavior - toggle sidebar visibility
      sidebar.classList.toggle('show');
    } else {
      // Desktop behavior - toggle sidebar collapse
      const collapsed = sidebar.classList.toggle('collapsed');
      mainContent.classList.toggle('collapsed');
      linkTexts.forEach(t => t.style.display = collapsed ? 'none' : 'inline');
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

  function viewDetails(buttonEl) {
    const b = buttonEl.dataset;
    const fmt = (dt) => {
      if (!dt) return '—';
      const d = new Date(dt.replace(' ', 'T'));
      if (isNaN(d.getTime())) return dt; // fallback raw
      return d.toLocaleString(undefined, { month:'short', day:'2-digit', year:'numeric', hour:'numeric', minute:'2-digit' });
    };

    document.getElementById('mdResId').textContent = `#${b.id || ''}`;
    document.getElementById('mdRequester').textContent = b.requester || '—';
    document.getElementById('mdDepartment').textContent = b.department || '—';
    document.getElementById('mdContact').textContent = b.contact || '—';
    document.getElementById('mdPassengers').textContent = b.passengers || '—';

    document.getElementById('mdPurpose').textContent = b.purpose || '—';
    document.getElementById('mdOrigin').textContent = b.origin || '—';
    document.getElementById('mdDestination').textContent = b.destination || '—';
    document.getElementById('mdStart').textContent = fmt(b.start);
    document.getElementById('mdEnd').textContent = fmt(b.end);

    const modal = new bootstrap.Modal(document.getElementById('reservationDetailsModal'));
    modal.show();
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
        // Show loading state
        const cancelBtn = event.target.closest('button');
        const originalText = cancelBtn.innerHTML;
        cancelBtn.innerHTML = '<i class="fas fa-spinner fa-spin me-1"></i> Cancelling...';
        cancelBtn.disabled = true;
        
        // Proceed with cancellation
        proceedWithCancellation(reservationId, cancelBtn, originalText);
      }
    });
  }
  
  function proceedWithCancellation(reservationId, cancelBtn, originalText) {
    
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
        // Restore button state
        cancelBtn.innerHTML = originalText;
        cancelBtn.disabled = false;
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
      // Restore button state
      cancelBtn.innerHTML = originalText;
      cancelBtn.disabled = false;
    });
  }
</script>
</body>
</html>


