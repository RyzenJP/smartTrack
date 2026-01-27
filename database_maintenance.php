<?php
session_start();
require 'db_connection.php';

// Check if user is super admin
if (!isset($_SESSION['role']) || strtolower($_SESSION['role']) !== 'super admin') {
    header("Location: login.php");
    exit;
}

// Create backup directory if it doesn't exist
$backupDir = 'database_backups';
if (!file_exists($backupDir)) {
    mkdir($backupDir, 0755, true);
}

// Handle backup actions
$message = '';
$error = '';

if ($_POST) {
    $action = $_POST['action'] ?? '';
    
    switch ($action) {
        case 'create_backup':
            $backupName = 'backup_' . date('Y-m-d_H-i-s') . '.sql';
            $backupPath = $backupDir . '/' . $backupName;
            
            // Create database backup using mysqldump
            $command = "mysqldump -u " . DB_USER . " -p" . DB_PASS . " " . DB_NAME . " > " . $backupPath;
            exec($command, $output, $returnCode);
            
            if ($returnCode === 0) {
                $message = "Backup created successfully: " . $backupName;
            } else {
                $error = "Failed to create backup. Please check database credentials.";
            }
            break;
            
        case 'restore_backup':
            $backupFile = $_POST['backup_file'] ?? '';
            if ($backupFile && file_exists($backupDir . '/' . $backupFile)) {
                $restorePath = $backupDir . '/' . $backupFile;
                
                // Restore database from backup
                $command = "mysql -u " . DB_USER . " -p" . DB_PASS . " " . DB_NAME . " < " . $restorePath;
                exec($command, $output, $returnCode);
                
                if ($returnCode === 0) {
                    $message = "Database restored successfully from: " . $backupFile;
                } else {
                    $error = "Failed to restore backup. Please check the backup file.";
                }
            } else {
                $error = "Invalid backup file selected.";
            }
            break;
            
        case 'delete_backup':
            $backupFile = $_POST['backup_file'] ?? '';
            if ($backupFile && file_exists($backupDir . '/' . $backupFile)) {
                if (unlink($backupDir . '/' . $backupFile)) {
                    $message = "Backup deleted successfully: " . $backupFile;
                } else {
                    $error = "Failed to delete backup file.";
                }
            } else {
                $error = "Invalid backup file selected.";
            }
            break;
    }
}

// Get list of backup files
$backups = [];
if (is_dir($backupDir)) {
    $files = scandir($backupDir);
    foreach ($files as $file) {
        if (pathinfo($file, PATHINFO_EXTENSION) === 'sql') {
            $backups[] = [
                'name' => $file,
                'size' => filesize($backupDir . '/' . $file),
                'date' => filemtime($backupDir . '/' . $file)
            ];
        }
    }
    // Sort by date (newest first)
    usort($backups, function($a, $b) {
        return $b['date'] - $a['date'];
    });
}

// Get database info - use prepared statements for consistency
$dbInfo = [];
$tables_stmt = $conn->prepare("SHOW TABLES");
$tables_stmt->execute();
$tables = $tables_stmt->get_result()->fetch_all(MYSQLI_NUM);
$dbInfo['tables'] = count($tables);
$tables_stmt->close();

$dbName = DB_NAME;
$size_stmt = $conn->prepare("SELECT ROUND(SUM(data_length + index_length) / 1024 / 1024, 2) AS 'DB Size in MB' FROM information_schema.tables WHERE table_schema = ?");
$size_stmt->bind_param("s", $dbName);
$size_stmt->execute();
$size_result = $size_stmt->get_result();
$dbInfo['size'] = $size_result->fetch_assoc()['DB Size in MB'] ?? 0;
$size_stmt->close();

