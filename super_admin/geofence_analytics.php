<?php
session_start();
// Allow Super Admin and Motor Pool Admin to access analytics
if (!isset($_SESSION['role']) || !in_array(strtolower($_SESSION['role']), ['super admin', 'motor_pool_admin'])) {
    header("Location: ../index.php");
    exit;
}
require_once __DIR__ . '/../db_connection.php';
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Geofence Statistics | Smart Track</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css" rel="stylesheet">
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <style>
        :root { --primary: #003566; --accent: #00b4d8; --bg: #f8f9fa; }
        body { font-family: 'Segoe UI', sans-serif; background-color: var(--bg); }
        .sidebar { position: fixed; top:0; left:0; width:250px; height:100vh; background-color: var(--primary); color:#fff; padding-top:60px; overflow-y:auto; transition: all 0.3s ease; z-index:1000; }
        .sidebar.collapsed { width:70px; }
        .sidebar a { display:block; padding:14px 20px; color:#d9d9d9; text-decoration:none; transition: background 0.2s; white-space:nowrap; }
        .sidebar a:hover, .sidebar a.active { background-color:#001d3d; color: var(--accent); }
        .sidebar a.active i { color: var(--accent) !important; }
        .main-content { margin-left:250px; margin-top:70px; padding:20px; transition: margin-left 0.3s ease; }
        .main-content.collapsed { margin-left:70px; }
        .navbar { position: fixed; top:0; left:0; width:100%; background-color:#fff; box-shadow:0 2px 5px rgba(0,0,0,0.05); border-bottom:1px solid #dee2e6; z-index:1100; height:60px; display:flex; align-items:center; }
        .navbar .container-fluid { display:flex; align-items:center; height:100%; }
        .burger-btn { font-size:1.5rem; background:transparent; border:none; color:#001d3d; margin-right:1rem; padding:8px 12px; border-radius:4px; transition:all 0.3s ease; cursor:pointer; }
        .burger-btn:hover { background-color:#f8f9fa; color:#00b4d8; }
        .burger-btn:focus { outline:none; box-shadow:0 0 0 2px rgba(0, 180, 216, 0.25); }
        .dropdown-chevron { color:#ffffff; transition:transform 0.3s ease, color 0.2s ease; font-size:0.8rem; margin-left:auto; }
        .dropdown-chevron:hover { color:var(--accent); }
        .dropdown-toggle[aria-expanded="true"] .dropdown-chevron { transform:rotate(90deg); }
        .dropdown-toggle { width:100%; justify-content:space-between; align-items:center; }
        .dropdown-toggle .dropdown-chevron { flex-shrink:0; margin-left:8px; }
        .dropdown-toggle::after { display:none; }
        .sidebar.collapsed .dropdown-chevron { display:none !important; }
        .sidebar.collapsed .link-text { display:none !important; }
        .sidebar.collapsed .collapse { display:none !important; }
        .navbar-brand { color:#000000 !important; }
        .card { border-radius: 0.5rem; border: none; box-shadow: 0 2px 10px rgba(0,0,0,0.1); }
        .card-header { background-color: var(--primary); color: white; border-bottom: none; border-radius: 0.5rem 0.5rem 0 0 !important; }
        .stats-card { background: linear-gradient(135deg, var(--primary), var(--accent)); color: white; }
        .chart-container { position: relative; height: 300px; }
        
        /* Mobile spacing for statistics cards */
        @media (max-width: 768px) {
            .stats-card {
                margin-bottom: 1.5rem !important;
            }
            .stats-card:last-child {
                margin-bottom: 0 !important;
            }
            
            /* Increase spacing between cards on mobile */
            .row.mb-4 .col-md-3 {
                margin-bottom: 1.5rem;
            }
            
            /* Ensure proper card spacing */
            .card.stats-card {
                margin-bottom: 1.5rem !important;
            }
        }
        
        /* Extra small mobile devices */
        @media (max-width: 576px) {
            .stats-card {
                margin-bottom: 2rem !important;
            }
            .row.mb-4 .col-md-3 {
                margin-bottom: 2rem;
            }
        }
    </style>
</head>
<body>

<?php include __DIR__ . '/../pages/sidebar.php'; ?>
<?php include __DIR__ . '/../pages/navbar.php'; ?>

<div class="main-content" id="mainContent">
    <div class="container-fluid">
        <div class="row mb-4">
            <div class="col-12">
                <h2 class="text-primary fw-bold">Geofence Statistics Dashboard - MOBILE-001</h2>
                <p class="text-muted">Comprehensive insights into MOBILE-001 device geofence activity and movements</p>
            </div>
        </div>

        <!-- Filter Controls -->
        <div class="row mb-4">
            <div class="col-12">
                <div class="card">
                    <div class="card-body">
                        <div class="row align-items-end">
                            <div class="col-md-3">
                                <label class="form-label">Date Range</label>
                                <select class="form-select" id="dateRange">
                                    <option value="1">Today</option>
                                    <option value="7" selected>Last 7 Days</option>
                                    <option value="30">Last 30 Days</option>
                                    <option value="90">Last 3 Months</option>
                                    <option value="365">Last Year</option>
                                </select>
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
                </div>
            </div>
        </div>

        <!-- Statistics Cards -->
        <div class="row mb-4">
            <div class="col-md-3">
                <div class="card stats-card">
                    <div class="card-body">
                        <div class="d-flex justify-content-between">
                            <div>
                                <h6 class="card-subtitle mb-2">Total Events Today</h6>
                                <h3 class="card-title mb-0" id="todayEvents">0</h3>
                            </div>
                            <div class="align-self-center">
                                <i class="fas fa-calendar-day fa-2x opacity-75"></i>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="card stats-card">
                    <div class="card-body">
                        <div class="d-flex justify-content-between">
                            <div>
                                <h6 class="card-subtitle mb-2">Active Geofences</h6>
                                <h3 class="card-title mb-0" id="activeGeofences">0</h3>
                            </div>
                            <div class="align-self-center">
                                <i class="fas fa-map-marker-alt fa-2x opacity-75"></i>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="card stats-card">
                    <div class="card-body">
                        <div class="d-flex justify-content-between">
                            <div>
                                <h6 class="card-subtitle mb-2">MOBILE-001 Status</h6>
                                <h3 class="card-title mb-0" id="vehiclesTracked">Active</h3>
                            </div>
                            <div class="align-self-center">
                                <i class="fas fa-mobile-alt fa-2x opacity-75"></i>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="card stats-card">
                    <div class="card-body">
                        <div class="d-flex justify-content-between">
                            <div>
                                <h6 class="card-subtitle mb-2">Alert Rate</h6>
                                <h3 class="card-title mb-0" id="alertRate">0%</h3>
                            </div>
                            <div class="align-self-center">
                                <i class="fas fa-bell fa-2x opacity-75"></i>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Charts Row -->
        <div class="row mb-4">
            <div class="col-md-6">
                <div class="card">
                    <div class="card-header">
                        <h5 class="mb-0"><i class="fas fa-chart-bar me-2"></i>Events by Geofence</h5>
                    </div>
                    <div class="card-body">
                        <div class="chart-container">
                            <canvas id="geofenceChart"></canvas>
                        </div>
                    </div>
                </div>
            </div>
            <div class="col-md-6">
                <div class="card">
                    <div class="card-header">
                        <h5 class="mb-0"><i class="fas fa-chart-pie me-2"></i>Events by Vehicle</h5>
                    </div>
                    <div class="card-body">
                        <div class="chart-container">
                            <canvas id="vehicleChart"></canvas>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Timeline and Export -->
        <div class="row">
            <div class="col-md-8">
                <div class="card">
                    <div class="card-header d-flex justify-content-between align-items-center">
                        <h5 class="mb-0"><i class="fas fa-history me-2"></i>Recent Geofence Events</h5>
                        <div class="btn-group btn-group-sm">
                            <button class="btn btn-outline-primary" onclick="exportData('csv')">
                                <i class="fas fa-download me-1"></i> CSV
                            </button>
                            <button class="btn btn-outline-primary" onclick="exportData('pdf')">
                                <i class="fas fa-file-pdf me-1"></i> PDF
                            </button>
                        </div>
                    </div>
                    <div class="card-body">
                        <!-- Date Range Filter for Events -->
                        <div class="row mb-3">
                            <div class="col-md-5">
                                <label class="form-label small mb-1">Start Date</label>
                                <input type="date" class="form-control form-control-sm" id="eventStartDate">
                            </div>
                            <div class="col-md-5">
                                <label class="form-label small mb-1">End Date</label>
                                <input type="date" class="form-control form-control-sm" id="eventEndDate">
                            </div>
                            <div class="col-md-2 d-flex align-items-end">
                                <button class="btn btn-outline-secondary btn-sm w-100" type="button" id="clearDateFilter">
                                    <i class="fas fa-undo me-1"></i>Clear
                                </button>
                            </div>
                        </div>
                        
                        <!-- Search Filter -->
                        <div class="mb-3">
                            <div class="input-group">
                                <span class="input-group-text">
                                    <i class="fas fa-search"></i>
                                </span>
                                <input type="text" class="form-control" id="searchEvents" placeholder="Search by vehicle, driver, geofence, or event type...">
                                <button class="btn btn-outline-secondary" type="button" id="clearSearch">
                                    <i class="fas fa-times"></i>
                                </button>
                            </div>
                        </div>
                        <div class="table-responsive" style="max-height: 400px; overflow-y: auto;">
                            <table class="table table-hover">
                                <thead class="sticky-top bg-white">
                                    <tr>
                                        <th>Time</th>
                                        <th>Vehicle</th>
                                        <th>Driver</th>
                                        <th>Geofence</th>
                                        <th>Event</th>
                                        <th>Status</th>
                                    </tr>
                                </thead>
                                <tbody id="eventsTable">
                                    <tr>
                                        <td colspan="6" class="text-center text-muted">Loading events...</td>
                                    </tr>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>
            <div class="col-md-4">
                <div class="card">
                    <div class="card-header">
                        <h5 class="mb-0"><i class="fas fa-chart-line me-2"></i>Activity Timeline</h5>
                    </div>
                    <div class="card-body">
                        <div class="chart-container">
                            <canvas id="timelineChart"></canvas>
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
document.addEventListener('DOMContentLoaded', function() {
    // Load filters first, then load analytics
    Promise.all([
        loadDriversForFilter(),
        loadVehicleTypesForFilter()
    ]).then(() => {
        // Add event listeners after loading filters
        addFilterEventListeners();
        // Load initial data
        loadAnalytics(getFilterParams());
        loadRecentEvents(getFilterParams());
    });
    
    // Refresh data every 5 seconds for real-time updates
    setInterval(() => {
        loadAnalytics(getFilterParams());
        loadRecentEvents(getFilterParams());
        loadTimelineChart(getFilterParams());
    }, 5000);
});

// Get current filter parameters
function getFilterParams() {
    const dateRange = document.getElementById('dateRange').value;
    
    return {
        dateRange: dateRange,
        deviceId: 'MOBILE-001'  // Hardcoded to only show MOBILE-001 data
    };
}

// Load drivers for filter dropdown
async function loadDriversForFilter() {
    try {
        const response = await fetch('reports_api.php?action=get_drivers_for_filter');
        if (!response.ok) {
            throw new Error(`HTTP error! status: ${response.status}`);
        }
        const contentType = response.headers.get('content-type');
        if (!contentType || !contentType.includes('application/json')) {
            const text = await response.text();
            console.error('Expected JSON but got:', text.substring(0, 200));
            throw new Error('Response is not JSON');
        }
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
        // Show user-friendly error message
        const driverFilter = document.getElementById('driverFilter');
        if (driverFilter) {
            driverFilter.innerHTML = '<option value="all" selected>All Drivers</option><option disabled>Error loading drivers</option>';
        }
    }
}

// Load vehicle types for filter dropdown
async function loadVehicleTypesForFilter() {
    try {
        const response = await fetch('reports_api.php?action=get_vehicle_types_for_filter');
        if (!response.ok) {
            throw new Error(`HTTP error! status: ${response.status}`);
        }
        const contentType = response.headers.get('content-type');
        if (!contentType || !contentType.includes('application/json')) {
            const text = await response.text();
            console.error('Expected JSON but got:', text.substring(0, 200));
            throw new Error('Response is not JSON');
        }
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
        // Show user-friendly error message
        const vehicleTypeFilter = document.getElementById('vehicleType');
        if (vehicleTypeFilter) {
            vehicleTypeFilter.innerHTML = '<option value="all" selected>All Vehicles</option><option disabled>Error loading vehicle types</option>';
        }
    }
}

// Add event listeners for filters
function addFilterEventListeners() {
    document.getElementById('dateRange').addEventListener('change', () => {
        loadAnalytics(getFilterParams());
        loadRecentEvents(getFilterParams());
    });
    document.getElementById('vehicleType').addEventListener('change', () => {
        loadAnalytics(getFilterParams());
        loadRecentEvents(getFilterParams());
    });
    document.getElementById('driverFilter').addEventListener('change', () => {
        loadAnalytics(getFilterParams());
        loadRecentEvents(getFilterParams());
    });
    document.getElementById('applyFilters').addEventListener('click', () => {
        loadAnalytics(getFilterParams());
        loadRecentEvents(getFilterParams());
    });
}

async function loadAnalytics(filters = {}) {
    try {
        console.log('🔄 Loading analytics data with filters:', filters);
        
        // Build query parameters
        const params = new URLSearchParams();
        if (filters.dateRange) params.append('dateRange', filters.dateRange);
        if (filters.deviceId) params.append('deviceId', filters.deviceId);
        
        const response = await fetch(`../geofence_alert_api.php?action=get_geofence_analytics&${params.toString()}`);
        const data = await response.json();
        
        console.log('📊 Analytics API Response:', data);
        
        if (data.success) {
            console.log('✅ Analytics data loaded successfully');
            console.log('📈 Data received:', data.data);
            updateStatistics(data.data);
            updateCharts(data.data);
        } else {
            console.error('❌ Analytics API error:', data.message);
        }
    } catch (error) {
        console.error('❌ Error loading analytics:', error);
    }
}

function updateStatistics(data) {
    console.log('📊 Updating statistics with data:', data);
    
    const todayEvents = data.today_events || 0;
    const activeGeofences = data.active_geofences || 0;
    const vehiclesTracked = data.by_vehicle?.length || 0;
    
    console.log('📈 Statistics values:', {
        todayEvents,
        activeGeofences,
        vehiclesTracked
    });
    
    document.getElementById('todayEvents').textContent = todayEvents;
    document.getElementById('activeGeofences').textContent = activeGeofences;
    document.getElementById('vehiclesTracked').textContent = vehiclesTracked;
    
    // Calculate alert rate
    const totalEvents = data.by_geofence?.reduce((sum, item) => sum + item.event_count, 0) || 0;
    const alertRate = totalEvents > 0 ? Math.round((data.today_events / totalEvents) * 100) : 0;
    document.getElementById('alertRate').textContent = alertRate + '%';
    
    console.log('✅ Statistics updated successfully');
}

function updateCharts(data) {
    // Geofence Chart
    const geofenceCtx = document.getElementById('geofenceChart').getContext('2d');
    
    // Check if chart exists and destroy it properly
    if (window.geofenceChart && typeof window.geofenceChart.destroy === 'function') {
        window.geofenceChart.destroy();
    }
    
    window.geofenceChart = new Chart(geofenceCtx, {
        type: 'bar',
        data: {
            labels: data.by_geofence?.map(item => item.name) || [],
            datasets: [{
                label: 'Events',
                data: data.by_geofence?.map(item => item.event_count) || [],
                backgroundColor: '#00b4d8',
                borderColor: '#003566',
                borderWidth: 1
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

    // Vehicle Chart
    const vehicleCtx = document.getElementById('vehicleChart').getContext('2d');
    
    // Exclude synthetic vehicles (article starting with 'Synthetic' or plate starting with 'SYN-')
    const byVehicleFiltered = (data.by_vehicle || []).filter(item => {
        const name = (item.article || '').toString().toLowerCase();
        const plate = (item.plate_number || '').toString().toUpperCase();
        const isSyntheticName = name.startsWith('synthetic');
        const isSyntheticPlate = plate.startsWith('SYN-');
        return !(isSyntheticName || isSyntheticPlate);
    });
    
    // Check if chart exists and destroy it properly
    if (window.vehicleChart && typeof window.vehicleChart.destroy === 'function') {
        window.vehicleChart.destroy();
    }
    
    window.vehicleChart = new Chart(vehicleCtx, {
        type: 'doughnut',
        data: {
            labels: byVehicleFiltered.map(item => item.article) || [],
            datasets: [{
                data: byVehicleFiltered.map(item => item.event_count) || [],
                backgroundColor: [
                    '#003566', '#00b4d8', '#28a745', '#ffc107', '#dc3545',
                    '#6f42c1', '#fd7e14', '#20c997', '#e83e8c', '#6c757d'
                ]
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

    // Load timeline chart with real data
    loadTimelineChart(getFilterParams());
}

async function loadTimelineChart(filters = {}) {
    try {
        // Build query parameters
        const params = new URLSearchParams();
        if (filters.dateRange) params.append('dateRange', filters.dateRange);
        if (filters.deviceId) params.append('deviceId', filters.deviceId);
        
        const response = await fetch(`../geofence_alert_api.php?action=get_timeline_data&${params.toString()}`);
        const result = await response.json();
        
        if (result.success && result.data) {
            renderTimelineChart(result.data);
        } else {
            console.error('Failed to load timeline data:', result.message);
            // Render empty chart if data fails
            renderTimelineChart([]);
        }
    } catch (error) {
        console.error('Error loading timeline data:', error);
        // Render empty chart on error
        renderTimelineChart([]);
    }
}

function renderTimelineChart(timelineData) {
    const timelineCtx = document.getElementById('timelineChart').getContext('2d');
    
    // Check if chart exists and destroy it properly
    if (window.timelineChart && typeof window.timelineChart.destroy === 'function') {
        window.timelineChart.destroy();
    }
    
    const labels = timelineData.map(item => item.hour);
    const data = timelineData.map(item => item.count);
    
    window.timelineChart = new Chart(timelineCtx, {
        type: 'line',
        data: {
            labels: labels,
            datasets: [{
                label: 'Events',
                data: data,
                borderColor: '#00b4d8',
                backgroundColor: 'rgba(0, 180, 216, 0.1)',
                tension: 0.4,
                fill: true,
                pointRadius: 4,
                pointHoverRadius: 6,
                pointBackgroundColor: '#003566',
                pointBorderColor: '#fff',
                pointBorderWidth: 2
            }]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            plugins: {
                legend: {
                    display: true,
                    position: 'top'
                },
                tooltip: {
                    callbacks: {
                        title: function(context) {
                            return `Time: ${context[0].label}`;
                        },
                        label: function(context) {
                            const count = context.parsed.y;
                            return `Events: ${count}`;
                        }
                    }
                }
            },
            scales: {
                y: {
                    beginAtZero: true,
                    ticks: {
                        stepSize: 1
                    },
                    title: {
                        display: true,
                        text: 'Number of Events'
                    }
                },
                x: {    
                    title: {
                        display: true,
                        text: 'Time (Last 24 Hours)'
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

async function loadRecentEvents(filters = {}) {
    try {
        // Build query parameters
        const params = new URLSearchParams();
        params.append('limit', '20');
        if (filters.dateRange) params.append('dateRange', filters.dateRange);
        if (filters.deviceId) params.append('deviceId', filters.deviceId);
        
        // Add date range filters for events table
        const startDate = document.getElementById('eventStartDate')?.value;
        const endDate = document.getElementById('eventEndDate')?.value;
        if (startDate) params.append('startDate', startDate);
        if (endDate) params.append('endDate', endDate);
        
        const response = await fetch(`../geofence_alert_api.php?action=get_geofence_events&${params.toString()}`);
        const data = await response.json();
        
        if (data.success) {
            updateEventsTable(data.data);
        }
    } catch (error) {
        console.error('Error loading events:', error);
    }
}

function updateEventsTable(events) {
    const tbody = document.getElementById('eventsTable');
    
    if (events.length === 0) {
        tbody.innerHTML = '<tr><td colspan="6" class="text-center text-muted">No events found</td></tr>';
        return;
    }
    
    tbody.innerHTML = events.map(event => `
        <tr>
            <td>${new Date(event.created_at).toLocaleString()}</td>
            <td>${event.vehicle_name || 'Unknown'} (${event.plate_number || 'N/A'})</td>
            <td>${event.driver_name || 'Unknown'}</td>
            <td>${event.geofence_name || 'Unknown'}</td>
            <td>
                <span class="badge ${event.event_type === 'entry' ? 'bg-success' : 'bg-warning'}">
                    ${event.event_type === 'entry' ? 'Entered' : 'Exited'}
                </span>
            </td>
            <td>
                <i class="fas fa-check-circle text-success"></i>
            </td>
        </tr>
    `).join('');
}

function exportData(format) {
    Swal.fire({
        title: 'Exporting Data',
        text: `Preparing ${format.toUpperCase()} export...`,
        icon: 'info',
        timer: 2000,
        showConfirmButton: false
    }).then(() => {
        // Mock export functionality
        Swal.fire('Export Complete', `Data exported as ${format.toUpperCase()} file`, 'success');
    });
}

// Date filtering functionality for events table
const eventStartDate = document.getElementById('eventStartDate');
const eventEndDate = document.getElementById('eventEndDate');
const clearDateFilterBtn = document.getElementById('clearDateFilter');

if (eventStartDate) {
    eventStartDate.addEventListener('change', function() {
        loadRecentEvents(getFilterParams());
    });
}

if (eventEndDate) {
    eventEndDate.addEventListener('change', function() {
        loadRecentEvents(getFilterParams());
    });
}

if (clearDateFilterBtn) {
    clearDateFilterBtn.addEventListener('click', function() {
        eventStartDate.value = '';
        eventEndDate.value = '';
        loadRecentEvents(getFilterParams());
    });
}

// Search functionality for events table
const searchInput = document.getElementById('searchEvents');
const clearSearchBtn = document.getElementById('clearSearch');

if (searchInput) {
    searchInput.addEventListener('input', function() {
        filterEventsTable(this.value);
    });
}

if (clearSearchBtn) {
    clearSearchBtn.addEventListener('click', function() {
        searchInput.value = '';
        filterEventsTable('');
    });
}

function filterEventsTable(searchTerm) {
    const tbody = document.getElementById('eventsTable');
    const rows = tbody.getElementsByTagName('tr');
    const term = searchTerm.toLowerCase().trim();
    
    let visibleCount = 0;
    
    for (let i = 0; i < rows.length; i++) {
        const row = rows[i];
        
        // Skip the "no events" or "loading" row
        if (row.cells.length === 1) {
            continue;
        }
        
        // Get text content from all cells
        const time = row.cells[0]?.textContent.toLowerCase() || '';
        const vehicle = row.cells[1]?.textContent.toLowerCase() || '';
        const driver = row.cells[2]?.textContent.toLowerCase() || '';
        const geofence = row.cells[3]?.textContent.toLowerCase() || '';
        const event = row.cells[4]?.textContent.toLowerCase() || '';
        
        // Check if any cell contains the search term
        const matches = time.includes(term) || 
                       vehicle.includes(term) || 
                       driver.includes(term) || 
                       geofence.includes(term) || 
                       event.includes(term);
        
        if (matches || term === '') {
            row.style.display = '';
            visibleCount++;
        } else {
            row.style.display = 'none';
        }
    }
    
    // Show "no results" message if no rows are visible
    if (visibleCount === 0 && term !== '') {
        let noResultsRow = document.getElementById('noResultsRow');
        if (!noResultsRow) {
            noResultsRow = document.createElement('tr');
            noResultsRow.id = 'noResultsRow';
            noResultsRow.innerHTML = '<td colspan="6" class="text-center text-muted">No matching events found</td>';
            tbody.appendChild(noResultsRow);
        }
        noResultsRow.style.display = '';
    } else {
        const noResultsRow = document.getElementById('noResultsRow');
        if (noResultsRow) {
            noResultsRow.style.display = 'none';
        }
    }
}

// Sidebar toggle functionality
const burgerBtn = document.getElementById('burgerBtn');
const sidebar = document.getElementById('sidebar');
const mainContent = document.getElementById('mainContent');

if (burgerBtn) {
    burgerBtn.addEventListener('click', () => {
        const isCollapsed = sidebar.classList.toggle('collapsed');
        mainContent.classList.toggle('collapsed');
    });
}
</script>

</body>
</html>
