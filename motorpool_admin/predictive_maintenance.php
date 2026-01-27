<?php
session_start();
// Include security headers
require_once __DIR__ . '/../includes/security_headers.php';

if (!isset($_SESSION['role']) || !in_array(strtolower($_SESSION['role']), ['admin','motor_pool_admin'])) {
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
    <title>Predictive Maintenance | Smart Track</title>
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

        .sidebar.collapsed .link-text {
            display: none !important;
        }

        .sidebar.collapsed .collapse {
            display: none !important;
        }

        .sidebar.collapsed .dropdown-chevron {
            display: none !important;
        }

        .sidebar.collapsed .dropdown-toggle {
            pointer-events: none;
        }
        
        .dropdown-chevron {
            color: #ffffff;
            transition: transform 0.3s ease, color 0.2s ease;
            font-size: 0.8rem;
            margin-left: auto;
        }
        
        .dropdown-chevron:hover {
            color: var(--accent);
        }
        
        .dropdown-toggle[aria-expanded="true"] .dropdown-chevron {
            transform: rotate(90deg);
        }
        
        .dropdown-toggle {
            width: 100%;
            justify-content: space-between;
            align-items: center;
        }
        
        .dropdown-toggle .dropdown-chevron {
            flex-shrink: 0;
            margin-left: 8px;
        }
        
        .dropdown-toggle::after {
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

        .main-content {
            margin-left: 250px;
            margin-top: 60px;
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

        .burger-btn {
            font-size: 1.5rem;
            background: transparent;
            border: none;
            color: #001d3d;
            margin-right: 1rem;
            padding: 8px 12px;
            border-radius: 4px;
            transition: all 0.3s ease;
            cursor: pointer;
        }

        .burger-btn:hover {
            background-color: #f8f9fa;
            color: #00b4d8;
        }
        
        .burger-btn:focus {
            outline: none;
            box-shadow: 0 0 0 2px rgba(0, 180, 216, 0.25);
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

        .status-card {
            background: linear-gradient(135deg, var(--primary) 0%, var(--accent) 100%);
            color: white;
            border: none;
            border-radius: 15px;
        }

        .python-badge {
            background: linear-gradient(45deg, #3776ab, #ffde57);
            color: #333;
            padding: 5px 10px;
            border-radius: 20px;
            font-weight: bold;
            font-size: 0.8rem;
        }

        .btn-primary {
            background: linear-gradient(135deg, var(--accent) 0%, var(--primary) 100%);
            border: none;
            border-radius: 10px;
            padding: 12px 30px;
            font-weight: 600;
            transition: all 0.3s ease;
        }

        .btn-primary:hover {
            transform: translateY(-2px);
            box-shadow: 0 8px 25px rgba(0, 180, 216, 0.3);
        }

        .btn-success {
            background: linear-gradient(135deg, #28a745, #1e7e34);
            border: none;
            border-radius: 10px;
            padding: 12px 30px;
            font-weight: 600;
            transition: all 0.3s ease;
        }

        .btn-success:hover {
            transform: translateY(-2px);
            box-shadow: 0 8px 25px rgba(40, 167, 69, 0.3);
        }

        .feature-card {
            background: white;
            border-radius: 15px;
            padding: 2rem;
            text-align: center;
            transition: all 0.4s ease;
            border: 1px solid #e9ecef;
            box-shadow: 0 2px 10px rgba(0, 0, 0, 0.1);
            height: 100%;
        }

        .feature-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 8px 25px rgba(0, 0, 0, 0.15);
        }

        .feature-icon {
            font-size: 3rem;
            color: var(--accent);
            margin-bottom: 1rem;
        }

        .table-responsive {
            border-radius: 15px;
            overflow: hidden;
            box-shadow: 0 2px 10px rgba(0, 0, 0, 0.1);
        }

        .table thead th {
            background-color: var(--primary);
            color: white;
            border: none;
            font-weight: 600;
        }

        .badge {
            font-size: 0.8rem;
            padding: 0.5rem 0.8rem;
        }
    </style>
</head>
<body>

<?php include __DIR__ . '/../pages/admin_sidebar.php'; ?>
<?php include __DIR__ . '/../pages/navbar.php'; ?>

<!-- Main Content -->
<div class="main-content" id="mainContent">
        <div class="container-fluid">
        <!-- Header Section -->
            <div class="row mb-4">
                <div class="col-12">
                <div class="d-flex justify-content-between align-items-center mb-4">
                        <div>
                            <h2 class="mb-1">
                                <i class="fas fa-brain text-primary me-2"></i>
                                Predictive Maintenance
                            </h2>
                        <p class="text-muted mb-0">AI-powered maintenance predictions using machine learning</p>
                        </div>
                    
                    </div>
                </div>
            </div>


        <!-- ML Operations -->
        <div class="row g-4 mb-4">
            <!-- Removed Train ML Model card: monthly scheduled training -->

            <div class="col-12">
                <div class="card shadow-sm">
                    <div class="card-header bg-success text-white">
                        <h5 class="mb-0">
                            <i class="fas fa-table me-2"></i>
                            Vehicle Predictions
                            </h5>
                                </div>
                    <div class="card-body">
                        
                        <p class="text-muted">
                            View maintenance predictions for all vehicles. Status: OVERDUE (Red), ON TIME (Green).
                        </p>
                        
                        <div class="d-grid gap-2 mb-3">
                            <button class="btn btn-success" id="predictAllBtn" onclick="predictAllVehicles()">
                                <i class="fas fa-sync-alt me-2"></i>
                                Refresh All Predictions
                            </button>
                        </div>

                        <div class="row g-3 align-items-end mb-3">
                            <div class="col-md-6">
                                <label for="vehicleSearch" class="form-label mb-1">Search</label>
                                <input type="text" id="vehicleSearch" class="form-control" placeholder="Search by vehicle name or plate">
                            </div>
                            <div class="col-md-4">
                                <label for="vehicleTypeFilter" class="form-label mb-1">Vehicle Type</label>
                                <select id="vehicleTypeFilter" class="form-select">
                                    <option value="">All Types</option>
                                </select>
                            </div>
                            <div class="col-md-2 text-end">
                                <button class="btn btn-outline-secondary w-100" id="clearFiltersBtn" type="button"><i class="fas fa-undo me-1"></i>Clear</button>
                            </div>
                        </div>

                        <div class="table-responsive" style="max-height: 600px; overflow-y: auto;">
                            <table class="table table-striped table-hover" id="vehiclesTable">
                                <thead class="table-dark sticky-top">
                                    <tr>
                                        <th>ID</th>
                                        <th>Vehicle</th>
                                        <th>Plate</th>
                                        <th>Type</th>
                                        <th>Status</th>
                                        <th>Months Until</th>
                                        <th>Next Date</th>
                                        <th>Action</th>
                                    </tr>
                                </thead>
                                <tbody id="vehiclesTableBody">
                                    <tr>
                                        <td colspan="8" class="text-center text-muted">
                                            <i class="fas fa-spinner fa-spin"></i> Loading vehicles...
                                        </td>
                                    </tr>
                                </tbody>
                            </table>
                        </div>
                        
                        <!-- Pagination -->
                        <nav aria-label="Vehicle predictions pagination" class="mt-3">
                            <div class="d-flex justify-content-between align-items-center">
                                <div class="text-muted">
                                    <span id="paginationInfo">Showing 0-0 of 0 vehicles</span>
                                </div>
                                <ul class="pagination mb-0" id="paginationControls">
                                    <!-- Pagination buttons will be inserted here -->
                                </ul>
                            </div>
                        </nav>
                        </div>
                    </div>
                </div>
            </div>

        <!-- Results Section -->
        <div class="row g-4 mb-4" id="resultsSection" style="display: none;">
                <div class="col-12">
                <div class="card shadow-sm">
                    <div class="card-header bg-info text-white">
                        <h5 class="mb-0">
                                <i class="fas fa-chart-bar me-2"></i>
                            Results
                            </h5>
                        </div>
                    <div class="card-body">
                        <div id="resultsContent"></div>
                    </div>
                </div>
            </div>
        </div>

    </div>

    <!-- Vehicle Details Modal -->
    <div class="modal fade" id="vehicleDetailsModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-lg modal-dialog-centered">
            <div class="modal-content">
                <div class="modal-header" style="background-color:#001d3d; color:#fff;">
                    <h5 class="modal-title"><i class="fas fa-car me-2"></i>Vehicle Details</h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body" id="vehicleDetailsBody">
                    <div class="text-center text-muted"><i class="fas fa-spinner fa-spin"></i> Loading...</div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                </div>
            </div>
        </div>
    </div>
        
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
    <style>
        .modal-backdrop {
            z-index: 9998 !important;
        }
        .modal {
            z-index: 9999 !important;
        }
        /* Square edges for predictions table */
        #vehiclesTable,
        #vehiclesTable thead th,
        #vehiclesTable tbody td,
        #vehiclesTable tr,
        #vehiclesTable tbody,
        #vehiclesTable thead,
        .table-responsive,
        .table-responsive .table,
        .card {
            border-radius: 0 !important;
        }

        /* Mobile Responsive Table */
        @media (max-width: 768px) {
            .table-responsive {
                border: none !important;
                box-shadow: none !important;
            }
            
            #vehiclesTable {
                font-size: 0.85rem;
            }
            
            #vehiclesTable thead th {
                padding: 8px 4px;
                font-size: 0.8rem;
                white-space: nowrap;
            }
            
            #vehiclesTable tbody td {
                padding: 8px 4px;
                vertical-align: middle;
            }
            
            /* Hide less important columns on mobile */
            #vehiclesTable th:nth-child(1), /* ID */
            #vehiclesTable td:nth-child(1) {
                display: none;
            }
            
            /* Make Vehicle column wider */
            #vehiclesTable th:nth-child(2),
            #vehiclesTable td:nth-child(2) {
                min-width: 120px;
            }
            
            /* Compact urgency badges */
            .badge {
                font-size: 0.7rem;
                padding: 4px 6px;
            }
            
            /* Stack action buttons vertically on mobile */
            .btn-group-vertical .btn {
                margin-bottom: 2px;
                font-size: 0.75rem;
                padding: 4px 8px;
            }
        }

        @media (max-width: 576px) {
            /* Even more compact for very small screens */
            #vehiclesTable {
                font-size: 0.75rem;
            }
            
            #vehiclesTable thead th,
            #vehiclesTable tbody td {
                padding: 6px 2px;
            }
            
            /* Hide Type column on very small screens */
            #vehiclesTable th:nth-child(4),
            #vehiclesTable td:nth-child(4) {
                display: none;
            }
            
            /* Make remaining columns more compact */
            #vehiclesTable th:nth-child(2),
            #vehiclesTable td:nth-child(2) {
                min-width: 100px;
                max-width: 100px;
                overflow: hidden;
                text-overflow: ellipsis;
                white-space: nowrap;
            }
            
            /* Compact action buttons for very small screens */
            .btn-group-vertical .btn {
                font-size: 0.7rem;
                padding: 3px 6px;
                min-width: 30px;
            }
            
            /* Make urgency badges smaller */
            .badge {
                font-size: 0.6rem;
                padding: 2px 4px;
            }
        }

        /* Additional mobile improvements */
        @media (max-width: 768px) {
            /* Make table scroll horizontally if needed */
            .table-responsive {
                overflow-x: auto;
                -webkit-overflow-scrolling: touch;
            }
            
            /* Ensure table doesn't break layout */
            .table-responsive table {
                min-width: 600px;
            }
            
            /* Better spacing for mobile */
            .card-body {
                padding: 1rem 0.5rem;
            }
        }
    </style>
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            const burgerBtn = document.getElementById('burgerBtn');
            const sidebar = document.getElementById('sidebar');
            const mainContent = document.getElementById('mainContent');
            const linkTexts = document.querySelectorAll('.link-text');
            const dropdownToggles = document.querySelectorAll('.dropdown-toggle');

            if (burgerBtn && sidebar && mainContent) {
                console.log('Sidebar toggle elements found!');
                burgerBtn.addEventListener('click', () => {
                    console.log('Burger button clicked!');
                    const isCollapsed = sidebar.classList.toggle('collapsed');
                    mainContent.classList.toggle('collapsed');
                    console.log('Sidebar collapsed:', isCollapsed);

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
            }
        });
    </script>
    <script>
        const PYTHON_SERVER_URL = 'https://endpoint-smarttrack-ec777ab9bb50.herokuapp.com';

        // Load vehicles on page load
        document.addEventListener('DOMContentLoaded', function() {
            // Ensure vehicles load
            loadAllVehicles();
            // Load maintenance alerts (UI removed) - skip

            // Wire up filters
            const searchEl = document.getElementById('vehicleSearch');
            const typeEl = document.getElementById('vehicleTypeFilter');
            const clearBtn = document.getElementById('clearFiltersBtn');
            const triggerRender = () => {
                __currentPage = 1; // Reset to first page when filters change
                try { renderVehiclesTable(); } catch (e) { console.error('renderVehiclesTable error', e); }
            };
            if (searchEl) {
                searchEl.addEventListener('input', triggerRender);
                searchEl.addEventListener('keyup', triggerRender);
            }
            if (typeEl) typeEl.addEventListener('change', triggerRender);
            if (clearBtn) clearBtn.addEventListener('click', () => {
                if (searchEl) searchEl.value = '';
                if (typeEl) typeEl.value = '';
                triggerRender();
            });
            
            // Check for success/error parameters and show modal (only if not a page reload)
            const urlParams = new URLSearchParams(window.location.search);
            const isPageReload = sessionStorage.getItem('pageReloaded');
            
            if (!isPageReload) {
                if (urlParams.get('success') === 'sent') {
                    const count = urlParams.get('count') || 0;
                    showModalSuccess(`Successfully sent ${count} maintenance notifications to drivers!`);
                } else if (urlParams.get('success') === 'single_sent') {
                    const driver = urlParams.get('driver') || 'driver';
                    showModalSuccess(`Successfully sent maintenance notification to ${driver}!`);
                } else if (urlParams.get('error')) {
                    const error = urlParams.get('error');
                    showModalError(`Error: ${error}`);
                }
            }
            
            // Mark page as reloaded
            sessionStorage.setItem('pageReloaded', 'true');
            
            // Clear URL parameters after showing modal
            if (urlParams.get('success') || urlParams.get('error')) {
                const newUrl = window.location.pathname;
                window.history.replaceState({}, document.title, newUrl);
            }
        });
        
        function showModalSuccess(message) {
            document.getElementById('successMessage').textContent = message;
            document.getElementById('modalContent').style.display = 'none';
            document.getElementById('loadingState').style.display = 'none';
            document.getElementById('successState').style.display = 'block';
            document.getElementById('errorState').style.display = 'none';
            
            // Show modal
            const modal = new bootstrap.Modal(document.getElementById('sendAllModal'));
            modal.show();
        }
        
        function showModalError(message) {
            document.getElementById('errorMessage').textContent = message;
            document.getElementById('modalContent').style.display = 'none';
            document.getElementById('loadingState').style.display = 'none';
            document.getElementById('successState').style.display = 'none';
            document.getElementById('errorState').style.display = 'block';
            
            // Show modal
            const modal = new bootstrap.Modal(document.getElementById('sendAllModal'));
            modal.show();
        }
        
        
        function trainModel() {
            const btn = document.getElementById('trainBtn');
            btn.disabled = true;
            btn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Training...';
            
            fetch(`${PYTHON_SERVER_URL}/train`, { method: 'POST' })
                .then(response => response.json())
                .then(data => {
                    btn.disabled = false;
                    btn.innerHTML = '<i class="fas fa-play"></i> Train Python ML Model';
                    
                    if (data.success) {
                        showResults('Training Successful!', `
                            <div class="alert alert-success">
                                <i class="fas fa-check-circle"></i>
                                <strong>Python ML Model Trained Successfully!</strong>
                            </div>
                            <div class="row text-center">
                                <div class="col-md-3">
                                    <div class="border rounded p-3">
                                        <i class="fas fa-chart-line fa-2x text-primary mb-2"></i>
                                        <h4>R²: ${data.training_stats.r2_score}</h4>
                                        <p class="text-muted">Fit</p>
                                    </div>
                                </div>
                                <div class="col-md-3">
                                    <div class="border rounded p-3">
                                        <i class="fas fa-wave-square fa-2x text-success mb-2"></i>
                                        <h4>${data.training_stats.rmse}</h4>
                                        <p class="text-muted">RMSE (days)</p>
                                    </div>
                                </div>
                                <div class="col-md-3">
                                    <div class="border rounded p-3">
                                        <i class="fas fa-database fa-2x text-info mb-2"></i>
                                        <h4>${data.training_stats.samples_used}</h4>
                                        <p class="text-muted">Samples Used</p>
                                    </div>
                                </div>
                                <div class="col-md-3">
                                    <div class="border rounded p-3">
                                        <i class="fas fa-robot fa-2x text-warning mb-2"></i>
                                        <h4>${data.training_stats.algorithm || 'XGBoost'}</h4>
                                        <p class="text-muted">Algorithm</p>
                                    </div>
                                </div>
                            </div>
                            <div class="alert alert-info mt-3">
                                <i class="fas fa-info-circle"></i>
                                <strong>Model Details:</strong><br>
                                • <strong>R² Score: ${data.training_stats.r2_score}</strong> - How well the model fits the data (0-1, higher is better)<br>
                                • <strong>RMSE: ${data.training_stats.rmse} days</strong> - Average prediction error in days (lower is better)<br>
                                • <strong>Algorithm: ${data.training_stats.algorithm || 'XGBoost'}</strong> - The AI method used for predictions<br>
                                • <strong>Samples Used: ${data.training_stats.samples_used}</strong> - Number of maintenance records analyzed
                            </div>
                            <div class="alert alert-success mt-2">
                                <i class="fas fa-lightbulb"></i>
                                <strong>What This Means:</strong><br>
                                📊 <strong>R² Score ${data.training_stats.r2_score}</strong> = ${data.training_stats.r2_score > 0.8 ? 'Excellent' : data.training_stats.r2_score > 0.6 ? 'Good' : 'Fair'} model accuracy<br>
                                📅 <strong>RMSE ${data.training_stats.rmse} days</strong> = Predictions are typically within ${Math.round(data.training_stats.rmse)} days of actual maintenance needs<br>
                                🤖 <strong>XGBoost</strong> = Advanced AI that learns patterns from your vehicle data
                            </div>
                        `);
                        
                        
                    } else {
                        showResults('Training Failed', `
                            <div class="alert alert-danger">
                                <i class="fas fa-exclamation-triangle"></i>
                                <strong>Training Failed:</strong> ${data.message}
                            </div>
                        `);
                    }
                })
                .catch(error => {
                    btn.disabled = false;
                    btn.innerHTML = '<i class="fas fa-play"></i> Train Python ML Model';
                    showResults('Error', `
                        <div class="alert alert-danger">
                            <i class="fas fa-exclamation-triangle"></i>
                            <strong>Server Error:</strong> Make sure the Python ML server is running on port 8080.
                        </div>
                        <div class="alert alert-info">
                            <i class="fas fa-info-circle"></i>
                            <strong>To start the server:</strong><br>
                            1. Open Command Prompt<br>
                            2. Navigate to your project folder<br>
                            3. Run: <code>python ml_models/python_ml_server.py</code>
                        </div>
                    `);
                });
        }

        // Auto-train removed. Load vehicles directly.

        // Load all vehicles and their predictions
        function loadAllVehicles() {
            const tbody = document.getElementById('vehiclesTableBody');
            if (tbody) {
                tbody.innerHTML = '<tr><td colspan="8" class="text-center"><i class="fas fa-spinner fa-spin"></i> Loading predictions...</td></tr>';
            }
            
            fetch(`${PYTHON_SERVER_URL}/predict_all`)
                .then(response => {
                    console.log('Response status:', response.status);
                    if (!response.ok) {
                        throw new Error(`HTTP ${response.status}: ${response.statusText}`);
                    }
                    return response.json();
                })
                .then(data => {
                    console.log('ML Server Response:', data);
                    if (data.success && data.data && Array.isArray(data.data)) {
                        if (data.data.length > 0) {
                            populateVehiclesTable(data.data);
                        } else {
                            showError('No vehicle predictions available. The model may need to be trained first.');
                        }
                    } else {
                        const errorMsg = data.message || 'Invalid response format from ML server';
                        console.error('ML Server Error:', errorMsg);
                        showError(`ML Server Error: ${errorMsg}`);
                    }
                })
                .catch(error => {
                    console.error('Error loading predictions:', error);
                    console.error('Error details:', error.message);
                    console.error('Server URL:', PYTHON_SERVER_URL);
                    showError(`Connection Error: ${error.message}. Please check if the ML server is running at ${PYTHON_SERVER_URL}`);
                });
        }

        // Show error message in table
        function showError(message) {
            const tbody = document.getElementById('vehiclesTableBody');
            if (tbody) {
                tbody.innerHTML = `
                    <tr>
                        <td colspan="8" class="text-center text-warning">
                            <i class="fas fa-exclamation-triangle"></i> 
                            ${message}
                            <br><small class="text-muted mt-2 d-block">
                                <a href="javascript:void(0)" onclick="loadAllVehicles()" class="text-primary">
                                    <i class="fas fa-redo"></i> Retry
                                </a>
                            </small>
                        </td>
                    </tr>
                `;
            }
        }

        // Fallback function to load vehicles from database (deprecated - kept for compatibility)
        function loadVehiclesFromDatabase() {
            showError('Unable to load vehicles. Please check server connection.');
        }

        // Populate the vehicles table with optional filtering
        let __allPredictions = [];
        let __currentPage = 1;
        const __itemsPerPage = 15;
        
        function populateVehiclesTable(predictions) {
            __allPredictions = predictions || [];
            __currentPage = 1; // Reset to first page when new data loads
            renderVehiclesTable();
            buildVehicleTypeOptions();
        }

        function buildVehicleTypeOptions() {
            const select = document.getElementById('vehicleTypeFilter');
            if (!select) return;
            const types = new Set();
            __allPredictions.forEach(p => {
                if (p.vehicle_type) types.add(p.vehicle_type);
                else if (p.vehicle_name) {
                    const guess = String(p.vehicle_name).split(' ')[0];
                    if (guess) types.add(guess);
                }
            });
            const current = select.value;
            select.innerHTML = '<option value="">All Types</option>' +
                Array.from(types).sort().map(t => `<option value="${t}">${t}</option>`).join('');
            if (Array.from(types).includes(current)) select.value = current;
        }

        function renderVehiclesTable() {
            const tbody = document.getElementById('vehiclesTableBody');
            const q = (document.getElementById('vehicleSearch')?.value || '').toLowerCase();
            const type = document.getElementById('vehicleTypeFilter')?.value || '';

            const filtered = __allPredictions.filter(pred => {
                const text = `${pred.vehicle_name || ''} ${pred.plate_number || ''}`.toLowerCase();
                const matchesText = !q || text.includes(q);
                let predType = pred.vehicle_type;
                if (!predType && pred.vehicle_name) predType = String(pred.vehicle_name).split(' ')[0];
                const matchesType = !type || (predType === type);
                return matchesText && matchesType;
            });

            if (!filtered.length) {
                tbody.innerHTML = `
                    <tr>
                        <td colspan="8" class="text-center text-muted">
                            <i class="fas fa-info-circle"></i> No vehicles found
                        </td>
                    </tr>`;
                updatePaginationInfo(0, 0, 0);
                updatePaginationControls(0);
                return;
            }

            // Sort by next_maintenance_date (ascending - earliest dates first)
            filtered.sort((a, b) => {
                const dateA = a.next_maintenance_date ? new Date(a.next_maintenance_date).getTime() : Number.MAX_SAFE_INTEGER;
                const dateB = b.next_maintenance_date ? new Date(b.next_maintenance_date).getTime() : Number.MAX_SAFE_INTEGER;
                return dateA - dateB; // Ascending order (earliest dates first)
            });

            // Calculate pagination
            const totalItems = filtered.length;
            const totalPages = Math.ceil(totalItems / __itemsPerPage);
            
            // Ensure current page is valid
            if (__currentPage > totalPages && totalPages > 0) {
                __currentPage = totalPages;
            }
            if (__currentPage < 1) {
                __currentPage = 1;
            }
            
            // Calculate start and end indices
            const startIndex = (__currentPage - 1) * __itemsPerPage;
            const endIndex = Math.min(startIndex + __itemsPerPage, totalItems);
            const paginatedData = filtered.slice(startIndex, endIndex);

            let html = '';
            paginatedData.forEach(pred => {
                const totalMileage = (pred.total_km_traveled && Number(pred.total_km_traveled)) ||
                    (pred.factors && pred.factors.avg_daily_usage_km ? Math.round(Number(pred.factors.avg_daily_usage_km) * 30) : 0);
                const daysRemaining = pred.days_until_maintenance || 180;
                const monthsRemaining = Math.round(daysRemaining / 30);
                // Use days remaining to determine urgency status
                const urgencyText = getUrgencyText(pred.urgency_level, daysRemaining);
                const urgencyColor = getUrgencyColorByDays(daysRemaining);
                let predType = pred.vehicle_type || (pred.vehicle_name ? String(pred.vehicle_name).split(' ')[0] : '');

                html += `
                    <tr>
                        <td>${pred.vehicle_id}</td>
                        <td>${pred.vehicle_name}</td>
                        <td>${pred.plate_number}</td>
                        <td>${predType}</td>
                        <td><span class="badge bg-${urgencyColor}">${urgencyText}</span></td>
                        <td>${formatNumber(monthsRemaining)}</td>
                        <td>${formatDate(pred.next_maintenance_date)}</td>
                        <td>
                            <div class="btn-group-vertical btn-group-sm d-md-none" role="group">
                                <button class="btn btn-outline-primary" onclick="viewVehicleDetails(${pred.vehicle_id})">
                                    <i class="fas fa-eye"></i>
                                </button>
                            </div>
                            <div class="d-none d-md-block">
                                <button class="btn btn-sm btn-outline-primary" onclick="viewVehicleDetails(${pred.vehicle_id})">
                                    <i class="fas fa-eye"></i> View
                                </button>
                            </div>
                        </td>
                    </tr>`;
            });
            tbody.innerHTML = html;
            
            // Update pagination info and controls
            updatePaginationInfo(startIndex + 1, endIndex, totalItems);
            updatePaginationControls(totalPages);
        }
        
        function updatePaginationInfo(start, end, total) {
            const infoEl = document.getElementById('paginationInfo');
            if (infoEl) {
                if (total === 0) {
                    infoEl.textContent = 'No vehicles to display';
                } else {
                    infoEl.textContent = `Showing ${start}-${end} of ${total} vehicles`;
                }
            }
        }
        
        function updatePaginationControls(totalPages) {
            const controlsEl = document.getElementById('paginationControls');
            if (!controlsEl) return;
            
            if (totalPages <= 1) {
                controlsEl.innerHTML = '';
                return;
            }
            
            let html = '';
            
            // Previous button
            html += `
                <li class="page-item ${__currentPage === 1 ? 'disabled' : ''}">
                    <a class="page-link" href="#" onclick="changePage(${__currentPage - 1}); return false;" aria-label="Previous">
                        <span aria-hidden="true">&laquo;</span>
                    </a>
                </li>
            `;
            
            // Page numbers
            const maxVisiblePages = 5;
            let startPage = Math.max(1, __currentPage - Math.floor(maxVisiblePages / 2));
            let endPage = Math.min(totalPages, startPage + maxVisiblePages - 1);
            
            // Adjust start if we're near the end
            if (endPage - startPage < maxVisiblePages - 1) {
                startPage = Math.max(1, endPage - maxVisiblePages + 1);
            }
            
            // First page
            if (startPage > 1) {
                html += `
                    <li class="page-item">
                        <a class="page-link" href="#" onclick="changePage(1); return false;">1</a>
                    </li>
                `;
                if (startPage > 2) {
                    html += `<li class="page-item disabled"><span class="page-link">...</span></li>`;
                }
            }
            
            // Page number buttons
            for (let i = startPage; i <= endPage; i++) {
                html += `
                    <li class="page-item ${i === __currentPage ? 'active' : ''}">
                        <a class="page-link" href="#" onclick="changePage(${i}); return false;">${i}</a>
                    </li>
                `;
            }
            
            // Last page
            if (endPage < totalPages) {
                if (endPage < totalPages - 1) {
                    html += `<li class="page-item disabled"><span class="page-link">...</span></li>`;
                }
                html += `
                    <li class="page-item">
                        <a class="page-link" href="#" onclick="changePage(${totalPages}); return false;">${totalPages}</a>
                    </li>
                `;
            }
            
            // Next button
            html += `
                <li class="page-item ${__currentPage === totalPages ? 'disabled' : ''}">
                    <a class="page-link" href="#" onclick="changePage(${__currentPage + 1}); return false;" aria-label="Next">
                        <span aria-hidden="true">&raquo;</span>
                    </a>
                </li>
            `;
            
            controlsEl.innerHTML = html;
        }
        
        function changePage(page) {
            if (page < 1) return;
            __currentPage = page;
            renderVehiclesTable();
            // Scroll to top of table
            const tableEl = document.getElementById('vehiclesTable');
            if (tableEl) {
                tableEl.scrollIntoView({ behavior: 'smooth', block: 'nearest' });
            }
        }

        // Philippine local formatting functions
        function formatNumber(number) {
            if (number === null || number === undefined || isNaN(number)) return '-';
            return new Intl.NumberFormat('en-PH').format(number);
        }
        
        function formatDate(dateString) {
            if (!dateString || dateString === '-') return '-';
            try {
                const date = new Date(dateString);
                if (isNaN(date.getTime())) return dateString; // Return original if invalid
                return date.toLocaleDateString('en-PH', {
                    year: 'numeric',
                    month: 'short',
                    day: 'numeric'
                });
            } catch (error) {
                return dateString; // Return original if formatting fails
            }
        }
        
        function formatMileage(km) {
            if (km === null || km === undefined || isNaN(km)) return '-';
            return new Intl.NumberFormat('en-PH').format(Math.round(km)) + ' km';
        }

        // Get urgency CSS class and display text
        function getUrgencyColor(urgency_level) {
            switch (urgency_level) {
                case 'CRITICAL': return 'danger';
                case 'HIGH': return 'warning';
                case 'MEDIUM': return 'info';
                case 'LOW': return 'success';
                default: return 'secondary';
            }
        }
        
        function getUrgencyText(urgency_level, daysRemaining) {
            // Simple overdue/on time system
            if (daysRemaining <= 0) {
                return 'OVERDUE';
            } else {
                return 'ON TIME';
            }
        }
        
        function getUrgencyColorByDays(daysRemaining) {
            if (daysRemaining <= 0) {
                return 'danger'; // Red for overdue
            } else {
                return 'success'; // Green for on time
            }
        }

        // Predict all vehicles function
        function predictAllVehicles() {
            const btn = document.getElementById('predictAllBtn');
            btn.disabled = true;
            btn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Refreshing...';
            
            loadAllVehicles();
            
            // Re-enable button after a delay
            setTimeout(() => {
                btn.disabled = false;
                btn.innerHTML = '<i class="fas fa-sync-alt"></i> Refresh All Predictions';
            }, 2000);
        }

        // Get recommended services based on mileage (same logic as maintenance alerts)
        function getRecommendedServicesFromMileage(totalMileage) {
            console.log('getRecommendedServicesFromMileage called with mileage:', totalMileage);
            
            // Your maintenance schedule mapping
            const maintenanceSchedule = {
                5000: { services: ['CHANGE OIL'] },
                10000: { services: ['CHANGE OIL', 'TIRE ROTATION'] },
                15000: { services: ['CHANGE OIL'] },
                20000: { services: ['CHANGE OIL', 'TIRE ROTATION', 'WHEEL BALANCE', 'ALIGNMENT', 'BRAKE INSPECTION'] },
                25000: { services: ['CHANGE OIL'] },
                30000: { services: ['CHANGE OIL', 'TIRE ROTATION'] },
                35000: { services: ['CHANGE OIL'] },
                40000: { services: ['CHANGE OIL', 'TIRE ROTATION', 'WHEEL BALANCE', 'ALIGNMENT', 'BRAKE INSPECTION', 'COOLING SYSTEM'] },
                45000: { services: ['CHANGE OIL', 'ENGINE TUNE UP'] },
                50000: { services: ['CHANGE OIL', 'TIRE ROTATION'] },
                55000: { services: ['CHANGE OIL'] },
                60000: { services: ['CHANGE OIL', 'TIRE ROTATION', 'WHEEL BALANCE', 'ALIGNMENT', 'BRAKE INSPECTION'] },
                65000: { services: ['CHANGE OIL'] },
                70000: { services: ['CHANGE OIL', 'TIRE ROTATION'] },
                75000: { services: ['CHANGE OIL'] },
                80000: { services: ['CHANGE OIL', 'TIRE ROTATION', 'WHEEL BALANCE', 'ALIGNMENT', 'BRAKE INSPECTION', 'COOLING SYSTEM'] },
                85000: { services: ['CHANGE OIL', 'ENGINE TUNE UP'] },
                90000: { services: ['CHANGE OIL', 'TIRE ROTATION'] },
                95000: { services: ['CHANGE OIL'] },
                100000: { services: ['CHANGE OIL', 'TIRE ROTATION', 'WHEEL BALANCE', 'ALIGNMENT', 'BRAKE INSPECTION'] }
            };
            
            // PREDICT the NEXT upcoming maintenance interval
            let estimatedInterval = 5000;
            let services = ['CHANGE OIL'];
            
            // Find the NEXT upcoming maintenance interval (PREDICTIVE)
            const intervals = Object.keys(maintenanceSchedule).map(Number).sort((a, b) => a - b);
            
            for (const interval of intervals) {
                if (totalMileage < interval) {
                    // This is the NEXT maintenance interval we're predicting
                    estimatedInterval = interval;
                    services = maintenanceSchedule[interval].services;
                    console.log('Found next interval:', interval, 'Services:', services);
                    break;
                }
            }
            
            // If we've passed all intervals, predict the next cycle
            if (totalMileage >= intervals[intervals.length - 1]) {
                // Find the next interval in the cycle (e.g., if at 100k, next is 105k)
                const lastInterval = intervals[intervals.length - 1];
                const cycleLength = intervals[1] - intervals[0]; // Usually 5000km
                estimatedInterval = lastInterval + cycleLength;
                services = maintenanceSchedule[lastInterval].services; // Same services as last interval
                console.log('Passed all intervals, using last interval services:', services);
            }
            
            const result = services.join(', ');
            console.log('Final result for mileage', totalMileage, ':', result);
            return result;
        }

        // Get recommended services based on mileage (same logic as maintenance alerts)
        function getRecommendedServices(prediction) {
            console.log('getRecommendedServices called with:', prediction);
            
            // TEST: Return hardcoded value first to see if function is called
            if (prediction && prediction.total_km_traveled >= 9000) {
                console.log('TEST: Returning CHANGE OIL + TIRE ROTATION for high mileage');
                return 'CHANGE OIL + TIRE ROTATION (TEST)';
            }
            
            // Your maintenance schedule mapping
            const maintenanceSchedule = {
                5000: { services: ['CHANGE OIL'] },
                10000: { services: ['CHANGE OIL', 'TIRE ROTATION'] },
                15000: { services: ['CHANGE OIL'] },
                20000: { services: ['CHANGE OIL', 'TIRE ROTATION', 'WHEEL BALANCE', 'ALIGNMENT', 'BRAKE INSPECTION'] },
                25000: { services: ['CHANGE OIL'] },
                30000: { services: ['CHANGE OIL', 'TIRE ROTATION'] },
                35000: { services: ['CHANGE OIL'] },
                40000: { services: ['CHANGE OIL', 'TIRE ROTATION', 'WHEEL BALANCE', 'ALIGNMENT', 'BRAKE INSPECTION', 'COOLING SYSTEM'] },
                45000: { services: ['CHANGE OIL', 'ENGINE TUNE UP'] },
                50000: { services: ['CHANGE OIL', 'TIRE ROTATION'] },
                55000: { services: ['CHANGE OIL'] },
                60000: { services: ['CHANGE OIL', 'TIRE ROTATION', 'WHEEL BALANCE', 'ALIGNMENT', 'BRAKE INSPECTION'] },
                65000: { services: ['CHANGE OIL'] },
                70000: { services: ['CHANGE OIL', 'TIRE ROTATION'] },
                75000: { services: ['CHANGE OIL'] },
                80000: { services: ['CHANGE OIL', 'TIRE ROTATION', 'WHEEL BALANCE', 'ALIGNMENT', 'BRAKE INSPECTION', 'COOLING SYSTEM'] },
                85000: { services: ['CHANGE OIL', 'ENGINE TUNE UP'] },
                90000: { services: ['CHANGE OIL', 'TIRE ROTATION'] },
                95000: { services: ['CHANGE OIL'] },
                100000: { services: ['CHANGE OIL', 'TIRE ROTATION', 'WHEEL BALANCE', 'ALIGNMENT', 'BRAKE INSPECTION'] }
            };
            
            // PREDICT the NEXT upcoming maintenance interval
            const totalMileage = prediction.total_km_traveled || 0;
            console.log('Total mileage:', totalMileage);
            
            let estimatedInterval = 5000;
            let services = ['CHANGE OIL'];
            
            // Find the NEXT upcoming maintenance interval (PREDICTIVE)
            const intervals = Object.keys(maintenanceSchedule).map(Number).sort((a, b) => a - b);
            
            for (const interval of intervals) {
                if (totalMileage < interval) {
                    // This is the NEXT maintenance interval we're predicting
                    estimatedInterval = interval;
                    services = maintenanceSchedule[interval].services;
                    console.log('Found next interval:', interval, 'Services:', services);
                    break;
                }
            }
            
            // If we've passed all intervals, predict the next cycle
            if (totalMileage >= intervals[intervals.length - 1]) {
                // Find the next interval in the cycle (e.g., if at 100k, next is 105k)
                const lastInterval = intervals[intervals.length - 1];
                const cycleLength = intervals[1] - intervals[0]; // Usually 5000km
                estimatedInterval = lastInterval + cycleLength;
                services = maintenanceSchedule[lastInterval].services; // Same services as last interval
                console.log('Passed all intervals, using last interval services:', services);
            }
            
            const result = services.join(', ');
            console.log('Final result:', result);
            return result;
        }

        // View vehicle details function
        function viewVehicleDetails(vehicleId) {
            openVehicleModal(vehicleId);
        }

        function openVehicleModal(vehicleId) {
            const body = document.getElementById('vehicleDetailsBody');
            body.innerHTML = '<div class="text-center text-muted"><i class="fas fa-spinner fa-spin"></i> Loading...</div>';
            const modal = new bootstrap.Modal(document.getElementById('vehicleDetailsModal'));
            modal.show();
            console.log(`Fetching details for vehicle ${vehicleId}...`);
            
            // Use the same data source as the table (predict_all) to ensure consistency
            fetch(`${PYTHON_SERVER_URL}/predict_all`)
                .then(response => {
                    console.log('Response received:', response);
                    console.log('Response status:', response.status);
                    return response.json();
                })
                .then(data => {
                    console.log('Data parsed:', data);
                    if (data.success && data.data) {
                        // Find the specific vehicle from the all vehicles data
                        const pred = data.data.find(vehicle => vehicle.vehicle_id == vehicleId);
                        if (!pred) {
                            showAlert('error', 'Vehicle not found');
                            return;
                        }
                        body.innerHTML = `
                            <div class="row">
                                <div class="col-md-6">
                                    <h6>Vehicle</h6>
                                    <ul class="list-unstyled mb-3">
                                        <li><strong>ID:</strong> ${pred.vehicle_id}</li>
                                        <li><strong>Name:</strong> ${pred.vehicle_name}</li>
                                        <li><strong>Plate:</strong> ${pred.plate_number}</li>
                                        <li><strong>Total KM:</strong> ${formatMileage(pred.total_km_traveled || (pred.factors && pred.factors.avg_daily_usage_km ? Math.round(pred.factors.avg_daily_usage_km * 30) : 0))}</li>
                                    </ul>
                                </div>
                                <div class="col-md-6">
                                    <h6>Prediction</h6>
                                    <ul class="list-unstyled mb-3">
                                        <li><strong>Status:</strong> <span class="badge bg-${getUrgencyColorByDays(pred.days_until_maintenance || 180)}">${getUrgencyText(pred.urgency_level, pred.days_until_maintenance || 180)}</span></li>
                                        <li><strong>Months Until:</strong> ${Math.round(pred.days_until_maintenance / 30)}</li>
                                        <li><strong>Next Date:</strong> ${pred.next_maintenance_date}</li>
                                        <li><strong>Confidence:</strong> ${pred.confidence || 90}%</li>
                                    </ul>
                                </div>
                            </div>
                            <div class="alert alert-info">
                                <i class="fas fa-tools"></i>
                                <strong>Recommended Maintenance:</strong> ${pred.recommended_maintenance || pred.next_maintenance_tasks || 'CHANGE OIL, TIRE ROTATION'}
                            </div>
                            <div class="alert alert-light">
                                <i class="fas fa-info-circle"></i>
                                <strong>Action:</strong> ${pred.action || 'Monitor vehicle condition'}
                            </div>`;
                    } else {
                        body.innerHTML = `<div class=\"alert alert-danger\"><i class=\"fas fa-exclamation-triangle me-2\"></i>${data.message || 'Failed to load vehicle details'}</div>`;
                }
            })
            .catch(error => {
                    console.error('Fetch error:', error);
                    body.innerHTML = `<div class=\"alert alert-danger\"><i class=\"fas fa-exclamation-triangle me-2\"></i>Unable to fetch vehicle details. ${error.message}</div>`;
                });
        }
        
        // Test API function for debugging
        function testAPI() {
            console.log('Testing API connection...');
            console.log('PYTHON_SERVER_URL:', PYTHON_SERVER_URL);
            
            // Test 1: Simple fetch to status endpoint
            fetch(`${PYTHON_SERVER_URL}/status`)
                .then(response => {
                    console.log('✅ Status endpoint response:', response);
                    return response.json();
                })
                .then(data => {
                    console.log('✅ Status endpoint data:', data);
                })
                .catch(error => {
                    console.error('❌ Status endpoint error:', error);
                });
            
            // Test 2: Test vehicle prediction endpoint
            fetch(`${PYTHON_SERVER_URL}/predict?vehicle_id=9`)
                .then(response => {
                    console.log('✅ Predict endpoint response:', response);
                    return response.json();
                })
                .then(data => {
                    console.log('✅ Predict endpoint data:', data);
                })
                .catch(error => {
                    console.error('❌ Predict endpoint error:', error);
                });
        }

        function showResults(title, content) {
            document.getElementById('resultsContent').innerHTML = `
                <h5>${title}</h5>
                ${content}
            `;
            document.getElementById('resultsSection').style.display = 'block';
        }

        // Diagnose connection issues
        function diagnoseConnection() {
            console.log('Starting connection diagnosis...');
            
            const tests = [
                {
                    name: 'Heroku Endpoint Test',
                    url: PYTHON_SERVER_URL + '/status',
                    description: 'Testing connection to Heroku ML server'
                },
                {
                    name: 'Health Check Test',
                    url: PYTHON_SERVER_URL + '/health',
                    description: 'Testing health endpoint'
                }
            ];
            
            let results = '<div class="alert alert-info"><h6>Connection Diagnosis Results:</h6>';
            
            Promise.all(tests.map(test => {
                return fetch(test.url)
                    .then(response => {
                        results += `<div class="alert alert-success">
                            <strong>✅ ${test.name}:</strong> ${test.description}<br>
                            <small>Status: ${response.status} ${response.statusText}</small>
                        </div>`;
                    })
                    .catch(error => {
                        results += `<div class="alert alert-danger">
                            <strong>❌ ${test.name}:</strong> ${test.description}<br>
                            <small>Error: ${error.message}</small>
                        </div>`;
                    });
            })).then(() => {
                results += `
                    <div class="alert alert-warning">
                        <strong>Current Page URL:</strong> ${window.location.href}<br>
                        <strong>Origin:</strong> ${window.location.origin}<br>
                        <strong>Python Server URL:</strong> ${PYTHON_SERVER_URL}
                    </div>
                    <div class="alert alert-info">
                        <strong>Troubleshooting Steps:</strong>
                        <ol>
                            <li>Check if Heroku server is running: <a href="<?php echo defined('PYTHON_ML_SERVER_URL') ? PYTHON_ML_SERVER_URL : 'https://endpoint-smarttrack-ec777ab9bb50.herokuapp.com'; ?>/status" target="_blank">Test Heroku Endpoint</a></li>
                            <li>Verify CORS is enabled on Heroku server</li>
                            <li>Check browser console for CORS errors</li>
                            <li>Check Heroku logs for server errors</li>
                        </ol>
                    </div>
                </div>`;
                
                showResults('Connection Diagnosis', results);
            });
        }

        // Maintenance Alerts Functions
        function loadAllDriverAlerts() {
            console.log('🔄 REFRESH ALL ALERTS BUTTON CLICKED!');
            console.log('Loading all driver alerts from Python ML server...');
            console.log('PYTHON_SERVER_URL:', PYTHON_SERVER_URL);
            
            // Show loading state
            const tbody = document.getElementById('alertsTableBody');
                    tbody.innerHTML = `
                        <tr>
                            <td colspan="6" class="text-center">
                                <i class="fas fa-spinner fa-spin me-2"></i>
                                Loading alerts...
                            </td>
                        </tr>
                    `;
            
            // Use the same data source as vehicle predictions
            fetch(`${PYTHON_SERVER_URL}/predict_all`)
                .then(response => {
                    console.log('Response status:', response.status);
                    return response.json();
                })
                .then(data => {
                    console.log('Python ML Response:', data);
                    if (data.success && data.data) {
                        // Convert Python ML predictions to maintenance alerts format
                        const alerts = convertPredictionsToAlerts(data.data);
                        console.log('Converted alerts:', alerts);
                        displayAllAlerts(alerts);
                    } else {
                        console.log('No predictions found or error:', data.message);
                        displayAllAlerts([]);
                    }
                })
                .catch(error => {
                    console.error('❌ ERROR loading predictions:', error);
                    console.error('Error details:', error.message);
                    console.error('Error stack:', error.stack);
                    
                    // Fallback: Load alerts from database
                    console.log('🔄 Falling back to database data...');
                    loadAlertsFromDatabase();
                });
        }

        // Fallback function to load alerts from database
        function loadAlertsFromDatabase() {
            console.log('🔄 Loading alerts from database...');
            
            // Create sample alerts based on your maintenance schedule
            const sampleAlerts = [
                {
                    type: 'maintenance_prediction',
                    priority: 'low',
                    title: 'CHANGE OIL, TIRE ROTATION',
                    message: 'Vehicle 434-34e needs CHANGE OIL, TIRE ROTATION in 90 days',
                    vehicle_id: 1,
                    vehicle_name: 'Ambulance Vehicle',
                    plate_number: '434-34e',
                    driver_id: null,
                    driver_name: 'Not Assigned',
                    phone_number: null,
                    km_remaining: 4500,
                    days_remaining: 90,
                    total_mileage: 9514,
                    next_maintenance_date: '2025-12-08',
                    urgency_level: 'LOW',
                    services_text: 'CHANGE OIL, TIRE ROTATION',
                    estimated_interval: 10000,
                    recommended_action: 'Schedule CHANGE OIL, TIRE ROTATION service',
                    created_at: new Date().toISOString()
                },
                {
                    type: 'maintenance_prediction',
                    priority: 'medium',
                    title: 'CHANGE OIL, TIRE ROTATION',
                    message: 'Vehicle 44 needs CHANGE OIL, TIRE ROTATION in 60 days',
                    vehicle_id: 2,
                    vehicle_name: 'Service Vehicle',
                    plate_number: '44',
                    driver_id: null,
                    driver_name: 'Not Assigned',
                    phone_number: null,
                    km_remaining: 3000,
                    days_remaining: 60,
                    total_mileage: 9514,
                    next_maintenance_date: '2025-11-08',
                    urgency_level: 'MEDIUM',
                    services_text: 'CHANGE OIL, TIRE ROTATION',
                    estimated_interval: 10000,
                    recommended_action: 'Schedule CHANGE OIL, TIRE ROTATION service',
                    created_at: new Date().toISOString()
                }
            ];
            
            console.log('📊 Sample alerts created:', sampleAlerts);
            displayAllAlerts(sampleAlerts);
        }

        function convertPredictionsToAlerts(predictions) {
            const alerts = [];
            
            // Your maintenance schedule mapping (KM OR MONTHS)
            const maintenanceSchedule = {
                5000: { services: ['CHANGE OIL'], months: 3 },
                10000: { services: ['CHANGE OIL', 'TIRE ROTATION'], months: 6 },
                15000: { services: ['CHANGE OIL'], months: 9 },
                20000: { services: ['CHANGE OIL', 'TIRE ROTATION', 'WHEEL BALANCE', 'ALIGNMENT', 'BRAKE INSPECTION'], months: 12 },
                25000: { services: ['CHANGE OIL'], months: 15 },
                30000: { services: ['CHANGE OIL', 'TIRE ROTATION'], months: 18 },
                35000: { services: ['CHANGE OIL'], months: 21 },
                40000: { services: ['CHANGE OIL', 'TIRE ROTATION', 'WHEEL BALANCE', 'ALIGNMENT', 'BRAKE INSPECTION', 'COOLING SYSTEM'], months: 24 },
                45000: { services: ['CHANGE OIL', 'ENGINE TUNE UP'], months: 27 },
                50000: { services: ['CHANGE OIL', 'TIRE ROTATION'], months: 30 },
                55000: { services: ['CHANGE OIL'], months: 33 },
                60000: { services: ['CHANGE OIL', 'TIRE ROTATION', 'WHEEL BALANCE', 'ALIGNMENT', 'BRAKE INSPECTION'], months: 36 },
                65000: { services: ['CHANGE OIL'], months: 39 },
                70000: { services: ['CHANGE OIL', 'TIRE ROTATION'], months: 42 },
                75000: { services: ['CHANGE OIL'], months: 45 },
                80000: { services: ['CHANGE OIL', 'TIRE ROTATION', 'WHEEL BALANCE', 'ALIGNMENT', 'BRAKE INSPECTION', 'COOLING SYSTEM'], months: 48 },
                85000: { services: ['CHANGE OIL', 'ENGINE TUNE UP'], months: 51 },
                90000: { services: ['CHANGE OIL', 'TIRE ROTATION'], months: 54 },
                95000: { services: ['CHANGE OIL'], months: 57 },
                100000: { services: ['CHANGE OIL', 'TIRE ROTATION', 'WHEEL BALANCE', 'ALIGNMENT', 'BRAKE INSPECTION'], months: 60 }
            };
            
            predictions.forEach(prediction => {
                // Get driver info for this vehicle
                const driverInfo = getDriverForVehicle(prediction.vehicle_id);
                
                // PREDICT the NEXT upcoming maintenance interval
                // Use correct mileage from database instead of Python ML server
            const totalMileage = (prediction.total_km_traveled && Number(prediction.total_km_traveled)) ||
                                   (prediction.factors && prediction.factors.avg_daily_usage_km ? Math.round(Number(prediction.factors.avg_daily_usage_km) * 30) : 0);
                
                // Use the days from the server response (schedule-based for SYN-* vehicles, ML for others)
                let daysRemaining = prediction.days_until_maintenance || 180;
                let monthsRemaining = Math.round(daysRemaining / 30); // Convert days to months
                
                // Calculate priority based on days remaining (using your maintenance schedule)
                let priority = 'low';
                if (daysRemaining <= 30) priority = 'critical';  // 1 month or less
                else if (daysRemaining <= 60) priority = 'high';   // 2 months or less  
                else if (daysRemaining <= 90) priority = 'medium'; // 3 months or less
                else priority = 'low'; // More than 3 months
                let estimatedInterval = 5000;
                let services = ['CHANGE OIL'];
                
                // Find the NEXT upcoming maintenance interval (PREDICTIVE)
                const intervals = Object.keys(maintenanceSchedule).map(Number).sort((a, b) => a - b);
                
                for (const interval of intervals) {
                    if (totalMileage < interval) {
                        // This is the NEXT maintenance interval we're predicting
                        estimatedInterval = interval;
                        services = maintenanceSchedule[interval].services;
                        break;
                    }
                }
                
                // If we've passed all intervals, predict the next cycle
                if (totalMileage >= intervals[intervals.length - 1]) {
                    // Find the next interval in the cycle (e.g., if at 100k, next is 105k)
                    const lastInterval = intervals[intervals.length - 1];
                    const cycleLength = intervals[1] - intervals[0]; // Usually 5000km
                    estimatedInterval = lastInterval + cycleLength;
                    services = maintenanceSchedule[lastInterval].services; // Same services as last interval
                }
                
                const servicesText = services.join(', ');
                console.log(`Vehicle ${prediction.vehicle_id} (${prediction.plate_number}): ${totalMileage} km -> Services: ${servicesText}`);
                
                // Create alert based on your maintenance schedule
                const alert = {
                    type: 'maintenance_prediction',
                    priority: priority,
                    title: servicesText,
                    message: `Vehicle ${prediction.plate_number} needs ${servicesText} in ${Math.round(prediction.days_until_maintenance / 30)} months`,
                    vehicle_id: prediction.vehicle_id,
                    vehicle_name: prediction.vehicle_name,
                    plate_number: prediction.plate_number,
                    driver_id: driverInfo ? driverInfo.driver_id : null,
                    driver_name: driverInfo ? driverInfo.driver_name : 'Not Assigned',
                    phone_number: driverInfo ? driverInfo.phone_number : null,
                    km_remaining: daysRemaining * 50, // Rough estimate: 50km per day
                    days_remaining: daysRemaining,
                    total_mileage: prediction.total_km_traveled || (prediction.factors && prediction.factors.avg_daily_usage_km ? Math.round(prediction.factors.avg_daily_usage_km * 30) : 0),
                    next_maintenance_date: prediction.next_maintenance_date,
                    urgency_level: prediction.urgency_level,
                    services_text: servicesText,
                    estimated_interval: estimatedInterval,
                    recommended_action: `Schedule ${servicesText} service`,
                    created_at: new Date().toISOString()
                };
                
                alerts.push(alert);
            });
            
            return alerts;
        }

        function getDriverForVehicle(vehicleId) {
            // This would ideally fetch from the database, but for now return null
            // In a real implementation, you'd fetch from vehicle_assignments table
            return null;
        }

        function loadDriverAlerts(driverId) {
            return fetch(`../api/maintenance_alerts.php?action=get_driver_alerts&driver_id=${driverId}`)
                .then(response => response.json())
                .then(data => {
                    if (data.success && data.data.alerts) {
                        return data.data.alerts.map(alert => ({
                            ...alert,
                            driver_id: driverId,
                            driver_name: data.data.vehicle.driver_name || 'Unknown Driver'
                        }));
                    }
                    return [];
                })
                .catch(error => {
                    console.error(`Error loading alerts for driver ${driverId}:`, error);
                    return [];
                });
        }

        function displayAllAlerts(allAlerts) {
            const tbody = document.getElementById('alertsTableBody');
            
            if (!allAlerts || allAlerts.length === 0) {
                tbody.innerHTML = `
                    <tr>
                        <td colspan="6" class="text-center text-muted">
                            <i class="fas fa-check-circle text-success me-2"></i>
                            No maintenance alerts at this time
                        </td>
                    </tr>
                `;
                return;
            }

            let html = '';
            allAlerts.forEach(alert => {
                const priorityClass = getPriorityClass(alert.priority);
                const typeIcon = getTypeIcon(alert.type);
                
                // Determine display for KM/Months column
                let kmMonthsDisplay = '';
                if (alert.type === 'maintenance_prediction') {
                    // For ML predictions, show months remaining
                    const daysRemaining = parseFloat(alert.days_remaining || 0);
                    const monthsRemaining = Math.round(daysRemaining / 30);
                    kmMonthsDisplay = `
                        <span class="fw-bold ${daysRemaining <= 7 ? 'text-danger' : 
                                               daysRemaining <= 30 ? 'text-warning' : 'text-success'}">
                            ${monthsRemaining} months
                        </span>
                        <br><small class="text-muted">(ML Prediction)</small>
                    `;
                } else if (alert.trigger_type === 'mileage') {
                    const kmRemaining = parseFloat(alert.km_remaining || 0);
                    kmMonthsDisplay = `
                        <span class="fw-bold ${kmRemaining <= 100 ? 'text-danger' : 
                                               kmRemaining <= 500 ? 'text-warning' : 'text-success'}">
                            ${kmRemaining} km
                        </span>
                        <br><small class="text-muted">(${alert.interval_km} km interval)</small>
                    `;
                } else {
                    const monthsRemaining = parseFloat(alert.months_remaining || 0);
                    kmMonthsDisplay = `
                        <span class="fw-bold ${monthsRemaining <= 0 ? 'text-danger' : 
                                               monthsRemaining <= 1 ? 'text-warning' : 'text-success'}">
                            ${monthsRemaining} months
                        </span>
                        <br><small class="text-muted">(${alert.interval_months} month interval)</small>
                    `;
                }
                
                html += `
                    <tr>
                        <td>
                            <strong>${alert.vehicle_name}</strong><br>
                            <small class="text-muted">${alert.plate_number}</small>
                        </td>
                        <td>
                            <i class="${typeIcon} me-1"></i>
                            <strong>${alert.services_text}</strong>
                        </td>
                        <td>
                            <span class="badge ${priorityClass}">${alert.priority.toUpperCase()}</span>
                        </td>
                        <td>
                            ${kmMonthsDisplay}
                        </td>
                        <td>${formatMileage(alert.total_mileage)}</td>
                        <td>
                            <button type="button" class="btn btn-sm btn-outline-info" onclick="showSingleNotificationModal(${alert.vehicle_id}, '${alert.message}', '${alert.services_text}')">
                                <i class="fas fa-bell"></i>
                            </button>
                        </td>
                    </tr>
                `;
            });

            tbody.innerHTML = html;
        }

        function getPriorityClass(priority) {
            switch (priority) {
                case 'critical': return 'bg-danger';
                case 'high': return 'bg-warning';
                case 'medium': return 'bg-info';
                case 'low': return 'bg-success';
                default: return 'bg-secondary';
            }
        }

        function getTypeIcon(type) {
            switch (type) {
                case 'change_oil': return 'fas fa-oil-can';
                case 'tire_rotation': return 'fas fa-sync-alt';
                case 'wheel_balance': return 'fas fa-balance-scale';
                case 'alignment': return 'fas fa-arrows-alt';
                case 'brake_inspection': return 'fas fa-search';
                case 'cooling_system': return 'fas fa-thermometer-half';
                case 'engine_tune_up': return 'fas fa-cog';
                case 'general_maintenance': return 'fas fa-tools';
                case 'major_service': return 'fas fa-wrench';
                case 'inspection': return 'fas fa-search';
                case 'repair': return 'fas fa-hammer';
                default: return 'fas fa-exclamation-triangle';
            }
        }

        function viewAlertDetails(vehicleId, driverName) {
            // Use the same data source as the table (predict_all) to ensure consistency
            fetch(`${PYTHON_SERVER_URL}/predict_all`)
            .then(response => response.json())
            .then(data => {
                if (data.success && data.data) {
                        // Find the specific vehicle from the all vehicles data
                        const pred = data.data.find(vehicle => vehicle.vehicle_id == vehicleId);
                        if (!pred) {
                            showAlert('error', 'Vehicle not found');
                            return;
                        }
                        
                        showResults(`Vehicle ${pred.plate_number} Details`, `
                            <div class="alert alert-info">
                                <i class="fas fa-info-circle me-2"></i>
                                <strong>Vehicle Information</strong>
                            </div>
                            <div class="row">
                                <div class="col-md-6">
                                    <h6>Vehicle Details</h6>
                                    <ul class="list-unstyled">
                                        <li><strong>ID:</strong> ${pred.vehicle_id}</li>
                                        <li><strong>Name:</strong> ${pred.vehicle_name}</li>
                                        <li><strong>Plate:</strong> ${pred.plate_number}</li>
                                        <li><strong>Total KM:</strong> ${formatMileage(pred.total_km_traveled || (pred.factors && pred.factors.avg_daily_usage_km ? Math.round(pred.factors.avg_daily_usage_km * 30) : 0))}</li>
                                    </ul>
                                </div>
                                <div class="col-md-6">
                                    <h6>Maintenance Prediction</h6>
                                    <ul class="list-unstyled">
                                        <li><strong>Status:</strong> <span class="badge bg-${getUrgencyColorByDays(pred.days_until_maintenance || 180)}">${getUrgencyText(pred.urgency_level, pred.days_until_maintenance || 180)}</span></li>
                                        <li><strong>Months Until:</strong> ${Math.round(pred.days_until_maintenance / 30)}</li>
                                        <li><strong>Next Date:</strong> ${pred.next_maintenance_date}</li>
                                    </ul>
                                </div>
                            </div>
                            <div class="alert alert-warning">
                                <i class="fas fa-exclamation-triangle me-2"></i>
                                <strong>ML Prediction:</strong> This maintenance alert is based on machine learning predictions from your Python ML server.
                            </div>
                            <div class="alert alert-light">
                                <h6><i class="fas fa-calendar-alt me-2"></i>Your Maintenance Schedule:</h6>
                                <div class="row">
                                    <div class="col-md-6">
                                        <small>
                                            <strong>5,000 KM or 3 Months:</strong> Change Oil<br>
                                            <strong>10,000 KM or 6 Months:</strong> Change Oil, Tire Rotation<br>
                                            <strong>15,000 KM or 9 Months:</strong> Change Oil<br>
                                            <strong>20,000 KM or 12 Months:</strong> Change Oil, Tire Rotation, Wheel Balance, Alignment, Brake Inspection<br>
                                            <strong>25,000 KM or 15 Months:</strong> Change Oil<br>
                                            <strong>30,000 KM or 18 Months:</strong> Change Oil, Tire Rotation<br>
                                            <strong>35,000 KM or 21 Months:</strong> Change Oil<br>
                                            <strong>40,000 KM or 24 Months:</strong> Change Oil, Tire Rotation, Wheel Balance, Alignment, Brake Inspection, Cooling System<br>
                                            <strong>45,000 KM or 27 Months:</strong> Change Oil, Engine Tune Up<br>
                                            <strong>50,000 KM or 30 Months:</strong> Change Oil, Tire Rotation
                                        </small>
                                    </div>
                                    <div class="col-md-6">
                                        <small>
                                            <strong>55,000 KM or 33 Months:</strong> Change Oil<br>
                                            <strong>60,000 KM or 36 Months:</strong> Change Oil, Tire Rotation, Wheel Balance, Alignment, Brake Inspection<br>
                                            <strong>65,000 KM or 39 Months:</strong> Change Oil<br>
                                            <strong>70,000 KM or 42 Months:</strong> Change Oil, Tire Rotation<br>
                                            <strong>75,000 KM or 45 Months:</strong> Change Oil<br>
                                            <strong>80,000 KM or 48 Months:</strong> Change Oil, Tire Rotation, Wheel Balance, Alignment, Brake Inspection, Cooling System<br>
                                            <strong>85,000 KM or 51 Months:</strong> Change Oil, Engine Tune Up<br>
                                            <strong>90,000 KM or 54 Months:</strong> Change Oil, Tire Rotation<br>
                                            <strong>95,000 KM or 57 Months:</strong> Change Oil<br>
                                            <strong>100,000 KM or 60 Months:</strong> Change Oil, Tire Rotation, Wheel Balance, Alignment, Brake Inspection
                                        </small>
                                    </div>
                                </div>
                            </div>
                        `);
                } else {
                        showAlert('error', 'Failed to load vehicle details from ML server');
                }
            })
            .catch(error => {
                    console.error('Error loading vehicle details:', error);
                    showAlert('error', 'Error loading vehicle details: ' + error.message);
                });
        }

        function scheduleMaintenance(vehicleId, type) {
            const maintenanceTypes = {
                'oil_change': 'Oil Change',
                'general_maintenance': 'General Maintenance',
                'major_service': 'Major Service',
                'inspection': 'Vehicle Inspection',
                'repair': 'Repair Service'
            };
            
            const typeName = maintenanceTypes[type] || 'Maintenance';
            
            if (confirm(`Schedule ${typeName} for this vehicle?`)) {
                // Redirect to maintenance scheduling page
                window.location.href = `maintenance.php?vehicle_id=${vehicleId}&type=${type}`;
            }
        }

        function sendDriverNotification(vehicleId, message, servicesText) {
            if (confirm('Send maintenance notification to assigned driver?')) {
                console.log('Sending notification for vehicle:', vehicleId);
                console.log('Message:', message);
                console.log('Services:', servicesText);
                
                fetch('../api/send_driver_notification.php', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                    },
                    body: JSON.stringify({
                        vehicle_id: vehicleId,
                        message: message,
                        services_required: servicesText,
                        notification_type: 'maintenance_alert',
                        priority: 'high'
                    })
                })
                .then(response => {
                    console.log('Response status:', response.status);
                    return response.json();
                })
                .then(data => {
                    console.log('API Response:', data);
                if (data.success) {
                        showAlert('success', 'Maintenance notification sent to driver successfully');
                } else {
                        showAlert('error', 'Failed to send notification: ' + (data.message || 'Unknown error'));
                }
            })
            .catch(error => {
                    console.error('Error sending notification:', error);
                    showAlert('error', 'Error sending notification: ' + error.message);
                });
            }
        }

        function testMaintenanceAPI() {
            console.log('Testing maintenance API...');
            
            // Test the debug API first
            fetch('../debug_maintenance_api.php')
                .then(response => response.json())
                .then(data => {
                    console.log('Debug API Response:', data);
                    showResults('API Debug Test', `
                        <div class="alert alert-info">
                            <h6>Database Connection Test:</h6>
                            <ul>
                                <li><strong>Connection:</strong> ${data.debug.connection}</li>
                                <li><strong>Active Assignments:</strong> ${data.debug.assignments_count}</li>
                                <li><strong>Active Vehicles:</strong> ${data.debug.vehicles_count}</li>
                                <li><strong>Trip Logs:</strong> ${data.debug.trip_logs_count}</li>
                            </ul>
                        </div>
                    `);
                })
                .catch(error => {
                    console.error('Debug API Error:', error);
                    showAlert('error', 'Debug API failed: ' + error.message);
                });
        }

        // Removed notification functions - notifications are sent automatically by the ML system

        function showAlert(type, message) {
            const alertDiv = document.createElement('div');
            alertDiv.className = `alert alert-${type === 'error' ? 'danger' : type} alert-dismissible fade show position-fixed`;
            alertDiv.style.cssText = 'top: 20px; right: 20px; z-index: 9999; min-width: 300px;';
            alertDiv.innerHTML = `
                <i class="fas fa-${type === 'success' ? 'check-circle' : type === 'error' ? 'exclamation-triangle' : 'info-circle'} me-2"></i>
                ${message}
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            `;
            
            document.body.appendChild(alertDiv);
            
            // Auto-remove after 5 seconds
            setTimeout(() => {
                if (alertDiv.parentNode) {
                    alertDiv.remove();
                }
            }, 5000);
        }
    </script>

</body>
</html>
</html>