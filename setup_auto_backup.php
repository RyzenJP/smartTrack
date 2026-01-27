<?php
// Setup Automated Backup System
echo "🔄 Setting up Automated Backup System...\n\n";

// Create backup directory
$backupDir = 'database_backups';
if (!file_exists($backupDir)) {
    mkdir($backupDir, 0755, true);
    echo "✅ Created backup directory: {$backupDir}\n";
} else {
    echo "✅ Backup directory already exists: {$backupDir}\n";
}

// Create cron job file
$cronFile = 'backup_cron.txt';
$cronContent = "# Automated Database Backup Cron Jobs
# Add these lines to your crontab (crontab -e)

# Daily backup at 2 AM
0 2 * * * /usr/bin/php " . __DIR__ . "/auto_backup_scheduler.php run

# Weekly full backup on Sunday at 3 AM
0 3 * * 0 /usr/bin/php " . __DIR__ . "/auto_backup_scheduler.php run

# Clean old backups monthly (1st of month at 4 AM)
0 4 1 * * /usr/bin/php " . __DIR__ . "/auto_backup_scheduler.php run

# For Windows Task Scheduler, use:
# php.exe " . __DIR__ . "\\auto_backup_scheduler.php run
";

file_put_contents($cronFile, $cronContent);
echo "✅ Created cron job file: {$cronFile}\n";

// Test backup generation
echo "\n🧪 Testing backup generation...\n";
require 'db_connection.php';
require 'backup_generator.php';

$backupGen = new BackupGenerator(DB_HOST, DB_USER, DB_PASS, DB_NAME);
$result = $backupGen->generateBackup('test');

if ($result['success']) {
    echo "✅ Test backup created successfully!\n";
    echo "   File: {$result['filename']}\n";
    echo "   Size: " . number_format($result['size'] / 1024, 2) . " KB\n";
    echo "   Version: {$result['version']}\n";
} else {
    echo "❌ Test backup failed: " . ($result['error'] ?? 'Unknown error') . "\n";
}

// Create backup monitoring script
$monitorScript = '<?php
// Backup Monitor - Check backup status
require "auto_backup_scheduler.php";

$scheduler = new AutoBackupScheduler();
$status = $scheduler->getStatus();

echo "📊 Backup System Status:\n";
echo "Last Backup: " . $status["last_backup"] . "\n";
echo "Backup Needed: " . ($status["backup_needed"] ? "YES" : "NO") . "\n";
echo "Total Backups: " . $status["total_backups"] . "\n";
echo "Total Size: " . number_format($status["total_size"] / 1024 / 1024, 2) . " MB\n";
echo "Scheduler Active: " . ($status["scheduler_active"] ? "YES" : "NO") . "\n";
?>';

file_put_contents('backup_monitor.php', $monitorScript);
echo "✅ Created backup monitor: backup_monitor.php\n";

// Create README for backup system
$readme = '# Database Backup System

## Features
- ✅ Automated backup generation with versioning
- ✅ Compressed backup files (.gz)
- ✅ Backup history and statistics
- ✅ Automatic cleanup of old backups
- ✅ Web interface for management
- ✅ Cron job scheduling support

## Files
- `backup_generator.php` - Core backup functionality
- `auto_backup_scheduler.php` - Automated scheduling
- `backup_management.php` - Web interface
- `backup_monitor.php` - Status monitoring

## Setup Cron Jobs

### Linux/Mac:
```bash
crontab -e
```

Add these lines:
```
# Daily backup at 2 AM
0 2 * * * /usr/bin/php ' . __DIR__ . '/auto_backup_scheduler.php run

# Weekly full backup on Sunday at 3 AM  
0 3 * * 0 /usr/bin/php ' . __DIR__ . '/auto_backup_scheduler.php run
```

### Windows:
Use Task Scheduler to run:
```
php.exe "' . __DIR__ . '\\auto_backup_scheduler.php" run
```

## Manual Usage

### Generate Backup:
```bash
php auto_backup_scheduler.php run
```

### Check Status:
```bash
php backup_monitor.php
```

### Web Interface:
Access `backup_management.php` in your browser (Super Admin only)

## Backup Types
- **Manual**: User-initiated backups
- **Scheduled**: Automated daily/weekly backups  
- **Emergency**: Critical situation backups

## Configuration
- Backup retention: 30 days (configurable)
- Compression: Automatic (.gz)
- Versioning: Automatic incremental versioning
- Logging: Complete backup history

## Security
- Super Admin access only
- Secure file permissions
- Backup file integrity checks
';

file_put_contents('BACKUP_README.md', $readme);
echo "✅ Created documentation: BACKUP_README.md\n";

echo "\n🎉 Automated Backup System setup complete!\n";
echo "📋 Next steps:\n";
echo "1. Set up cron jobs using: {$cronFile}\n";
echo "2. Access web interface: backup_management.php\n";
echo "3. Monitor system: php backup_monitor.php\n";
echo "4. Read documentation: BACKUP_README.md\n";
?>
