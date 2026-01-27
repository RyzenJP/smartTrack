<?php
// Automated Backup Scheduler
// This script should be run via cron job for automated backups

require 'db_connection.php';
require 'backup_generator.php';

class AutoBackupScheduler {
    private $backupGen;
    private $logFile;
    
    public function __construct() {
        $this->backupGen = new BackupGenerator(DB_HOST, DB_USER, DB_PASS, DB_NAME);
        $this->logFile = 'database_backups/scheduler_log.txt';
    }
    
    // Run scheduled backup
    public function runScheduledBackup() {
        $this->log("Starting scheduled backup...");
        
        try {
            // Generate backup
            $result = $this->backupGen->generateBackup('scheduled');
            
            if ($result['success']) {
                $this->log("Backup created successfully: {$result['filename']} (Size: " . number_format($result['size'] / 1024, 2) . " KB)");
                
                // Clean old backups (keep last 30 days)
                $deleted = $this->backupGen->cleanOldBackups(30);
                if ($deleted > 0) {
                    $this->log("Cleaned {$deleted} old backup files");
                }
                
                // Send notification (optional)
                $this->sendNotification($result);
                
                return true;
            } else {
                $this->log("Backup failed: " . ($result['error'] ?? 'Unknown error'));
                return false;
            }
        } catch (Exception $e) {
            $this->log("Backup error: " . $e->getMessage());
            return false;
        }
    }
    
    // Log scheduler activity
    private function log($message) {
        $logEntry = date('Y-m-d H:i:s') . " | SCHEDULER | {$message}\n";
        file_put_contents($this->logFile, $logEntry, FILE_APPEND | LOCK_EX);
    }
    
    // Send notification (email or webhook)
    private function sendNotification($backupResult) {
        // You can implement email notification here
        // or send to a webhook for monitoring
        $this->log("Backup notification sent for: {$backupResult['filename']}");
    }
    
    // Check if backup is needed (based on last backup time)
    public function isBackupNeeded($intervalHours = 24) {
        $lastBackup = $this->getLastBackupTime();
        if (!$lastBackup) {
            return true; // No previous backup
        }
        
        $hoursSinceLastBackup = (time() - $lastBackup) / 3600;
        return $hoursSinceLastBackup >= $intervalHours;
    }
    
    // Get last backup time
    private function getLastBackupTime() {
        $files = glob('database_backups/*.sql*');
        if (empty($files)) {
            return null;
        }
        
        $lastFile = '';
        $lastTime = 0;
        
        foreach ($files as $file) {
            $fileTime = filemtime($file);
            if ($fileTime > $lastTime) {
                $lastTime = $fileTime;
                $lastFile = $file;
            }
        }
        
        return $lastTime;
    }
    
    // Get scheduler status
    public function getStatus() {
        $lastBackup = $this->getLastBackupTime();
        $stats = $this->backupGen->getBackupStats();
        
        return [
            'last_backup' => $lastBackup ? date('Y-m-d H:i:s', $lastBackup) : 'Never',
            'backup_needed' => $this->isBackupNeeded(),
            'total_backups' => $stats['count'],
            'total_size' => $stats['total_size'],
            'scheduler_active' => true
        ];
    }
}

// Run if called directly (for cron job)
if (php_sapi_name() === 'cli' || isset($_GET['run'])) {
    $scheduler = new AutoBackupScheduler();
    
    if ($scheduler->isBackupNeeded()) {
        $success = $scheduler->runScheduledBackup();
        echo $success ? "Backup completed successfully\n" : "Backup failed\n";
    } else {
        echo "Backup not needed yet\n";
    }
}

// API endpoint for status check
if (isset($_GET['status'])) {
    header('Content-Type: application/json');
    $scheduler = new AutoBackupScheduler();
    echo json_encode($scheduler->getStatus());
}
?>
