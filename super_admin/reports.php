<?php
session_start();
// Allow Super Admin and Motor Pool Admin to access reports
if (!isset($_SESSION['role']) || !in_array(strtolower($_SESSION['role']), ['super admin', 'motor_pool_admin'])) {
    header("Location: ../index.php");
    exit;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0"/>
  <title>Reports | Smart Track</title>
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
      margin-top: 20px;
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
    .chart-container {
      position: relative;
      height: 300px;
      width: 100%;
    }

    /* Professional Report Cards */
    .report-card {
      transition: all 0.3s ease;
      border-radius: 12px;
      border: none;
      overflow: hidden;
    }

    .report-card:hover {
      transform: translateY(-3px);
      box-shadow: 0 8px 25px rgba(0, 0, 0, 0.1) !important;
    }

    .report-card .card-icon {
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

    .report-card h3 {
      font-size: 2rem;
      font-weight: 700;
      margin: 0;
    }

    .report-card h6 {
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
  </style>
</head>
<body>

<?php include __DIR__ . '/../pages/sidebar.php'; ?>
<?php include __DIR__ . '/../pages/navbar.php'; ?>

<div class="main-content p-2" id="mainContent">
  <div class="container-fluid">
    <h2 class="text-primary fw-bold mb-4">Fleet Management Reports</h2>

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
            <option value="custom">Custom Range</option>
          </select>
        </div>
        <div class="col-md-3" id="customDateRange" style="display:none;">
          <label class="form-label">Custom Range</label>
          <input type="text" class="form-control daterange-input" placeholder="Select date range">
        </div>
        <div class="col-md-3">
          <label class="form-label">Vehicle Type</label>
          <select class="form-select" id="vehicleType">
            <option value="all" selected>All Vehicles</option>
            <!-- Vehicle types will be loaded dynamically -->
          </select>
        </div>
        <div class="col-md-3">
          <label class="form-label">Driver</label>
          <select class="form-select" id="driverFilter">
            <option value="all" selected>All Drivers</option>
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

    <!-- Summary Cards -->
<div class="row g-4 mb-4">
  <div class="col-md-2">
    <div class="card shadow-sm report-card">
      <div class="card-body">
        <div class="d-flex justify-content-between align-items-center">
          <div>
            <h6 class="text-muted">Total Vehicles</h6>
            <h3 class="fw-bold" id="totalVehicles">-</h3>
            <span class="badge bg-primary">Fleet Size</span>
          </div>
          <div class="card-icon"><i class="fas fa-truck"></i></div>
        </div>
      </div>
    </div>
  </div>
  <div class="col-md-2">
    <div class="card shadow-sm report-card">
      <div class="card-body">
        <div class="d-flex justify-content-between align-items-center">
          <div>
            <h6 class="text-muted">Active Vehicles</h6>
            <h3 class="fw-bold" id="activeVehicles">-</h3>
            <span class="badge bg-success">Operational</span>
          </div>
          <div class="card-icon"><i class="fas fa-check-circle"></i></div>
        </div>
      </div>
    </div>
  </div>
  <div class="col-md-2">
    <div class="card shadow-sm report-card">
      <div class="card-body">
        <div class="d-flex justify-content-between align-items-center">
          <div>
            <h6 class="text-muted">Total Drivers</h6>
            <h3 class="fw-bold" id="totalDrivers">-</h3>
            <span class="badge bg-info">Assigned</span>
          </div>
          <div class="card-icon"><i class="fas fa-user"></i></div>
        </div>
      </div>
    </div>
  </div>
  <div class="col-md-2">
    <div class="card shadow-sm report-card">
      <div class="card-body">
        <div class="d-flex justify-content-between align-items-center">
          <div>
            <h6 class="text-muted">GPS Devices</h6>
            <h3 class="fw-bold" id="gpsDevices">-</h3>
            <span class="badge bg-warning">Tracked</span>
          </div>
          <div class="card-icon"><i class="fas fa-satellite"></i></div>
        </div>
      </div>
    </div>
  </div>
  <div class="col-md-2">
    <div class="card shadow-sm report-card">
      <div class="card-body">
        <div class="d-flex justify-content-between align-items-center">
          <div>
            <h6 class="text-muted">Active Geofences</h6>
            <h3 class="fw-bold" id="activeGeofences">-</h3>
            <span class="badge bg-secondary">Monitoring</span>
          </div>
          <div class="card-icon"><i class="fas fa-map-marker-alt"></i></div>
        </div>
      </div>
    </div>
  </div>
  <div class="col-md-2">
    <div class="card shadow-sm report-card">
      <div class="card-body">
        <div class="d-flex justify-content-between align-items-center">
          <div>
            <h6 class="text-muted">Recent Events</h6>
            <h3 class="fw-bold" id="recentEvents">-</h3>
          </div>
          <div class="card-icon"><i class="fas fa-bell"></i></div>
        </div>
      </div>
    </div>
  </div>
</div>
    <!-- Add chart section here -->
     <div class="row g-4 mb-4">
      <div class="col-md-6">
        <div class="card">
          <div class="card-body">
            <h5 class="card-title">Vehicle Distribution</h5>
            <div class="chart-container">
              <canvas id="vehicleDistributionChart"></canvas>
            </div>
          </div>
        </div>
      </div>
      <div class="col-md-6">
        <div class="card">
          <div class="card-body">
            <h5 class="card-title d-flex justify-content-between align-items-center">
              <span>🚀 Vehicle Activity Heatmap</span>
              <div>
                <button class="btn btn-sm btn-outline-primary me-1" onclick="toggleShowAll()" title="Show All/Top 20" id="toggleAllBtn">
                  <i class="fas fa-list"></i> <span id="toggleText">Show All</span>
                </button>
                <button class="btn btn-sm btn-outline-primary" onclick="loadReportData(getFilterParams())" title="Refresh Heatmap">
                  <i class="fas fa-sync-alt"></i>
                </button>
              </div>
            </h5>
            <div class="mb-2">
              <small class="text-muted">Showing: <span id="vehicleCount">0</span> vehicles | 
              <input type="text" id="vehicleSearch" class="form-control form-control-sm d-inline-block" style="width: 200px;" placeholder="Search vehicles..." oninput="filterVehicles()">
              </small>
            </div>
            <div class="chart-container" style="height: 400px; overflow-y: auto;">
              <canvas id="vehicleActivityChart"></canvas>
            </div>
          </div>
        </div>
      </div>
      <div class="col-md-12">
        <div class="card">
          <div class="card-body">
            <h5 class="card-title">Distance Traveled by Vehicle</h5>
            <div class="chart-container" style="height: 350px;">
              <canvas id="distanceChart"></canvas>
            </div>
          </div>
        </div>
      </div>
      <div class="col-md-6">
        <div class="card">
          <div class="card-body">
            <h5 class="card-title">Maintenance Status</h5>
            <div class="chart-container">
              <canvas id="maintenanceChart"></canvas>
            </div>
          </div>
        </div>
      </div>
      <div class="col-md-6">
        <div class="card">
          <div class="card-body">
            <h5 class="card-title">Geofence Events</h5>
            <div class="chart-container">
              <canvas id="driverPerformanceChart"></canvas>
            </div>
          </div>
        </div>
      </div>
    </div>
  </div>
</div>
  </div>
</div>
<script>
document.addEventListener('DOMContentLoaded', function () {
  // Load drivers and vehicle types for filter dropdowns first
  Promise.all([
    loadDriversForFilter(),
    loadVehicleTypesForFilter()
  ]).then(() => {
    // Add event listeners after loading all filters
    addFilterEventListeners();
    // Then load initial report data
    loadReportData(getFilterParams());
  });
  
  // Refresh data every 5 minutes
  setInterval(() => loadReportData(getFilterParams()), 300000);
});

// Handle custom date range display
function handleDateRangeChange() {
  const dateRange = document.getElementById('dateRange').value;
  const customRange = document.getElementById('customDateRange');
  
  if (dateRange === 'custom') {
    customRange.style.display = 'block';
    // Initialize date range picker here if needed
  } else {
    customRange.style.display = 'none';
    loadReportData(getFilterParams());
  }
}

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
      driverFilter.innerHTML = '<option value="all" selected>All Drivers</option>';
      
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
      vehicleTypeFilter.innerHTML = '<option value="all" selected>All Vehicles</option>';
      
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
  document.getElementById('dateRange').addEventListener('change', handleDateRangeChange);
  document.getElementById('vehicleType').addEventListener('change', () => loadReportData(getFilterParams()));
  document.getElementById('driverFilter').addEventListener('change', () => loadReportData(getFilterParams()));
  document.getElementById('applyFilters').addEventListener('click', () => loadReportData(getFilterParams()));
}

async function loadReportData(filters = {}) {
  try {
    console.log('Loading report data with filters:', filters);
    
    // Build query parameters
    const params = new URLSearchParams();
    if (filters.dateRange) params.append('dateRange', filters.dateRange);
    if (filters.vehicleType) params.append('vehicleType', filters.vehicleType);
    if (filters.driverId) params.append('driverId', filters.driverId);
    
    // Load summary data
    const summaryResponse = await fetch(`reports_api.php?action=get_summary&${params.toString()}`);
    const summaryData = await summaryResponse.json();
    console.log('Summary data:', summaryData);
    
    if (summaryData.success) {
      updateSummaryCards(summaryData.data);
    }
    
    // Load vehicle distribution
    const vehicleResponse = await fetch(`reports_api.php?action=get_vehicle_distribution&${params.toString()}`);
    const vehicleData = await vehicleResponse.json();
    console.log('Vehicle distribution data:', vehicleData);
    
    if (vehicleData.success) {
      updateVehicleDistributionChart(vehicleData.data);
    }
    
    // Load maintenance status
    const maintenanceResponse = await fetch(`reports_api.php?action=get_maintenance_status&${params.toString()}`);
    const maintenanceData = await maintenanceResponse.json();
    console.log('Maintenance data:', maintenanceData);
    
    if (maintenanceData.success) {
      updateMaintenanceChart(maintenanceData.data);
    }
    
    // Load distance trend
    const distanceResponse = await fetch(`reports_api.php?action=get_distance_trend&${params.toString()}`);
    const distanceData = await distanceResponse.json();
    console.log('Distance data:', distanceData);
    
    if (distanceData.success) {
      updateDistanceChart(distanceData.data);
    }
    
    // Load driver performance
    const driverResponse = await fetch(`reports_api.php?action=get_driver_performance&${params.toString()}`);
    const driverData = await driverResponse.json();
    console.log('Driver data:', driverData);
    
    if (driverData.success) {
      updateDriverPerformanceChart(driverData.data);
    }
    
    // Load vehicle activity
    const activityResponse = await fetch(`reports_api.php?action=get_vehicle_activity&${params.toString()}`);
    const activityData = await activityResponse.json();
    console.log('Activity data:', activityData);
    
    if (activityData.success) {
      updateVehicleActivityChart(activityData.data);
    } else {
      console.error('Failed to load activity data:', activityData);
      updateVehicleActivityChart([]); // Show empty chart
    }
    
  } catch (error) {
    console.error('Error loading report data:', error);
  }
}

function updateSummaryCards(data) {
  document.getElementById('totalVehicles').textContent = data.total_vehicles || 0;
  document.getElementById('activeVehicles').textContent = data.active_vehicles || 0;
  document.getElementById('totalDrivers').textContent = data.total_drivers || 0;
  document.getElementById('gpsDevices').textContent = data.gps_devices || 0;
  document.getElementById('activeGeofences').textContent = data.active_geofences || 0;
  document.getElementById('recentEvents').textContent = data.recent_events || 0;
}

function updateVehicleDistributionChart(data) {
  console.log('Updating vehicle distribution chart with data:', data);
  const ctx = document.getElementById('vehicleDistributionChart').getContext('2d');
  
  // Check if chart exists and destroy it properly
  if (window.vehicleDistributionChart && typeof window.vehicleDistributionChart.destroy === 'function') {
    window.vehicleDistributionChart.destroy();
  }
  
  window.vehicleDistributionChart = new Chart(ctx, {
    type: 'pie',
    data: {
      labels: data.map(item => item.type),
      datasets: [{
        data: data.map(item => item.count),
        backgroundColor: ['#003566', '#00b4d8', '#2a9d8f', '#f4a261', '#e76f51', '#264653']
      }]
    },
    options: { 
      responsive: true, 
      maintainAspectRatio: false,
      plugins: {
        legend: {
          position: 'bottom'
        }
      }
    }
  });
  console.log('Vehicle distribution chart updated');
}

function updateMaintenanceChart(data) {
  console.log('Updating maintenance chart with data:', data);
  const ctx = document.getElementById('maintenanceChart').getContext('2d');
  
  // Check if chart exists and destroy it properly
  if (window.maintenanceChart && typeof window.maintenanceChart.destroy === 'function') {
    window.maintenanceChart.destroy();
  }
  
  window.maintenanceChart = new Chart(ctx, {
    type: 'doughnut',
    data: {
      labels: data.map(item => item.status),
      datasets: [{
        data: data.map(item => item.count),
        backgroundColor: ['#2a9d8f', '#f4a261', '#e76f51']
      }]
    },
    options: { 
      responsive: true, 
      maintainAspectRatio: false,
      plugins: {
        legend: {
          position: 'bottom'
        }
      }
    }
  });
  console.log('Maintenance chart updated');
}

function updateDistanceChart(data) {
  const ctx = document.getElementById('distanceChart').getContext('2d');
  
  // Check if chart exists and destroy it properly
  if (window.distanceChart && typeof window.distanceChart.destroy === 'function') {
    window.distanceChart.destroy();
  }
  
  // Handle empty data
  if (!data || data.length === 0) {
    window.distanceChart = new Chart(ctx, {
      type: 'bar',
      data: {
        labels: ['No Data'],
        datasets: [{
          label: 'Distance (km)',
          data: [0],
          backgroundColor: '#6c757d'
        }]
      },
      options: {
        responsive: true,
        maintainAspectRatio: false,
        plugins: {
          title: {
            display: true,
            text: 'No distance data available'
          }
        }
      }
    });
    return;
  }
  
  // Sort data by distance (descending)
  data.sort((a, b) => parseFloat(b.distance_km || 0) - parseFloat(a.distance_km || 0));
  
  // Create labels combining vehicle type and plate number
  const labels = data.map(item => {
    const vehicle = item.vehicle || item.article || 'Unknown Vehicle';
    const plate = item.plate_number;
    return plate ? `${vehicle} (${plate})` : vehicle;
  });
  
  const distances = data.map(item => parseFloat(item.distance_km || 0).toFixed(2));
  
  // Create gradient colors based on distance
  const backgroundColors = distances.map(distance => {
    const dist = parseFloat(distance);
    if (dist >= 500) return '#e76f51'; // Very high - red
    if (dist >= 300) return '#f4a261'; // High - orange
    if (dist >= 150) return '#2a9d8f'; // Medium - teal
    if (dist >= 50) return '#00b4d8';  // Low-medium - blue
    return '#6c757d'; // Very low - gray
  });
  
  // Generate unique colors for each vehicle
  const vehicleColors = [
    '#003566', // Navy blue
    '#00b4d8', // Cyan
    '#e76f51', // Red-orange
    '#2a9d8f', // Teal
    '#f4a261', // Orange
    '#264653', // Dark teal
    '#e9c46a', // Yellow
    '#06ffa5', // Mint
    '#9b5de5', // Purple
    '#f15bb5'  // Pink
  ];
  
  // Assign a unique color to each vehicle
  const uniqueColors = labels.map((label, index) => vehicleColors[index % vehicleColors.length]);
  
  window.distanceChart = new Chart(ctx, {
    type: 'bar',
    data: {
      labels: labels,
      datasets: [{
        label: 'Distance (km)',
        data: distances,
        backgroundColor: uniqueColors,
        borderColor: uniqueColors.map(color => color),
        borderWidth: 2,
        borderRadius: 8,
        borderSkipped: false
      }]
    },
    options: {
      indexAxis: 'x', // Vertical bar chart
      responsive: true,
      maintainAspectRatio: false,
      plugins: {
        legend: {
          display: false
        },
        tooltip: {
          callbacks: {
            title: function(context) {
              return `🚗 ${context[0].label}`;
            },
            label: function(context) {
              const distance = parseFloat(context.parsed.y).toFixed(2);
              const index = context.dataIndex;
              const vehicleData = data[index];
              
              let status = '';
              if (distance >= 500) status = '🔥 Very High Distance';
              else if (distance >= 300) status = '⚡ High Distance';
              else if (distance >= 150) status = '📊 Medium Distance';
              else if (distance >= 50) status = '🚗 Low-Medium Distance';
              else status = '😴 Low Distance';
              
              const tooltipLines = [
                `Distance: ${distance} km`,
                `Status: ${status}`
              ];
              
              // Add fuel consumption if available
              if (vehicleData && vehicleData.estimated_fuel_liters) {
                tooltipLines.push(`⛽ Est. Fuel: ${vehicleData.estimated_fuel_liters} L`);
              }
              if (vehicleData && vehicleData.fuel_efficiency) {
                tooltipLines.push(`📊 Efficiency: ${vehicleData.fuel_efficiency}`);
              }
              
              return tooltipLines;
            }
          }
        }
      },
      scales: {
        y: {
          beginAtZero: true,
          title: { 
            display: true, 
            text: 'Distance (Kilometers)',
            color: '#003566',
            font: { weight: 'bold' }
          },
          ticks: {
            callback: function(value) {
              return value + ' km';
            }
          }
        },
        x: {
          title: { 
            display: true, 
            text: 'Vehicles',
            color: '#003566',
            font: { weight: 'bold' }
          },
          ticks: {
            maxRotation: 45,
            minRotation: 45
          }
        }
      }
    }
  });
}

function updateDriverPerformanceChart(data) {
  const ctx = document.getElementById('driverPerformanceChart').getContext('2d');
  
  // Check if chart exists and destroy it properly
  if (window.driverPerformanceChart && typeof window.driverPerformanceChart.destroy === 'function') {
    window.driverPerformanceChart.destroy();
  }
  
  // Handle empty data
  if (!data || data.length === 0) {
    window.driverPerformanceChart = new Chart(ctx, {
      type: 'bar',
      data: {
        labels: ['No Data'],
        datasets: [{
          label: 'Events',
          data: [0],
          backgroundColor: '#6c757d'
        }]
      },
      options: {
        responsive: true,
        maintainAspectRatio: false,
        plugins: {
          title: {
            display: true,
            text: 'No geofence events available'
          }
        }
      }
    });
    return;
  }
  
  const labels = data.map(item => item.geofence_name);
  const events = data.map(item => item.event_count);
  
  // Generate colors for each geofence
  const colors = labels.map((_, index) => {
    const colorPalette = ['#003566', '#00b4d8', '#2a9d8f', '#f4a261', '#e76f51', '#264653', '#e9c46a', '#06ffa5', '#9b5de5', '#f15bb5'];
    return colorPalette[index % colorPalette.length];
  });
  
  window.driverPerformanceChart = new Chart(ctx, {
    type: 'bar',
    data: {
      labels: labels,
      datasets: [{
        label: 'Total Events',
        data: events,
        backgroundColor: colors,
        borderColor: colors.map(color => color),
        borderWidth: 2,
        borderRadius: 8,
        borderSkipped: false
      }]
    },
    options: {
      responsive: true,
      maintainAspectRatio: false,
      plugins: {
        legend: {
          display: false
        },
        tooltip: {
          callbacks: {
            title: function(context) {
              return `📍 ${context[0].label}`;
            },
            label: function(context) {
              const index = context.dataIndex;
              const geofence = data[index];
              return [
                `Total Events: ${geofence.event_count}`,
                `Entries: ${geofence.entries}`,
                `Exits: ${geofence.exits}`
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
            text: 'Number of Events',
            color: '#003566',
            font: { weight: 'bold' }
          }
        },
        x: {
          title: { 
            display: true, 
            text: 'Geofences',
            color: '#003566',
            font: { weight: 'bold' }
          },
          ticks: {
            maxRotation: 45,
            minRotation: 45
          }
        }
      }
    }
  });
}

// Global variables for chart state
window.allVehicleData = [];
window.showAllVehicles = false;
window.searchTerm = '';

function updateVehicleActivityChart(data) {
  console.log('updateVehicleActivityChart called with data:', data);
  
  // Store all data globally
  window.allVehicleData = data || [];
  
  const ctx = document.getElementById('vehicleActivityChart');
  if (!ctx) {
    console.error('vehicleActivityChart canvas element not found');
    return;
  }
  
  // Check if chart exists and destroy it properly
  if (window.vehicleActivityChart && typeof window.vehicleActivityChart.destroy === 'function') {
    window.vehicleActivityChart.destroy();
  }
  
  // Handle empty data
  if (!data || data.length === 0) {
    console.log('No activity data available, showing empty chart');
    document.getElementById('vehicleCount').textContent = '0';
    window.vehicleActivityChart = new Chart(ctx.getContext('2d'), {
      type: 'bar',
      data: {
        labels: ['No Data'],
        datasets: [{
          label: 'Activity Score',
          data: [0],
          backgroundColor: '#6c757d',
          borderColor: '#003566',
          borderWidth: 2
        }]
      },
      options: {
        responsive: true,
        maintainAspectRatio: false,
        plugins: {
          title: {
            display: true,
            text: 'No vehicle activity data available'
          }
        }
      }
    });
    return;
  }
  
  // Filter data based on search term
  let filteredData = data;
  if (window.searchTerm) {
    filteredData = data.filter(item => {
      const searchable = `${item.vehicle} ${item.plate_number}`.toLowerCase();
      return searchable.includes(window.searchTerm.toLowerCase());
    });
  }
  
  // Sort by activity score (descending)
  filteredData.sort((a, b) => b.activity_score - a.activity_score);
  
  // Limit to top 20 if not showing all
  const displayData = window.showAllVehicles ? filteredData : filteredData.slice(0, 20);
  
  // Update vehicle count
  document.getElementById('vehicleCount').textContent = displayData.length;
  
  // Create unique labels combining vehicle type and plate number
  const labels = displayData.map(item => {
    const plate = item.plate_number || 'No Plate';
    return `${item.vehicle} (${plate})`;
  });
  const activity = displayData.map(item => item.activity_score);
  
  // Create gradient colors based on activity level
  const backgroundColors = activity.map(score => {
    if (score >= 80) return '#e76f51'; // High activity - red
    if (score >= 60) return '#f4a261'; // Medium-high - orange
    if (score >= 40) return '#2a9d8f'; // Medium - teal
    if (score >= 20) return '#00b4d8'; // Low-medium - blue
    return '#6c757d'; // Low activity - gray
  });
  
  window.vehicleActivityChart = new Chart(ctx, {
    type: 'bar',
    data: {
      labels: labels,
      datasets: [{
        label: 'Activity Score',
        data: activity,
        backgroundColor: backgroundColors,
        borderColor: '#003566',
        borderWidth: 2,
        borderRadius: 8,
        borderSkipped: false,
      }]
    },
    options: {
      responsive: true,
      maintainAspectRatio: false,
      plugins: {
        legend: {
          display: false
        },
        tooltip: {
          callbacks: {
            title: function(context) {
              const dataIndex = context[0].dataIndex;
              const vehicle = context[0].label;
              return `🚗 ${vehicle}`;
            },
            label: function(context) {
              const score = context.parsed.y;
              const dataIndex = context.dataIndex;
              const vehicleData = data[dataIndex];
              
              let status = '';
              if (score >= 80) status = '🔥 High Activity';
              else if (score >= 60) status = '⚡ Medium-High';
              else if (score >= 40) status = '📊 Medium';
              else if (score >= 20) status = '🐌 Low-Medium';
              else status = '😴 Low Activity';
              
              const tooltipLines = [
                `Activity Score: ${score.toFixed(1)}%`,
                `Status: ${status}`
              ];
              
              // Add detailed breakdown if available
              if (vehicleData.gps_points) {
                tooltipLines.push(`📍 GPS Points: ${vehicleData.gps_points}`);
              }
              if (vehicleData.event_count) {
                tooltipLines.push(`🚧 Geofence Events: ${vehicleData.event_count}`);
              }
              if (vehicleData.avg_speed) {
                tooltipLines.push(`⚡ Avg Speed: ${parseFloat(vehicleData.avg_speed).toFixed(1)} km/h`);
              }
              if (vehicleData.estimated_distance_km) {
                tooltipLines.push(`📏 Distance: ${vehicleData.estimated_distance_km} km (24h)`);
              }
              if (vehicleData.estimated_fuel_liters) {
                tooltipLines.push(`⛽ Est. Fuel: ${vehicleData.estimated_fuel_liters} L`);
              }
              if (vehicleData.fuel_efficiency) {
                tooltipLines.push(`📊 Efficiency: ${vehicleData.fuel_efficiency}`);
              }
              if (vehicleData.last_update) {
                const lastUpdate = new Date(vehicleData.last_update);
                tooltipLines.push(`🕒 Last Update: ${lastUpdate.toLocaleString()}`);
              }
              
              return tooltipLines;
            }
          }
        }
      },
      scales: {
        y: {
          beginAtZero: true,
          max: 100,
          title: { 
            display: true, 
            text: 'Activity Level (%)',
            color: '#003566',
            font: { weight: 'bold' }
          },
          ticks: {
            callback: function(value) {
              return value + '%';
            }
          }
        },
        x: {
          title: { 
            display: true, 
            text: 'Vehicles',
            color: '#003566',
            font: { weight: 'bold' }
          }
        }
      }
    }
  });
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

    // 🚨 Collapse all sidebar dropdowns when sidebar is collapsed
    if (isCollapsed) {
      const openMenus = sidebar.querySelectorAll('.collapse.show');
      openMenus.forEach(menu => {
        const collapseInstance = bootstrap.Collapse.getInstance(menu) || new bootstrap.Collapse(menu, { toggle: false });
        collapseInstance.hide();
      });
    }
  });

// Toggle functions for vehicle activity chart
function toggleShowAll() {
  window.showAllVehicles = !window.showAllVehicles;
  const toggleBtn = document.getElementById('toggleText');
  toggleBtn.textContent = window.showAllVehicles ? 'Show Top 20' : 'Show All';
  updateVehicleActivityChart(window.allVehicleData);
}

function filterVehicles() {
  window.searchTerm = document.getElementById('vehicleSearch').value;
  updateVehicleActivityChart(window.allVehicleData);
}
</script>
</body>
</html>
