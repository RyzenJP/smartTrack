<?php
session_start();
if (!isset($_SESSION['user_id'])) {
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
            --success: #28a745;
            --warning: #ffc107;
            --danger: #dc3545;
            --info: #17a2b8;
        }

        body {
            font-family: 'Segoe UI', sans-serif;
            background-color: #f8f9fa;
        }

        .main-content {
            margin-left: 250px;
            margin-top: 60px;
            padding: 20px;
        }

        .card {
            border: none;
            border-radius: 15px;
            box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1);
            transition: transform 0.2s;
        }

        .card:hover {
            transform: translateY(-2px);
        }

        .status-badge {
            padding: 0.5rem 1rem;
            border-radius: 25px;
            font-weight: 600;
            font-size: 0.875rem;
        }

        .status-urgent {
            background-color: var(--danger);
            color: white;
        }

        .status-warning {
            background-color: var(--warning);
            color: #212529;
        }

        .status-notice {
            background-color: var(--info);
            color: white;
        }

        .status-ok {
            background-color: var(--success);
            color: white;
        }

        .prediction-card {
            border-left: 4px solid var(--accent);
        }

        .loading-spinner {
            display: inline-block;
            width: 20px;
            height: 20px;
            border: 3px solid #f3f3f3;
            border-top: 3px solid var(--accent);
            border-radius: 50%;
            animation: spin 1s linear infinite;
        }

        @keyframes spin {
            0% { transform: rotate(0deg); }
            100% { transform: rotate(360deg); }
        }

        .alert-card {
            border-left: 4px solid var(--warning);
            background: linear-gradient(135deg, #fff3cd 0%, #ffeaa7 100%);
        }

        .stats-card {
            background: linear-gradient(135deg, var(--primary) 0%, var(--accent) 100%);
            color: white;
        }

        .btn-train {
            background: linear-gradient(135deg, var(--accent) 0%, var(--primary) 100%);
            border: none;
            color: white;
            padding: 12px 24px;
            border-radius: 25px;
            font-weight: 600;
            transition: all 0.3s ease;
        }

        .btn-train:hover {
            transform: translateY(-2px);
            box-shadow: 0 8px 25px rgba(0, 180, 216, 0.3);
            color: white;
        }

        .prediction-table {
            background: white;
            border-radius: 15px;
            overflow: hidden;
        }

        .prediction-table th {
            background: var(--primary);
            color: white;
            border: none;
            font-weight: 600;
        }

        .prediction-table td {
            vertical-align: middle;
            border-color: #e9ecef;
        }
    </style>
</head>
<body>
    <!-- Include your existing navbar/sidebar here -->
    <?php include 'navbar.php'; ?>
    <?php include 'sidebar.php'; ?>

    <div class="main-content">
        <div class="container-fluid">
            <!-- Header -->
            <div class="row mb-4">
                <div class="col-12">
                    <div class="d-flex justify-content-between align-items-center">
                        <div>
                            <h2 class="mb-1">
                                <i class="fas fa-brain text-primary me-2"></i>
                                Predictive Maintenance
                            </h2>
                            <p class="text-muted mb-0">AI-powered maintenance predictions using Python ML</p>
                        </div>
                        <div>
                            <button class="btn btn-train me-2" onclick="trainModel()">
                                <i class="fas fa-cog me-2"></i>
                                Train Model
                            </button>
                            <button class="btn btn-outline-primary" onclick="refreshPredictions()">
                                <i class="fas fa-sync-alt me-2"></i>
                                Refresh
                            </button>
                        </div>
                    </div>
                </div>
            </div>


            <!-- Statistics Cards -->
            <div class="row mb-4">
                <div class="col-md-3">
                    <div class="card stats-card">
                        <div class="card-body text-center">
                            <i class="fas fa-car fa-2x mb-2"></i>
                            <h4 id="totalVehicles">-</h4>
                            <p class="mb-0">Total Vehicles</p>
                        </div>
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="card stats-card">
                        <div class="card-body text-center">
                            <i class="fas fa-exclamation-triangle fa-2x mb-2"></i>
                            <h4 id="urgentMaintenance">-</h4>
                            <p class="mb-0">Urgent Maintenance</p>
                        </div>
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="card stats-card">
                        <div class="card-body text-center">
                            <i class="fas fa-tools fa-2x mb-2"></i>
                            <h4 id="inMaintenance">-</h4>
                            <p class="mb-0">In Maintenance</p>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Predictions Table -->
            <div class="row">
                <div class="col-12">
                    <div class="card prediction-card">
                        <div class="card-header bg-white">
                            <h5 class="card-title mb-0">
                                <i class="fas fa-chart-bar me-2"></i>
                                Maintenance Predictions
                            </h5>
                        </div>
                        <div class="card-body p-0">
                            <div class="table-responsive">
                                <table class="table table-hover prediction-table mb-0">
                                    <thead>
                                        <tr>
                                            <th>Vehicle</th>
                                            <th>Plate Number</th>
                                            <th>Days Until Maintenance</th>
                                            <th>Status</th>
                                            <th>Recommended Action</th>
                                            <th>Confidence</th>
                                            <th>Actions</th>
                                        </tr>
                                    </thead>
                                    <tbody id="predictionsTable">
                                        <tr>
                                            <td colspan="7" class="text-center py-4">
                                                <div class="loading-spinner"></div>
                                                <p class="mt-2 mb-0">Loading predictions...</p>
                                            </td>
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

    <!-- Modal for Model Training -->
    <div class="modal fade" id="trainingModal" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">
                        <i class="fas fa-cog me-2"></i>
                        Training ML Model
                    </h5>
                </div>
                <div class="modal-body">
                    <div id="trainingProgress">
                        <div class="text-center">
                            <div class="loading-spinner mb-3"></div>
                            <p>Training Python ML model with your vehicle data...</p>
                            <small class="text-muted">This may take a few minutes</small>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        // Global variables
        let predictions = [];

        // Initialize page
        document.addEventListener('DOMContentLoaded', function() {
            checkServerStatus();
            loadStatistics();
            loadPredictions();
        });

        // Check Python ML server status
        function checkServerStatus() {
            fetch('../api/python_ml_bridge.php?action=status')
                .then(response => response.json())
                .then(data => {
                    const alert = document.getElementById('serverStatusAlert');
                    const message = document.getElementById('serverStatusMessage');
                    
                    if (data.success && data.server_running) {
                        alert.className = 'alert alert-success';
                        message.innerHTML = '<i class="fas fa-check-circle me-2"></i>Python ML server is running and ready';
                    } else {
                        alert.className = 'alert alert-warning';
                        message.innerHTML = '<i class="fas fa-exclamation-triangle me-2"></i>Python ML server is not running. Please start it using: <code>python python_ml_server.py 8080</code>';
                    }
                    alert.classList.remove('d-none');
                })
                .catch(error => {
                    const alert = document.getElementById('serverStatusAlert');
                    const message = document.getElementById('serverStatusMessage');
                    alert.className = 'alert alert-danger';
                    message.innerHTML = '<i class="fas fa-times-circle me-2"></i>Cannot connect to Python ML server';
                    alert.classList.remove('d-none');
                });
        }

        // Load maintenance statistics
        function loadStatistics() {
            // Get basic vehicle count from database
            fetch('../api/fleet_api.php?action=get_vehicle_count')
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        document.getElementById('totalVehicles').textContent = data.count || 0;
                    }
                })
                .catch(error => {
                    console.error('Error loading statistics:', error);
                });
        }

        // Load maintenance predictions
        function loadPredictions() {
            fetch('../api/python_ml_bridge.php?action=predict_all')
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        predictions = data.data;
                        displayPredictions();
                        updateUrgentCount();
                    } else {
                        document.getElementById('predictionsTable').innerHTML = `
                            <tr>
                                <td colspan="7" class="text-center py-4">
                                    <i class="fas fa-exclamation-triangle text-warning me-2"></i>
                                    ${data.message || 'Failed to load predictions'}
                                </td>
                            </tr>
                        `;
                    }
                })
                .catch(error => {
                    console.error('Error loading predictions:', error);
                    document.getElementById('predictionsTable').innerHTML = `
                        <tr>
                            <td colspan="7" class="text-center py-4">
                                <i class="fas fa-exclamation-triangle text-danger me-2"></i>
                                Error loading predictions. Make sure Python ML server is running.
                            </td>
                        </tr>
                    `;
                });
        }

        // Display predictions in table
        function displayPredictions() {
            const tbody = document.getElementById('predictionsTable');
            
            if (predictions.length === 0) {
                tbody.innerHTML = `
                    <tr>
                        <td colspan="7" class="text-center py-4">
                            <i class="fas fa-info-circle text-info me-2"></i>
                            No predictions available
                        </td>
                    </tr>
                `;
                return;
            }

            let html = '';
            predictions.forEach(prediction => {
                const statusClass = getStatusClass(prediction.urgency_level);
                const confidenceColor = prediction.confidence > 80 ? 'text-success' : 
                                      prediction.confidence > 60 ? 'text-warning' : 'text-danger';
                
                html += `
                    <tr>
                        <td>
                            <strong>${prediction.vehicle_name}</strong>
                        </td>
                        <td>${prediction.plate_number}</td>
                        <td>
                            <span class="fw-bold ${prediction.days_until_maintenance <= 5 ? 'text-danger' : 
                                                   prediction.days_until_maintenance <= 15 ? 'text-warning' : 'text-success'}">
                                ${prediction.days_until_maintenance} days
                            </span>
                        </td>
                        <td>
                            <span class="status-badge ${statusClass}">
                                ${prediction.urgency_level}
                            </span>
                        </td>
                        <td>${prediction.action || prediction.recommended_maintenance}</td>
                        <td>
                            <span class="${confidenceColor}">
                                ${prediction.confidence}%
                            </span>
                        </td>
                        <td>
                            <button class="btn btn-sm btn-outline-primary" onclick="viewDetails(${prediction.vehicle_id})">
                                <i class="fas fa-eye"></i>
                            </button>
                            <button class="btn btn-sm btn-outline-success" onclick="scheduleMaintenance(${prediction.vehicle_id})">
                                <i class="fas fa-calendar-plus"></i>
                            </button>
                        </td>
                    </tr>
                `;
            });

            tbody.innerHTML = html;
        }

        // Get status CSS class
        function getStatusClass(status) {
            switch (status) {
                case 'CRITICAL':
                case 'URGENT': return 'status-urgent';
                case 'HIGH':
                case 'WARNING': return 'status-warning';
                case 'MEDIUM':
                case 'NOTICE': return 'status-notice';
                case 'LOW':
                case 'OK': return 'status-ok';
                default: return 'status-notice';
            }
        }

        // Update urgent maintenance count
        function updateUrgentCount() {
            const urgentCount = predictions.filter(p => 
                p.urgency_level === 'URGENT' || p.urgency_level === 'CRITICAL'
            ).length;
            document.getElementById('urgentMaintenance').textContent = urgentCount;
        }

        // Train ML model
        function trainModel() {
            const modal = new bootstrap.Modal(document.getElementById('trainingModal'));
            modal.show();

            fetch('../api/python_ml_bridge.php?action=train', {
                method: 'POST'
            })
            .then(response => response.json())
            .then(data => {
                modal.hide();
                
                if (data.success) {
                    showAlert('success', 'Model trained successfully using Python ML!');
                    loadPredictions(); // Refresh predictions
                } else {
                    showAlert('error', 'Model training failed: ' + data.message);
                }
            })
            .catch(error => {
                modal.hide();
                showAlert('error', 'Error training model: ' + error.message);
            });
        }

        // Refresh all data
        function refreshPredictions() {
            checkServerStatus();
            loadStatistics();
            loadPredictions();
            showAlert('info', 'Data refreshed successfully!');
        }

        // View vehicle details
        function viewDetails(vehicleId) {
            // Redirect to vehicle details page
            window.location.href = `fleet.php?id=${vehicleId}`;
        }

        // Schedule maintenance
        function scheduleMaintenance(vehicleId) {
            // Redirect to maintenance scheduling page
            window.location.href = `maintenance.php?vehicle_id=${vehicleId}`;
        }

        // Show alert
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

        // Auto-refresh every 5 minutes
        setInterval(() => {
            loadStatistics();
            loadPredictions();
        }, 300000); // 5 minutes
    </script>
</body>
</html>
