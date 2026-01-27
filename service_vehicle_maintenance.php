<?php
session_start();
require_once 'db_connection.php';

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit();
}

// Get Service Vehicle details - use prepared statement for consistency
$plate_number = 'M-2752';
$vehicle_stmt = $conn->prepare("
    SELECT fv.id, fv.article, fv.plate_number, fv.status, fv.created_at,
           gd.device_id, gd.lat, gd.lng, gd.updated_at
    FROM fleet_vehicles fv 
    LEFT JOIN gps_devices gd ON fv.id = gd.vehicle_id 
    WHERE fv.plate_number = ?
");
$vehicle_stmt->bind_param("s", $plate_number);
$vehicle_stmt->execute();
$vehicle_result = $vehicle_stmt->get_result();
$vehicle = $vehicle_result->fetch_assoc();
$vehicle_stmt->close();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Service Vehicle (Audi Q7) - AI Maintenance Dashboard</title>
    
    <!-- Bootstrap CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <!-- Font Awesome -->
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
    <!-- Leaflet CSS -->
    <link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css" />
    <!-- SweetAlert2 -->
    <link href="https://cdn.jsdelivr.net/npm/sweetalert2@11/dist/sweetalert2.min.css" rel="stylesheet">
    
    <style>
        :root {
            --primary-color: #2563eb;
            --secondary-color: #64748b;
            --success-color: #059669;
            --warning-color: #d97706;
            --danger-color: #dc2626;
            --info-color: #0891b2;
            --audi-color: #000000;
        }
        
        body {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            min-height: 100vh;
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
        }
        
        .dashboard-container {
            background: rgba(255, 255, 255, 0.95);
            backdrop-filter: blur(10px);
            border-radius: 20px;
            box-shadow: 0 20px 40px rgba(0, 0, 0, 0.1);
            margin: 20px;
            padding: 30px;
        }
        
        .vehicle-header {
            background: linear-gradient(135deg, var(--audi-color), #333);
            color: white;
            border-radius: 15px;
            padding: 30px;
            margin-bottom: 30px;
            text-align: center;
        }
        
        .vehicle-info {
            background: white;
            border-radius: 15px;
            padding: 25px;
            box-shadow: 0 10px 30px rgba(0, 0, 0, 0.1);
            margin-bottom: 30px;
        }
        
        .status-card {
            background: white;
            border-radius: 15px;
            padding: 25px;
            box-shadow: 0 10px 30px rgba(0, 0, 0, 0.1);
            transition: transform 0.3s ease;
            border-left: 5px solid var(--primary-color);
        }
        
        .status-card:hover {
            transform: translateY(-5px);
        }
        
        .status-card.urgent {
            border-left-color: var(--danger-color);
            background: linear-gradient(135deg, #fef2f2, #fee2e2);
        }
        
        .status-card.warning {
            border-left-color: var(--warning-color);
            background: linear-gradient(135deg, #fffbeb, #fef3c7);
        }
        
        .status-card.ok {
            border-left-color: var(--success-color);
            background: linear-gradient(135deg, #f0fdf4, #dcfce7);
        }
        
        #map {
            height: 400px;
            border-radius: 15px;
            box-shadow: 0 10px 30px rgba(0, 0, 0, 0.1);
        }
        
        .esp32-status {
            background: #1a1a1a;
            color: #00ff00;
            border-radius: 10px;
            padding: 15px;
            font-family: 'Courier New', monospace;
            margin: 10px 0;
        }
        
        .maintenance-timeline {
            background: white;
            border-radius: 15px;
            padding: 25px;
            box-shadow: 0 10px 30px rgba(0, 0, 0, 0.1);
        }
        
        .timeline-item {
            border-left: 3px solid var(--primary-color);
            padding-left: 20px;
            margin-bottom: 20px;
            position: relative;
        }
        
        .timeline-item::before {
            content: '';
            position: absolute;
            left: -8px;
            top: 0;
            width: 13px;
            height: 13px;
            border-radius: 50%;
            background: var(--primary-color);
        }
        
        .timeline-item.completed::before {
            background: var(--success-color);
        }
        
        .timeline-item.pending::before {
            background: var(--warning-color);
        }
        
        .prediction-details {
            background: white;
            border-radius: 15px;
            padding: 25px;
            box-shadow: 0 10px 30px rgba(0, 0, 0, 0.1);
        }
        
        .feature-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 15px;
            margin-top: 20px;
        }
        
        .feature-card {
            background: #f8f9fa;
            border-radius: 10px;
            padding: 15px;
            text-align: center;
        }
        
        .feature-value {
            font-size: 1.5rem;
            font-weight: bold;
            color: var(--primary-color);
        }
        
        .btn-audi {
            background: linear-gradient(135deg, var(--audi-color), #333);
            border: none;
            color: white;
            padding: 12px 24px;
            border-radius: 10px;
            font-weight: 600;
            transition: all 0.3s ease;
        }
        
        .btn-audi:hover {
            background: linear-gradient(135deg, #333, var(--audi-color));
            color: white;
            transform: translateY(-2px);
        }
    </style>
</head>
<body>
    <div class="dashboard-container">
        <!-- Vehicle Header -->
        <div class="vehicle-header">
            <div class="row align-items-center">
                <div class="col-md-8">
                    <h1 class="mb-2">
                        <i class="fas fa-car text-warning me-3"></i>
                        Service Vehicle - Audi Q7
                    </h1>
                    <p class="mb-0 opacity-75">
                        ESP32-Powered Predictive Maintenance Dashboard
                    </p>
                </div>
                <div class="col-md-4 text-end">
                    <button class="btn btn-audi me-2" onclick="predictMaintenance()">
                        <i class="fas fa-brain me-2"></i>Predict Maintenance
                    </button>
                    <button class="btn btn-outline-light" onclick="refreshData()">
                        <i class="fas fa-sync-alt me-2"></i>Refresh
                    </button>
                </div>
            </div>
        </div>

        <!-- Vehicle Information -->
        <div class="vehicle-info">
            <div class="row">
                <div class="col-md-6">
                    <h4><i class="fas fa-info-circle text-primary me-2"></i>Vehicle Details</h4>
                    <table class="table table-borderless">
                        <tr>
                            <td><strong>Vehicle ID:</strong></td>
                            <td><?= $vehicle['id'] ?></td>
                        </tr>
                        <tr>
                            <td><strong>Name:</strong></td>
                            <td><?= $vehicle['article'] ?></td>
                        </tr>
                        <tr>
                            <td><strong>Plate Number:</strong></td>
                            <td><span class="badge bg-secondary"><?= $vehicle['plate_number'] ?></span></td>
                        </tr>
                        <tr>
                            <td><strong>Status:</strong></td>
                            <td><span class="badge bg-success"><?= $vehicle['status'] ?></span></td>
                        </tr>
                        <tr>
                            <td><strong>Created:</strong></td>
                            <td><?= date('M d, Y', strtotime($vehicle['created_at'])) ?></td>
                        </tr>
                    </table>
                </div>
                <div class="col-md-6">
                    <h4><i class="fas fa-satellite text-info me-2"></i>ESP32 Device</h4>
                    <div class="esp32-status">
                        <div><strong>Device ID:</strong> <?= $vehicle['device_id'] ?></div>
                        <div><strong>Current Lat:</strong> <?= $vehicle['lat'] ?></div>
                        <div><strong>Current Lng:</strong> <?= $vehicle['lng'] ?></div>
                        <div><strong>Last Updated:</strong> <?= $vehicle['updated_at'] ?></div>
                        <div><strong>Status:</strong> <span style="color: #00ff00;">● ONLINE</span></div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Main Content Row -->
        <div class="row">
            <!-- Left Column -->
            <div class="col-md-8">
                <!-- Map -->
                <div class="card mb-4">
                    <div class="card-header">
                        <h5><i class="fas fa-map-marker-alt text-primary me-2"></i>Live Location</h5>
                    </div>
                    <div class="card-body p-0">
                        <div id="map"></div>
                    </div>
                </div>

                <!-- Maintenance Timeline -->
                <div class="maintenance-timeline">
                    <h4><i class="fas fa-history text-primary me-2"></i>Maintenance History</h4>
                    <div id="maintenanceTimeline">
                        <div class="text-center">
                            <i class="fas fa-spinner fa-spin fa-2x text-primary mb-2"></i>
                            <p>Loading maintenance history...</p>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Right Column -->
            <div class="col-md-4">
                <!-- AI Prediction Status -->
                <div class="status-card" id="predictionCard">
                    <div class="text-center">
                        <i class="fas fa-spinner fa-spin fa-2x text-primary mb-2"></i>
                        <p>Loading AI prediction...</p>
                    </div>
                </div>

                <!-- Prediction Details -->
                <div class="prediction-details">
                    <h5><i class="fas fa-chart-line text-primary me-2"></i>AI Analysis</h5>
                    <div id="predictionDetails">
                        <div class="text-center">
                            <i class="fas fa-spinner fa-spin fa-2x text-primary mb-2"></i>
                            <p>Analyzing vehicle data...</p>
                        </div>
                    </div>
                </div>

                <!-- Quick Actions -->
                <div class="card mt-4">
                    <div class="card-header">
                        <h5><i class="fas fa-tools text-primary me-2"></i>Quick Actions</h5>
                    </div>
                    <div class="card-body">
                        <button class="btn btn-audi w-100 mb-2" onclick="scheduleMaintenance()">
                            <i class="fas fa-calendar-plus me-2"></i>Schedule Maintenance
                        </button>
                        <button class="btn btn-outline-primary w-100 mb-2" onclick="viewMaintenanceHistory()">
                            <i class="fas fa-clipboard-list me-2"></i>View Full History
                        </button>
                        <button class="btn btn-outline-info w-100" onclick="exportReport()">
                            <i class="fas fa-download me-2"></i>Export Report
                        </button>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Scripts -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    
    <script>
        // Vehicle data
        const vehicleData = {
            id: <?= $vehicle['id'] ?>,
            name: '<?= $vehicle['article'] ?>',
            plate: '<?= $vehicle['plate_number'] ?>',
            deviceId: '<?= $vehicle['device_id'] ?>',
            lat: <?= $vehicle['lat'] ?>,
            lng: <?= $vehicle['lng'] ?>
        };

        // Initialize map
        const map = L.map('map').setView([vehicleData.lat, vehicleData.lng], 15);
        L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
            maxZoom: 19
        }).addTo(map);

        // Add vehicle marker
        const vehicleMarker = L.marker([vehicleData.lat, vehicleData.lng]).addTo(map);
        vehicleMarker.bindPopup(`
            <strong>${vehicleData.name}</strong><br>
            Plate: ${vehicleData.plate}<br>
            ESP32: ${vehicleData.deviceId}
        `).openPopup();

        // Load data on page load
        document.addEventListener('DOMContentLoaded', function() {
            loadMaintenanceHistory();
            predictMaintenance();
        });

        // Load maintenance history
        function loadMaintenanceHistory() {
            fetch(`api/maintenance_prediction.php?action=vehicle_history&vehicle_id=${vehicleData.id}`)
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        displayMaintenanceHistory(data.data);
                    } else {
                        document.getElementById('maintenanceTimeline').innerHTML = 
                            '<div class="alert alert-warning">No maintenance history available</div>';
                    }
                })
                .catch(error => {
                    console.error('Error loading maintenance history:', error);
                    document.getElementById('maintenanceTimeline').innerHTML = 
                        '<div class="alert alert-danger">Failed to load maintenance history</div>';
                });
        }

        // Display maintenance history
        function displayMaintenanceHistory(history) {
            let html = '';
            history.forEach(item => {
                const statusClass = item.status === 'completed' ? 'completed' : 'pending';
                const statusColor = item.status === 'completed' ? 'success' : 'warning';
                
                html += `
                    <div class="timeline-item ${statusClass}">
                        <div class="d-flex justify-content-between align-items-start">
                            <div>
                                <h6 class="mb-1">${item.maintenance_type}</h6>
                                <p class="mb-1 text-muted">${item.notes || 'No notes'}</p>
                                <small class="text-muted">Created: ${new Date(item.created_at).toLocaleDateString()}</small>
                            </div>
                            <span class="badge bg-${statusColor}">${item.status}</span>
                        </div>
                    </div>
                `;
            });
            
            document.getElementById('maintenanceTimeline').innerHTML = html;
        }

        // Predict maintenance
        function predictMaintenance() {
            fetch(`api/maintenance_prediction.php?action=predict&vehicle_id=${vehicleData.id}`)
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        displayPrediction(data.data);
                    } else {
                        document.getElementById('predictionCard').innerHTML = 
                            '<div class="alert alert-danger">Failed to get prediction</div>';
                    }
                })
                .catch(error => {
                    console.error('Error predicting maintenance:', error);
                    document.getElementById('predictionCard').innerHTML = 
                        '<div class="alert alert-danger">Error getting prediction</div>';
                });
        }

        // Display prediction
        function displayPrediction(prediction) {
            const statusClass = prediction.status === 'URGENT' ? 'urgent' : 
                               prediction.status === 'WARNING' ? 'warning' : 'ok';
            
            const statusIcon = prediction.status === 'URGENT' ? 'exclamation-triangle' :
                              prediction.status === 'WARNING' ? 'exclamation-circle' : 'check-circle';
            
            const statusColor = prediction.status === 'URGENT' ? 'danger' :
                               prediction.status === 'WARNING' ? 'warning' : 'success';

            document.getElementById('predictionCard').innerHTML = `
                <div class="text-center">
                    <i class="fas fa-${statusIcon} fa-3x text-${statusColor} mb-3"></i>
                    <h4 class="mb-2">${prediction.status}</h4>
                    <p class="mb-2">${prediction.action}</p>
                    <div class="alert alert-${statusColor}">
                        <strong>${prediction.days_until_maintenance} days</strong> until maintenance
                    </div>
                    <small class="text-muted">Confidence: ${Math.round(prediction.confidence * 100)}%</small>
                </div>
            `;

            // Display prediction details
            const features = prediction.features_used;
            document.getElementById('predictionDetails').innerHTML = `
                <div class="feature-grid">
                    <div class="feature-card">
                        <div class="feature-value">${features.vehicle_age_days}</div>
                        <small>Vehicle Age (days)</small>
                    </div>
                    <div class="feature-card">
                        <div class="feature-value">${features.days_since_maintenance}</div>
                        <small>Days Since Maintenance</small>
                    </div>
                    <div class="feature-card">
                        <div class="feature-value">${features.maintenance_interval}</div>
                        <small>Maintenance Interval</small>
                    </div>
                    <div class="feature-card">
                        <div class="feature-value">${features.avg_daily_usage}</div>
                        <small>Avg Daily Usage</small>
                    </div>
                    <div class="feature-card">
                        <div class="feature-value">${features.gps_points_last_week}</div>
                        <small>GPS Points (Week)</small>
                    </div>
                </div>
            `;
        }

        // Refresh data
        function refreshData() {
            loadMaintenanceHistory();
            predictMaintenance();
            
            Swal.fire({
                title: 'Refreshed!',
                text: 'Data has been updated',
                icon: 'success',
                timer: 1500,
                showConfirmButton: false
            });
        }

        // Schedule maintenance
        function scheduleMaintenance() {
            Swal.fire({
                title: 'Schedule Maintenance',
                html: `
                    <div class="text-start">
                        <label class="form-label">Maintenance Type:</label>
                        <select id="maintenanceType" class="form-select mb-3">
                            <option value="oil_change">Oil Change</option>
                            <option value="tire_rotation">Tire Rotation</option>
                            <option value="brake_service">Brake Service</option>
                            <option value="ac_maintenance">AC Maintenance</option>
                            <option value="general_inspection">General Inspection</option>
                        </select>
                        <label class="form-label">Scheduled Date:</label>
                        <input type="datetime-local" id="scheduledDate" class="form-control mb-3">
                        <label class="form-label">Notes:</label>
                        <textarea id="notes" class="form-control" rows="3"></textarea>
                    </div>
                `,
                showCancelButton: true,
                confirmButtonText: 'Schedule',
                cancelButtonText: 'Cancel',
                preConfirm: () => {
                    return {
                        type: document.getElementById('maintenanceType').value,
                        date: document.getElementById('scheduledDate').value,
                        notes: document.getElementById('notes').value
                    };
                }
            }).then((result) => {
                if (result.isConfirmed) {
                    // Here you would typically send the data to your backend
                    Swal.fire('Scheduled!', 'Maintenance has been scheduled successfully.', 'success');
                }
            });
        }

        // View maintenance history
        function viewMaintenanceHistory() {
            Swal.fire({
                title: 'Maintenance History',
                text: 'Redirecting to full maintenance history...',
                icon: 'info',
                timer: 2000,
                showConfirmButton: false
            });
        }

        // Export report
        function exportReport() {
            Swal.fire({
                title: 'Export Report',
                text: 'Generating maintenance report...',
                icon: 'info',
                timer: 2000,
                showConfirmButton: false
            });
        }

        // Auto-refresh every 30 seconds
        setInterval(() => {
            predictMaintenance();
        }, 30000);
    </script>
</body>
</html>
