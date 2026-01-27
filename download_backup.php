<?php
// Simple backup download handler
$file = $_GET['file'] ?? '';

if (empty($file)) {
    die('No file specified');
}

$backupDir = 'database_backups';
$filePath = $backupDir . '/' . basename($file);

if (!file_exists($filePath)) {
    die('File not found');
}

// Set headers for download
header('Content-Type: application/octet-stream');
header('Content-Disposition: attachment; filename="' . basename($file) . '"');
header('Content-Length: ' . filesize($filePath));

// Output file
readfile($filePath);
?>
