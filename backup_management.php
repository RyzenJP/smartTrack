<?php
session_start();
require 'db_connection.php';
require 'backup_generator.php';

// Check if user is super admin
if (!isset($_SESSION['role']) || strtolower($_SESSION['role']) !== 'super admin') {
    header("Location: login.php");
    exit;
}

$backupGen = new BackupGenerator(DB_HOST, DB_USER, DB_PASS, DB_NAME);
$message = '';
$error = '';

// Handle actions
if ($_POST) {
    $action = $_POST['action'] ?? '';
    
    switch ($action) {
        case 'generate_backup':
            $type = $_POST['backup_type'] ?? 'manual';
            $result = $backupGen->generateBackup($type);
            
            if ($result['success']) {
                $message = "Backup generated successfully! Version: {$result['version']}, File: {$result['filename']}";
            } else {
                $error = "Failed to generate backup: " . ($result['error'] ?? 'Unknown error');
            }
            break;
            
        case 'clean_old_backups':
            $days = (int)($_POST['days'] ?? 30);
            $deleted = $backupGen->cleanOldBackups($days);
            $message = "Cleaned {$deleted} old backup files (older than {$days} days)";
            break;
    }
}

// Get data
$backupHistory = $backupGen->getBackupHistory();
$backupStats = $backupGen->getBackupStats();
$backupFiles = glob('database_backups/*.sql*');
rsort($backupFiles); // Sort by modification time (newest first)
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Backup Management & Generation</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <style>
        .backup-card {
            transition: all 0.3s ease;
            border-left: 4px solid #007bff;
        }
        .backup-card:hover {
            transform: translateY(-2px);
            box-shadow: 0 4px 8px rgba(0,0,0,0.1);
        }
        .backup-type {
            font-size: 0.8em;
            padding: 2px 8px;
            border-radius: 12px;
        }
        .type-manual { background-color: #e3f2fd; color: #1976d2; }
        .type-scheduled { background-color: #f3e5f5; color: #7b1fa2; }
        .type-emergency { background-color: #ffebee; color: #c62828; }
        .progress-ring {
            transform: rotate(-90deg);
        }
        .progress-ring-circle {
            transition: stroke-dasharray 0.35s;
        }
    </style>
</head>
<body>
    <div class="container-fluid">
        <div class="row">
            <!-- Sidebar -->
            <div class="col-md-2 bg-dark text-white min-vh-100 p-0">
                <?php include 'pages/sidebar.php'; ?>
            </div>
            
            <!-- Main Content -->
            <div class="col-md-10">
                <div class="container-fluid py-4">
                    <div class="d-flex justify-content-between align-items-center mb-4">
                        <h1><i class="fas fa-database text-primary"></i> Backup Management & Generation</h1>
                        <div>
                            <button class="btn btn-success me-2" data-bs-toggle="modal" data-bs-target="#generateBackupModal">
                                <i class="fas fa-plus"></i> Generate Backup
                            </button>
                            <button class="btn btn-warning" data-bs-toggle="modal" data-bs-target="#cleanBackupsModal">
                                <i class="fas fa-broom"></i> Clean Old Backups
                            </button>
                        </div>
                    </div>

                    <!-- Messages -->
                    <?php if ($message): ?>
                        <div class="alert alert-success alert-dismissible fade show">
                            <i class="fas fa-check-circle"></i> <?= $message ?>
                            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                        </div>
                    <?php endif; ?>

                    <?php if ($error): ?>
                        <div class="alert alert-danger alert-dismissible fade show">
                            <i class="fas fa-exclamation-triangle"></i> <?= $error ?>
                            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                        </div>
                    <?php endif; ?>

                    <!-- Statistics Cards -->
                    <div class="row mb-4">
                        <div class="col-md-3">
                            <div class="card bg-primary text-white">
                                <div class="card-body">
                                    <div class="d-flex justify-content-between">
                                        <div>
                                            <h4><?= $backupStats['count'] ?></h4>
                                            <p class="mb-0">Total Backups</p>
                                        </div>
                                        <i class="fas fa-archive fa-2x"></i>
                                    </div>
                                </div>
                            </div>
                        </div>
                        <div class="col-md-3">
                            <div class="card bg-info text-white">
                                <div class="card-body">
                                    <div class="d-flex justify-content-between">
                                        <div>
                                            <h4><?= number_format($backupStats['total_size'] / 1024 / 1024, 2) ?> MB</h4>
                                            <p class="mb-0">Total Size</p>
                                        </div>
                                        <i class="fas fa-hdd fa-2x"></i>
                                    </div>
                                </div>
                            </div>
                        </div>
                        <div class="col-md-3">
                            <div class="card bg-success text-white">
                                <div class="card-body">
                                    <div class="d-flex justify-content-between">
                                        <div>
                                            <h4><?= number_format($backupStats['average_size'] / 1024, 2) ?> KB</h4>
                                            <p class="mb-0">Avg Size</p>
                                        </div>
                                        <i class="fas fa-chart-line fa-2x"></i>
                                    </div>
                                </div>
                            </div>
                        </div>
                        <div class="col-md-3">
                            <div class="card bg-warning text-white">
                                <div class="card-body">
                                    <div class="d-flex justify-content-between">
                                        <div>
                                            <h6><?= $backupStats['newest'] ? date('M d, H:i', strtotime($backupStats['newest'])) : 'Never' ?></h6>
                                            <p class="mb-0">Last Backup</p>
                                        </div>
                                        <i class="fas fa-clock fa-2x"></i>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Backup Files -->
                    <div class="card mb-4">
                        <div class="card-header">
                            <h5><i class="fas fa-folder-open"></i> Backup Files</h5>
                        </div>
                        <div class="card-body">
                            <?php if (empty($backupFiles)): ?>
                                <div class="text-center py-5">
                                    <i class="fas fa-archive fa-3x text-muted mb-3"></i>
                                    <h5 class="text-muted">No backup files found</h5>
                                    <p class="text-muted">Generate your first backup to get started</p>
                                </div>
                            <?php else: ?>
                                <div class="row">
                                    <?php foreach ($backupFiles as $file): ?>
                                        <?php
                                        $filename = basename($file);
                                        $fileSize = filesize($file);
                                        $fileDate = date('M d, Y H:i', filemtime($file));
                                        $isCompressed = strpos($filename, '.gz') !== false;
                                        ?>
                                        <div class="col-md-4 mb-3">
                                            <div class="card backup-card h-100">
                                                <div class="card-body">
                                                    <div class="d-flex justify-content-between align-items-start mb-2">
                                                        <h6 class="card-title">
                                                            <i class="fas fa-file-archive text-primary"></i>
                                                            <?= $filename ?>
                                                        </h6>
                                                        <div class="btn-group btn-group-sm">
                                                            <a href="<?= $file ?>" class="btn btn-outline-primary" download>
                                                                <i class="fas fa-download"></i>
                                                            </a>
                                                            <button class="btn btn-outline-danger" onclick="deleteBackup('<?= $filename ?>')">
                                                                <i class="fas fa-trash"></i>
                                                            </button>
                                                        </div>
                                                    </div>
                                                    <p class="card-text">
                                                        <small class="text-muted">
                                                            <i class="fas fa-calendar"></i> <?= $fileDate ?><br>
                                                            <i class="fas fa-weight-hanging"></i> <?= number_format($fileSize / 1024, 2) ?> KB
                                                            <?php if ($isCompressed): ?>
                                                                <span class="badge bg-info ms-1">Compressed</span>
                                                            <?php endif; ?>
                                                        </small>
                                                    </p>
                                                </div>
                                            </div>
                                        </div>
                                    <?php endforeach; ?>
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>

                    <!-- Backup History -->
                    <div class="card">
                        <div class="card-header">
                            <h5><i class="fas fa-history"></i> Backup History</h5>
                        </div>
                        <div class="card-body">
                            <?php if (empty($backupHistory)): ?>
                                <p class="text-muted">No backup history available</p>
                            <?php else: ?>
                                <div class="table-responsive">
                                    <table class="table table-sm">
                                        <thead>
                                            <tr>
                                                <th>Date</th>
                                                <th>Type</th>
                                                <th>Filename</th>
                                                <th>Size</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <?php foreach ($backupHistory as $entry): ?>
                                                <tr>
                                                    <td><?= $entry['date'] ?></td>
                                                    <td>
                                                        <span class="backup-type type-<?= $entry['type'] ?>">
                                                            <?= ucfirst($entry['type']) ?>
                                                        </span>
                                                    </td>
                                                    <td><?= $entry['filename'] ?></td>
                                                    <td><?= $entry['size'] ?></td>
                                                </tr>
                                            <?php endforeach; ?>
                                        </tbody>
                                    </table>
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Generate Backup Modal -->
    <div class="modal fade" id="generateBackupModal" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title"><i class="fas fa-plus"></i> Generate New Backup</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <form method="POST">
                    <div class="modal-body">
                        <input type="hidden" name="action" value="generate_backup">
                        <div class="mb-3">
                            <label for="backup_type" class="form-label">Backup Type</label>
                            <select class="form-select" name="backup_type" id="backup_type" required>
                                <option value="manual">Manual Backup</option>
                                <option value="scheduled">Scheduled Backup</option>
                                <option value="emergency">Emergency Backup</option>
                            </select>
                        </div>
                        <div class="alert alert-info">
                            <i class="fas fa-info-circle"></i>
                            This will create a complete backup of your database with versioning.
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" class="btn btn-success">
                            <i class="fas fa-download"></i> Generate Backup
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Clean Backups Modal -->
    <div class="modal fade" id="cleanBackupsModal" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title"><i class="fas fa-broom"></i> Clean Old Backups</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <form method="POST">
                    <div class="modal-body">
                        <input type="hidden" name="action" value="clean_old_backups">
                        <div class="mb-3">
                            <label for="days" class="form-label">Keep backups newer than (days)</label>
                            <input type="number" class="form-control" name="days" id="days" value="30" min="1" max="365" required>
                        </div>
                        <div class="alert alert-warning">
                            <i class="fas fa-exclamation-triangle"></i>
                            This will permanently delete backup files older than the specified number of days.
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" class="btn btn-warning">
                            <i class="fas fa-broom"></i> Clean Backups
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        function deleteBackup(filename) {
            if (confirm('Are you sure you want to delete this backup file? This action cannot be undone.')) {
                // Implement delete functionality
                console.log('Delete backup:', filename);
            }
        }
    </script>
</body>
</html>
