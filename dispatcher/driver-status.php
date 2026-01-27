<?php
session_start();
if (!isset($_SESSION['role']) || strtolower($_SESSION['role']) !== 'dispatcher') {
    header("Location: ../index.php");
    exit;
}
require_once __DIR__ . '/../db_connection.php';

// Real-time driver status queries - use prepared statements for consistency
$availableDrivers_stmt = $conn->prepare("SELECT COUNT(*)
                                  FROM user_table u
                                  WHERE u.role = 'Driver' AND u.status = 'Active'
                                  AND (
                                    NOT EXISTS (
                                      SELECT 1 FROM vehicle_assignments a
                                      WHERE a.driver_id = u.user_id AND a.status = 'active'
                                    )
                                    OR EXISTS (
                                      SELECT 1 FROM vehicle_assignments a
                                      WHERE a.driver_id = u.user_id AND a.status = 'available'
                                    )
                                  )");
$availableDrivers_stmt->execute();
$availableDrivers = $availableDrivers_stmt->get_result()->fetch_row()[0];
$availableDrivers_stmt->close();

// Drivers currently assigned to vehicles (On Duty)
$onDutyDrivers_stmt = $conn->prepare("
    SELECT COUNT(DISTINCT u.user_id) 
    FROM user_table u 
    INNER JOIN vehicle_assignments va ON u.user_id = va.driver_id 
    WHERE u.role = 'Driver' AND u.status = 'Active' AND va.status = 'active'
");
$onDutyDrivers_stmt->execute();
$onDutyDrivers = $onDutyDrivers_stmt->get_result()->fetch_row()[0];
$onDutyDrivers_stmt->close();

// Drivers with recent GPS activity (actively driving)
$activeDrivers_stmt = $conn->prepare("
    SELECT COUNT(DISTINCT u.user_id) 
    FROM user_table u 
    INNER JOIN vehicle_assignments va ON u.user_id = va.driver_id 
    INNER JOIN fleet_vehicles v ON va.vehicle_id = v.id 
    INNER JOIN gps_devices gd ON v.id = gd.vehicle_id 
    WHERE u.role = 'Driver' AND u.status = 'Active' 
    AND va.status = 'active' 
    AND gd.last_update > NOW() - INTERVAL 1 HOUR
");
$activeDrivers_stmt->execute();
$activeDrivers = $activeDrivers_stmt->get_result()->fetch_row()[0];
$activeDrivers_stmt->close();

// Drivers without vehicle assignments (Available but not on duty)
$availableButNotOnDuty = $availableDrivers - $onDutyDrivers;
?>

<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0"/>
  <title>Dispatcher Dashboard | Smart Track</title>
  <link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css" />
  <script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js"></script>
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
  <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css" rel="stylesheet">
  <style>
    :root { --primary: #003566; --accent: #00b4d8; --bg: #f8f9fa; }
    body { font-family: 'Segoe UI', sans-serif; background-color: var(--bg); }
    .sidebar { position: fixed; top:0; left:0; width:250px; height:100vh; background-color: var(--primary); color:#fff; padding-top:60px; overflow-y:auto; transition: all 0.3s ease; z-index:1000; }
    .sidebar.collapsed { width:70px; }
    .sidebar a { display:block; padding:14px 20px; color:#d9d9d9; text-decoration:none; transition: background 0.2s; white-space:nowrap; }
    .sidebar a:hover, .sidebar a.active { background-color:#001d3d; color: var(--accent); }
    .sidebar a.active i { color: var(--accent) !important; }
    .sidebar .collapse a { color:#d9d9d9; font-size:0.95rem; padding:10px 16px; margin:4px 8px; border-radius:0.35rem; display:block; text-decoration:none; transition: background 0.2s, color 0.2s; }
    .sidebar .collapse a:hover { background-color:#002855; color: var(--accent); }
    .dropdown-chevron { color:#fff; transition: transform 0.3s ease, color 0.2s ease; }
    .dropdown-chevron:hover { color: var(--accent); }
    .dropdown-toggle[aria-expanded="true"] .dropdown-chevron { transform: rotate(90deg); }
    .dropdown-toggle::after { display:none; }
    .main-content { margin-left:250px; margin-top:10px; padding:20px; transition: margin-left 0.3s ease; }
    .main-content.collapsed { margin-left:70px; }
    .navbar { position: fixed; top:0; left:0; width:100%; background-color:#fff; box-shadow:0 2px 5px rgba(0,0,0,0.05); border-bottom:1px solid #dee2e6; z-index:1100; }
    .dropdown-menu { border-radius:0.5rem; border:none; box-shadow:0 8px 25px rgba(0,0,0,0.08); min-width:190px; padding:0.4rem 0; background-color:#fff; animation: fadeIn 0.25s ease-in-out; }
    @keyframes fadeIn { from { opacity:0; transform:translateY(5px); } to { opacity:1; transform:translateY(0); } }
    .dropdown-menu .dropdown-item { display:flex; align-items:center; padding:10px 16px; font-size:0.95rem; color:#343a40; transition: all 0.3s ease; border-radius:0.35rem; }
    .dropdown-menu .dropdown-item:hover { background-color:#001d3d; color: var(--accent); box-shadow: inset 2px 0 0 var(--accent); }
    .dropdown-menu .dropdown-item i { margin-right:10px; color:#6c757d; transition: color 0.3s ease; }
    .dropdown-menu .dropdown-item:hover i { color: var(--accent); }
    .burger-btn { font-size:1.5rem; background:none; border:none; color: var(--primary); margin-right:1rem; }
         .modal-content { border-radius:0.5rem; border:none; box-shadow:0 5px 15px rgba(0,0,0,0.1); }
     .modal-header { background-color: var(--primary); color:white; border-bottom:none; }
     .modal-footer { border-top:none; }
     .alert-item { transition: all 0.3s; border-left:4px solid var(--accent); }
     
     /* Fix modal z-index to appear above navbar */
     .modal { z-index: 1300 !important; }
     .modal-backdrop { z-index: 1299 !important; }
  </style>
</head>
<body>

<?php include __DIR__ . '/../pages/dispatcher_sidebar.php'; ?>
<?php include __DIR__ . '/../pages/dispatcher_navbar.php'; ?>

<div class="main-content" id="mainContent">
  <div class="container-fluid py-4">
    <div class="row mt-4">
      <div class="col-12">
        <div class="card shadow-sm">
          <div class="card-body">
            <h5 class="card-title text-primary fw-bold mb-3">Driver Status</h5>
            <div class="row">
              <!-- Status Summary Cards -->
              <div class="col-md-3 mb-3">
                <div class="card bg-primary text-white">
                  <div class="card-body">
                    <h6 class="card-subtitle mb-2">Total Drivers</h6>
                    <h4 class="card-title"><?= $availableDrivers ?></h4>
                    <small class="text-light">All active drivers</small>
                  </div>
                </div>
              </div>
              <div class="col-md-3 mb-3">
                <div class="card bg-success text-white">
                  <div class="card-body">
                    <h6 class="card-subtitle mb-2">On Duty</h6>
                    <h4 class="card-title"><?= $onDutyDrivers ?></h4>
                    <small class="text-light">Assigned to vehicles</small>
                  </div>
                </div>
              </div>
              <div class="col-md-3 mb-3">
                <div class="card bg-warning text-dark">
                  <div class="card-body">
                    <h6 class="card-subtitle mb-2">Active</h6>
                    <h4 class="card-title"><?= $activeDrivers ?></h4>
                    <small class="text-muted">Recent GPS activity</small>
                  </div>
                </div>
              </div>
              <div class="col-md-3 mb-3">
                <div class="card bg-info text-white">
                  <div class="card-body">
                    <h6 class="card-subtitle mb-2">Available</h6>
                    <h4 class="card-title"><?= $availableButNotOnDuty ?></h4>
                    <small class="text-light">Not assigned to vehicles</small>
                  </div>
                </div>
              </div>
            </div>

            <div class="table-responsive">
  <table class="table table-hover align-middle">
    <thead class="table-light">
      <tr>
        <th>Driver ID</th>
        <th>Driver Name</th>
        <th>Status</th>
        <th>Current Vehicle</th>
        
        <th class="text-center">Actions</th>
      </tr>
    </thead>
    <tbody>
      <?php
      $driversQuery = "
      SELECT u.user_id, u.full_name, u.status AS driver_status, u.phone,
             v.article AS vehicle_name, v.plate_number,
             -- Compute status to match cards: Active (recent GPS), On Duty (assigned), Available (no assignment or available status)
             CASE 
               WHEN v.id IS NOT NULL AND glh.recorded_at IS NOT NULL AND glh.recorded_at > (NOW() - INTERVAL 1 HOUR) THEN 'Active'
               WHEN v.id IS NOT NULL AND va.status = 'active' THEN 'On Duty'
               WHEN v.id IS NOT NULL AND va.status = 'available' THEN 'Available'
               ELSE 'Available'
             END AS status_label,
             glh.latitude, glh.longitude, glh.recorded_at
      FROM user_table u
      LEFT JOIN vehicle_assignments va ON va.driver_id = u.user_id AND (va.status='active' OR va.status='available')
      LEFT JOIN fleet_vehicles v ON v.id = va.vehicle_id
      LEFT JOIN fleet_location_history glh ON glh.vehicle_id = v.id
      AND glh.recorded_at = (
          SELECT MAX(recorded_at) 
          FROM fleet_location_history 
          WHERE vehicle_id = v.id
      )
      WHERE u.role='Driver'
      ORDER BY u.full_name ASC
      ";

      // Use prepared statement for consistency (static query but best practice)
      $stmt = $conn->prepare($driversQuery);
      $stmt->execute();
      $result = $stmt->get_result();
      if($result && $result->num_rows > 0) {
          while($row = $result->fetch_assoc()) {
              // Determine status label: On Duty if assigned to a vehicle, otherwise Available
              $status = $row['status_label'] ?? $row["driver_status"];    
              // Match colors to cards: Active -> info (cyan), On Duty -> success (green), Available -> warning (yellow)
              $badgeClass = match(strtolower($status)) {
                  'active' => 'info',
                  'on duty' => 'success',
                  'available' => 'info',
                  'on break' => 'warning',
                  'unavailable' => 'danger',
                  default => 'secondary'
              };

              $vehicle = $row['vehicle_name'] ? "{$row['vehicle_name']} ({$row['plate_number']})" : '-';
              $location = ($row['latitude'] && $row['longitude']) ? "{$row['latitude']}, {$row['longitude']}" : '-';
              $lastUpdate = $row['recorded_at'] ? date('M d, Y H:i', strtotime($row['recorded_at'])) : '-';

              $hasPhone = !empty($row['phone']);
              $phoneNumber = $row['phone'] ? htmlspecialchars($row['phone']) : 'No phone registered';
              
              echo "<tr>
                      <td>{$row['user_id']}</td>
                      <td>".htmlspecialchars($row['full_name'])."</td>
                      <td><span class='badge bg-{$badgeClass}'>".htmlspecialchars($status)."</span></td>
                      <td>{$vehicle}</td>
                     
                      <td class='text-center'>
                      <button class='btn btn-sm btn-success' 
                              onclick='openMessageModal(\"{$row['user_id']}\", \"".htmlspecialchars($row['full_name'])."\", \"{$phoneNumber}\", {$hasPhone})'
                              title='Send Message to ".htmlspecialchars($row['full_name'])."'>
                          <i class='fas fa-envelope'></i>
                      </button>
                      </td>

                    </tr>";
                    }
                  } else {
                    echo "<tr><td colspan='7' class='text-center'>No drivers found.</td></tr>";
                  }
                    ?>
                </tbody>
              </table>
            </div>
      <!-- Message Driver Modal -->
      <div class="modal fade" id="messageModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog">
          <div class="modal-content">
            <div class="modal-header bg-primary text-white">
              <h5 class="modal-title">Message Driver</h5>
              <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
              <input type="hidden" id="modalDriverId">
              <div class="mb-3">
                <label class="form-label">Driver Information</label>
                <div class="alert alert-info">
                  <strong>Driver:</strong> <span id="modalDriverName">-</span><br>
                  <strong>Phone:</strong> <span id="modalDriverPhone">-</span>
                </div>
              </div>
              <div class="mb-3">
                <label for="messageText" class="form-label">Message <span class="text-muted">(max 160 characters)</span></label>
                <textarea class="form-control" id="messageText" rows="4" placeholder="Type your message here..." maxlength="160"></textarea>
                <div class="form-text">
                  <span id="charCount">0</span>/160 characters
                </div>
              </div>
            </div>
            <div class="modal-footer">
              <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
              <button type="button" class="btn btn-primary" id="sendMessageBtn" onclick="sendMessage()">
                <i class="fas fa-paper-plane me-1"></i> Send SMS
              </button>
            </div>
          </div>
        </div>
      </div>

          </div>
        </div>
      </div>
    </div>
  </div>
</div>





<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
<script>
const burgerBtn = document.getElementById('burgerBtn');
const sidebar = document.getElementById('sidebar');
const mainContent = document.getElementById('mainContent');
const linkTexts = document.querySelectorAll('.link-text');
const dropdownToggles = document.querySelectorAll('.dropdown-toggle');

burgerBtn.addEventListener('click', () => {
  const isCollapsed = sidebar.classList.toggle('collapsed');
  mainContent.classList.toggle('collapsed');
  
  linkTexts.forEach(text => { text.style.display = isCollapsed ? 'none' : 'inline'; });
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
});
</script>
<script>
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
        customClass: { popup: 'rounded-4 shadow', confirmButton: 'swal-btn', cancelButton: 'swal-btn' }
      }).then((result) => { if (result.isConfirmed) window.location.href = '../logout.php'; });
    });
  }
  
  // Character counter for message textarea
  const messageText = document.getElementById('messageText');
  const charCount = document.getElementById('charCount');
  
  if (messageText && charCount) {
    messageText.addEventListener('input', function() {
      const length = this.value.length;
      charCount.textContent = length;
      
      if (length > 140) {
        charCount.style.color = '#dc3545';
      } else if (length > 120) {
        charCount.style.color = '#ffc107';
      } else {
        charCount.style.color = '#6c757d';
      }
    });
  }
});

// Global variables for SMS functionality
let currentDriverId = null;
let currentDriverName = null;
let currentDriverPhone = null;
let hasPhoneNumber = false;

// Open message modal
function openMessageModal(driverId, driverName, phoneNumber, hasPhone) {
  currentDriverId = driverId;
  currentDriverName = driverName;
  currentDriverPhone = phoneNumber;
  hasPhoneNumber = hasPhone;
  
  // Update modal content
  document.getElementById('modalDriverName').textContent = driverName;
  document.getElementById('modalDriverPhone').textContent = phoneNumber;
  document.getElementById('messageText').value = '';
  document.getElementById('charCount').textContent = '0';
  
  // Disable send button if no phone number
  const sendBtn = document.getElementById('sendMessageBtn');
  if (!hasPhone) {
    sendBtn.disabled = true;
    sendBtn.innerHTML = '<i class="fas fa-exclamation-triangle me-1"></i> No Phone Number';
    sendBtn.classList.remove('btn-primary');
    sendBtn.classList.add('btn-secondary');
  } else {
    sendBtn.disabled = false;
    sendBtn.innerHTML = '<i class="fas fa-paper-plane me-1"></i> Send SMS';
    sendBtn.classList.remove('btn-secondary');
    sendBtn.classList.add('btn-primary');
  }
  
  // Show modal
  const modal = new bootstrap.Modal(document.getElementById('messageModal'));
  modal.show();
}

// Send SMS message
async function sendMessage() {
  const message = document.getElementById('messageText').value.trim();
  
  if (!message) {
    Swal.fire('Error', 'Please enter a message to send.', 'error');
    return;
  }
  
  if (!hasPhoneNumber) {
    Swal.fire('Error', 'This driver does not have a phone number registered.', 'error');
    return;
  }
  
  // Show loading
  const sendBtn = document.getElementById('sendMessageBtn');
  const originalText = sendBtn.innerHTML;
  sendBtn.disabled = true;
  sendBtn.innerHTML = '<i class="fas fa-spinner fa-spin me-1"></i> Sending...';
  
  try {
    const response = await fetch('send_driver_sms.php', {
      method: 'POST',
      headers: {
        'Content-Type': 'application/json',
      },
      body: JSON.stringify({
        driver_id: currentDriverId,
        message: message
      })
    });
    
    const result = await response.json();
    
    if (result.success) {
      Swal.fire({
        title: 'SMS Sent Successfully!',
        text: result.message,
        icon: 'success',
        confirmButtonColor: '#28a745'
      });
      
      // Close modal
      const modal = bootstrap.Modal.getInstance(document.getElementById('messageModal'));
      modal.hide();
      
    } else {
      throw new Error(result.error || 'Failed to send SMS');
    }
    
  } catch (error) {
    console.error('SMS sending error:', error);
    Swal.fire({
      title: 'SMS Sending Failed',
      text: error.message || 'An error occurred while sending the SMS. Please try again.',
      icon: 'error',
      confirmButtonColor: '#dc3545'
    });
  } finally {
    // Reset button
    sendBtn.disabled = false;
    sendBtn.innerHTML = originalText;
  }
}
</script>
</body>
</html>
