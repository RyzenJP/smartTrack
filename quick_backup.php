<?php
// Quick & Simple Backup System
session_start();
// Include security headers
require_once __DIR__ . '/includes/security_headers.php';

require 'db_connection.php';

// Check if user is super admin
if (!isset($_SESSION['role']) || strtolower($_SESSION['role']) !== 'super admin') {
    die("Access denied. Super Admin only.");
}

$message = '';
$error = '';

// Handle restore action
if ($_POST && $_POST['action'] === 'restore_backup') {
    // Include security class and validate CSRF token
    require_once __DIR__ . '/config/security.php';
    $security = Security::getInstance();
    
    // Validate CSRF token
    if (!isset($_POST['csrf_token']) || !$security->validateCSRFToken($_POST['csrf_token'] ?? '')) {
        $error = "❌ Invalid security token. Please try again.";
    } else {
        // Sanitize input
        $backupFile = $security->sanitizeInput($_POST['backup_file'] ?? '', 'string');
    $backupPath = 'database_backups/' . basename($backupFile);
    
    if (empty($backupFile) || !file_exists($backupPath)) {
        $error = "❌ Backup file not found.";
    } else {
        // Read backup file
        $sql = file_get_contents($backupPath);
        
        if ($sql === false) {
            $error = "❌ Failed to read backup file.";
        } else {
            // Disable foreign key checks temporarily - use prepared statements for consistency
            $fk_stmt = $conn->prepare("SET FOREIGN_KEY_CHECKS = 0");
            $fk_stmt->execute();
            $fk_stmt->close();
            $ac_stmt = $conn->prepare("SET AUTOCOMMIT = 0");
            $ac_stmt->execute();
            $ac_stmt->close();
            $conn->begin_transaction();
            
            try {
                // Remove comments and clean SQL
                $sql = preg_replace('/--.*$/m', '', $sql); // Remove single-line comments
                $sql = preg_replace('/\/\*.*?\*\//s', '', $sql); // Remove multi-line comments
                
                // Split SQL into individual statements (handle multi-line)
                $statements = [];
                $currentStatement = '';
                $lines = explode("\n", $sql);
                
                foreach ($lines as $line) {
                    $line = trim($line);
                    if (empty($line)) continue;
                    
                    $currentStatement .= $line . "\n";
                    
                    // Check if line ends with semicolon (end of statement)
                    if (substr(rtrim($line), -1) === ';') {
                        $stmt = trim($currentStatement);
                        if (!empty($stmt) && strlen($stmt) > 5) {
                            $statements[] = $stmt;
                        }
                        $currentStatement = '';
                    }
                }
                
                // Add any remaining statement
                if (!empty(trim($currentStatement))) {
                    $statements[] = trim($currentStatement);
                }
                
                $executed = 0;
                $errors = [];
                
                foreach ($statements as $statement) {
                    $statement = trim($statement);
                    if (empty($statement) || strlen($statement) < 5) {
                        continue;
                    }
                    
                    // Remove trailing semicolon if present
                    $statement = rtrim($statement, ';');
                    
                    // Validate statement doesn't contain dangerous operations
                    if (stripos($statement, 'DROP DATABASE') !== false || 
                        stripos($statement, 'CREATE DATABASE') !== false ||
                        stripos($statement, 'USE ') !== false) {
                        $errors[] = "Dangerous operation detected in backup file";
                        continue;
                    }
                    
                    if ($conn->query($statement)) {
                        $executed++;
                    } else {
                        $errorMsg = $conn->error;
                        if (!empty($errorMsg)) {
                            $errors[] = $errorMsg;
                        }
                    }
                }
                
                if (empty($errors)) {
                    $conn->commit();
                    restoreDatabaseSettings($conn);
                    $message = "✅ Database restored successfully from: " . basename($backupFile) . " ($executed statements executed)";
                } else {
                    $conn->rollback();
                    restoreDatabaseSettings($conn);
                    $error = "❌ Restore failed. Errors: " . implode('; ', array_slice($errors, 0, 5));
                }
            } catch (Exception $e) {
                $conn->rollback();
                restoreDatabaseSettings($conn);
                $error = "❌ Restore failed: " . $e->getMessage();
            }
        }
    }
    }
}

