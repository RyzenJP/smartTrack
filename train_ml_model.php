<?php
/**
 * ML Model Training Interface
 * Simple PHP page to train the ML model via Heroku server
 */

session_start();
require 'db_connection.php';

// Check if user is logged in (optional - remove if you want public access)
if (!isset($_SESSION['username'])) {
    // Uncomment the line below if you want to require login
    // header("Location: login.php");
    // exit;
}

// Load the Python ML Bridge
require 'api/python_ml_bridge.php';

$trainingResult = null;
$statusResult = null;

// Handle training request
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['train'])) {
    $trainingResult = trainModel();
}

// Get server status
$statusResult = getServerStatus();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Train ML Model - Smart Track</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <style>
        .status-card {
            border-left: 4px solid #0d6efd;
        }
        .success-card {
            border-left: 4px solid #198754;
        }
        .error-card {
            border-left: 4px solid #dc3545;
        }
        .training-stats {
            background: #f8f9fa;
            border-radius: 8px;
            padding: 20px;
            margin-top: 20px;
        }
        .stat-item {
            display: flex;
            justify-content: space-between;
            padding: 10px 0;
            border-bottom: 1px solid #dee2e6;
        }
        .stat-item:last-child {
            border-bottom: none;
        }
        .stat-label {
            font-weight: 600;
            color: #495057;
        }
        .stat-value {
            color: #0d6efd;
            font-weight: 700;
        }
    </style>
