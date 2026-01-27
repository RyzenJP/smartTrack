<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0"/>
  <title>Dashboard | Smart Track</title>

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
</style>
  </style>
</head>
<body>

<?php include __DIR__ . '/../pages/admin_sidebar.php'; ?>
<?php include __DIR__ . '/../pages/navbar.php'; ?>

<!-- Main Content -->
<div class="main-content" id="mainContent">
  <div class="container-fluid">
    <!-- Fleet Vehicles Section -->
    <div class="row mt-4">
      <div class="col-12">
        <div class="card shadow-sm">
          <div class="card-body">
            <div class="d-flex justify-content-between align-items-center mb-3">
              <h5 class="card-title text-primary fw-bold m-0">Fleet Vehicles</h5>
              <button class="btn btn-primary btn-sm" id="addVehicleBtn" style="background-color: #003566; border-color: #003566;">
                <i class="fas fa-plus me-1"></i> Add Vehicle
              </button>
            </div>
            <div class="table-responsive" style="max-height: 520px; overflow-y: auto;">
              <table class="table table-hover align-middle">
                <thead class="table-light">
                  <tr>
                    <th>Article</th>
                    <th>Unit</th>
                    <th>Plate No.</th>
                    <th>Status</th>
                    <th>Fuel Level</th>
                    <th>Consumption</th>
                    <th class="text-nowrap">Last Updated</th>
                    <th class="text-center">Action</th>
                  </tr>
                </thead>
                <tbody id="fleetTableBody">
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

<!-- Vehicle Modal -->
<!-- Update the Vehicle Modal div by adding 'modal-dialog-centered' class -->
<div class="modal fade" id="vehicleModal" tabindex="-1" aria-labelledby="vehicleModalLabel" aria-hidden="true">
  <div class="modal-dialog modal-dialog-centered"> <!-- Added modal-dialog-centered here -->
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title" id="vehicleModalLabel">Add New Vehicle</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
      </div>
      <div class="modal-body">
        <form id="vehicleForm">
          <input type="hidden" id="vehicleId" name="id">
          <div class="mb-3">
            <label for="article" class="form-label">Article</label>
            <select class="form-select" id="article" name="article" required>
              <option value="">Select Vehicle Type</option>
              <option value="Ambulance Vehicle">Ambulance Vehicle</option>
              <option value="Service Vehicle">Service Vehicle</option>
              <option value="Utility Vehicle">Utility Vehicle</option>
              <option value="Patrol Service Vehicle">Patrol Service Vehicle</option>
              <option value="Relief Vehicle">Relief Vehicle</option>
              <option value="Sanitation Vehicle">Sanitation Vehicle</option>
              <option value="Special Purpose Vehicle">Special Purpose Vehicle</option>
              <option value="Rescue Vehicle">Rescue Vehicle</option>
              <option value="Mobile Kitchen">Mobile Kitchen</option>
              <option value="Transport Vehicle">Transport Vehicle</option>
            </select>
          </div>
          <div class="mb-3">
            <label for="unit" class="form-label">Unit</label>
            <input type="text" class="form-control" id="unit" name="unit" required>
          </div>
          <div class="mb-3">
            <label for="plate_number" class="form-label">Plate Number</label>
            <input type="text" class="form-control" id="plate_number" name="plate_number" required>
          </div>
          <div class="mb-3">
            <label for="status" class="form-label">Status</label>
            <select class="form-select" id="status" name="status" required>
              <option value="active">Active</option>
              <option value="maintenance">Maintenance</option>
              <option value="out_of_service">Out of Service</option>
            </select>
          </div>
          
          <!-- Fuel Consumption Fields -->
          <div class="row">
            <div class="col-md-6">
              <div class="mb-3">
                <label for="fuel_consumption_l_per_km" class="form-label">Fuel Consumption (L/km)</label>
                <input type="number" class="form-control" id="fuel_consumption_l_per_km" name="fuel_consumption_l_per_km" 
                       step="0.001" min="0.001" max="1.000" placeholder="0.100">
                <div class="form-text">Liters per kilometer (e.g., 0.100 = 10 km/L)</div>
              </div>
            </div>
            <div class="col-md-6">
              <div class="mb-3">
                <label for="fuel_tank_capacity_liters" class="form-label">Tank Capacity (L)</label>
                <input type="number" class="form-control" id="fuel_tank_capacity_liters" name="fuel_tank_capacity_liters" 
                       step="0.01" min="1" max="1000" placeholder="50.00">
                <div class="form-text">Full tank capacity in liters</div>
              </div>
            </div>
          </div>
          
          <div class="row">
            <div class="col-md-6">
              <div class="mb-3">
                <label for="current_fuel_level_liters" class="form-label">Current Fuel Level (L)</label>
                <input type="number" class="form-control" id="current_fuel_level_liters" name="current_fuel_level_liters" 
                       step="0.01" min="0" max="1000" placeholder="50.00">
                <div class="form-text">Current fuel level in liters</div>
              </div>
            </div>
            <div class="col-md-6">
              <div class="mb-3">
                <label for="fuel_cost_per_liter" class="form-label">Fuel Cost (₱/L)</label>
                <input type="number" class="form-control" id="fuel_cost_per_liter" name="fuel_cost_per_liter" 
                       step="0.01" min="0" max="1000" placeholder="0.00">
                <div class="form-text">Current fuel cost per liter</div>
              </div>
            </div>
          </div>
          <div class="mb-3" id="notesContainer" style="display: none;">
            <label for="notes" class="form-label">Status Change Notes</label>
            <textarea class="form-control" id="notes" name="notes" rows="2"></textarea>
          </div>
        </form>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
        <button type="button" class="btn btn-primary" id="saveVehicle">Save</button>
      </div>
    </div>
  </div>
