<?php
session_start();
if (!isset($_SESSION['role']) || strtolower($_SESSION['role']) !== 'dispatcher') {
    header("Location: ../index.php");
    exit;
}
require_once __DIR__ . '/../db_connection.php';

// Real-time data queries (filter out synthetic vehicles) - use prepared statements for consistency
$activeVehicles_stmt = $conn->prepare("SELECT COUNT(*) FROM fleet_vehicles WHERE status = 'active' AND article NOT LIKE '%Synthetic%' AND plate_number NOT LIKE 'SYN-%' AND plate_number NOT LIKE '%SYN%'");
$activeVehicles_stmt->execute();
$activeVehicles = $activeVehicles_stmt->get_result()->fetch_row()[0];
$activeVehicles_stmt->close();

$onRoute_stmt = $conn->prepare("SELECT COUNT(DISTINCT r.unit) FROM routes r JOIN fleet_vehicles v ON r.unit = v.unit WHERE r.status = 'active' AND v.article NOT LIKE '%Synthetic%' AND v.plate_number NOT LIKE 'SYN-%' AND v.plate_number NOT LIKE '%SYN%'");
$onRoute_stmt->execute();
$onRoute = $onRoute_stmt->get_result()->fetch_row()[0];
$onRoute_stmt->close();

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
      margin-top: 10px;
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
      background-color: #001d3d;
      color: var(--accent);
      box-shadow: inset 2px 0 0 var(--accent);
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
    
    /* Modal styles */
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
    
    /* Assignment table styles */
    .badge-active {
      background-color: #28a745;
    }
    
    .badge-inactive {
      background-color: #dc3545;
    }

    .action-btn {
      width: 30px;
      height: 30px;
      display: inline-flex;
      align-items: center;
      justify-content: center;
      padding: 0;
    }
    
    /* Stats cards */
    .stat-card {
      border-radius: 0.5rem;
      transition: transform 0.2s;
    }
    
    .stat-card:hover {
      transform: translateY(-3px);
    }
    
    .stat-icon {
      font-size: 1.75rem;
      opacity: 0.8;
    }
    
    /* Tab navigation */
    .nav-tabs .nav-link {
      border: none;
      color: #6c757d;
      font-weight: 500;
      padding: 0.75rem 1.25rem;
    }
    
    .nav-tabs .nav-link.active {
      color: var(--primary);
      border-bottom: 3px solid var(--accent);
      background: transparent;
    }

    /* Make Available Vehicles list scrollable */
    #availableVehiclesList {
      max-height: 420px;
      overflow-y: auto;
      scrollbar-width: thin;
      scrollbar-color: #cbd5e1 #f1f5f9;
    }

    #availableVehiclesList::-webkit-scrollbar {
      width: 8px;
      height: 8px;
    }

    #availableVehiclesList::-webkit-scrollbar-thumb {
      background-color: #cbd5e1;
      border-radius: 8px;
    }

    #availableVehiclesList::-webkit-scrollbar-track {
      background: #f1f5f9;
    }
  </style>
</head>
<body>

<?php include __DIR__ . '/../pages/dispatcher_sidebar.php'; ?>
<?php include __DIR__ . '/../pages/dispatcher_navbar.php'; ?>

