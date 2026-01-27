<?php
session_start();
// Allow only Motor Pool Admin to access fleet reports
if (!isset($_SESSION['role']) || strtolower($_SESSION['role']) !== 'admin') {
    header("Location: ../index.php");
    exit;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0"/>
  <title>Fleet Reports | Smart Track</title>
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
  <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css" rel="stylesheet">
  <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
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
      margin-top: 70px;
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

    .chart-container {
      position: relative;
      height: 300px;
      width: 100%;
    }

    /* Fleet Report Cards */
    .fleet-card {
      transition: all 0.3s ease;
      border-radius: 12px;
      border: none;
      overflow: hidden;
      background: linear-gradient(135deg, #f8f9fa 0%, #e9ecef 100%);
    }

    .fleet-card:hover {
      transform: translateY(-3px);
      box-shadow: 0 8px 25px rgba(0, 0, 0, 0.1) !important;
    }

    .fleet-card .card-icon {
      width: 50px;
      height: 50px;
      border-radius: 50%;
      display: flex;
      align-items: center;
      justify-content: center;
      font-size: 1.5rem;
      color: white;
      background: linear-gradient(135deg, var(--primary), var(--accent));
    }

    .fleet-card h3 {
      font-size: 2rem;
      font-weight: 700;
      margin: 0;
    }

    .fleet-card h6 {
      font-size: 0.85rem;
      font-weight: 600;
      text-transform: uppercase;
      letter-spacing: 0.5px;
      margin-bottom: 0.5rem;
    }

    .filter-controls {
      background: white;
      border-radius: 12px;
      padding: 1.5rem;
      box-shadow: 0 2px 10px rgba(0, 0, 0, 0.05);
      margin-bottom: 2rem;
    }

    .chart-card {
      border-radius: 12px;
      border: none;
      box-shadow: 0 2px 10px rgba(0, 0, 0, 0.05);
      transition: all 0.3s ease;
    }

    .chart-card:hover {
      box-shadow: 0 4px 20px rgba(0, 0, 0, 0.1);
    }

    /* Fleet-specific styling */
    .fleet-status-good { color: #28a745; }
    .fleet-status-warning { color: #ffc107; }
    .fleet-status-danger { color: #dc3545; }
    .fleet-status-info { color: #17a2b8; }
  </style>
</head>
<body>

<?php include __DIR__ . '/../pages/admin_sidebar.php'; ?>
<?php include __DIR__ . '/../pages/admin_navbar.php'; ?>

<div class="main-content p-2" id="mainContent">
  <div class="container-fluid">
    <h2 class="text-primary fw-bold mb-4">🚛 Fleet Management Reports</h2>
    <p class="text-muted mb-4">Comprehensive fleet performance and operational analytics</p>

    <!-- Filter Controls -->
    <div class="filter-controls mb-4">
      <div class="row">
        <div class="col-md-3">
          <label class="form-label">Date Range</label>
          <select class="form-select" id="dateRange">
            <option value="7">Last 7 Days</option>
            <option value="30" selected>Last 30 Days</option>
            <option value="90">Last 90 Days</option>
            <option value="365">Last Year</option>
          </select>
        </div>
        <div class="col-md-3">
          <label class="form-label">Vehicle Type</label>
          <select class="form-select" id="vehicleType">
            <option value="all" selected>All Fleet Vehicles</option>
            <!-- Vehicle types will be loaded dynamically -->
          </select>
        </div>
        <div class="col-md-3">
          <label class="form-label">Driver</label>
          <select class="form-select" id="driverFilter">
            <option value="all" selected>All Assigned Drivers</option>
            <!-- Drivers will be loaded dynamically -->
          </select>
        </div>
        <div class="col-md-3">
          <label class="form-label">&nbsp;</label>
          <button class="btn btn-primary w-100" id="applyFilters">
            <i class="fas fa-filter me-2"></i>Apply Filters
          </button>
        </div>
      </div>
    </div>

    <!-- Fleet Summary Cards -->
    <div class="row g-4 mb-4">
      <div class="col-md-3">
        <div class="card shadow-sm fleet-card">
          <div class="card-body">
            <div class="d-flex justify-content-between align-items-center">
              <div>
                <h6 class="text-muted">Fleet Size</h6>
                <h3 class="fw-bold" id="totalFleetVehicles">-</h3>
                <span class="badge bg-primary">Total Vehicles</span>
              </div>
              <div class="card-icon"><i class="fas fa-truck"></i></div>
            </div>
          </div>
        </div>
      </div>
      <div class="col-md-3">
        <div class="card shadow-sm fleet-card">
          <div class="card-body">
            <div class="d-flex justify-content-between align-items-center">
              <div>
                <h6 class="text-muted">Active Fleet</h6>
                <h3 class="fw-bold fleet-status-good" id="activeFleetVehicles">-</h3>
                <span class="badge bg-success">Operational</span>
              </div>
              <div class="card-icon"><i class="fas fa-check-circle"></i></div>
            </div>
          </div>
        </div>
      </div>
      <div class="col-md-3">
        <div class="card shadow-sm fleet-card">
          <div class="card-body">
            <div class="d-flex justify-content-between align-items-center">
              <div>
                <h6 class="text-muted">Maintenance Due</h6>
                <h3 class="fw-bold fleet-status-warning" id="maintenanceDue">-</h3>
                <span class="badge bg-warning">Scheduled</span>
              </div>
              <div class="card-icon"><i class="fas fa-wrench"></i></div>
            </div>
          </div>
        </div>
      </div>
      <div class="col-md-3">
        <div class="card shadow-sm fleet-card">
          <div class="card-body">
            <div class="d-flex justify-content-between align-items-center">
              <div>
                <h6 class="text-muted">Fleet Utilization</h6>
                <h3 class="fw-bold fleet-status-info" id="fleetUtilization">-</h3>
                <span class="badge bg-info">% Active</span>
              </div>
              <div class="card-icon"><i class="fas fa-chart-pie"></i></div>
            </div>
          </div>
        </div>
      </div>
    </div>

    <!-- Fleet Performance Charts -->
    <div class="row g-4 mb-4">
      <div class="col-md-6">
        <div class="card chart-card">
          <div class="card-body">
            <h5 class="card-title">📊 Fleet Composition</h5>
            <div class="chart-container">
              <canvas id="fleetCompositionChart"></canvas>
            </div>
          </div>
        </div>
      </div>
      <div class="col-md-6">
        <div class="card chart-card">
          <div class="card-body">
            <h5 class="card-title">🔧 Maintenance Status</h5>
            <div class="chart-container">
              <canvas id="maintenanceStatusChart"></canvas>
            </div>
          </div>
        </div>
      </div>
    </div>

    <!-- Fleet Activity & Performance -->
    <div class="row g-4 mb-4">
      <div class="col-md-8">
        <div class="card chart-card">
          <div class="card-body">
            <h5 class="card-title">🚛 Fleet Activity Timeline</h5>
            <div class="chart-container" style="height: 350px;">
              <canvas id="fleetActivityChart"></canvas>
            </div>
          </div>
        </div>
      </div>
      <div class="col-md-4">
        <div class="card chart-card">
          <div class="card-body">
            <h5 class="card-title">⛽ Fuel Consumption</h5>
            <div class="chart-container">
              <canvas id="performanceMetricsChart"></canvas>
            </div>
          </div>
        </div>
      </div>
    </div>

    <!-- Fleet Efficiency & Cost Analysis -->
    <div class="row g-4 mb-4">
      <div class="col-md-6">
        <div class="card chart-card">
          <div class="card-body">
            <h5 class="card-title">⛽ Fuel Efficiency Trends</h5>
            <div class="chart-container">
              <canvas id="fuelEfficiencyChart"></canvas>
            </div>
          </div>
        </div>
      </div>
      <div class="col-md-6">
        <div class="card chart-card">
          <div class="card-body">
            <h5 class="card-title">🚨 Emergency Maintenance Costing</h5>
            <div class="chart-container">
              <canvas id="maintenanceCostChart"></canvas>
            </div>
          </div>
        </div>
      </div>
    </div>

    <!-- Fleet Alerts & Notifications -->
    <div class="row g-4 mb-4">
      <div class="col-12">
        <div class="card chart-card">
          <div class="card-body">
            <h5 class="card-title">🚨 Fleet Alerts & Issues</h5>
            <div class="table-responsive">
              <table class="table table-hover">
                <thead>
                  <tr>
                    <th>Vehicle</th>
                    <th>Driver</th>
                    <th>Issue Type</th>
                    <th>Priority</th>
                    <th>Status</th>
                    <th>Date</th>
                    <th>Actions</th>
                  </tr>
                </thead>
                <tbody id="fleetAlertsTable">
                  <tr>
                    <td colspan="7" class="text-center text-muted">Loading fleet alerts...</td>
                  </tr>
                </tbody>
              </table>
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
document.addEventListener('DOMContentLoaded', function () {
  // Load drivers and vehicle types for filter dropdowns first
  Promise.all([
    loadDriversForFilter(),
    loadVehicleTypesForFilter()
  ]).then(() => {
    // Add event listeners after loading all filters
    addFilterEventListeners();
    // Then load initial fleet report data
    loadFleetReportData(getFilterParams());
  });
  
  // Refresh data every 5 minutes
  setInterval(() => loadFleetReportData(getFilterParams()), 300000);
});

// Get current filter parameters
function getFilterParams() {
  const dateRange = document.getElementById('dateRange').value;
  const vehicleType = document.getElementById('vehicleType').value;
  const driverId = document.getElementById('driverFilter').value;
  
  return {
    dateRange: dateRange,
    vehicleType: vehicleType,
    driverId: driverId
  };
}

// Load drivers for filter dropdown
async function loadDriversForFilter() {
  try {
    const response = await fetch('reports_api.php?action=get_drivers_for_filter');
    const data = await response.json();
    
    if (data.success) {
      const driverFilter = document.getElementById('driverFilter');
      driverFilter.innerHTML = '<option value="all" selected>All Assigned Drivers</option>';
      
      data.data.forEach(driver => {
        const option = document.createElement('option');
        option.value = driver.user_id;
        option.textContent = driver.full_name;
        driverFilter.appendChild(option);
      });
    }
  } catch (error) {
    console.error('Error loading drivers for filter:', error);
  }
}

// Load vehicle types for filter dropdown
async function loadVehicleTypesForFilter() {
  try {
    const response = await fetch('reports_api.php?action=get_vehicle_types_for_filter');
    const data = await response.json();
    
    if (data.success) {
      const vehicleTypeFilter = document.getElementById('vehicleType');
      vehicleTypeFilter.innerHTML = '<option value="all" selected>All Fleet Vehicles</option>';
      
      data.data.forEach(vehicleType => {
        const option = document.createElement('option');
        option.value = vehicleType.vehicle_type;
        option.textContent = vehicleType.vehicle_type;
        vehicleTypeFilter.appendChild(option);
      });
    }
  } catch (error) {
    console.error('Error loading vehicle types for filter:', error);
  }
}

// Add event listeners after loading all filters
function addFilterEventListeners() {
  document.getElementById('dateRange').addEventListener('change', () => loadFleetReportData(getFilterParams()));
  document.getElementById('vehicleType').addEventListener('change', () => loadFleetReportData(getFilterParams()));
  document.getElementById('driverFilter').addEventListener('change', () => loadFleetReportData(getFilterParams()));
  document.getElementById('applyFilters').addEventListener('click', () => loadFleetReportData(getFilterParams()));
}

async function loadFleetReportData(filters = {}) {
  try {
    console.log('Loading fleet report data with filters:', filters);
    
    // Build query parameters
    const params = new URLSearchParams();
    if (filters.dateRange) params.append('dateRange', filters.dateRange);
    if (filters.vehicleType) params.append('vehicleType', filters.vehicleType);
    if (filters.driverId) params.append('driverId', filters.driverId);
    
    // Load fleet summary data
    const summaryResponse = await fetch(`reports_api.php?action=get_summary&${params.toString()}`);
    const summaryData = await summaryResponse.json();
    console.log('Fleet summary data:', summaryData);
    
    if (summaryData.success) {
      updateFleetSummaryCards(summaryData.data);
    }
    
    // Load vehicle distribution (fleet composition)
    const distributionResponse = await fetch(`reports_api.php?action=get_vehicle_distribution&${params.toString()}`);
    const distributionData = await distributionResponse.json();
    
    if (distributionData.success) {
      updateFleetCompositionChart(distributionData.data);
    }
    
    // Load maintenance status
    const maintenanceResponse = await fetch(`reports_api.php?action=get_maintenance_status&${params.toString()}`);
    const maintenanceData = await maintenanceResponse.json();
    
    if (maintenanceData.success) {
      updateMaintenanceStatusChart(maintenanceData.data);
    }
    
    // Load fleet activity timeline (real data)
    const activityResponse = await fetch(`reports_api.php?action=get_fleet_activity_timeline&${params.toString()}`);
    const activityData = await activityResponse.json();
    
    if (activityData.success) {
      updateFleetActivityChart(activityData.data);
    }
    
    // Load fuel consumption data (from distance trend which includes fuel data)
    const fuelConsumptionResponse = await fetch(`reports_api.php?action=get_distance_trend&${params.toString()}`);
    const fuelConsumptionData = await fuelConsumptionResponse.json();
    
    if (fuelConsumptionData.success) {
      updatePerformanceMetricsChart(fuelConsumptionData.data);
    }
    
    // Load fuel efficiency
    const fuelResponse = await fetch(`reports_api.php?action=get_fuel_efficiency&${params.toString()}`);
    const fuelData = await fuelResponse.json();
    
    if (fuelData.success) {
      updateFuelEfficiencyChart(fuelData.data);
    }
    
    // Load maintenance cost analysis (real data)
    const maintenanceCostResponse = await fetch(`reports_api.php?action=get_maintenance_cost_analysis&${params.toString()}`);
    const maintenanceCostData = await maintenanceCostResponse.json();
    
    if (maintenanceCostData.success) {
      updateMaintenanceCostChart(maintenanceCostData.data);
    }
    
    // Load fleet alerts (real data)
    const alertsResponse = await fetch(`reports_api.php?action=get_fleet_alerts&${params.toString()}`);
    const alertsData = await alertsResponse.json();
    
    console.log('Fleet alerts response:', alertsData);
    
    if (alertsData.success) {
      updateFleetAlertsTable(alertsData.data);
    } else {
      console.error('Fleet alerts error:', alertsData.error);
      // Show error in table
      const tbody = document.getElementById('fleetAlertsTable');
      tbody.innerHTML = `<tr><td colspan="7" class="text-center text-danger">❌ Error loading alerts: ${alertsData.error || 'Unknown error'}</td></tr>`;
    }
    
  } catch (error) {
    console.error('Error loading fleet report data:', error);
  }
}

function updateFleetSummaryCards(data) {
  document.getElementById('totalFleetVehicles').textContent = data.total_vehicles || 0;
  document.getElementById('activeFleetVehicles').textContent = data.active_vehicles || 0;
  
  // Calculate maintenance due (this would need to be added to the API)
  const maintenanceDue = data.maintenance_due || Math.floor((data.total_vehicles || 0) * 0.2); // 20% estimate
  document.getElementById('maintenanceDue').textContent = maintenanceDue;
  
  // Calculate fleet utilization percentage
  const totalVehicles = data.total_vehicles || 0;
  const activeVehicles = data.active_vehicles || 0;
  const utilization = totalVehicles > 0 ? Math.round((activeVehicles / totalVehicles) * 100) : 0;
  document.getElementById('fleetUtilization').textContent = utilization + '%';
}

function updateFleetCompositionChart(data) {
  const ctx = document.getElementById('fleetCompositionChart').getContext('2d');
  
  // Check if chart exists and destroy it properly
  if (window.fleetCompositionChart && typeof window.fleetCompositionChart.destroy === 'function') {
    window.fleetCompositionChart.destroy();
  }
  
  window.fleetCompositionChart = new Chart(ctx, {
    type: 'doughnut',
    data: {
      labels: data.map(item => item.type),
      datasets: [{
        data: data.map(item => item.count),
        backgroundColor: ['#003566', '#00b4d8', '#2a9d8f', '#f4a261', '#e76f51', '#264653', '#e9c46a']
      }]
    },
    options: { 
      responsive: true, 
      maintainAspectRatio: false,
      plugins: {
        legend: {
          position: 'bottom'
        },
        title: {
          display: true,
          text: 'Fleet Composition by Vehicle Type'
        }
      }
    }
  });
}

function updateMaintenanceStatusChart(data) {
  const ctx = document.getElementById('maintenanceStatusChart').getContext('2d');
  
  // Check if chart exists and destroy it properly
  if (window.maintenanceStatusChart && typeof window.maintenanceStatusChart.destroy === 'function') {
    window.maintenanceStatusChart.destroy();
  }
  
  window.maintenanceStatusChart = new Chart(ctx, {
    type: 'bar',
    data: {
      labels: data.map(item => item.status),
      datasets: [{
        label: 'Vehicles',
        data: data.map(item => item.count),
        backgroundColor: ['#28a745', '#ffc107', '#dc3545', '#17a2b8']
      }]
    },
    options: { 
      responsive: true, 
      maintainAspectRatio: false,
      scales: {
        y: {
          beginAtZero: true
        }
      }
    }
  });
}

function updateFleetActivityChart(data) {
  const ctx = document.getElementById('fleetActivityChart').getContext('2d');
  
  // Check if chart exists and destroy it properly
  if (window.fleetActivityChart && typeof window.fleetActivityChart.destroy === 'function') {
    window.fleetActivityChart.destroy();
  }
  
  // Check if we have real data
  const hasRealData = data && data.length > 0 && data.some(item => item.active_vehicles > 0);
  
  if (!hasRealData) {
    // Show empty state with sample data
    const sampleLabels = [];
    const sampleActivity = [];
    for (let i = 6; i >= 0; i--) {
      const date = new Date();
      date.setDate(date.getDate() - i);
      sampleLabels.push(date.toLocaleDateString());
      sampleActivity.push(0);
    }
    
    window.fleetActivityChart = new Chart(ctx, {
      type: 'line',
      data: {
        labels: sampleLabels,
        datasets: [{
          label: 'Fleet Activity Score (No Data)',
          data: sampleActivity,
          backgroundColor: 'rgba(233,236,239,0.2)',
          borderColor: '#6c757d',
          borderWidth: 2,
          tension: 0.4,
          fill: true
        }]
      },
      options: {
        responsive: true,
        maintainAspectRatio: false,
        scales: {
          y: {
            beginAtZero: true,
            max: 100,
            title: { display: true, text: 'Activity Score (0-100)' }
          }
        },
        plugins: {
          title: {
            display: true,
            text: 'Fleet Activity Timeline (No GPS Data Available)'
          }
        }
      }
    });
    return;
  }
  
  const labels = data.map(item => new Date(item.date).toLocaleDateString());
  const activity = data.map(item => parseFloat(item.activity_score) || 0);
  const activeVehicles = data.map(item => item.active_vehicles || 0);
  const avgSpeed = data.map(item => parseFloat(item.avg_speed) || 0);
  
  window.fleetActivityChart = new Chart(ctx, {
    type: 'line',
    data: {
      labels: labels,
      datasets: [
        {
          label: 'Activity Score',
          data: activity,
          backgroundColor: 'rgba(0,181,216,0.2)',
          borderColor: '#00b4d8',
          borderWidth: 3,
          tension: 0.4,
          fill: true,
          yAxisID: 'y'
        },
        {
          label: 'Active Vehicles',
          data: activeVehicles,
          backgroundColor: 'rgba(42,157,143,0.1)',
          borderColor: '#2a9d8f',
          borderWidth: 2,
          tension: 0.4,
          fill: false,
          yAxisID: 'y1'
        },
        {
          label: 'Avg Speed (km/h)',
          data: avgSpeed,
          backgroundColor: 'rgba(244,162,97,0.1)',
          borderColor: '#f4a261',
          borderWidth: 2,
          tension: 0.4,
          fill: false,
          yAxisID: 'y2'
        }
      ]
    },
    options: {
      responsive: true,
      maintainAspectRatio: false,
      interaction: {
        mode: 'index',
        intersect: false,
      },
      scales: {
        y: {
          type: 'linear',
          display: true,
          position: 'left',
          beginAtZero: true,
          max: 100,
          title: { 
            display: true, 
            text: 'Activity Score (0-100)' 
          }
        },
        y1: {
          type: 'linear',
          display: true,
          position: 'right',
          beginAtZero: true,
          title: { 
            display: true, 
            text: 'Active Vehicles' 
          },
          grid: {
            drawOnChartArea: false,
          },
        },
        y2: {
          type: 'linear',
          display: false,
          beginAtZero: true,
          title: { 
            display: true, 
            text: 'Speed (km/h)' 
          }
        }
      },
      plugins: {
        title: {
          display: true,
          text: 'Fleet Activity Timeline - Real-time GPS Data'
        },
        legend: {
          display: true,
          position: 'top'
        },
        tooltip: {
          callbacks: {
            afterLabel: function(context) {
              const index = context.dataIndex;
              const item = data[index];
              return [
                `Active Vehicles: ${item.active_vehicles}`,
                `GPS Points: ${item.total_gps_points}`,
                `Avg Speed: ${parseFloat(item.avg_speed).toFixed(1)} km/h`,
                `Geofence Events: ${item.geofence_events}`
              ];
            }
          }
        }
      }
    }
  });
}

function updatePerformanceMetricsChart(data) {
  const ctx = document.getElementById('performanceMetricsChart').getContext('2d');
  
  // Check if chart exists and destroy it properly
  if (window.performanceMetricsChart && typeof window.performanceMetricsChart.destroy === 'function') {
    window.performanceMetricsChart.destroy();
  }
  
  // Check if we have real fuel data
  if (!data || data.length === 0) {
    // Show empty state
    window.performanceMetricsChart = new Chart(ctx, {
      type: 'bar',
      data: {
        labels: ['No Data'],
        datasets: [{
          label: 'Fuel (L)',
          data: [0],
          backgroundColor: '#e9ecef'
        }]
      },
      options: {
        responsive: true,
        maintainAspectRatio: false,
        plugins: {
          title: {
            display: true,
            text: 'No fuel consumption data available'
          }
        }
      }
    });
    return;
  }
  
  // Sort by fuel consumption (highest first) and take top 5
  const sortedData = data
    .filter(item => item.estimated_fuel_liters > 0)
    .sort((a, b) => parseFloat(b.estimated_fuel_liters) - parseFloat(a.estimated_fuel_liters))
    .slice(0, 5);
  
  const labels = sortedData.map(item => {
    const vehicle = item.vehicle || item.article || 'Unknown';
    const plate = item.plate_number;
    return plate ? `${vehicle}\n(${plate})` : vehicle;
  });
  
  const fuelData = sortedData.map(item => parseFloat(item.estimated_fuel_liters || 0).toFixed(2));
  
  const colors = ['#e76f51', '#f4a261', '#e9c46a', '#2a9d8f', '#264653'];
  
  window.performanceMetricsChart = new Chart(ctx, {
    type: 'bar',
    data: {
      labels: labels,
      datasets: [{
        label: 'Fuel Consumed (L)',
        data: fuelData,
        backgroundColor: colors,
        borderColor: colors,
        borderWidth: 2,
        borderRadius: 6
      }]
    },
    options: {
      responsive: true,
      maintainAspectRatio: false,
      plugins: {
        title: {
          display: true,
          text: '⛽ Top 5 Fuel Consumers',
          font: { size: 14, weight: 'bold' }
        },
        legend: {
          display: false
        },
        tooltip: {
          callbacks: {
            title: function(context) {
              return `🚗 ${context[0].label}`;
            },
            label: function(context) {
              const index = context.dataIndex;
              const item = sortedData[index];
              return [
                `⛽ Fuel: ${parseFloat(item.estimated_fuel_liters).toFixed(2)} L`,
                `📏 Distance: ${parseFloat(item.distance_km).toFixed(2)} km`,
                `📊 Efficiency: ${item.fuel_efficiency}`
              ];
            }
          }
        }
      },
      scales: {
        y: {
          beginAtZero: true,
          title: {
            display: true,
            text: 'Fuel Consumed (Liters)',
            color: '#003566',
            font: { weight: 'bold' }
          },
          ticks: {
            callback: function(value) {
              return value + ' L';
            }
          }
        },
        x: {
          title: {
            display: false
          },
          ticks: {
            font: { size: 10 },
            maxRotation: 45,
            minRotation: 0
          }
        }
      }
    }
  });
}

function updateFuelEfficiencyChart(data) {
  const ctx = document.getElementById('fuelEfficiencyChart').getContext('2d');
  
  // Check if chart exists and destroy it properly
  if (window.fuelEfficiencyChart && typeof window.fuelEfficiencyChart.destroy === 'function') {
    window.fuelEfficiencyChart.destroy();
  }
  
  if (!data || data.length === 0) {
    // Show no data message
    ctx.fillStyle = '#666';
    ctx.font = '16px Arial';
    ctx.textAlign = 'center';
    ctx.fillText('No fuel efficiency data available', ctx.canvas.width / 2, ctx.canvas.height / 2);
    return;
  }
  
  // Group data by date for timeline view
  const groupedData = data.reduce((acc, item) => {
    const date = item.date || 'Unknown';
    if (!acc[date]) {
      acc[date] = [];
    }
    acc[date].push(item);
    return acc;
  }, {});
  
  // Calculate average efficiency by date
  const dates = Object.keys(groupedData).sort();
  const avgEfficiency = dates.map(date => {
    const dayData = groupedData[date];
    const totalEfficiency = dayData.reduce((sum, item) => sum + (item.estimated_km_per_liter || 0), 0);
    return totalEfficiency / dayData.length;
  });
  
  // Get top 5 most efficient vehicles for comparison
  const topVehicles = data
    .sort((a, b) => (b.estimated_km_per_liter || 0) - (a.estimated_km_per_liter || 0))
    .slice(0, 5);
  
  window.fuelEfficiencyChart = new Chart(ctx, {
    type: 'line',
    data: {
      labels: dates.length > 0 ? dates : topVehicles.map(item => `${item.vehicle_type} (${item.plate_number})`),
      datasets: [
        {
          label: 'Average Fleet Efficiency (km/L)',
          data: dates.length > 0 ? avgEfficiency : topVehicles.map(item => item.estimated_km_per_liter || 0),
          borderColor: '#00b4d8',
          backgroundColor: 'rgba(0, 180, 216, 0.1)',
          tension: 0.4,
          fill: true,
          yAxisID: 'y'
        },
        {
          label: 'Average Speed (km/h)',
          data: dates.length > 0 ? dates.map(date => {
            const dayData = groupedData[date];
            const totalSpeed = dayData.reduce((sum, item) => sum + (item.avg_speed || 0), 0);
            return totalSpeed / dayData.length;
          }) : topVehicles.map(item => item.avg_speed || 0),
          borderColor: '#f4a261',
          backgroundColor: 'rgba(244, 162, 97, 0.1)',
          tension: 0.4,
          fill: false,
          yAxisID: 'y1'
        }
      ]
    },
    options: { 
      responsive: true, 
      maintainAspectRatio: false,
      plugins: {
        title: {
          display: true,
          text: 'Fuel Efficiency Trends & Speed Analysis'
        },
        legend: {
          display: true,
          position: 'top'
        }
      },
      scales: {
        y: {
          type: 'linear',
          display: true,
          position: 'left',
          beginAtZero: true,
          title: {
            display: true,
            text: 'Fuel Efficiency (km/L)',
            color: '#00b4d8'
          },
          ticks: {
            color: '#00b4d8'
          }
        },
        y1: {
          type: 'linear',
          display: true,
          position: 'right',
          beginAtZero: true,
          title: {
            display: true,
            text: 'Average Speed (km/h)',
            color: '#f4a261'
          },
          ticks: {
            color: '#f4a261'
          },
          grid: {
            drawOnChartArea: false,
          }
        },
        x: {
          title: {
            display: true,
            text: dates.length > 0 ? 'Date' : 'Vehicle'
          }
        }
      }
    }
  });
}

function updateMaintenanceCostChart(data) {
  const ctx = document.getElementById('maintenanceCostChart').getContext('2d');
  
  // Check if chart exists and destroy it properly
  if (window.maintenanceCostChart && typeof window.maintenanceCostChart.destroy === 'function') {
    window.maintenanceCostChart.destroy();
  }
  
  // Check if we have real data
  const hasData = data && data.length > 0 && data.some(item => item.actual_cost > 0 || item.estimated_cost > 0);
  
  if (!hasData) {
    // Show empty state
    window.maintenanceCostChart = new Chart(ctx, {
      type: 'bar',
      data: {
        labels: ['Critical Repairs', 'High Priority', 'Medium Priority', 'Low Priority'],
        datasets: [{
          label: 'Actual Cost (PHP)',
          data: [0, 0, 0, 0],
          backgroundColor: '#e9ecef',
          borderColor: '#6c757d',
          borderWidth: 1
        }]
      },
      options: { 
        responsive: true, 
        maintainAspectRatio: false,
        scales: {
          y: {
            beginAtZero: true,
            title: {
              display: true,
              text: 'Cost (PHP)'
            }
          }
        },
        plugins: {
          legend: {
            display: true,
            position: 'top'
          },
          title: {
            display: true,
            text: 'Emergency Maintenance Costing by Priority (No Data Available)'
          }
        }
      }
    });
    return;
  }
  
  window.maintenanceCostChart = new Chart(ctx, {
    type: 'bar',
    data: {
      labels: data.map(item => item.category),
      datasets: [
        {
          label: 'Actual Cost (PHP)',
          data: data.map(item => parseFloat(item.actual_cost) || 0),
          backgroundColor: '#dc3545',
          borderColor: '#c82333',
          borderWidth: 1
        },
        {
          label: 'Estimated Cost (PHP)',
          data: data.map(item => parseFloat(item.estimated_cost) || 0),
          backgroundColor: '#fd7e14',
          borderColor: '#e55a00',
          borderWidth: 1
        }
      ]
    },
    options: { 
      responsive: true, 
      maintainAspectRatio: false,
      scales: {
        y: {
          beginAtZero: true,
          title: {
            display: true,
            text: 'Cost (PHP)'
          }
        }
      },
      plugins: {
        legend: {
          display: true,
          position: 'top'
        },
        title: {
          display: true,
          text: 'Emergency Maintenance Costing by Priority Level'
        },
        tooltip: {
          callbacks: {
            afterLabel: function(context) {
              const index = context.dataIndex;
              const item = data[index];
              return [
                `Count: ${item.count} repairs`,
                `Avg Cost: ₱${parseFloat(item.avg_cost_per_repair).toFixed(2)}`,
                `Avg Estimated: ₱${parseFloat(item.avg_estimated_cost).toFixed(2)}`
              ];
            }
          }
        }
      }
    }
  });
}

function updateFleetAlertsTable(alerts) {
  const tbody = document.getElementById('fleetAlertsTable');
  
  // Check if alerts data is valid
  if (!alerts || alerts.length === 0) {
    tbody.innerHTML = '<tr><td colspan="7" class="text-center text-muted">✅ No fleet alerts at this time - All systems operational!</td></tr>';
    return;
  }
  
  // Use real data from the API
  tbody.innerHTML = alerts.map(alert => {
    // Format the issue type based on alert type
    let issueType = alert.issue_type;
    let issueDescription = '';
    let badgeClass = 'bg-secondary';
    
    if (alert.alert_type === 'maintenance') {
      issueType = 'Emergency Maintenance';
      issueDescription = 'Vehicle requires immediate maintenance attention';
      badgeClass = 'bg-danger';
    } else if (alert.issue_type === 'entry') {
      issueType = 'Geofence Entry';
      issueDescription = `Entered ${alert.geofence_name || 'geofenced area'}`;
      badgeClass = 'bg-info';
    } else if (alert.issue_type === 'exit') {
      issueType = 'Geofence Exit';
      issueDescription = `Exited ${alert.geofence_name || 'geofenced area'}`;
      badgeClass = 'bg-warning';
    }
    
    return `
      <tr>
        <td><strong>${alert.vehicle_name || 'Unknown Vehicle'} (${alert.plate_number || 'N/A'})</strong></td>
        <td>${alert.driver_name || 'Unassigned'}</td>
        <td><span class="badge ${badgeClass}">${issueType}</span></td>
        <td><span class="badge bg-${alert.priority === 'High' ? 'danger' : alert.priority === 'Medium' ? 'warning' : 'info'}">${alert.priority}</span></td>
        <td><span class="badge bg-${alert.status === 'Resolved' ? 'success' : alert.status === 'In Progress' ? 'warning' : 'secondary'}">${alert.status}</span></td>
        <td>${formatDate(alert.created_at)}</td>
        <td>
          <button class="btn btn-sm btn-outline-primary" onclick="viewAlertDetails('${alert.id}', '${issueType}', '${issueDescription}', '${alert.geofence_name || 'N/A'}', '${alert.alert_type}')" title="View Details">
            <i class="fas fa-eye"></i>
          </button>
          ${alert.status !== 'Resolved' ? `
            <button class="btn btn-sm btn-outline-success" onclick="resolveAlert('${alert.id}')" title="Mark Resolved">
              <i class="fas fa-check"></i>
            </button>
          ` : ''}
        </td>
      </tr>
    `;
  }).join('');
}

// Helper functions for realistic data
function getRandomIssueType() {
  const issues = [
    'GPS Signal Lost', 'Low Fuel Warning', 'Maintenance Due', 'Speed Violation',
    'Geofence Exit', 'Engine Warning', 'Tire Pressure Low', 'Battery Low',
    'Route Deviation', 'Emergency Stop'
  ];
  return issues[Math.floor(Math.random() * issues.length)];
}

function getRandomPriority() {
  const priorities = ['High', 'Medium', 'Low'];
  const weights = [0.2, 0.5, 0.3]; // 20% High, 50% Medium, 30% Low
  const random = Math.random();
  let cumulative = 0;
  
  for (let i = 0; i < priorities.length; i++) {
    cumulative += weights[i];
    if (random <= cumulative) {
      return priorities[i];
    }
  }
  return 'Medium';
}

function getRandomStatus() {
  const statuses = ['Open', 'In Progress', 'Resolved'];
  const weights = [0.3, 0.4, 0.3]; // 30% Open, 40% In Progress, 30% Resolved
  const random = Math.random();
  let cumulative = 0;
  
  for (let i = 0; i < statuses.length; i++) {
    cumulative += weights[i];
    if (random <= cumulative) {
      return statuses[i];
    }
  }
  return 'In Progress';
}

function formatDate(dateString) {
  try {
    const date = new Date(dateString);
    if (isNaN(date.getTime())) {
      return 'N/A';
    }
    return date.toLocaleDateString('en-US', {
      year: 'numeric',
      month: 'short',
      day: 'numeric',
      hour: '2-digit',
      minute: '2-digit'
    });
  } catch (error) {
    return 'N/A';
  }
}

function viewAlertDetails(alertId, issueType, issueDescription, geofenceName, alertType) {
  // Get alert data from the table
  const tableRow = document.querySelector(`button[onclick*="viewAlertDetails('${alertId}'"]`).closest('tr');
  const cells = tableRow.querySelectorAll('td');
  
  const vehicle = cells[0].textContent.trim();
  const driver = cells[1].textContent.trim();
  const priority = cells[3].textContent.trim();
  const status = cells[4].textContent.trim();
  const date = cells[5].textContent.trim();
  
  // Create detailed alert information based on alert type
  let alertDetails = `
    <div class="text-start">
      <div class="row mb-3">
        <div class="col-4"><strong>Vehicle:</strong></div>
        <div class="col-8">${vehicle}</div>
      </div>
      <div class="row mb-3">
        <div class="col-4"><strong>Driver:</strong></div>
        <div class="col-8">${driver}</div>
      </div>
      <div class="row mb-3">
        <div class="col-4"><strong>Issue Type:</strong></div>
        <div class="col-8">${issueType}</div>
      </div>
      <div class="row mb-3">
        <div class="col-4"><strong>Priority:</strong></div>
        <div class="col-8">${priority}</div>
      </div>
      <div class="row mb-3">
        <div class="col-4"><strong>Status:</strong></div>
        <div class="col-8">${status}</div>
      </div>
      <div class="row mb-3">
        <div class="col-4"><strong>Date:</strong></div>
        <div class="col-8">${date}</div>
      </div>
  `;
  
  // Add specific details based on alert type
  if (alertType === 'maintenance') {
    alertDetails += `
      <hr>
      <div class="row mb-3">
        <div class="col-4"><strong>Alert Type:</strong></div>
        <div class="col-8"><span class="badge bg-danger">Emergency Maintenance</span></div>
      </div>
      <div class="row mb-3">
        <div class="col-4"><strong>Description:</strong></div>
        <div class="col-8">Vehicle requires immediate maintenance attention. This could be due to mechanical issues, safety concerns, or performance problems.</div>
      </div>
      <div class="row mb-3">
        <div class="col-4"><strong>Recommended Action:</strong></div>
        <div class="col-8">Contact maintenance team immediately. Remove vehicle from active routes until issue is resolved. Schedule diagnostic inspection.</div>
      </div>
    `;
  } else if (alertType === 'geofence') {
    alertDetails += `
      <hr>
      <div class="row mb-3">
        <div class="col-4"><strong>Alert Type:</strong></div>
        <div class="col-8"><span class="badge bg-info">Geofence Event</span></div>
      </div>
      <div class="row mb-3">
        <div class="col-4"><strong>Geofence:</strong></div>
        <div class="col-8">${geofenceName || 'N/A'}</div>
      </div>
      <div class="row mb-3">
        <div class="col-4"><strong>Description:</strong></div>
        <div class="col-8">${issueDescription}</div>
      </div>
      <div class="row mb-3">
        <div class="col-4"><strong>Recommended Action:</strong></div>
        <div class="col-8">${getRecommendedAction(issueType)}</div>
      </div>
    `;
  }
  
  alertDetails += `</div>`;
  
  Swal.fire({
    title: alertType === 'maintenance' ? '🔧 Emergency Maintenance Alert' : '🚨 Fleet Alert Details',
    html: alertDetails,
    icon: priority === 'High' ? 'error' : priority === 'Medium' ? 'warning' : 'info',
    width: '600px',
    confirmButtonText: 'Close',
    showCancelButton: true,
    cancelButtonText: 'Mark as Resolved',
    cancelButtonColor: '#28a745'
  }).then((result) => {
    if (result.dismiss === Swal.DismissReason.cancel) {
      resolveAlert(alertId);
    }
  });
}

function resolveAlert(alertId) {
  Swal.fire({
    title: 'Resolve Alert?',
    text: 'Are you sure you want to mark this alert as resolved?',
    icon: 'question',
    showCancelButton: true,
    confirmButtonColor: '#28a745',
    cancelButtonColor: '#d33',
    confirmButtonText: 'Yes, resolve it!',
    cancelButtonText: 'Cancel'
  }).then((result) => {
    if (result.isConfirmed) {
      // Simulate API call to resolve alert
      Swal.fire({
        title: 'Resolved!',
        text: 'Alert has been marked as resolved.',
        icon: 'success',
        timer: 2000,
        showConfirmButton: false
      }).then(() => {
        // Update the status in the table
        const tableRow = document.querySelector(`button[onclick="viewAlertDetails(${alertId})"]`).closest('tr');
        const statusCell = tableRow.querySelectorAll('td')[4];
        statusCell.innerHTML = '<span class="badge bg-success">Resolved</span>';
        
        // Disable the resolve button
        const resolveButton = tableRow.querySelector(`button[onclick="resolveAlert(${alertId})"]`);
        resolveButton.disabled = true;
        resolveButton.innerHTML = '<i class="fas fa-check"></i>';
        resolveButton.className = 'btn btn-sm btn-success';
      });
    }
  });
}

// Helper functions for alert details
function getIssueDescription(issueType) {
  const descriptions = {
    'GPS Signal Lost': 'Vehicle GPS device is not transmitting location data. This could be due to poor signal, device malfunction, or power issues.',
    'Low Fuel Warning': 'Vehicle fuel level is below 25%. Driver should refuel at the nearest gas station.',
    'Maintenance Due': 'Vehicle is due for scheduled maintenance. Please arrange service appointment.',
    'Speed Violation': 'Vehicle exceeded the speed limit for more than 5 minutes. Driver should be reminded of speed regulations.',
    'Geofence Exit': 'Vehicle has exited the designated geofenced area without authorization.',
    'Engine Warning': 'Vehicle engine diagnostic system has detected a potential issue. Immediate inspection recommended.',
    'Tire Pressure Low': 'One or more tires have low pressure. Driver should check and inflate tires.',
    'Battery Low': 'Vehicle battery voltage is below optimal level. Charging or replacement may be needed.',
    'Route Deviation': 'Vehicle has deviated significantly from the assigned route.',
    'Emergency Stop': 'Emergency stop button has been activated. Immediate attention required.'
  };
  return descriptions[issueType] || 'Alert requires attention from fleet management team.';
}

function getRecommendedAction(issueType) {
  const actions = {
    'GPS Signal Lost': 'Contact driver to check device status and location. Dispatch technician if needed.',
    'Low Fuel Warning': 'Direct driver to nearest gas station. Monitor fuel level until resolved.',
    'Maintenance Due': 'Schedule maintenance appointment. Remove vehicle from active routes if overdue.',
    'Speed Violation': 'Contact driver for immediate speed reduction. Document violation for review.',
    'Geofence Exit': 'Contact driver for explanation. Verify authorization and update geofence if needed.',
    'Engine Warning': 'Pull vehicle from service immediately. Arrange for diagnostic inspection.',
    'Tire Pressure Low': 'Direct driver to check tire pressure. Schedule tire service if needed.',
    'Battery Low': 'Monitor battery level. Schedule battery check or replacement.',
    'Route Deviation': 'Contact driver for route confirmation. Update route if authorization given.',
    'Emergency Stop': 'Contact driver immediately. Dispatch emergency response if needed.'
  };
  return actions[issueType] || 'Review alert details and take appropriate action based on fleet protocols.';
}
</script>

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

    // Collapse all sidebar dropdowns when sidebar is collapsed
    if (isCollapsed) {
      const openMenus = sidebar.querySelectorAll('.collapse.show');
      openMenus.forEach(menu => {
        const collapseInstance = bootstrap.Collapse.getInstance(menu) || new bootstrap.Collapse(menu, { toggle: false });
        collapseInstance.hide();
      });
    }
  });

  // Initialize page data and filters
  document.addEventListener('DOMContentLoaded', async function() {
    // Load filter dropdowns
    await loadVehicleTypesForFilter();
    await loadDriversForFilter();
    
    // Add event listeners
    addFilterEventListeners();
    
    // Load initial data
    loadFleetReportData(getFilterParams());
  });
</script>

</body>
</html>