if ($_POST && $_POST['action'] === 'create_backup') {
    // Validate CSRF token
    require_once __DIR__ . '/config/security.php';
    $security = Security::getInstance();
    
    if (!isset($_POST['csrf_token']) || !$security->validateCSRFToken($_POST['csrf_token'] ?? '')) {
        $error = "❌ Invalid security token. Please try again.";
    } else {
        $backupDir = 'database_backups';
    if (!file_exists($backupDir)) mkdir($backupDir, 0755, true);
    
    $timestamp = date('Y-m-d_H-i-s');
    $backupFile = $backupDir . '/backup_' . $timestamp . '.sql';
    
    // Simple backup using PHP (no mysqldump needed)
    $tables = [];
    // Use prepared statement for consistency (static query but best practice)
    $stmt = $conn->prepare("SHOW TABLES");
    $stmt->execute();
    $result = $stmt->get_result();
    if ($result) {
        while ($row = $result->fetch_array()) {
            $tables[] = $row[0];
        }
    }
    $stmt->close();
    
    $backup = "-- Quick Backup Generated: " . date('Y-m-d H:i:s') . "\n";
    $backup .= "-- Database: " . DB_NAME . "\n";
    $backup .= "-- Tables: " . implode(', ', $tables) . "\n\n";
    
    foreach ($tables as $table) {
        // Sanitize table name (whitelist approach - only alphanumeric, underscore, dash)
        $table = preg_replace('/[^a-zA-Z0-9_-]/', '', $table);
        if (empty($table)) continue;
        
        // Get table structure - use prepared statement
        $backup .= "DROP TABLE IF EXISTS `$table`;\n";
        $createStmt = $conn->prepare("SHOW CREATE TABLE `$table`");
        if ($createStmt && $createStmt->execute()) {
            $createResult = $createStmt->get_result();
            if ($createRow = $createResult->fetch_array()) {
                $backup .= $createRow[1] . ";\n\n";
            }
            $createStmt->close();
        }
        
        // Get table data - use prepared statement
        $dataStmt = $conn->prepare("SELECT * FROM `$table`");
        if ($dataStmt && $dataStmt->execute()) {
            $dataResult = $dataStmt->get_result();
            while ($row = $dataResult->fetch_assoc()) {
                $backup .= "INSERT INTO `$table` VALUES (";
                $values = [];
                foreach ($row as $value) {
                    if ($value === null) {
                        $values[] = 'NULL';
                    } else {
                        $values[] = "'" . addslashes($value) . "'";
                    }
                }
                $backup .= implode(',', $values) . ");\n";
            }
            $dataStmt->close();
        }
        $backup .= "\n";
    }
    
    // Ensure directory exists and is writable
    if (!is_dir($backupDir)) {
        mkdir($backupDir, 0755, true);
    }
    
    // Write backup file
    $result = file_put_contents($backupFile, $backup);
    
    if ($result !== false && file_exists($backupFile)) {
        $fileSize = filesize($backupFile);
        $message = "✅ Backup created: " . basename($backupFile) . " (" . number_format($fileSize / 1024, 2) . " KB)";
    } else {
        $error = "❌ Failed to create backup file. Check directory permissions.";
    }
    }
}

