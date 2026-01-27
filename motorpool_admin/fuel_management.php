<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0"/>
  <title>Fuel Management | Smart Track</title>

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

    .fuel-card {
      border-left: 4px solid var(--accent);
      transition: transform 0.2s;
    }

    .fuel-card:hover {
      transform: translateY(-2px);
    }

    .fuel-level-bar {
      height: 20px;
      border-radius: 10px;
      position: relative;
      overflow: hidden;
    }

    .fuel-level-fill {
      height: 100%;
      transition: width 0.3s ease;
      border-radius: 10px;
    }

    .fuel-low { background: linear-gradient(90deg, #dc3545, #fd7e14); }
    .fuel-medium { background: linear-gradient(90deg, #ffc107, #fd7e14); }
    .fuel-high { background: linear-gradient(90deg, #28a745, #20c997); }

    .chart-container {
      position: relative;
      height: 300px;
    }

    .modal-dialog-centered {
      display: flex;
      align-items: center;
      min-height: calc(100% - 1rem);
    }
  </style>
</head>
<body>

<?php include __DIR__ . '/../pages/admin_sidebar.php'; ?>
<?php include __DIR__ . '/../pages/navbar.php'; ?>

<!-- Main Content -->
<div class="main-content" id="mainContent">
  <div class="container-fluid">
    <!-- Page Header -->
    <div class="row mb-4">
      <div class="col-12">
        <div class="d-flex justify-content-between align-items-center">
          <div>
            <h2 class="text-primary fw-bold mb-1">⛽ Fuel Management</h2>
            <p class="text-muted mb-0">Monitor and manage fleet fuel consumption and refueling</p>
          </div>
          <div class="btn-group">
            <button class="btn btn-primary" id="addRefuelBtn">
              <i class="fas fa-gas-pump me-2"></i>Record Refuel
            </button>
            <button class="btn btn-success" id="updateFuelFromGPSBtn">
              <i class="fas fa-sync-alt me-2"></i>Update from GPS
            </button>
          </div>
        </div>
      </div>
    </div>

    <!-- Fuel Overview Cards -->
    <div class="row g-4 mb-4">
      <div class="col-md-3">
        <div class="card fuel-card h-100">
          <div class="card-body text-center">
            <i class="fas fa-gas-pump fa-2x text-primary mb-3"></i>
            <h5 class="card-title">Total Vehicles</h5>
            <h3 class="text-primary" id="totalVehicles">0</h3>
          </div>
        </div>
      </div>
      <div class="col-md-3">
        <div class="card fuel-card h-100">
          <div class="card-body text-center">
            <i class="fas fa-tachometer-alt fa-2x text-warning mb-3"></i>
            <h5 class="card-title">Low Fuel Alert</h5>
            <h3 class="text-warning" id="lowFuelVehicles">0</h3>
          </div>
        </div>
      </div>
      <div class="col-md-3">
        <div class="card fuel-card h-100">
          <div class="card-body text-center">
            <i class="fas fa-chart-line fa-2x text-success mb-3"></i>
            <h5 class="card-title">Avg Efficiency</h5>
            <h3 class="text-success" id="avgEfficiency">0 km/L</h3>
          </div>
        </div>
      </div>
      <div class="col-md-3">
        <div class="card fuel-card h-100">
          <div class="card-body text-center">
            <i class="fas fa-peso-sign fa-2x text-info mb-3"></i>
            <h5 class="card-title">Monthly Cost</h5>
            <h3 class="text-info" id="monthlyCost">₱0</h3>
          </div>
        </div>
      </div>
    </div>

    <!-- Fuel Level Overview -->
    <div class="row g-4 mb-4">
      <div class="col-md-8">
        <div class="card">
          <div class="card-header">
            <h5 class="card-title mb-0">🚗 Vehicle Fuel Levels</h5>
          </div>
          <div class="card-body">
            <div class="table-responsive">
              <table class="table table-hover">
                <thead>
                  <tr>
                    <th>Vehicle</th>
                    <th>Fuel Level</th>
                    <th>Efficiency</th>
                    <th>Last Refuel</th>
                    <th>Action</th>
                  </tr>
                </thead>
                <tbody id="fuelLevelTableBody">
                  <!-- Dynamic content -->
                </tbody>
              </table>
            </div>
          </div>
        </div>
      </div>
    </div>

    <!-- Refueling History -->
    <div class="row g-4">
      <div class="col-12">
        <div class="card">
          <div class="card-header d-flex justify-content-between align-items-center">
            <h5 class="card-title mb-0">📋 Recent Refueling History</h5>
            <div class="btn-group" role="group">
              <button class="btn btn-outline-primary btn-sm" id="filterToday">Today</button>
              <button class="btn btn-outline-primary btn-sm" id="filterWeek">This Week</button>
              <button class="btn btn-outline-primary btn-sm" id="filterMonth">This Month</button>
            </div>
          </div>
          <div class="card-body">
            <div class="table-responsive">
              <table class="table table-hover">
                <thead>
                  <tr>
                    <th>Date</th>
                    <th>Vehicle</th>
                    <th>Amount (L)</th>
                    <th>Cost/Liter</th>
                    <th>Total Cost</th>
                    <th>Station</th>
                    <th>Type</th>
                  </tr>
                </thead>
                <tbody id="refuelHistoryTableBody">
                  <!-- Dynamic content -->
                </tbody>
              </table>
            </div>
          </div>
        </div>
      </div>
    </div>
  </div>
</div>

<!-- Refuel Modal -->
<div class="modal fade" id="refuelModal" tabindex="-1" aria-labelledby="refuelModalLabel" aria-hidden="true">
  <div class="modal-dialog modal-dialog-centered">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title" id="refuelModalLabel">Record Refuel</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
      </div>
      <div class="modal-body">
        <form id="refuelForm">
          <input type="hidden" id="refuelVehicleId" name="vehicle_id">
          <div class="mb-3">
            <label for="refuelAmount" class="form-label">Fuel Amount (Liters)</label>
            <input type="number" class="form-control" id="refuelAmount" name="fuel_amount_liters" 
                   step="0.01" min="0.01" max="1000" required>
          </div>
          <div class="mb-3">
            <label for="refuelCostPerLiter" class="form-label">Cost per Liter (₱)</label>
            <input type="number" class="form-control" id="refuelCostPerLiter" name="fuel_cost_per_liter" 
                   step="0.01" min="0.01" max="1000" required>
          </div>
          <div class="mb-3">
            <label for="refuelStation" class="form-label">Fuel Station</label>
            <input type="text" class="form-control" id="refuelStation" name="fuel_station" 
                   placeholder="e.g., Shell, Petron, Caltex">
          </div>
          <div class="mb-3">
            <label for="refuelType" class="form-label">Refuel Type</label>
            <select class="form-select" id="refuelType" name="refuel_type" required>
              <option value="full_tank">Full Tank</option>
              <option value="partial">Partial</option>
              <option value="emergency">Emergency</option>
            </select>
          </div>
          <div class="mb-3">
            <label for="refuelOdometer" class="form-label">Odometer Reading (km)</label>
            <input type="number" class="form-control" id="refuelOdometer" name="odometer_reading" 
                   min="0" placeholder="Auto-calculated from GPS distance" readonly>
            <div class="form-text">Automatically calculated from GPS distance traveled</div>
          </div>
          <div class="mb-3">
            <label for="refuelNotes" class="form-label">Notes</label>
            <textarea class="form-control" id="refuelNotes" name="notes" rows="2" 
                      placeholder="Additional notes..."></textarea>
          </div>
          <div class="alert alert-info">
            <strong>Total Cost: </strong><span id="totalCostDisplay">₱0.00</span>
          </div>
        </form>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
        <button type="button" class="btn btn-primary" id="saveRefuel">Record Refuel</button>
      </div>
    </div>
  </div>
</div>

<!-- JS -->
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
<script>
  // Sidebar toggle functionality
  const burgerBtn = document.getElementById('burgerBtn');
  const sidebar = document.getElementById('sidebar');
  const mainContent = document.getElementById('mainContent');
  const linkTexts = document.querySelectorAll('.link-text');

  if (burgerBtn) {
    burgerBtn.addEventListener('click', () => {
      const isCollapsed = sidebar.classList.toggle('collapsed');
      mainContent.classList.toggle('collapsed');
      linkTexts.forEach(text => {
        text.style.display = isCollapsed ? 'none' : 'inline';
      });
    });
  }

  // Initialize page
  document.addEventListener('DOMContentLoaded', function() {
    loadFuelData();
    loadRefuelHistory();
    setupEventListeners();
  });

  function setupEventListeners() {
    // Add refuel button
    document.getElementById('addRefuelBtn').addEventListener('click', showRefuelModal);
    
    // Update fuel from GPS button
    document.getElementById('updateFuelFromGPSBtn').addEventListener('click', updateFuelFromGPS);
    
    // Save refuel
    document.getElementById('saveRefuel').addEventListener('click', saveRefuel);
    
    // Calculate total cost
    document.getElementById('refuelAmount').addEventListener('input', calculateTotalCost);
    document.getElementById('refuelCostPerLiter').addEventListener('input', calculateTotalCost);
    
    // Filter buttons
    document.getElementById('filterToday').addEventListener('click', () => filterHistory('today'));
    document.getElementById('filterWeek').addEventListener('click', () => filterHistory('week'));
    document.getElementById('filterMonth').addEventListener('click', () => filterHistory('month'));
  }

  function loadFuelData() {
    fetch('fuel_management_api.php?action=get_fuel_overview')
      .then(response => response.json())
      .then(data => {
        if (data.success) {
          updateOverviewCards(data.overview);
          renderFuelLevelTable(data.vehicles);
          renderFuelConsumptionChart(data.consumption);
        }
      });
  }

  function loadRefuelHistory(filter = 'month') {
    fetch(`fuel_management_api.php?action=get_refuel_history&filter=${filter}`)
      .then(response => response.json())
      .then(data => {
        if (data.success) {
          renderRefuelHistoryTable(data.history);
        }
      });
  }

  function updateOverviewCards(overview) {
    document.getElementById('totalVehicles').textContent = overview.total_vehicles || 0;
    document.getElementById('lowFuelVehicles').textContent = overview.low_fuel_vehicles || 0;
    document.getElementById('avgEfficiency').textContent = `${overview.avg_efficiency || 0} km/L`;
    document.getElementById('monthlyCost').textContent = `₱${(overview.monthly_cost || 0).toLocaleString()}`;
  }

  function renderFuelLevelTable(vehicles) {
    const tbody = document.getElementById('fuelLevelTableBody');
    tbody.innerHTML = '';
    
    vehicles.forEach(vehicle => {
      const fuelLevel = parseFloat(vehicle.current_fuel_level_liters || 0);
      const tankCapacity = parseFloat(vehicle.fuel_tank_capacity_liters || 50);
      const percentage = tankCapacity > 0 ? Math.round((fuelLevel / tankCapacity) * 100) : 0;
      const efficiency = parseFloat(vehicle.fuel_efficiency_km_per_l || 0);
      
      const fuelClass = percentage >= 50 ? 'fuel-high' : percentage >= 25 ? 'fuel-medium' : 'fuel-low';
      
      const row = document.createElement('tr');
      row.innerHTML = `
        <td>
          <div>
            <strong>${vehicle.article}</strong><br>
            <small class="text-muted">${vehicle.plate_number}</small>
          </div>
        </td>
        <td>
          <div class="fuel-level-bar bg-light">
            <div class="fuel-level-fill ${fuelClass}" style="width: ${percentage}%"></div>
          </div>
          <small class="text-muted">${fuelLevel.toFixed(1)}L / ${tankCapacity}L (${percentage}%)</small>
        </td>
        <td>
          <strong>${efficiency.toFixed(1)} km/L</strong><br>
          <small class="text-muted">${(1/efficiency*1000).toFixed(1)} L/100km</small>
        </td>
        <td>
          <small>${vehicle.last_refuel_date ? new Date(vehicle.last_refuel_date).toLocaleDateString() : 'Never'}</small>
        </td>
        <td>
          <button class="btn btn-sm btn-success" onclick="showRefuelModal(${vehicle.id})">
            <i class="fas fa-gas-pump"></i> Refuel
          </button>
        </td>
      `;
      tbody.appendChild(row);
    });
  }


  function renderRefuelHistoryTable(history) {
    const tbody = document.getElementById('refuelHistoryTableBody');
    tbody.innerHTML = '';
    
    history.forEach(record => {
      const row = document.createElement('tr');
      row.innerHTML = `
        <td>${new Date(record.refuel_date).toLocaleDateString()}</td>
        <td>${record.vehicle_name}</td>
        <td>${parseFloat(record.fuel_amount_liters).toFixed(2)}L</td>
        <td>₱${parseFloat(record.fuel_cost_per_liter).toFixed(2)}</td>
        <td>₱${parseFloat(record.total_cost).toFixed(2)}</td>
        <td>${record.fuel_station || 'N/A'}</td>
        <td>
          <span class="badge ${getRefuelTypeBadge(record.refuel_type)}">
            ${record.refuel_type.replace('_', ' ').toUpperCase()}
          </span>
        </td>
      `;
      tbody.appendChild(row);
    });
  }

  function getRefuelTypeBadge(type) {
    switch(type) {
      case 'full_tank': return 'bg-success';
      case 'partial': return 'bg-warning text-dark';
      case 'emergency': return 'bg-danger';
      default: return 'bg-secondary';
    }
  }

  function showRefuelModal(vehicleId = null) {
    const modal = new bootstrap.Modal(document.getElementById('refuelModal'));
    const form = document.getElementById('refuelForm');
    
    form.reset();
    if (vehicleId) {
      document.getElementById('refuelVehicleId').value = vehicleId;
      
      // Fetch vehicle's GPS-based distance for odometer reading
      fetch(`fuel_management_api.php?action=get_vehicle_distance&vehicle_id=${vehicleId}`)
        .then(response => response.json())
        .then(data => {
          if (data.success && data.distance) {
            document.getElementById('refuelOdometer').value = data.distance.toFixed(2);
          }
        })
        .catch(error => {
          console.log('Could not fetch GPS distance:', error);
          document.getElementById('refuelOdometer').value = '';
        });
    }
    
    modal.show();
  }

  function calculateTotalCost() {
    const amount = parseFloat(document.getElementById('refuelAmount').value) || 0;
    const costPerLiter = parseFloat(document.getElementById('refuelCostPerLiter').value) || 0;
    const total = amount * costPerLiter;
    
    document.getElementById('totalCostDisplay').textContent = `₱${total.toFixed(2)}`;
  }

  function saveRefuel() {
    const formData = new FormData(document.getElementById('refuelForm'));
    const data = Object.fromEntries(formData.entries());
    
    fetch('fuel_management_api.php?action=record_refuel', {
      method: 'POST',
      headers: {
        'Content-Type': 'application/json',
      },
      body: JSON.stringify(data)
    })
    .then(response => response.json())
    .then(result => {
      if (result.success) {
        bootstrap.Modal.getInstance(document.getElementById('refuelModal')).hide();
        Swal.fire({
          title: 'Success!',
          text: 'Refuel recorded successfully',
          icon: 'success',
          confirmButtonColor: '#003566'
        });
        loadFuelData();
        loadRefuelHistory();
      } else {
        Swal.fire({
          title: 'Error',
          text: result.message || 'Failed to record refuel',
          icon: 'error',
          confirmButtonColor: '#dc3545'
        });
      }
    });
  }

  function filterHistory(filter) {
    loadRefuelHistory(filter);
    
    // Update button states
    document.querySelectorAll('#filterToday, #filterWeek, #filterMonth').forEach(btn => {
      btn.classList.remove('active');
    });
    document.getElementById(`filter${filter.charAt(0).toUpperCase() + filter.slice(1)}`).classList.add('active');
  }

  function updateFuelFromGPS() {
    const button = document.getElementById('updateFuelFromGPSBtn');
    const originalText = button.innerHTML;
    
    // Show loading state
    button.innerHTML = '<i class="fas fa-spinner fa-spin me-2"></i>Updating...';
    button.disabled = true;
    
    fetch('fuel_management_api.php?action=update_fuel_from_gps', {
      method: 'POST',
      headers: {
        'Content-Type': 'application/json',
      }
    })
    .then(response => response.json())
    .then(result => {
      if (result.success) {
        Swal.fire({
          title: 'Success!',
          text: `Fuel consumption updated for ${result.vehicles_updated} vehicles based on GPS data`,
          icon: 'success',
          confirmButtonColor: '#003566'
        });
        
        // Reload fuel data to show updated levels
        loadFuelData();
        loadRefuelHistory();
      } else {
        Swal.fire({
          title: 'Error',
          text: result.message || 'Failed to update fuel from GPS',
          icon: 'error',
          confirmButtonColor: '#dc3545'
        });
      }
    })
    .catch(error => {
      Swal.fire({
        title: 'Error',
        text: 'Network error occurred',
        icon: 'error',
        confirmButtonColor: '#dc3545'
      });
    })
    .finally(() => {
      // Restore button state
      button.innerHTML = originalText;
      button.disabled = false;
    });
  }
</script>
</body>
</html>