</div>

<!-- Location Modal -->
<div class="modal fade" id="locationModal" tabindex="-1" aria-labelledby="locationModalLabel" aria-hidden="true">
  <div class="modal-dialog modal-lg">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title" id="locationModalLabel">Vehicle Location</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
      </div>
      <div class="modal-body">
        <div id="locationMap" style="height: 400px; width: 100%;"></div>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
      </div>
    </div>
  </div>
</div>

<!-- JS -->
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
<script>
  // Sidebar toggle functionality
  const burgerBtn = document.getElementById('burgerBtn');
  const sidebar = document.getElementById('sidebar');
  const mainContent = document.getElementById('mainContent');
  const linkTexts = document.querySelectorAll('.link-text');
  const dropdownToggles = document.querySelectorAll('.dropdown-toggle');

  if (burgerBtn) {
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

  // Fleet Management CRUD Operations
  document.addEventListener('DOMContentLoaded', function() {
    const vehicleModal = new bootstrap.Modal(document.getElementById('vehicleModal'));
    const vehicleForm = document.getElementById('vehicleForm');
    const saveVehicleBtn = document.getElementById('saveVehicle');
    const addVehicleBtn = document.getElementById('addVehicleBtn');

    // Load initial fleet data
    loadFleetData();
    
    // Set up real-time updates (polling every 30 seconds)
    setInterval(loadFleetData, 30000);

    // Add vehicle button click handler
    addVehicleBtn.addEventListener('click', function() {
      document.getElementById('vehicleModalLabel').textContent = 'Add New Vehicle';
      vehicleForm.reset();
      document.getElementById('vehicleId').value = '';
      document.getElementById('notesContainer').style.display = 'none';
      vehicleModal.show();
    });
    
    // Auto-calculate fuel consumption based on vehicle type
    document.getElementById('article').addEventListener('change', function() {
      const article = this.value;
      const fuelConsumptionField = document.getElementById('fuel_consumption_l_per_km');
      const tankCapacityField = document.getElementById('fuel_tank_capacity_liters');
      const currentFuelField = document.getElementById('current_fuel_level_liters');
      
      // Set default values based on vehicle type
      let fuelConsumption, tankCapacity;
      
      if (article.includes('Ambulance')) {
        fuelConsumption = 0.120; // 8.3 km/L
        tankCapacity = 80;
      } else if (article.includes('Truck') || article.includes('Bus')) {
        fuelConsumption = 0.200; // 5.0 km/L
        tankCapacity = 150;
      } else if (article.includes('Van') || article.includes('SUV')) {
        fuelConsumption = 0.143; // 7.0 km/L
        tankCapacity = 60;
      } else if (article.includes('Service')) {
        fuelConsumption = 0.149; // 6.7 km/L
        tankCapacity = 50;
      } else {
        fuelConsumption = 0.100; // 10.0 km/L
        tankCapacity = 50;
      }
      
      if (fuelConsumptionField.value === '' || fuelConsumptionField.value === '0') {
        fuelConsumptionField.value = fuelConsumption.toFixed(3);
      }
      if (tankCapacityField.value === '' || tankCapacityField.value === '0') {
        tankCapacityField.value = tankCapacity;
      }
      if (currentFuelField.value === '' || currentFuelField.value === '0') {
        currentFuelField.value = tankCapacity; // Start with full tank
      }
    });

    // Save vehicle (add or update)
    saveVehicleBtn.addEventListener('click', function() {
      const formData = new FormData(vehicleForm);
      const data = Object.fromEntries(formData.entries());
      const isEdit = !!data.id;
      
      // Convert vehicle ID to integer if editing
      if (isEdit && data.id) {
        data.id = parseInt(data.id);
      }
      
      // Convert numeric fields to proper numbers
      data.fuel_consumption_l_per_km = data.fuel_consumption_l_per_km ? parseFloat(data.fuel_consumption_l_per_km) : 0.1;
      data.fuel_tank_capacity_liters = data.fuel_tank_capacity_liters ? parseFloat(data.fuel_tank_capacity_liters) : 50.0;
      data.current_fuel_level_liters = data.current_fuel_level_liters ? parseFloat(data.current_fuel_level_liters) : 50.0;
      data.fuel_cost_per_liter = data.fuel_cost_per_liter ? parseFloat(data.fuel_cost_per_liter) : 0.0;
      
      // Calculate fuel efficiency (km per liter) from consumption (liters per km)
      if (data.fuel_consumption_l_per_km > 0) {
        data.fuel_efficiency_km_per_l = 1 / data.fuel_consumption_l_per_km;
      } else {
        data.fuel_efficiency_km_per_l = 10.0; // Default
      }
      
      // Validate required fields
      if (!data.article || !data.unit || !data.plate_number || !data.status) {
        Swal.fire({
          title: 'Validation Error',
          text: 'Please fill in all required fields',
          icon: 'error',
          confirmButtonColor: '#dc3545'
        });
        return;
      }
      
      if (isEdit) {
        fetchFleetData().then(fleetData => {
          const originalVehicle = fleetData.find(v => v.id == data.id);
          data.status_changed = originalVehicle && originalVehicle.status !== data.status;
          performSave(data, isEdit);
        }).catch(error => {
          console.error('Error fetching fleet data:', error);
          // Still try to save even if fetch fails
          data.status_changed = false;
          performSave(data, isEdit);
        });
      } else {
        data.status_changed = false;
        performSave(data, isEdit);
      }
    });

    // Table click handlers (delegated events)
    document.getElementById('fleetTableBody').addEventListener('click', function(e) {
      if (e.target.closest('.edit-vehicle')) {
        const vehicleId = e.target.closest('.edit-vehicle').dataset.id;
        showEditModal(vehicleId);
      }
      
      if (e.target.closest('.view-location')) {
        const link = e.target.closest('.view-location');
        const lat = link.dataset.lat;
        const lng = link.dataset.lng;
        showOnMap(lat, lng);
      }
    });

    // Helper functions
    function loadFleetData() {
      fetchFleetData().then(data => {
        renderFleetTable(data);
      });
    }

    function fetchFleetData() {
      return fetch('fleet_api.php?action=get_fleet')
        .then(response => response.json())
        .then(data => {
          if (data.success) {
            return data.data;
          }
          return [];
        });
    }

    function renderFleetTable(vehicles) {
      const tableBody = document.getElementById('fleetTableBody');
      tableBody.innerHTML = '';
      
      vehicles.forEach(vehicle => {
        const fuelLevel = parseFloat(vehicle.current_fuel_level_liters || 0);
        const tankCapacity = parseFloat(vehicle.fuel_tank_capacity_liters || 50);
        const fuelPercentage = tankCapacity > 0 ? Math.round((fuelLevel / tankCapacity) * 100) : 0;
        const fuelConsumption = parseFloat(vehicle.fuel_consumption_l_per_km || 0.1);
        const efficiency = fuelConsumption > 0 ? (1 / fuelConsumption).toFixed(1) : 'N/A';
        
        const fuelLevelBadge = getFuelLevelBadge(fuelPercentage);
        
        const row = document.createElement('tr');
        row.innerHTML = `
          <td>${vehicle.article}</td>
          <td>${vehicle.unit}</td>
          <td>${vehicle.plate_number}</td>
          <td>
            <span class="badge ${getStatusBadgeClass(vehicle.status)}">
              ${formatStatus(vehicle.status)}
            </span>
          </td>
          <td>
            <div class="d-flex align-items-center">
              <div class="progress me-2" style="width: 60px; height: 8px;">
                <div class="progress-bar ${fuelLevelBadge.class}" role="progressbar" 
                     style="width: ${fuelPercentage}%" aria-valuenow="${fuelPercentage}" 
                     aria-valuemin="0" aria-valuemax="100"></div>
              </div>
              <small class="text-muted">${fuelLevel.toFixed(1)}L</small>
            </div>
            <small class="text-muted">${fuelPercentage}% of ${tankCapacity}L</small>
          </td>
          <td>
            <div class="small">
              <div><strong>${fuelConsumption.toFixed(3)} L/km</strong></div>
              <div class="text-muted">${efficiency} km/L</div>
            </div>
          </td>
          <td class="text-nowrap">${formatDateTime(vehicle.last_updated)}</td>
          <td class="text-center">
            <button class="btn btn-sm edit-vehicle" data-id="${vehicle.id}" 
              style="background-color: #00b4d8; color: #fff;" 
              data-bs-toggle="tooltip" data-bs-placement="top" title="Edit">
              <i class="fas fa-edit"></i>
            </button>
          </td>
        `;
        tableBody.appendChild(row);
      });
      
      // Initialize tooltips for new elements
      const tooltipTriggerList = [].slice.call(document.querySelectorAll('[data-bs-toggle="tooltip"]'));
      tooltipTriggerList.map(tooltipTriggerEl => new bootstrap.Tooltip(tooltipTriggerEl));
    }
    
    function getFuelLevelBadge(percentage) {
      if (percentage >= 75) return { class: 'bg-success' };
      if (percentage >= 50) return { class: 'bg-warning' };
      if (percentage >= 25) return { class: 'bg-warning' };
      return { class: 'bg-danger' };
    }

    function getStatusBadgeClass(status) {
      switch (status) {
        case 'active': return 'bg-success';
        case 'maintenance': return 'bg-warning text-dark';
        case 'out_of_service': return 'bg-danger';
        default: return 'bg-secondary';
      }
    }

    function formatStatus(status) {
      return status.split('_').map(word => word.charAt(0).toUpperCase() + word.slice(1)).join(' ');
    }

    function formatDateTime(datetime) {
      const date = new Date(datetime);
      return date.toLocaleString();
    }

    function showEditModal(vehicleId) {
      fetchFleetData().then(data => {
        const vehicle = data.find(v => v.id == vehicleId);
        if (vehicle) {
          document.getElementById('vehicleModalLabel').textContent = 'Edit Vehicle';
          document.getElementById('vehicleId').value = vehicle.id;
          document.getElementById('article').value = vehicle.article;
          document.getElementById('unit').value = vehicle.unit;
          document.getElementById('plate_number').value = vehicle.plate_number;
          document.getElementById('status').value = vehicle.status;
          
          // Populate fuel fields
          document.getElementById('fuel_consumption_l_per_km').value = vehicle.fuel_consumption_l_per_km || '';
          document.getElementById('fuel_tank_capacity_liters').value = vehicle.fuel_tank_capacity_liters || '';
          document.getElementById('current_fuel_level_liters').value = vehicle.current_fuel_level_liters || '';
          document.getElementById('fuel_cost_per_liter').value = vehicle.fuel_cost_per_liter || '';
          
          document.getElementById('notesContainer').style.display = 'block';
          vehicleModal.show();
        }
      });
    }

    function performSave(data, isEdit) {
      const action = isEdit ? 'update_vehicle' : 'add_vehicle';
      
      // Show loading state
      saveVehicleBtn.disabled = true;
      saveVehicleBtn.innerHTML = '<i class="fas fa-spinner fa-spin me-1"></i> Saving...';
      
      fetch(`fleet_api.php?action=${action}`, {
        method: 'POST',
        headers: {
          'Content-Type': 'application/json',
        },
        body: JSON.stringify(data)
      })
      .then(response => {
        if (!response.ok) {
          throw new Error(`HTTP error! status: ${response.status}`);
        }
        return response.json();
      })
      .then(result => {
        if (result.success) {
          vehicleModal.hide();
          loadFleetData();
          Swal.fire({
            title: 'Success!',
            text: `Vehicle ${isEdit ? 'updated' : 'added'} successfully`,
            icon: 'success',
            confirmButtonColor: '#003566'
          });
        } else {
          Swal.fire({
            title: 'Error',
            text: result.message || 'Operation failed',
            icon: 'error',
            confirmButtonColor: '#dc3545'
          });
        }
      })
      .catch(error => {
        console.error('Error saving vehicle:', error);
        Swal.fire({
          title: 'Error',
          text: 'Failed to save vehicle. Please check the console for details.',
          icon: 'error',
          confirmButtonColor: '#dc3545'
        });
      })
      .finally(() => {
        // Restore button state
        saveVehicleBtn.disabled = false;
        saveVehicleBtn.innerHTML = 'Save';
      });
    }

    function showOnMap(lat, lng) {
      const locationModal = new bootstrap.Modal(document.getElementById('locationModal'));
      const mapElement = document.getElementById('locationMap');
      
      document.getElementById('locationModalLabel').textContent = 'Vehicle Location';
      locationModal.show();
      
      // Initialize map (you'll need to include your map library)
      setTimeout(() => {
        console.log(`Initializing map at ${lat}, ${lng}`);
        // Example with Leaflet:
        // const map = L.map('locationMap').setView([lat, lng], 15);
        // L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png').addTo(map);
        // L.marker([lat, lng]).addTo(map).bindPopup('Vehicle Location');
      }, 500);
    }
  });
</script>
</body>
</html>

<?php
// Create a separate fleet_api.php file with this content:
/*
<?php
header('Content-Type: application/json');
require_once __DIR__ . '/../config/database.php';

$action = $_GET['action'] ?? '';

try {
    $pdo = new PDO("mysql:host=".DB_HOST.";dbname=".DB_NAME, DB_USER, DB_PASS);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

    switch ($action) {
        case 'get_fleet':
            $stmt = $pdo->query("SELECT * FROM fleet_vehicles ORDER BY last_updated DESC");
            $vehicles = $stmt->fetchAll(PDO::FETCH_ASSOC);
            echo json_encode(['success' => true, 'data' => $vehicles]);
            break;

        case 'add_vehicle':
            $data = json_decode(file_get_contents('php://input'), true);
            $stmt = $pdo->prepare("INSERT INTO fleet_vehicles (article, unit, plate_number, status) VALUES (?, ?, ?, ?)");
            $stmt->execute([$data['article'], $data['unit'], $data['plate_number'], $data['status']]);
            echo json_encode(['success' => true, 'message' => 'Vehicle added successfully']);
            break;

        case 'update_vehicle':
            $data = json_decode(file_get_contents('php://input'), true);
            $stmt = $pdo->prepare("UPDATE fleet_vehicles SET article=?, unit=?, plate_number=?, status=? WHERE id=?");
            $stmt->execute([$data['article'], $data['unit'], $data['plate_number'], $data['status'], $data['id']]);
            
            if ($data['status_changed']) {
                $stmt = $pdo->prepare("INSERT INTO fleet_status_history (vehicle_id, status, notes) VALUES (?, ?, ?)");
                $stmt->execute([$data['id'], $data['status'], $data['notes'] ?? 'Status updated']);
            }
            
            echo json_encode(['success' => true, 'message' => 'Vehicle updated successfully']);
            break;

        case 'delete_vehicle':
            require_once __DIR__ . '/../../config/security.php';
            $security = Security::getInstance();
            $id = $security->getGet('id', 'int', 0);
            if ($id <= 0) {
                echo json_encode(['success' => false, 'message' => 'Invalid vehicle ID']);
                break;
            }
            $stmt = $pdo->prepare("DELETE FROM fleet_vehicles WHERE id=?");
            $stmt->execute([$id]);
            echo json_encode(['success' => true, 'message' => 'Vehicle deleted successfully']);
            break;

        case 'update_location':
            $data = json_decode(file_get_contents('php://input'), true);
            $stmt = $pdo->prepare("UPDATE fleet_vehicles SET current_latitude=?, current_longitude=?, last_updated=NOW() WHERE id=?");
            $stmt->execute([$data['latitude'], $data['longitude'], $data['vehicle_id']]);
            
            $stmt = $pdo->prepare("INSERT INTO fleet_location_history (vehicle_id, latitude, longitude) VALUES (?, ?, ?)");
            $stmt->execute([$data['vehicle_id'], $data['latitude'], $data['longitude']]);
            
            echo json_encode(['success' => true]);
            break;

        default:
            echo json_encode(['success' => false, 'message' => 'Invalid action']);
    }
} catch (PDOException $e) {
    echo json_encode(['success' => false, 'message' => 'Database error: ' . $e->getMessage()]);
}
*/

?>