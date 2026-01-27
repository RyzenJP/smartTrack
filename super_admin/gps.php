<?php
session_start();
require_once __DIR__ . '/../db_connection.php';
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>GPS Devices | Smart Track</title>
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

    #map {
      width: 100%;
      height: 400px;
      background-color: #e9ecef;
      border-radius: 0.5rem;
    }
    /* Add to your style section */
/* Perfectly centered modal */
  .modal.fade .modal-dialog {
    display: flex;
    align-items: center;
    min-height: calc(100% - 1rem);
    justify-content: center;
    margin: 0 auto;
  }
  
  /* Smooth transition */
  .modal.fade.show .modal-dialog {
    transform: none;
  }
  
  /* Optional: Control modal width */
  .modal-dialog {
    max-width: 500px;
    width: 100%;
    
  }
  /* Assignment Specific Styles */
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
    .badge-warning {
      background-color: #dc9f35ff;
    }
  </style>
</head>
<body>
<?php include __DIR__ . '/../pages/admin_sidebar.php'; ?>
<?php include __DIR__ . '/../pages/navbar.php'; ?>
<!-- Main Content -->
<div class="main-content" id="mainContent">
  <div class="container-fluid" style="margin-top: 70px;">
    <div class="row mt-4">
      <div class="col-12">
        <div class="card shadow-sm">
          <div class="card-body">
            <div class="d-flex justify-content-between align-items-center mb-3">
              <h5 class="card-title text-primary fw-bold m-0">GPS Devices</h5>
              <button class="btn btn-primary btn-sm" id="addGpsBtn" style="background-color: #003566; border-color: #003566;">
                <i class="fas fa-plus me-1"></i> New Device
              </button>
            </div>
            <div class="table-responsive">
              <table class="table table-hover align-middle">
                <thead class="table-light">
                  <tr>
                    <th>Device ID</th>
                    <th>IMEI</th>
                    <th>Vehicle</th>
                    <th>Status</th>
                    <th class="text-center">Action</th>
                  </tr>
                </thead>
                <tbody id="gpsTableBody">
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

<!-- GPS Device Modal -->
<div class="modal fade" id="gpsModal" tabindex="-1" aria-labelledby="gpsModalLabel" aria-hidden="true">
  <div class="modal-dialog modal-dialog-centered">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title" id="gpsModalLabel">New GPS Device</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
      </div>
      <div class="modal-body">
        <form id="gpsForm">
          <input type="hidden" id="gpsId" name="id">
          <div class="mb-3">
            <label for="device_id" class="form-label">Device ID</label>
            <input type="text" class="form-control" id="device_id" name="device_id" required>
          </div>
          <div class="mb-3">
            <label for="imei" class="form-label">IMEI Number</label>
            <input type="text" class="form-control" id="imei" name="imei" required>
          </div>
          <div class="mb-3">
            <label for="vehicle_id" class="form-label">Assigned Vehicle</label>
            <select class="form-select" id="vehicle_id" name="vehicle_id">
              <option value="">Not Assigned</option>
              <!-- Options will be loaded from fleet API -->
            </select>
          </div>
          <div class="mb-3">
            <label for="status" class="form-label">Status</label>
            <select class="form-select" id="status" name="status" required>
              <option value="active">Active</option>
              <option value="inactive">Inactive</option>
              <option value="maintenance">Maintenance</option>
            </select>
          </div>
        </form>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
        <button type="button" class="btn btn-primary" id="saveGps">Save</button>
      </div>
    </div>
  </div>
</div>

