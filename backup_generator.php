<?php
// Automated Database Backup Generator
class BackupGenerator {
    private $dbHost;
    private $dbUser;
    private $dbPass;
    private $dbName;
    private $backupDir;
    
    public function __construct($host, $user, $pass, $name) {
        $this->dbHost = $host;
        $this->dbUser = $user;
        $this->dbPass = $pass;
        $this->dbName = $name;
        $this->backupDir = 'database_backups';
        
        // Create backup directory if it doesn't exist
        if (!file_exists($this->backupDir)) {
            mkdir($this->backupDir, 0755, true);
        }
    }
    
    // Generate backup with versioning
    public function generateBackup($type = 'manual') {
        $timestamp = date('Y-m-d_H-i-s');
        $version = $this->getNextVersion();
        $backupName = "backup_v{$version}_{$type}_{$timestamp}.sql";
        $backupPath = $this->backupDir . '/' . $backupName;
        
        // Create mysqldump command
        $command = sprintf(
            'mysqldump -h %s -u %s -p%s --single-transaction --routines --triggers %s > %s',
            escapeshellarg($this->dbHost),
            escapeshellarg($this->dbUser),
            escapeshellarg($this->dbPass),
            escapeshellarg($this->dbName),
            escapeshellarg($backupPath)
        );
        
        // Execute backup
        exec($command, $output, $returnCode);
        
        if ($returnCode === 0 && file_exists($backupPath)) {
            // Compress backup
            $this->compressBackup($backupPath);
            
            // Log backup creation
            $this->logBackup($backupName, $type, filesize($backupPath));
            
            return [
                'success' => true,
                'filename' => $backupName,
                'path' => $backupPath,
                'size' => filesize($backupPath),
                'version' => $version
            ];
        } else {
            return [
                'success' => false,
                'error' => 'Failed to create backup'
            ];
        }
    }
    
    // Get next version number
    private function getNextVersion() {
        $versionFile = $this->backupDir . '/version.txt';
        $version = 1;
        
        if (file_exists($versionFile)) {
            $version = (int)file_get_contents($versionFile) + 1;
        }
        
        file_put_contents($versionFile, $version);
        return $version;
    }
    
    // Compress backup file
    private function compressBackup($filePath) {
        if (function_exists('gzopen')) {
            $gz = gzopen($filePath . '.gz', 'w9');
            gzwrite($gz, file_get_contents($filePath));
            gzclose($gz);
            
            // Remove original file
            unlink($filePath);
        }
    }
    
    // Log backup information
    private function logBackup($filename, $type, $size) {
        $logFile = $this->backupDir . '/backup_log.txt';
        $logEntry = date('Y-m-d H:i:s') . " | {$type} | {$filename} | " . number_format($size / 1024, 2) . " KB\n";
        file_put_contents($logFile, $logEntry, FILE_APPEND | LOCK_EX);
    }
    
    // Get backup history
    public function getBackupHistory() {
        $logFile = $this->backupDir . '/backup_log.txt';
        $history = [];
        
        if (file_exists($logFile)) {
            $lines = file($logFile, FILE_IGNORE_NEW_LINES);
            foreach (array_reverse($lines) as $line) {
                $parts = explode(' | ', $line);
                if (count($parts) >= 4) {
                    $history[] = [
                        'date' => $parts[0],
                        'type' => $parts[1],
                        'filename' => $parts[2],
                        'size' => $parts[3]
                    ];
                }
            }
        }
        
        return $history;
    }
    
    // Clean old backups (keep last 30 days)
    public function cleanOldBackups($days = 30) {
        $files = glob($this->backupDir . '/*.sql*');
        $cutoff = time() - ($days * 24 * 60 * 60);
        $deleted = 0;
        
        foreach ($files as $file) {
            if (filemtime($file) < $cutoff) {
                if (unlink($file)) {
                    $deleted++;
                }
            }
        }
        
        return $deleted;
    }
    
    // Get backup statistics
    public function getBackupStats() {
        $files = glob($this->backupDir . '/*.sql*');
        $totalSize = 0;
        $count = 0;
        
        foreach ($files as $file) {
            $totalSize += filesize($file);
            $count++;
        }
        
        return [
            'count' => $count,
            'total_size' => $totalSize,
            'average_size' => $count > 0 ? $totalSize / $count : 0,
            'oldest' => $count > 0 ? date('Y-m-d H:i:s', min(array_map('filemtime', $files))) : null,
            'newest' => $count > 0 ? date('Y-m-d H:i:s', max(array_map('filemtime', $files))) : null
        ];
    }
}

// Usage example and API endpoint
if (isset($_GET['action'])) {
    header('Content-Type: application/json');
    
    require 'db_connection.php';
    $backupGen = new BackupGenerator(DB_HOST, DB_USER, DB_PASS, DB_NAME);
    
    switch ($_GET['action']) {
        case 'generate':
            $type = $_GET['type'] ?? 'manual';
            $result = $backupGen->generateBackup($type);
            echo json_encode($result);
            break;
            
        case 'history':
            $history = $backupGen->getBackupHistory();
            echo json_encode(['success' => true, 'history' => $history]);
            break;
            
        case 'stats':
            $stats = $backupGen->getBackupStats();
            echo json_encode(['success' => true, 'stats' => $stats]);
            break;
            
        case 'clean':
            $deleted = $backupGen->cleanOldBackups();
            echo json_encode(['success' => true, 'deleted' => $deleted]);
            break;
            
        default:
            echo json_encode(['success' => false, 'message' => 'Invalid action']);
    }
}
?>