// Get existing backups
$backups = [];
if (is_dir('database_backups')) {
    $files = glob('database_backups/*.sql');
    foreach ($files as $file) {
        $backups[] = [
            'name' => basename($file),
            'size' => filesize($file),
            'date' => filemtime($file)
        ];
    }
    // Sort by date (newest first)
    usort($backups, function($a, $b) {
        return $b['date'] - $a['date']; // Descending order (newest first)
    });
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0"/>
    <title>Quick Backup System | Smart Track</title>
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

        /* Hide text and chevrons when sidebar is collapsed */
        .sidebar.collapsed .link-text {
            display: none;
        }

        .sidebar.collapsed .dropdown-chevron {
            display: none !important;
        }

        /* Hide dropdown submenus when collapsed */
        .sidebar.collapsed .collapse {
            display: none !important;
        }

        /* Center icons when collapsed */
        .sidebar.collapsed a {
            justify-content: center;
            padding: 14px 0;
            text-align: center;
        }

        .sidebar.collapsed .dropdown-toggle {
            justify-content: center !important;
        }

        .sidebar.collapsed .dropdown-toggle > div {
            justify-content: center;
        }

        .sidebar a {
            display: flex;
            align-items: center;
            padding: 14px 20px;
            color: #d9d9d9;
            text-decoration: none;
            transition: background 0.2s;
            white-space: nowrap;
        }

        .sidebar a i {
            min-width: 20px;
            margin-right: 12px;
        }

        .sidebar.collapsed a i {
            margin-right: 0;
        }

        .sidebar a:hover,
        .sidebar a.active {
            background-color: #001d3d;
            color: var(--accent);
        }

        .sidebar a.active i {
            color: var(--accent) !important;
        }

        /* Dropdown submenu links design */
        .sidebar .collapse a {
            color: #d9d9d9;
            font-size: 0.95rem;
            padding: 10px 16px;
            margin: 4px 8px;
            border-radius: 0.35rem;
            display: flex;
            align-items: center;
            text-decoration: none;
            transition: background 0.2s, color 0.2s;
        }

        .sidebar .collapse a i {
            margin-right: 8px;
            min-width: 16px;
        }

        .sidebar .collapse a:hover {
            background-color: #002855;
            color: var(--accent);
        }

        /* Custom chevron icon for dropdown */
        .dropdown-chevron {
            color: #ffffff;
            transition: transform 0.3s ease, color 0.2s ease;
            margin-left: auto;
            flex-shrink: 0;
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

        /* Dropdown toggle layout */
        .dropdown-toggle {
            display: flex;
            align-items: center;
            justify-content: space-between;
            width: 100%;
        }

        .dropdown-toggle > div {
            display: flex;
            align-items: center;
            flex: 1;
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

        .navbar-brand {
            color: #000 !important;
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
            background-color: #001d3d;
            color: var(--accent);
            box-shadow: inset 2px 0 0 var(--accent);
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

        /* Backup page specific styles */
        .backup-item {
            padding: 10px;
            border-bottom: 1px solid #dee2e6;
        }
        .backup-item:last-child {
            border-bottom: none;
        }

        /* Global responsive behavior for Super Admin sidebar */
        .navbar { z-index: 1202 !important; }
        @media (max-width: 991.98px) {
            .sidebar { width: 260px; transform: translateX(-100%); position: fixed; top: 0; left: 0; height: 100vh; z-index: 1101; }
            .sidebar.open { transform: translateX(0); }
            .main-content { margin-left: 0 !important; padding: 16px; }
        }
    </style>
</head>
<body>
<?php 
// Include sidebar with correct path handling
$currentPage = basename($_SERVER['PHP_SELF']);
$scriptPath = str_replace('\\', '/', $_SERVER['PHP_SELF']);
$isSuperAdmin = strpos($scriptPath, '/super_admin/') !== false;
$saPrefix = $isSuperAdmin ? '' : 'super_admin/';
include 'pages/sidebar.php'; 
?>
<?php include 'pages/navbar.php'; ?>

<!-- Main Content -->
<div class="main-content" id="mainContent">
    <div class="container">
        <h1>🔄 Quick Database Backup & Restore</h1>
        
        <?php if ($message): ?>
            <div class="alert alert-success"><?= $message ?></div>
        <?php endif; ?>
        
        <?php if ($error): ?>
            <div class="alert alert-danger"><?= $error ?></div>
        <?php endif; ?>
        
        <div class="row">
            <div class="col-md-6">
                <div class="card">
                    <div class="card-header">
                        <h5>Create Backup</h5>
                    </div>
                    <div class="card-body">
                        <form method="POST">
                            <input type="hidden" name="action" value="create_backup">
                            <input type="hidden" name="csrf_token" value="<?= defined('CSRF_TOKEN') ? CSRF_TOKEN : '' ?>">
                            <p>This will create a complete backup of your database.</p>
                            <button type="submit" class="btn btn-success">
                                <i class="fas fa-download"></i> Create Backup Now
                            </button>
                        </form>
                    </div>
                </div>
            </div>
            
            <div class="col-md-6">
                <div class="card">
                    <div class="card-header">
                        <h5>Existing Backups</h5>
                    </div>
                    <div class="card-body">
                        <?php if (empty($backups)): ?>
                            <p class="text-muted">No backups found</p>
                        <?php else: ?>
                            <div class="alert alert-warning">
                                <small><i class="fas fa-exclamation-triangle"></i> <strong>Warning:</strong> Restoring a backup will overwrite your current database. Make sure to create a backup before restoring!</small>
                            </div>
                            <?php foreach ($backups as $backup): ?>
                                <div class="backup-item d-flex justify-content-between align-items-center">
                                    <div class="flex-grow-1">
                                        <div class="d-flex align-items-center mb-1">
                                            <strong class="me-2"><?= htmlspecialchars($backup['name']) ?></strong>
                                        </div>
                                        <small class="text-muted d-block">
                                            <i class="fas fa-calendar-alt me-1"></i><?= date('M d, Y H:i', $backup['date']) ?>
                                            <span class="mx-2">•</span>
                                            <i class="fas fa-file me-1"></i><?= number_format($backup['size'] / 1024, 2) ?> KB
                                        </small>
                                    </div>
                                    <div class="ms-3">
                                        <a href="download_backup.php?file=<?= urlencode($backup['name']) ?>" class="btn btn-sm btn-outline-primary" title="Download">
                                            <i class="fas fa-download"></i>
                                        </a>
                                        <form method="POST" style="display: inline;" onsubmit="return confirm('⚠️ WARNING: This will overwrite your current database with the backup data. This action cannot be undone!\n\nAre you sure you want to restore this backup?');">
                                            <input type="hidden" name="action" value="restore_backup">
                                            <input type="hidden" name="csrf_token" value="<?= defined('CSRF_TOKEN') ? CSRF_TOKEN : '' ?>">
                                            <input type="hidden" name="backup_file" value="<?= htmlspecialchars($backup['name']) ?>">
                                            <button type="submit" class="btn btn-sm btn-outline-warning" title="Restore">
                                                <i class="fas fa-undo"></i> Restore
                                            </button>
                                        </form>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
        
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<script>
// Sidebar toggle functionality
document.addEventListener('DOMContentLoaded', function() {
    const burgerBtn = document.getElementById('burgerBtn');
    const sidebar = document.getElementById('sidebar');
    const mainContent = document.getElementById('mainContent');
    
    if (burgerBtn && sidebar && mainContent) {
        burgerBtn.addEventListener('click', function() {
            sidebar.classList.toggle('collapsed');
            mainContent.classList.toggle('collapsed');
        });
    }

    // Logout button functionality
    const logoutBtn = document.getElementById('logoutBtn');
    if (logoutBtn) {
        logoutBtn.addEventListener('click', function(e) {
            e.preventDefault();
            if (confirm('Are you sure you want to logout?')) {
                window.location.href = 'logout.php';
            }
        });
    }
});

// Global burger behavior for small screens
(function(){
    const burger = document.getElementById('burgerBtn');
    const sidebar = document.getElementById('sidebar');
    if (!burger || !sidebar) return;
    function isMobile(){ return window.innerWidth < 992; }
    let backdrop;
    function ensureBackdrop(){
        if (backdrop) return backdrop;
        backdrop = document.createElement('div');
        backdrop.style.position = 'fixed';
        backdrop.style.top = '0';
        backdrop.style.left = '0';
        backdrop.style.right = '0';
        backdrop.style.bottom = '0';
        backdrop.style.background = 'rgba(0,0,0,0.25)';
        backdrop.style.zIndex = '1100';
        backdrop.style.display = 'none';
        document.body.appendChild(backdrop);
        backdrop.addEventListener('click', closeSidebar);
        return backdrop;
    }
    function openSidebar(){
        sidebar.classList.add('open');
        const b = ensureBackdrop();
        b.style.display = 'block';
    }
    function closeSidebar(){
        sidebar.classList.remove('open');
        if (backdrop) backdrop.style.display = 'none';
    }
    function toggle(){
        if(!isMobile()) return;
        if (sidebar.classList.contains('open')) closeSidebar(); else openSidebar();
    }
    if (!burger.dataset.bound){
        burger.addEventListener('click', function(e){ e.preventDefault(); e.stopPropagation(); toggle(); });
        burger.addEventListener('touchstart', function(e){ e.preventDefault(); e.stopPropagation(); toggle(); }, { passive: false });
        burger.dataset.bound = '1';
    }
    sidebar.addEventListener('click', function(e){ e.stopPropagation(); });
    sidebar.addEventListener('touchstart', function(e){ e.stopPropagation(); }, { passive: true });
    document.addEventListener('click', function(e){
        if(!isMobile()) return;
        if(!sidebar.contains(e.target) && !burger.contains(e.target)) closeSidebar();
    });
    window.addEventListener('resize', function(){ if(!isMobile()){ closeSidebar(); } });
})();
</script>
</body>
</html>