<!-- JS -->
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
<script>
  // GPS Devices CRUD Functionality
  document.addEventListener('DOMContentLoaded', function() {
    const gpsModal = new bootstrap.Modal(document.getElementById('gpsModal'));
    const gpsForm = document.getElementById('gpsForm');
    const saveGpsBtn = document.getElementById('saveGps');
    const addGpsBtn = document.getElementById('addGpsBtn');
    const vehicleSelect = document.getElementById('vehicle_id');

    // Load vehicles from fleet API
    async function loadVehicles() {
      try {
        const response = await fetch('../super_admin/fleet_api.php?action=get_fleet');
        const data = await response.json();
        
        if (data.success) {
          vehicleSelect.innerHTML = '<option value="">Not Assigned</option>';
          data.data.forEach(vehicle => {
            const option = document.createElement('option');
            option.value = vehicle.id;
            option.textContent = `${vehicle.unit} (${vehicle.plate_number})`;
            vehicleSelect.appendChild(option);
          });
        }
      } catch (error) {
        console.error('Error loading vehicles:', error);
      }
    }

    // Load GPS devices
    async function loadGpsDevices() {
      try {
        const response = await fetch('gps_api.php?action=get_devices');
        const data = await response.json();
        
        const tableBody = document.getElementById('gpsTableBody');
        tableBody.innerHTML = '';
        
        if (data.success) {
          data.data.forEach(device => {
            const row = document.createElement('tr');
            row.innerHTML = `
              <td>${device.device_id}</td>
              <td>${device.imei}</td>
              <td>${device.vehicle_unit || 'Not Assigned'}</td>
              <td><span class="badge ${getStatusBadgeClass(device.status)}">${device.status}</span></td>
              <td class="text-center">
                <button class="btn btn-sm me-1 edit-gps" data-id="${device.id}" 
                  style="background-color: #00b4d8; color: #fff;" 
                  data-bs-toggle="tooltip" data-bs-placement="top" title="Edit">
                  <i class="fas fa-edit"></i>
                </button>
                <button class="btn btn-sm delete-gps" data-id="${device.id}" 
                  style="background-color: #dc3545; color: #fff;" 
                  data-bs-toggle="tooltip" data-bs-placement="top" title="Delete">
                  <i class="fas fa-trash-alt"></i>
                </button>
              </td>
            `;
            tableBody.appendChild(row);
          });
        }
      } catch (error) {
        console.error('Error loading GPS devices:', error);
      }
    }

    function getStatusBadgeClass(status) {
      switch(status) {
        case 'active': return 'badge-active';
        case 'inactive': return 'badge-inactive';
        case 'maintenance': return 'badge-warning';
        default: return 'badge-secondary';
      }
    }

    // Load vehicles for dropdown using fleet_api (filtered to exclude synthetic vehicles)
    async function loadVehicles() {
      try {
        const response = await fetch('fleet_api.php?action=get_fleet_filtered');
        const data = await response.json();
        
        const vehicleSelect = document.getElementById('vehicle_id');
        vehicleSelect.innerHTML = '<option value="">Not Assigned</option>';
        
        if (data.success) {
          data.data.forEach(vehicle => {
            const option = document.createElement('option');
            option.value = vehicle.id;
            option.textContent = `${vehicle.article} (${vehicle.plate_number})`;
            vehicleSelect.appendChild(option);
          });
        }
      } catch (error) {
        console.error('Error loading vehicles:', error);
      }
    }

    // Add new GPS device
    addGpsBtn.addEventListener('click', async () => {
      gpsForm.reset();
      document.getElementById('gpsId').value = '';
      document.getElementById('gpsModalLabel').textContent = 'New GPS Device';
      await loadVehicles();
      gpsModal.show();
    });

    // Save GPS device
    saveGpsBtn.addEventListener('click', async () => {
      if (!gpsForm.checkValidity()) {
        gpsForm.classList.add('was-validated');
        return;
      }

      const formData = new FormData(gpsForm);
      const data = Object.fromEntries(formData.entries());
      const isEdit = !!data.id;

      try {
        const response = await fetch(`gps_api.php?action=${isEdit ? 'update_device' : 'add_device'}`, {
          method: 'POST',
          headers: { 'Content-Type': 'application/json' },
          body: JSON.stringify(data)
        });
        
        const result = await response.json();
        
        if (result.success) {
          gpsModal.hide();
          loadGpsDevices();
          Swal.fire({
            title: 'Success!',
            text: `GPS device ${isEdit ? 'updated' : 'added'} successfully`,
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

    // Edit GPS device
    document.getElementById('gpsTableBody').addEventListener('click', async (e) => {
      if (e.target.closest('.edit-gps')) {
        const deviceId = e.target.closest('.edit-gps').dataset.id;
        
        try {
          const response = await fetch(`gps_api.php?action=get_device&id=${deviceId}`);
          const data = await response.json();
          
          if (data.success) {
            document.getElementById('gpsId').value = data.data.id;
            document.getElementById('device_id').value = data.data.device_id;
            document.getElementById('imei').value = data.data.imei;
            document.getElementById('status').value = data.data.status;
            
            document.getElementById('gpsModalLabel').textContent = 'Edit GPS Device';
            await loadVehicles(); // Load vehicles for edit modal FIRST
            // Set vehicle_id AFTER vehicles are loaded
            document.getElementById('vehicle_id').value = data.data.vehicle_id || '';
            gpsModal.show();
          }
        } catch (error) {
          console.error('Error loading GPS device:', error);
        }
      }
      
      // Delete GPS device
      if (e.target.closest('.delete-gps')) {
        const deviceId = e.target.closest('.delete-gps').dataset.id;
        
        Swal.fire({
          title: 'Delete GPS Device?',
          text: "Are you sure you want to delete this device?",
          icon: 'warning',
          showCancelButton: true,
          confirmButtonColor: '#dc3545',
          cancelButtonColor: '#6c757d',
          confirmButtonText: 'Delete'
        }).then(async (result) => {
          if (result.isConfirmed) {
            try {
              const response = await fetch(`gps_api.php?action=delete_device&id=${deviceId}`);
              const data = await response.json();
              
              if (data.success) {
                loadGpsDevices();
                Swal.fire('Deleted!', 'GPS device has been deleted.', 'success');
              }
            } catch (error) {
              console.error('Error deleting GPS device:', error);
            }
          }
        });
      }
    });

    // Initialize on page load
    loadVehicles();
    loadGpsDevices();
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
  });

  // ✅ Sidebar active class handler (top-level and submenu)
  const allSidebarLinks = sidebar.querySelectorAll('a:not(.dropdown-toggle)');

  allSidebarLinks.forEach(link => {
    link.addEventListener('click', function () {
      // Remove active from all links (top-level and submenu)
      allSidebarLinks.forEach(l => l.classList.remove('active'));

      // Add active to clicked link
      this.classList.add('active');

      // Optional: Expand parent menu if collapsed
      const parentCollapse = this.closest('.collapse');
      if (parentCollapse) {
        const bsCollapse = bootstrap.Collapse.getInstance(parentCollapse);
        if (bsCollapse) {
          bsCollapse.show();
        }
      }
    });
  });
</script>
</body>
</html>