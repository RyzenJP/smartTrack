
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Vehicle Assignments | Smart Track</title>
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

    #mainContent {
      padding-top: 70px;
    }

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
    }

    .card {
      border-radius: 0.5rem;
      box-shadow: 0 0.125rem 0.25rem rgba(0, 0, 0, 0.075);
    }
    
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
    margin-top: -50px; /* Adjust this value as needed */
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
  </style>
</head>
<body>

<!-- Sidebar -->
<?php include __DIR__ . '/../pages/sidebar.php'; ?>
<?php include __DIR__ . '/../pages/navbar.php'; ?>

<!-- Main Content -->
<div class="main-content" id="mainContent">
  <div class="container-fluid">
    <div class="row mt-4">
      <div class="col-12">
        <div class="card shadow-sm">
          <div class="card-body">
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

<!-- Assignment Modal -->
<div class="modal fade" id="assignmentModal" tabindex="-1" aria-labelledby="assignmentModalLabel" aria-hidden="true">
  <div class="modal-dialog modal-dialog-centered">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title" id="assignmentModalLabel">New Assignment</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
      </div>
      <div class="modal-body">
        <form id="assignmentForm">
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
            </select>
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
document.addEventListener('DOMContentLoaded', function() {
    const assignmentModal = new bootstrap.Modal(document.getElementById('assignmentModal'));
    const assignmentForm = document.getElementById('assignmentForm');
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
                    option.textContent = `${driver.full_name} (${driver.phone || 'No phone'})`;
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
        assignmentForm.reset();
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
});
</script>
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
</body>
</html>