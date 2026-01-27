<?php
session_start();
require_once '../config/database.php';

// Debug: Check session variables
if (!isset($_SESSION['user_id']) || !isset($_SESSION['role'])) {
    header('Location: ../login.php');
    exit();
}

// Check if user is logged in and is super admin
if (strtolower($_SESSION['role']) !== 'super admin') {
    header('Location: ../login.php');
    exit();
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Generate Synthetic Data - Smart Track</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <style>
        .card {
            border: none;
            border-radius: 15px;
            box-shadow: 0 0 20px rgba(0,0,0,0.1);
        }
        .btn-primary {
            background: linear-gradient(45deg, #007bff, #0056b3);
            border: none;
            border-radius: 10px;
            padding: 12px 30px;
        }
        .btn-success {
            background: linear-gradient(45deg, #28a745, #1e7e34);
            border: none;
            border-radius: 10px;
            padding: 12px 30px;
        }
        .progress {
            height: 25px;
            border-radius: 15px;
        }
        .status-card {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
        }
    </style>
</head>
<body class="bg-light">
    <div class="container-fluid">
        <div class="row">
            <!-- Sidebar -->
            <div class="col-md-3 col-lg-2 d-md-block bg-dark sidebar collapse">
                <?php include '../pages/admin_sidebar.php'; ?>
            </div>

            <!-- Main content -->
            <main class="col-md-9 ms-sm-auto col-lg-10 px-md-4">
                <div class="d-flex justify-content-between flex-wrap flex-md-nowrap align-items-center pt-3 pb-2 mb-3 border-bottom">
                    <h1 class="h2">
                        <i class="fas fa-database text-primary"></i>
                        Generate Synthetic Data
                    </h1>
                </div>

                <!-- Status Cards -->
                <div class="row mb-4">
                    <div class="col-md-4">
                        <div class="card status-card">
                            <div class="card-body text-center">
                                <i class="fas fa-car fa-2x mb-2"></i>
                                <h5 class="card-title">Total Vehicles</h5>
                                <h3 id="totalVehicles">-</h3>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-4">
                        <div class="card status-card">
                            <div class="card-body text-center">
                                <i class="fas fa-tools fa-2x mb-2"></i>
                                <h5 class="card-title">Maintenance Records</h5>
                                <h3 id="totalMaintenance">-</h3>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-4">
                        <div class="card status-card">
                            <div class="card-body text-center">
                                <i class="fas fa-map-marker-alt fa-2x mb-2"></i>
                                <h5 class="card-title">GPS Records</h5>
                                <h3 id="totalGps">-</h3>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Generation Form -->
                <div class="row">
                    <div class="col-md-8">
                        <div class="card">
                            <div class="card-header bg-primary text-white">
                                <h5 class="mb-0">
                                    <i class="fas fa-magic"></i>
                                    Synthetic Data Generator
                                </h5>
                            </div>
                            <div class="card-body">
                                <form id="syntheticDataForm">
                                    <div class="row">
                                        <div class="col-md-6">
                                            <div class="mb-3">
                                                <label for="numVehicles" class="form-label">
                                                    <i class="fas fa-car"></i> Number of Vehicles
                                                </label>
                                                <input type="number" class="form-control" id="numVehicles" 
                                                       name="numVehicles" min="1" max="20" value="10" required>
                                                <div class="form-text">How many vehicles to generate data for (1-20)</div>
                                            </div>
                                        </div>
                                        <div class="col-md-6">
                                            <div class="mb-3">
                                                <label for="maxKm" class="form-label">
                                                    <i class="fas fa-road"></i> Maximum Kilometers
                                                </label>
                                                <input type="number" class="form-control" id="maxKm" 
                                                       name="maxKm" min="5000" max="100000" value="100000" required>
                                                <div class="form-text">Maximum km to simulate per vehicle</div>
                                            </div>
                                        </div>
                                    </div>

                                    <div class="alert alert-info">
                                        <i class="fas fa-info-circle"></i>
                                        <strong>What this will generate:</strong>
                                        <ul class="mb-0 mt-2">
                                            <li>Realistic maintenance history based on your schedule</li>
                                            <li>GPS tracking data with realistic coordinates</li>
                                            <li>Usage patterns that match real-world scenarios</li>
                                            <li>Data that will improve your ML model accuracy</li>
                                        </ul>
                                    </div>

                                    <div class="d-grid gap-2">
                                        <button type="submit" class="btn btn-primary btn-lg" id="generateBtn">
                                            <i class="fas fa-play"></i>
                                            Generate Synthetic Data
                                        </button>
                                    </div>
                                </form>
                            </div>
                        </div>
                    </div>

                    <div class="col-md-4">
                        <div class="card">
                            <div class="card-header bg-success text-white">
                                <h5 class="mb-0">
                                    <i class="fas fa-brain"></i>
                                    ML Model Training
                                </h5>
                            </div>
                            <div class="card-body">
                                <p class="text-muted">
                                    After generating synthetic data, you can train your ML model for better predictions.
                                </p>
                                
                                <div class="d-grid gap-2">
                                    <button class="btn btn-success" id="trainModelBtn">
                                        <i class="fas fa-cogs"></i>
                                        Train ML Model
                                    </button>
                                    
                                    <a href="predictive_maintenance.php" class="btn btn-outline-primary">
                                        <i class="fas fa-chart-line"></i>
                                        View Predictions
                                    </a>
                                </div>

                                <hr>
                                
                                <div class="alert alert-warning">
                                    <i class="fas fa-exclamation-triangle"></i>
                                    <strong>Note:</strong> This will create realistic fake data for training purposes only.
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Progress Section -->
                <div class="row mt-4" id="progressSection" style="display: none;">
                    <div class="col-12">
                        <div class="card">
                            <div class="card-header bg-info text-white">
                                <h5 class="mb-0">
                                    <i class="fas fa-spinner fa-spin"></i>
                                    Generating Data...
                                </h5>
                            </div>
                            <div class="card-body">
                                <div class="progress mb-3">
                                    <div class="progress-bar progress-bar-striped progress-bar-animated" 
                                         role="progressbar" style="width: 0%" id="progressBar">0%</div>
                                </div>
                                <div id="progressText" class="text-center">Initializing...</div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Results Section -->
                <div class="row mt-4" id="resultsSection" style="display: none;">
                    <div class="col-12">
                        <div class="card">
                            <div class="card-header bg-success text-white">
                                <h5 class="mb-0">
                                    <i class="fas fa-check-circle"></i>
                                    Generation Complete!
                                </h5>
                            </div>
                            <div class="card-body">
                                <div id="resultsContent"></div>
                            </div>
                        </div>
                    </div>
                </div>
            </main>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    
    <script>
        $(document).ready(function() {
            // Load initial status
            loadStatus();
            
            // Handle form submission
            $('#syntheticDataForm').on('submit', function(e) {
                e.preventDefault();
                generateSyntheticData();
            });
            
            // Handle ML model training
            $('#trainModelBtn').on('click', function() {
                trainMLModel();
            });
        });
        
        function loadStatus() {
            $.get('../api/generate_synthetic_data.php?action=status', function(response) {
                if (response.success) {
                    $('#totalVehicles').text(response.data.total_vehicles || 0);
                    $('#totalMaintenance').text(response.data.total_maintenance_records || 0);
                    $('#totalGps').text(response.data.total_gps_records || 0);
                }
            });
        }
        
        function generateSyntheticData() {
            const numVehicles = $('#numVehicles').val();
            const maxKm = $('#maxKm').val();
            
            // Show progress section
            $('#progressSection').show();
            $('#resultsSection').hide();
            $('#generateBtn').prop('disabled', true);
            
            // Simulate progress
            let progress = 0;
            const progressInterval = setInterval(function() {
                progress += Math.random() * 15;
                if (progress > 90) progress = 90;
                
                $('#progressBar').css('width', progress + '%').text(Math.round(progress) + '%');
                
                if (progress < 30) {
                    $('#progressText').text('Fetching vehicle data...');
                } else if (progress < 60) {
                    $('#progressText').text('Generating maintenance records...');
                } else if (progress < 90) {
                    $('#progressText').text('Generating GPS data...');
                }
            }, 500);
            
            // Make API call
            $.post('../api/generate_synthetic_data.php?action=generate', {
                num_vehicles: numVehicles,
                max_km: maxKm
            }, function(response) {
                clearInterval(progressInterval);
                $('#progressBar').css('width', '100%').text('100%');
                $('#progressText').text('Complete!');
                
                setTimeout(function() {
                    $('#progressSection').hide();
                    $('#generateBtn').prop('disabled', false);
                    
                    if (response.success) {
                        showResults(response);
                        loadStatus(); // Refresh status
                    } else {
                        showError(response.message);
                    }
                }, 1000);
            }).fail(function() {
                clearInterval(progressInterval);
                $('#progressSection').hide();
                $('#generateBtn').prop('disabled', false);
                showError('Failed to generate synthetic data. Please try again.');
            });
        }
        
        function showResults(response) {
            const data = response.data;
            const html = `
                <div class="row text-center">
                    <div class="col-md-4">
                        <div class="border rounded p-3">
                            <i class="fas fa-car fa-2x text-primary mb-2"></i>
                            <h4>${data.vehicles_processed}</h4>
                            <p class="text-muted">Vehicles Processed</p>
                        </div>
                    </div>
                    <div class="col-md-4">
                        <div class="border rounded p-3">
                            <i class="fas fa-tools fa-2x text-success mb-2"></i>
                            <h4>${data.total_maintenance_records}</h4>
                            <p class="text-muted">Maintenance Records</p>
                        </div>
                    </div>
                    <div class="col-md-4">
                        <div class="border rounded p-3">
                            <i class="fas fa-map-marker-alt fa-2x text-info mb-2"></i>
                            <h4>${data.total_gps_records}</h4>
                            <p class="text-muted">GPS Records</p>
                        </div>
                    </div>
                </div>
                <div class="alert alert-success mt-3">
                    <i class="fas fa-check-circle"></i>
                    <strong>Success!</strong> Synthetic data has been generated successfully. 
                    You can now train your ML model for better predictions.
                </div>
            `;
            
            $('#resultsContent').html(html);
            $('#resultsSection').show();
        }
        
        function showError(message) {
            const html = `
                <div class="alert alert-danger">
                    <i class="fas fa-exclamation-triangle"></i>
                    <strong>Error:</strong> ${message}
                </div>
            `;
            
            $('#resultsContent').html(html);
            $('#resultsSection').show();
        }
        
        function trainMLModel() {
            $('#trainModelBtn').prop('disabled', true).html('<i class="fas fa-spinner fa-spin"></i> Training...');
            
            $.post('../api/hybrid_maintenance_prediction.php?action=train', function(response) {
                $('#trainModelBtn').prop('disabled', false).html('<i class="fas fa-cogs"></i> Train ML Model');
                
                if (response.success) {
                    let method = response.method === 'python' ? '🐍 Python XGBoost' : '🔧 PHP Rule Engine';
                    alert('ML Model trained successfully!\n\nMethod: ' + method + '\nAlgorithm: ' + response.training_stats.algorithm + '\nAccuracy: ' + response.training_stats.accuracy + '%\nTraining Time: ' + response.training_stats.training_time);
                } else {
                    alert('Failed to train ML Model: ' + response.message);
                }
            }).fail(function() {
                $('#trainModelBtn').prop('disabled', false).html('<i class="fas fa-cogs"></i> Train ML Model');
                alert('Failed to train ML Model. Please try again.');
            });
        }
    </script>
</body>
</html>