<div class="main-content" id="mainContent">
  <div class="container-fluid py-4">
    <!-- Dashboard Stats -->
    <div class="row mb-4">
      <div class="col-md-4">
        <div class="card stat-card bg-primary text-white">
          <div class="card-body">
            <div class="d-flex justify-content-between align-items-center">
              <div>
                <h6 class="text-uppercase mb-0">Active Vehicles</h6>
                <h2 class="mb-0"><?= $activeVehicles ?></h2>
              </div>
              <div class="stat-icon">
                <i class="fas fa-car"></i>
              </div>
            </div>
          </div>
        </div>
      </div>
      <div class="col-md-4">
        <div class="card stat-card bg-success text-white">
          <div class="card-body">
            <div class="d-flex justify-content-between align-items-center">
              <div>
                <h6 class="text-uppercase mb-0">On Route</h6>
                <h2 class="mb-0"><?= $onRoute ?></h2>
              </div>
              <div class="stat-icon">
                <i class="fas fa-road"></i>
              </div>
            </div>
          </div>
        </div>
      </div>
      <div class="col-md-4">
        <div class="card stat-card bg-info text-white">
          <div class="card-body">
            <div class="d-flex justify-content-between align-items-center">
              <div>
                <h6 class="text-uppercase mb-0">Available Drivers</h6>
                <h2 class="mb-0"><?= $availableDrivers ?></h2>
              </div>
              <div class="stat-icon">
                <i class="fas fa-users"></i>
              </div>
            </div>
          </div>
        </div>
      </div>
    </div>
    
    <!-- Main Content Tabs -->
    <div class="row mt-4">
      <div class="col-12">
        <div class="card shadow-sm">
          <div class="card-header bg-white border-bottom">
            <ul class="nav nav-tabs card-header-tabs" id="dispatcherTabs" role="tablist">
              <li class="nav-item" role="presentation">
                <button class="nav-link active" id="assign-tab" data-bs-toggle="tab" data-bs-target="#assign-tab-pane" type="button" role="tab">
                  <i class="fas fa-car me-1"></i> Assign Vehicles
                </button>
              </li>
              <li class="nav-item" role="presentation">
                <button class="nav-link" id="manage-tab" data-bs-toggle="tab" data-bs-target="#manage-tab-pane" type="button" role="tab">
                  <i class="fas fa-list me-1"></i> Manage Assignments
                </button>
              </li>
            </ul>
          </div>
          <div class="card-body">
            <div class="tab-content" id="dispatcherTabContent">
              <!-- Assign Vehicles Tab -->
              <div class="tab-pane fade show active" id="assign-tab-pane" role="tabpanel" tabindex="0">
                <div class="alert alert-info mb-4">
                  <i class="fas fa-info-circle me-2"></i>
                  <strong>Quick Overview:</strong> View available drivers and vehicles below. To create new assignments, use the <strong>"Manage Assignments"</strong> tab.
                </div>
                <div class="row mb-4">
                  <div class="col-md-6">
                    <div class="card h-100">
                      <div class="card-header bg-primary text-white d-flex justify-content-between align-items-center">
                        <span>Available Drivers</span>
                        <span class="badge bg-light text-primary">
                          <?php echo number_format($availableDrivers); ?>
                        </span>
                      </div>
                      <div class="card-body">
                        <div class="list-group" id="availableDriversList">
                          <?php
                          // Use prepared statement for consistency and security best practices
                          $drivers_stmt = $conn->prepare("SELECT u.user_id, u.full_name, u.phone
                                                     FROM user_table u
                                                     WHERE u.role = 'Driver' AND u.status = 'Active'
                                                     AND NOT EXISTS (
                                                       SELECT 1 FROM vehicle_assignments a
                                                       WHERE a.driver_id = u.user_id AND a.status = 'active'
                                                     )");
                          $drivers_stmt->execute();
                          $drivers = $drivers_stmt->get_result();
                          while($driver = $drivers->fetch_assoc()): ?>
                            <a href="#" class="list-group-item list-group-item-action d-flex justify-content-between align-items-center">
                              <?= htmlspecialchars($driver['full_name']) ?>
                              <span class="badge bg-primary rounded-pill">Available</span>
                            </a>
                          <?php endwhile; ?>
                        </div>
                      </div>
                    </div>
                  </div>
                  <div class="col-md-6">
                    <div class="card h-100">
                      <div class="card-header bg-success text-white">
                        Available Vehicles
                      </div>
                      <div class="card-body">
                        <div class="list-group" id="availableVehiclesList">
                          <?php
                          // Use prepared statement for consistency and security best practices
                          $vehicles_stmt = $conn->prepare("SELECT v.id, v.article, v.plate_number, v.unit
                                                     FROM fleet_vehicles v
                                                     WHERE v.status = 'active'
                                                     AND v.article NOT LIKE '%Synthetic%'
                                                     AND v.plate_number NOT LIKE 'SYN-%'
                                                     AND v.plate_number NOT LIKE '%SYN%'
                                                     AND (
                                                       NOT EXISTS (
                                                         SELECT 1 FROM vehicle_assignments a
                                                         WHERE a.vehicle_id = v.id AND a.status = 'active'
                                                       )
                                                       OR EXISTS (
                                                         SELECT 1 FROM vehicle_assignments a
                                                         WHERE a.vehicle_id = v.id AND a.status = 'available'
                                                       )
                                                     )");
                          $vehicles_stmt->execute();
                          $vehicles = $vehicles_stmt->get_result();
                          while($vehicle = $vehicles->fetch_assoc()): ?>
                            <a href="#" class="list-group-item list-group-item-action d-flex justify-content-between align-items-center">
                              <?= htmlspecialchars($vehicle['unit']) ?> (<?= $vehicle['plate_number'] ?>) - <?= $vehicle['article'] ?>
                              <span class="badge bg-success rounded-pill">Ready</span>
                            </a>
                          <?php endwhile; 
                          $vehicles_stmt->close(); ?>
                        </div>
                      </div>
                    </div>
                  </div>
                </div>
              </div>
              
              <!-- Manage Assignments Tab -->
              <div class="tab-pane fade" id="manage-tab-pane" role="tabpanel" tabindex="0">
                <div class="d-flex justify-content-between align-items-center mb-3">
                  <h5 class="card-title text-primary fw-bold m-0">Vehicle Assignments</h5>
                  <button class="btn btn-primary btn-sm" id="addAssignmentBtn">
                    <i class="fas fa-plus me-1"></i> New Assignment
                  </button>
                </div>
                <div class="table-responsive">
                  <table class="table table-hover align-middle">
                    <thead class="table-light">
                      <tr>
                        <th>Driver Name</th>
                        <th>Phone Number</th>
                        <th>Assigned Vehicle</th>
                        <th>Plate Number</th>
                        <th>Status</th>
                        <th>Date Assigned</th>
                        <th class="text-center">Actions</th>
                      </tr>
                    </thead>
                    <tbody id="assignmentTableBody">
                      <!-- Dynamic content will be loaded here -->
                    </tbody>
                  </table>
                </div>
              </div>
            </div>
          </div>
        </div>
      </div>
    </div>
  </div>
</div>

<!-- Assignment Modal -->
<div class="modal fade" id="assignmentModal" tabindex="-1" aria-labelledby="assignmentModalLabel" aria-hidden="true">
  <div class="modal-dialog modal-dialog-centered">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title" id="assignmentModalLabel">New Assignment</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
      </div>
      <div class="modal-body">
        <form id="assignmentFormModal">
          <input type="hidden" id="assignmentId">
          <div class="mb-3">
            <label class="form-label">Driver *</label>
            <select id="driver_id" class="form-select" required>
              <option value="">Select Driver</option>
              <!-- Will be filled by JavaScript -->
            </select>
          </div>
          <div class="mb-3">
            <label class="form-label">Phone Number</label>
            <input type="text" id="phone_number" class="form-control" readonly>
          </div>
          <div class="mb-3">
            <label class="form-label">Vehicle *</label>
            <select id="vehicle_id" class="form-select" required>
              <option value="">Select Vehicle</option>
              <!-- Will be filled by JavaScript -->
            </select>
          </div>
          <div class="mb-3">
            <label class="form-label">Status *</label>
            <select id="status" class="form-select" required>
              <option value="active">Active</option>
              <option value="inactive">Inactive</option>
              <option value="pending">Pending</option>
            </select>
          </div>
          <div class="mb-3">
            <label class="form-label">Notes</label>
            <textarea class="form-control" id="notes" rows="3"></textarea>
          </div>
        </form>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
        <button type="button" class="btn btn-primary" id="saveAssignment">Save</button>
      </div>
    </div>
  </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>

<script>
// Sidebar toggle functionality
const burgerBtn = document.getElementById('burgerBtn');
const sidebar = document.getElementById('sidebar');
const mainContent = document.getElementById('mainContent');
const linkTexts = document.querySelectorAll('.link-text');
const dropdownToggles = document.querySelectorAll('.dropdown-toggle');

burgerBtn.addEventListener('click', () => {
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
});

// Logout confirmation
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

// Assignment Management
document.addEventListener('DOMContentLoaded', function() {
    const assignmentModal = new bootstrap.Modal(document.getElementById('assignmentModal'));
    const assignmentFormModal = document.getElementById('assignmentFormModal');
    const saveAssignmentBtn = document.getElementById('saveAssignment');
    const addAssignmentBtn = document.getElementById('addAssignmentBtn');
    const driverSelect = document.getElementById('driver_id');
    const vehicleSelect = document.getElementById('vehicle_id');
    const phoneInput = document.getElementById('phone_number');

    // Load assignments
    async function loadAssignments() {
        try {
            const response = await fetch('assignment_api.php?action=get_assignments');
            const data = await response.json();
            
            const tableBody = document.getElementById('assignmentTableBody');
            tableBody.innerHTML = '';
            
            if (data.success && data.data.length > 0) {
                data.data.forEach(assignment => {
                    const row = `
                    <tr>
                        <td>${assignment.driver_name}</td>
                        <td>${assignment.phone_number}</td>
                        <td>${assignment.vehicle_unit || 'N/A'}</td>
                        <td>${assignment.plate_number || 'N/A'}</td>
                        <td><span class="badge ${assignment.status === 'active' ? 'badge-active' : 'badge-inactive'}">
                            ${assignment.status}
                        </span></td>
                        <td>${new Date(assignment.date_assigned).toLocaleDateString()}</td>
                        <td class="text-center">
                            <button class="btn btn-sm btn-primary edit-assignment" data-id="${assignment.id}">
                                <i class="fas fa-edit"></i>
                            </button>
                            <button class="btn btn-sm btn-danger delete-assignment" data-id="${assignment.id}">
                                <i class="fas fa-trash-alt"></i>
                            </button>
                        </td>
                    </tr>`;
                    tableBody.insertAdjacentHTML('beforeend', row);
                });
            } else {
                tableBody.innerHTML = `
                    <tr>
                        <td colspan="7" class="text-center text-muted py-4">No assignments found</td>
                    </tr>
                `;
            }
        } catch (error) {
            console.error('Error loading assignments:', error);
            Swal.fire('Error', 'Failed to load assignments', 'error');
        }
    }

    // Load drivers
    async function loadDrivers() {
        try {
            const response = await fetch('assignment_api.php?action=get_drivers');
            const data = await response.json();
            
            driverSelect.innerHTML = '<option value="">Select Driver</option>';
            
            if (data.success && data.data.length > 0) {
                data.data.forEach(driver => {
                    const option = document.createElement('option');
                    option.value = driver.user_id;
                    option.textContent = driver.full_name;
                    driverSelect.appendChild(option);
                });
            } else {
                const option = document.createElement('option');
                option.disabled = true;
                option.textContent = 'No drivers available';
                driverSelect.appendChild(option);
            }
        } catch (error) {
            console.error('Error loading drivers:', error);
            Swal.fire('Error', 'Failed to load drivers', 'error');
        }
    }

    // Load vehicles
    async function loadVehicles() {
        try {
            const response = await fetch('../super_admin/fleet_api.php?action=get_fleet');
            const data = await response.json();
            
            vehicleSelect.innerHTML = '<option value="">Select Vehicle</option>';
            
            if (data.success && data.data.length > 0) {
                data.data.forEach(vehicle => {
                    const option = document.createElement('option');
                    option.value = vehicle.id;
                    option.textContent = `${vehicle.unit} (${vehicle.plate_number}) - ${vehicle.article}`;
                    vehicleSelect.appendChild(option);
                });
            }
        } catch (error) {
            console.error('Error loading vehicles:', error);
            Swal.fire('Error', 'Failed to load vehicles', 'error');
        }
    }

    // Handle driver selection change
    driverSelect.addEventListener('change', async function() {
        const driverId = this.value;
        if (driverId) {
            try {
                const response = await fetch(`assignment_api.php?action=get_driver_phone&id=${driverId}`);
                const data = await response.json();
                if (data.success) {
                    phoneInput.value = data.phone || '';
                }
            } catch (error) {
                console.error('Error loading phone:', error);
            }
        } else {
            phoneInput.value = '';
        }
    });

    // Add new assignment
    addAssignmentBtn.addEventListener('click', () => {
        assignmentFormModal.reset();
        document.getElementById('assignmentId').value = '';
        document.getElementById('assignmentModalLabel').textContent = 'New Assignment';
        assignmentModal.show();
    });

    // Save assignment
    saveAssignmentBtn.addEventListener('click', async () => {
        const driverId = driverSelect.value;
        const vehicleId = vehicleSelect.value;
        
        if (!driverId || !vehicleId) {
            Swal.fire('Error', 'Please select both driver and vehicle', 'error');
            return;
        }

        const formData = {
            driver_id: driverId,
            vehicle_id: vehicleId,
            status: document.getElementById('status').value,
            notes: document.getElementById('notes').value,
            id: document.getElementById('assignmentId').value || undefined
        };

        try {
            const action = formData.id ? 'update_assignment' : 'add_assignment';
            const response = await fetch(`assignment_api.php?action=${action}`, {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify(formData)
            });
            
            const result = await response.json();
            
            if (result.success) {
                assignmentModal.hide();
                loadAssignments();
                Swal.fire({
                    title: 'Success!',
                    text: `Assignment ${formData.id ? 'updated' : 'added'} successfully`,
                    icon: 'success'
                });
            } else {
                Swal.fire({
                    title: 'Error',
                    text: result.message || 'Operation failed',
                    icon: 'error'
                });
            }
        } catch (error) {
            console.error('Error:', error);
            Swal.fire({
                title: 'Error',
                text: 'Network error occurred',
                icon: 'error'
            });
        }
    });

    // Edit assignment
    document.getElementById('assignmentTableBody').addEventListener('click', async (e) => {
        if (e.target.closest('.edit-assignment')) {
            const assignmentId = e.target.closest('.edit-assignment').dataset.id;
            
            try {
                const response = await fetch(`assignment_api.php?action=get_assignment&id=${assignmentId}`);
                const data = await response.json();
                
                if (data.success) {
                    document.getElementById('assignmentId').value = data.data.id;
                    document.getElementById('driver_id').value = data.data.driver_id;
                    document.getElementById('phone_number').value = data.data.phone_number;
                    document.getElementById('vehicle_id').value = data.data.vehicle_id;
                    document.getElementById('status').value = data.data.status;
                    document.getElementById('notes').value = data.data.notes || '';
                    
                    document.getElementById('assignmentModalLabel').textContent = 'Edit Assignment';
                    assignmentModal.show();
                }
            } catch (error) {
                console.error('Error loading assignment:', error);
                Swal.fire('Error', 'Failed to load assignment details', 'error');
            }
        }
        
        // Delete assignment
        if (e.target.closest('.delete-assignment')) {
            const assignmentId = e.target.closest('.delete-assignment').dataset.id;
            
            Swal.fire({
                title: 'Delete Assignment?',
                text: "You won't be able to revert this!",
                icon: 'warning',
                showCancelButton: true,
                confirmButtonColor: '#dc3545',
                cancelButtonColor: '#6c757d',
                confirmButtonText: 'Yes, delete it!'
            }).then(async (result) => {
                if (result.isConfirmed) {
                    try {
                        const response = await fetch(`assignment_api.php?action=delete_assignment&id=${assignmentId}`);
                        const data = await response.json();
                        
                        if (data.success) {
                            loadAssignments();
                            Swal.fire('Deleted!', 'Assignment has been deleted.', 'success');
                        } else {
                            Swal.fire('Error', data.message || 'Failed to delete assignment', 'error');
                        }
                    } catch (error) {
                        console.error('Error deleting assignment:', error);
                        Swal.fire('Error', 'Failed to delete assignment', 'error');
                    }
                }
            });
        }
    });

    // Initialize on page load
    loadVehicles();
    loadAssignments();
    loadDrivers();
    
    // Auto-refresh assignments every 30 seconds
    setInterval(loadAssignments, 30000);
});
</script>
</body>
</html>