// Use prepared statement for consistency and security best practices
$version_stmt = $conn->prepare("SELECT VERSION() as version");
$version_stmt->execute();
$version_result = $version_stmt->get_result();
$dbInfo['version'] = $version_result->fetch_assoc()['version'] ?? 'Unknown';
$version_stmt->close();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Database Maintenance & Backup</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <style>
        .backup-card {
            transition: transform 0.2s;
        }
        .backup-card:hover {
            transform: translateY(-2px);
        }
        .file-size {
            color: #6c757d;
            font-size: 0.9em;
        }
        .backup-actions {
            opacity: 0;
            transition: opacity 0.2s;
        }
        .backup-card:hover .backup-actions {
            opacity: 1;
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
                        <h1><i class="fas fa-database text-primary"></i> Database Maintenance & Backup</h1>
                        <button class="btn btn-success" data-bs-toggle="modal" data-bs-target="#createBackupModal">
                            <i class="fas fa-plus"></i> Create Backup
                        </button>
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

                    <!-- Database Info -->
                    <div class="row mb-4">
                        <div class="col-md-3">
                            <div class="card bg-primary text-white">
                                <div class="card-body">
                                    <div class="d-flex justify-content-between">
                                        <div>
                                            <h4><?= $dbInfo['tables'] ?></h4>
                                            <p class="mb-0">Total Tables</p>
                                        </div>
                                        <i class="fas fa-table fa-2x"></i>
                                    </div>
                                </div>
                            </div>
                        </div>
                        <div class="col-md-3">
                            <div class="card bg-info text-white">
                                <div class="card-body">
                                    <div class="d-flex justify-content-between">
                                        <div>
                                            <h4><?= $dbInfo['size'] ?> MB</h4>
                                            <p class="mb-0">Database Size</p>
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
                                            <h4><?= count($backups) ?></h4>
                                            <p class="mb-0">Total Backups</p>
                                        </div>
                                        <i class="fas fa-archive fa-2x"></i>
                                    </div>
                                </div>
                            </div>
                        </div>
                        <div class="col-md-3">
                            <div class="card bg-warning text-white">
                                <div class="card-body">
                                    <div class="d-flex justify-content-between">
                                        <div>
                                            <h6><?= $dbInfo['version'] ?></h6>
                                            <p class="mb-0">MySQL Version</p>
                                        </div>
                                        <i class="fas fa-server fa-2x"></i>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Backup List -->
                    <div class="card">
                        <div class="card-header">
                            <h5><i class="fas fa-archive"></i> Available Backups</h5>
                        </div>
                        <div class="card-body">
                            <?php if (empty($backups)): ?>
                                <div class="text-center py-5">
                                    <i class="fas fa-archive fa-3x text-muted mb-3"></i>
                                    <h5 class="text-muted">No backups found</h5>
                                    <p class="text-muted">Create your first backup to get started</p>
                                </div>
                            <?php else: ?>
                                <div class="row">
                                    <?php foreach ($backups as $backup): ?>
                                        <div class="col-md-4 mb-3">
                                            <div class="card backup-card h-100">
                                                <div class="card-body">
                                                    <div class="d-flex justify-content-between align-items-start mb-2">
                                                        <h6 class="card-title">
                                                            <i class="fas fa-file-archive text-primary"></i>
                                                            <?= $backup['name'] ?>
                                                        </h6>
                                                        <div class="backup-actions">
                                                            <div class="btn-group btn-group-sm">
                                                                <button class="btn btn-outline-primary" onclick="restoreBackup('<?= $backup['name'] ?>')">
                                                                    <i class="fas fa-undo"></i>
                                                                </button>
                                                                <button class="btn btn-outline-danger" onclick="deleteBackup('<?= $backup['name'] ?>')">
                                                                    <i class="fas fa-trash"></i>
                                                                </button>
                                                            </div>
                                                        </div>
                                                    </div>
                                                    <p class="card-text">
                                                        <small class="text-muted">
                                                            <i class="fas fa-calendar"></i> <?= date('M d, Y H:i', $backup['date']) ?><br>
                                                            <i class="fas fa-weight-hanging"></i> <?= number_format($backup['size'] / 1024, 2) ?> KB
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
                </div>
            </div>
        </div>
    </div>

    <!-- Create Backup Modal -->
    <div class="modal fade" id="createBackupModal" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title"><i class="fas fa-plus"></i> Create New Backup</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <form method="POST">
                    <div class="modal-body">
                        <input type="hidden" name="action" value="create_backup">
                        <div class="alert alert-info">
                            <i class="fas fa-info-circle"></i>
                            This will create a complete backup of your database including all tables, data, and structure.
                        </div>
                        <p><strong>Database:</strong> <?= DB_NAME ?></p>
                        <p><strong>Estimated Size:</strong> <?= $dbInfo['size'] ?> MB</p>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" class="btn btn-success">
                            <i class="fas fa-download"></i> Create Backup
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Restore Backup Modal -->
    <div class="modal fade" id="restoreBackupModal" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title"><i class="fas fa-undo"></i> Restore Backup</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <form method="POST" id="restoreForm">
                    <div class="modal-body">
                        <input type="hidden" name="action" value="restore_backup">
                        <input type="hidden" name="backup_file" id="restoreBackupFile">
                        <div class="alert alert-warning">
                            <i class="fas fa-exclamation-triangle"></i>
                            <strong>Warning:</strong> This will replace all current data with the backup data. This action cannot be undone!
                        </div>
                        <p>Are you sure you want to restore from backup: <strong id="restoreFileName"></strong>?</p>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" class="btn btn-warning">
                            <i class="fas fa-undo"></i> Restore Backup
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Delete Backup Modal -->
    <div class="modal fade" id="deleteBackupModal" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title"><i class="fas fa-trash"></i> Delete Backup</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <form method="POST" id="deleteForm">
                    <div class="modal-body">
                        <input type="hidden" name="action" value="delete_backup">
                        <input type="hidden" name="backup_file" id="deleteBackupFile">
                        <div class="alert alert-danger">
                            <i class="fas fa-exclamation-triangle"></i>
                            <strong>Warning:</strong> This will permanently delete the backup file. This action cannot be undone!
                        </div>
                        <p>Are you sure you want to delete backup: <strong id="deleteFileName"></strong>?</p>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" class="btn btn-danger">
                            <i class="fas fa-trash"></i> Delete Backup
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        function restoreBackup(filename) {
            document.getElementById('restoreBackupFile').value = filename;
            document.getElementById('restoreFileName').textContent = filename;
            new bootstrap.Modal(document.getElementById('restoreBackupModal')).show();
        }
        
        function deleteBackup(filename) {
            document.getElementById('deleteBackupFile').value = filename;
            document.getElementById('deleteFileName').textContent = filename;
            new bootstrap.Modal(document.getElementById('deleteBackupModal')).show();
        }
    </script>
</body>
</html>

<?php
$conn->close();
?>
