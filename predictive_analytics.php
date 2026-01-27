<?php
session_start();
require_once 'db_connection.php';

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit();
}

// Get all vehicles with ESP32 devices
$vehicles_query = "
    SELECT fv.id, fv.article, fv.plate_number, fv.status, fv.created_at,
           gd.device_id, gd.lat, gd.lng, gd.updated_at
    FROM fleet_vehicles fv 
    INNER JOIN gps_devices gd ON fv.id = gd.vehicle_id 
    WHERE gd.device_id LIKE 'ESP32%'
    ORDER BY fv.article
";
// Use prepared statement for consistency (static query but best practice)
$vehicles_stmt = $conn->prepare($vehicles_query);
$vehicles_stmt->execute();
$vehicles_result = $vehicles_stmt->get_result();
$vehicles = [];
while ($row = $vehicles_result->fetch_assoc()) {
    $vehicles[] = $row;
}
$vehicles_stmt->close();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Predictive Analytics - Vehicle Maintenance System</title>
    
    <!-- Bootstrap CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <!-- Google Fonts -->
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&family=Poppins:wght@400;500;600;700&display=swap" rel="stylesheet">
    <!-- Bootstrap Icons CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons/font/bootstrap-icons.css" rel="stylesheet">
    <!-- Font Awesome -->
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css" rel="stylesheet">
    <!-- SweetAlert2 -->
    <link href="https://cdn.jsdelivr.net/npm/sweetalert2@11/dist/sweetalert2.min.css" rel="stylesheet">
    
    <style>
        :root {
            --primary-blue: #003566;
            --accent-blue: #00b4d8;
            --light-blue: #0096c7;
            --dark-blue: #001d3d;
            --text-dark: #2d3748;
            --text-light: #718096;
            --bg-light: #f7fafc;
            --white: #ffffff;
            --success: #48bb78;
            --warning: #ed8936;
            --danger: #f56565;
            --border-light: #e2e8f0;
            --shadow-sm: 0 1px 3px 0 rgba(0, 0, 0, 0.1), 0 1px 2px 0 rgba(0, 0, 0, 0.06);
            --shadow-md: 0 4px 6px -1px rgba(0, 0, 0, 0.1), 0 2px 4px -1px rgba(0, 0, 0, 0.06);
            --shadow-lg: 0 10px 15px -3px rgba(0, 0, 0, 0.1), 0 4px 6px -2px rgba(0, 0, 0, 0.05);
            --shadow-xl: 0 20px 25px -5px rgba(0, 0, 0, 0.1), 0 10px 10px -5px rgba(0, 0, 0, 0.04);
        }

        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: 'Inter', sans-serif;
            background: linear-gradient(135deg, var(--primary-blue) 0%, var(--dark-blue) 100%);
            color: var(--text-dark);
            line-height: 1.6;
            min-height: 100vh;
        }

        .dashboard-container {
            background: rgba(255, 255, 255, 0.95);
            backdrop-filter: blur(20px);
            border-radius: 20px;
            box-shadow: var(--shadow-xl);
            margin: 20px;
            padding: 30px;
            min-height: calc(100vh - 40px);
        }

        .page-header {
            background: linear-gradient(135deg, var(--primary-blue), var(--dark-blue));
            color: white;
            border-radius: 15px;
            padding: 30px;
            margin-bottom: 30px;
            text-align: center;
            box-shadow: var(--shadow-lg);
        }

        .page-header h1 {
            font-family: 'Poppins', sans-serif;
            font-weight: 700;
            font-size: 2.5rem;
            margin-bottom: 10px;
        }

        .page-header p {
            font-size: 1.1rem;
            opacity: 0.9;
        }

        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
            gap: 20px;
            margin-bottom: 30px;
        }

        .stat-card {
            background: white;
            border-radius: 15px;
            padding: 25px;
            box-shadow: var(--shadow-md);
            text-align: center;
            transition: all 0.3s ease;
            border-left: 5px solid var(--accent-blue);
        }

        .stat-card:hover {
            transform: translateY(-5px);
            box-shadow: var(--shadow-lg);
        }

        .stat-card.urgent {
            border-left-color: var(--danger);
            background: linear-gradient(135deg, #fef2f2, #fee2e2);
        }

        .stat-card.warning {
            border-left-color: var(--warning);
            background: linear-gradient(135deg, #fffbeb, #fef3c7);
        }

        .stat-card.success {
            border-left-color: var(--success);
            background: linear-gradient(135deg, #f0fdf4, #dcfce7);
        }

        .stat-icon {
            font-size: 2.5rem;
            margin-bottom: 15px;
        }

        .stat-card.urgent .stat-icon {
            color: var(--danger);
        }

        .stat-card.warning .stat-icon {
            color: var(--warning);
        }

        .stat-card.success .stat-icon {
            color: var(--success);
        }

        .stat-number {
            font-size: 2rem;
            font-weight: 700;
            margin-bottom: 5px;
            font-family: 'Poppins', sans-serif;
        }

        .stat-label {
            color: var(--text-light);
            font-weight: 500;
        }

        .vehicles-section {
            background: white;
            border-radius: 15px;
            padding: 30px;
            box-shadow: var(--shadow-md);
            margin-bottom: 30px;
        }

        .section-header {
            display: flex;
            justify-content: between;
            align-items: center;
            margin-bottom: 25px;
            padding-bottom: 15px;
            border-bottom: 2px solid var(--border-light);
        }

        .section-title {
            font-family: 'Poppins', sans-serif;
            font-weight: 600;
            font-size: 1.5rem;
            color: var(--primary-blue);
        }

        .vehicle-card {
            background: white;
            border-radius: 12px;
            padding: 20px;
            margin-bottom: 20px;
            box-shadow: var(--shadow-sm);
            border: 1px solid var(--border-light);
            transition: all 0.3s ease;
        }

        .vehicle-card:hover {
            transform: translateY(-2px);
            box-shadow: var(--shadow-md);
        }

        .vehicle-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 15px;
        }

        .vehicle-info h5 {
            font-weight: 600;
            color: var(--primary-blue);
            margin-bottom: 5px;
        }

        .vehicle-info p {
            color: var(--text-light);
            margin: 0;
        }

        .status-badge {
            padding: 8px 16px;
            border-radius: 20px;
            font-weight: 600;
            font-size: 0.85rem;
        }

        .status-urgent {
            background: linear-gradient(135deg, var(--danger), #e53e3e);
            color: white;
        }

        .status-warning {
            background: linear-gradient(135deg, var(--warning), #dd6b20);
            color: white;
        }

        .status-notice {
            background: linear-gradient(135deg, var(--accent-blue), var(--light-blue));
            color: white;
        }

        .status-ok {
            background: linear-gradient(135deg, var(--success), #38a169);
            color: white;
        }

        .prediction-details {
            background: var(--bg-light);
            border-radius: 8px;
            padding: 15px;
            margin-top: 15px;
        }

        .detail-row {
            display: flex;
            justify-content: space-between;
            margin-bottom: 8px;
        }

        .detail-label {
            font-weight: 500;
            color: var(--text-dark);
        }

        .detail-value {
            font-weight: 600;
            color: var(--primary-blue);
        }

        .btn-primary {
            background: linear-gradient(135deg, var(--primary-blue), var(--dark-blue));
            border: none;
            padding: 12px 24px;
            border-radius: 10px;
            font-weight: 600;
            transition: all 0.3s ease;
        }

        .btn-primary:hover {
            background: linear-gradient(135deg, var(--dark-blue), var(--primary-blue));
            transform: translateY(-2px);
            box-shadow: var(--shadow-md);
        }

        .loading-spinner {
            text-align: center;
            padding: 40px;
        }

        .loading-spinner i {
            font-size: 2rem;
            color: var(--accent-blue);
        }

        .no-vehicles {
            text-align: center;
            padding: 40px;
            color: var(--text-light);
        }

        .no-vehicles i {
            font-size: 3rem;
            margin-bottom: 15px;
            color: var(--border-light);
        }
    </style>
</head>
<body>
    <div class="dashboard-container">
        <!-- Page Header -->
        <div class="page-header">
            <h1><i class="fas fa-chart-line me-3"></i>Predictive Analytics</h1>
            <p>AI-Powered Vehicle Maintenance Forecasting System</p>
        </div>

        <!-- Statistics Grid -->
        <div class="stats-grid">
            <div class="stat-card" id="totalVehicles">
                <i class="fas fa-car stat-icon"></i>
                <div class="stat-number">0</div>
                <div class="stat-label">Total Vehicles</div>
            </div>
            <div class="stat-card urgent" id="urgentVehicles">
                <i class="fas fa-exclamation-triangle stat-icon"></i>
                <div class="stat-number">0</div>
                <div class="stat-label">Urgent Maintenance</div>
            </div>
            <div class="stat-card warning" id="warningVehicles">
                <i class="fas fa-exclamation-circle stat-icon"></i>
                <div class="stat-number">0</div>
                <div class="stat-label">Warning</div>
            </div>
            <div class="stat-card success" id="okVehicles">
                <i class="fas fa-check-circle stat-icon"></i>
                <div class="stat-number">0</div>
                <div class="stat-label">All Good</div>
            </div>
        </div>

        <!-- Vehicles Section -->
        <div class="vehicles-section">
            <div class="section-header">
                <h3 class="section-title">
                    <i class="fas fa-satellite me-2"></i>ESP32-Enabled Vehicles
                </h3>
                <button class="btn btn-primary" onclick="refreshAllPredictions()">
                    <i class="fas fa-sync-alt me-2"></i>Refresh All
                </button>
            </div>

            <div id="vehiclesContainer">
                <div class="loading-spinner">
                    <i class="fas fa-spinner fa-spin"></i>
                    <p>Loading vehicle predictions...</p>
                </div>
            </div>
        </div>
    </div>

    <!-- Scripts -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    
    <script>
        // Vehicle data from PHP
        const vehicles = <?= json_encode($vehicles) ?>;
        
        // Load predictions on page load
        document.addEventListener('DOMContentLoaded', function() {
            loadAllPredictions();
        });

        // Load predictions for all vehicles
        function loadAllPredictions() {
            if (vehicles.length === 0) {
                document.getElementById('vehiclesContainer').innerHTML = `
                    <div class="no-vehicles">
                        <i class="fas fa-car"></i>
                        <h4>No ESP32 Vehicles Found</h4>
                        <p>No vehicles with ESP32 devices are currently registered.</p>
                    </div>
                `;
                return;
            }

            let html = '';
            vehicles.forEach(vehicle => {
                html += `
                    <div class="vehicle-card" id="vehicle-${vehicle.id}">
                        <div class="vehicle-header">
                            <div class="vehicle-info">
                                <h5>${vehicle.article}</h5>
                                <p>Plate: ${vehicle.plate_number} | ESP32: ${vehicle.device_id}</p>
                            </div>
                            <div class="loading-spinner">
                                <i class="fas fa-spinner fa-spin"></i>
                            </div>
                        </div>
                    </div>
                `;
            });
            
            document.getElementById('vehiclesContainer').innerHTML = html;

            // Load predictions for each vehicle
            vehicles.forEach(vehicle => {
                loadVehiclePrediction(vehicle);
            });
        }

        // Load prediction for a specific vehicle
        function loadVehiclePrediction(vehicle) {
            fetch(`api/maintenance_prediction.php?action=predict&vehicle_id=${vehicle.id}`)
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        displayVehiclePrediction(vehicle, data.data);
                    } else {
                        displayVehicleError(vehicle, 'Failed to get prediction');
                    }
                })
                .catch(error => {
                    console.error('Error predicting maintenance:', error);
                    displayVehicleError(vehicle, 'Error getting prediction');
                });
        }

        // Display vehicle prediction
        function displayVehiclePrediction(vehicle, prediction) {
            const statusClass = prediction.status === 'URGENT' ? 'urgent' : 
                               prediction.status === 'WARNING' ? 'warning' : 
                               prediction.status === 'NOTICE' ? 'notice' : 'ok';
            
            const statusText = prediction.status;
            const action = prediction.action;

            const vehicleCard = document.getElementById(`vehicle-${vehicle.id}`);
            vehicleCard.innerHTML = `
                <div class="vehicle-header">
                    <div class="vehicle-info">
                        <h5>${vehicle.article}</h5>
                        <p>Plate: ${vehicle.plate_number} | ESP32: ${vehicle.device_id}</p>
                    </div>
                    <span class="status-badge status-${statusClass.toLowerCase()}">${statusText}</span>
                </div>
                <div class="prediction-details">
                    <div class="detail-row">
                        <span class="detail-label">Action Required:</span>
                        <span class="detail-value">${action}</span>
                    </div>
                    <div class="detail-row">
                        <span class="detail-label">Total KM Traveled:</span>
                        <span class="detail-value">${prediction.total_km_traveled} km</span>
                    </div>
                    <div class="detail-row">
                        <span class="detail-label">Next Milestone:</span>
                        <span class="detail-value">${prediction.next_milestone_km} km</span>
                    </div>
                    <div class="detail-row">
                        <span class="detail-label">KM Until Maintenance:</span>
                        <span class="detail-value">${prediction.km_until_maintenance} km</span>
                    </div>
                    <div class="detail-row">
                        <span class="detail-label">Next Tasks:</span>
                        <span class="detail-value">${prediction.next_maintenance_tasks.join(', ')}</span>
                    </div>
                    <div class="detail-row">
                        <span class="detail-label">Confidence:</span>
                        <span class="detail-value">${Math.round(prediction.confidence * 100)}%</span>
                    </div>
                </div>
            `;
        }

        // Display vehicle error
        function displayVehicleError(vehicle, error) {
            const vehicleCard = document.getElementById(`vehicle-${vehicle.id}`);
            vehicleCard.innerHTML = `
                <div class="vehicle-header">
                    <div class="vehicle-info">
                        <h5>${vehicle.article}</h5>
                        <p>Plate: ${vehicle.plate_number} | ESP32: ${vehicle.device_id}</p>
                    </div>
                    <span class="status-badge status-urgent">ERROR</span>
                </div>
                <div class="prediction-details">
                    <div class="detail-row">
                        <span class="detail-label">Error:</span>
                        <span class="detail-value">${error}</span>
                    </div>
                </div>
            `;
        }

        // Refresh all predictions
        function refreshAllPredictions() {
            Swal.fire({
                title: 'Refreshing Predictions',
                text: 'Updating all vehicle maintenance predictions...',
                icon: 'info',
                timer: 2000,
                showConfirmButton: false
            });

            // Reload all predictions
            vehicles.forEach(vehicle => {
                loadVehiclePrediction(vehicle);
            });

            // Update statistics after a delay
            setTimeout(updateStatistics, 3000);
        }

        // Update statistics
        function updateStatistics() {
            let urgent = 0, warning = 0, ok = 0;

            vehicles.forEach(vehicle => {
                const vehicleCard = document.getElementById(`vehicle-${vehicle.id}`);
                if (vehicleCard) {
                    const statusBadge = vehicleCard.querySelector('.status-badge');
                    if (statusBadge) {
                        if (statusBadge.classList.contains('status-urgent')) urgent++;
                        else if (statusBadge.classList.contains('status-warning')) warning++;
                        else if (statusBadge.classList.contains('status-ok')) ok++;
                    }
                }
            });

            document.getElementById('totalVehicles').querySelector('.stat-number').textContent = vehicles.length;
            document.getElementById('urgentVehicles').querySelector('.stat-number').textContent = urgent;
            document.getElementById('warningVehicles').querySelector('.stat-number').textContent = warning;
            document.getElementById('okVehicles').querySelector('.stat-number').textContent = ok;
        }

        // Auto-refresh every 5 minutes
        setInterval(() => {
            refreshAllPredictions();
        }, 300000);
    </script>
</body>
</html>