</head>
<body class="bg-light">
    <div class="container mt-5">
        <div class="row justify-content-center">
            <div class="col-md-10">
                <div class="card shadow">
                    <div class="card-header bg-primary text-white">
                        <h3 class="mb-0">
                            <i class="fas fa-brain me-2"></i>
                            ML Model Training Interface
                        </h3>
                    </div>
                    <div class="card-body">
                        
                        <!-- Server Status -->
                        <div class="card status-card mb-4">
                            <div class="card-body">
                                <h5 class="card-title">
                                    <i class="fas fa-server me-2"></i>Server Status
                                </h5>
                                <?php if ($statusResult['success']): ?>
                                    <div class="alert alert-success mb-0">
                                        <i class="fas fa-check-circle me-2"></i>
                                        <strong>Server is Running</strong>
                                        <br>
                                        <small>
                                            Algorithm: <?php echo htmlspecialchars($statusResult['data']['algorithm'] ?? 'N/A'); ?> | 
                                            Port: <?php echo htmlspecialchars($statusResult['data']['port'] ?? 'N/A'); ?>
                                        </small>
                                    </div>
                                <?php else: ?>
                                    <div class="alert alert-danger mb-0">
                                        <i class="fas fa-times-circle me-2"></i>
                                        <strong>Server is Not Available</strong>
                                        <br>
                                        <small><?php echo htmlspecialchars($statusResult['message'] ?? 'Unknown error'); ?></small>
                                    </div>
                                <?php endif; ?>
                            </div>
                        </div>

                        <!-- Training Form -->
                        <div class="card mb-4">
                            <div class="card-body">
                                <h5 class="card-title">
                                    <i class="fas fa-cogs me-2"></i>Train Model
                                </h5>
                                <p class="text-muted">
                                    Click the button below to train the ML model. This will use all available training data from your database.
                                </p>
                                
                                <form method="POST" action="">
                                    <button type="submit" name="train" class="btn btn-primary btn-lg" 
                                            <?php echo (!$statusResult['success']) ? 'disabled' : ''; ?>>
                                        <i class="fas fa-play me-2"></i>
                                        Train ML Model
                                    </button>
                                </form>
                            </div>
                        </div>

                        <!-- Training Results -->
                        <?php if ($trainingResult !== null): ?>
                            <div class="card <?php echo $trainingResult['success'] ? 'success-card' : 'error-card'; ?> mb-4">
                                <div class="card-body">
                                    <h5 class="card-title">
                                        <i class="fas fa-<?php echo $trainingResult['success'] ? 'check-circle' : 'exclamation-triangle'; ?> me-2"></i>
                                        Training Result
                                    </h5>
                                    
                                    <?php if ($trainingResult['success']): ?>
                                        <div class="alert alert-success">
                                            <i class="fas fa-check-circle me-2"></i>
                                            <strong><?php echo htmlspecialchars($trainingResult['message'] ?? 'Training completed successfully!'); ?></strong>
                                        </div>
                                        
                                        <?php if (isset($trainingResult['training_stats'])): ?>
                                            <div class="training-stats">
                                                <h6 class="mb-3"><i class="fas fa-chart-line me-2"></i>Training Statistics</h6>
                                                <div class="stat-item">
                                                    <span class="stat-label">Algorithm:</span>
                                                    <span class="stat-value"><?php echo htmlspecialchars($trainingResult['training_stats']['algorithm'] ?? 'N/A'); ?></span>
                                                </div>
                                                <div class="stat-item">
                                                    <span class="stat-label">Accuracy:</span>
                                                    <span class="stat-value"><?php echo htmlspecialchars($trainingResult['training_stats']['accuracy'] ?? 'N/A'); ?>%</span>
                                                </div>
                                                <?php if (isset($trainingResult['training_stats']['r2_score'])): ?>
                                                <div class="stat-item">
                                                    <span class="stat-label">R² Score:</span>
                                                    <span class="stat-value"><?php echo htmlspecialchars($trainingResult['training_stats']['r2_score']); ?></span>
                                                </div>
                                                <?php endif; ?>
                                                <?php if (isset($trainingResult['training_stats']['rmse'])): ?>
                                                <div class="stat-item">
                                                    <span class="stat-label">RMSE:</span>
                                                    <span class="stat-value"><?php echo htmlspecialchars($trainingResult['training_stats']['rmse']); ?></span>
                                                </div>
                                                <?php endif; ?>
                                                <?php if (isset($trainingResult['training_stats']['samples_used'])): ?>
                                                <div class="stat-item">
                                                    <span class="stat-label">Samples Used:</span>
                                                    <span class="stat-value"><?php echo htmlspecialchars($trainingResult['training_stats']['samples_used']); ?></span>
                                                </div>
                                                <?php endif; ?>
                                                <?php if (isset($trainingResult['training_stats']['training_time'])): ?>
                                                <div class="stat-item">
                                                    <span class="stat-label">Training Time:</span>
                                                    <span class="stat-value"><?php echo htmlspecialchars($trainingResult['training_stats']['training_time']); ?>s</span>
                                                </div>
                                                <?php endif; ?>
                                                <?php if (isset($trainingResult['training_stats']['timestamp'])): ?>
                                                <div class="stat-item">
                                                    <span class="stat-label">Timestamp:</span>
                                                    <span class="stat-value"><?php echo htmlspecialchars($trainingResult['training_stats']['timestamp']); ?></span>
                                                </div>
                                                <?php endif; ?>
                                            </div>
                                        <?php endif; ?>
                                    <?php else: ?>
                                        <div class="alert alert-danger">
                                            <i class="fas fa-exclamation-triangle me-2"></i>
                                            <strong>Training Failed</strong>
                                            <br>
                                            <small><?php echo htmlspecialchars($trainingResult['message'] ?? 'Unknown error occurred'); ?></small>
                                        </div>
                                    <?php endif; ?>
                                </div>
                            </div>
                        <?php endif; ?>

                        <!-- Quick Links -->
                        <div class="card">
                            <div class="card-body">
                                <h5 class="card-title">
                                    <i class="fas fa-link me-2"></i>Quick Links
                                </h5>
                                <div class="d-flex gap-2 flex-wrap">
                                    <a href="motorpool_admin/predictive_maintenance.php" class="btn btn-outline-primary">
                                        <i class="fas fa-tachometer-alt me-2"></i>Predictive Maintenance Dashboard
                                    </a>
                                    <a href="api/python_ml_bridge.php?action=status" class="btn btn-outline-info" target="_blank">
                                        <i class="fas fa-info-circle me-2"></i>Check API Status
                                    </a>
                                    <a href="python_ml_interface.html" class="btn btn-outline-secondary">
                                        <i class="fas fa-code me-2"></i>ML Interface
                                    </a>
                                </div>
                            </div>
                        </div>

                    </div>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>

