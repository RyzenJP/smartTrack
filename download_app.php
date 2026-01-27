<?php
/**
 * Mobile App Download Handler
 * Directly downloads the APK file without 3rd party services
 */

// Try multiple possible paths to find the APK file
$possible_paths = [
    __DIR__ . DIRECTORY_SEPARATOR . 'mobile_app' . DIRECTORY_SEPARATOR . 'SmartTrack.apk',
    __DIR__ . '/mobile_app/SmartTrack.apk',
    dirname(__DIR__) . '/mobile_app/SmartTrack.apk',
];

// Add document root paths if available
if (isset($_SERVER['DOCUMENT_ROOT'])) {
    $doc_root = $_SERVER['DOCUMENT_ROOT'];
    $possible_paths[] = $doc_root . '/trackingv2/trackingv2/mobile_app/SmartTrack.apk';
    $possible_paths[] = $doc_root . '/trackingv2/mobile_app/SmartTrack.apk';
    // Try to detect the correct path from script name
    if (isset($_SERVER['SCRIPT_NAME'])) {
        $script_dir = dirname($doc_root . $_SERVER['SCRIPT_NAME']);
        $possible_paths[] = $script_dir . '/mobile_app/SmartTrack.apk';
    }
}

$apk_file = null;
$file_found = false;

// Try each path until we find the file
foreach ($possible_paths as $path) {
    // Normalize path separators
    $path = str_replace(['/', '\\'], DIRECTORY_SEPARATOR, $path);
    
    if (@file_exists($path) && @is_readable($path)) {
        $apk_file = $path;
        $file_found = true;
        break;
    }
}

// Check if file exists
if (!$file_found || !$apk_file) {
    http_response_code(404);
    die('Mobile app file not found. Please contact the administrator.');
}

// Get file info
$filename = basename($apk_file);
$filesize = filesize($apk_file);
$file_extension = strtolower(pathinfo($apk_file, PATHINFO_EXTENSION));

// Validate it's an APK file
if ($file_extension !== 'apk') {
    http_response_code(400);
    die('Invalid file type. Only APK files are allowed.');
}

// Set headers for file download
header('Content-Type: application/vnd.android.package-archive');
header('Content-Disposition: attachment; filename="' . $filename . '"');
header('Content-Length: ' . $filesize);
header('Cache-Control: no-cache, must-revalidate');
header('Pragma: no-cache');
header('Expires: 0');

// Disable output buffering for large files
if (ob_get_level()) {
    ob_end_clean();
}

// Stream the file
readfile($apk_file);
exit;



