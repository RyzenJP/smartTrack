<?php
header('Content-Type: application/json');
require_once __DIR__ . '/../includes/cors_helper.php';
setCORSHeaders(true);

// Generate a unique API key
function generateApiKey() {
    $prefix = 'ST'; // Smart Track prefix
    $timestamp = time();
    $random = substr(md5(uniqid()), 0, 8);
    return $prefix . $timestamp . $random;
}

try {
    $apiKey = generateApiKey();
    
    echo json_encode([
        'success' => true,
        'api_key' => $apiKey,
        'message' => 'API key generated successfully',
        'instructions' => [
            '1. Copy this API key',
            '2. Paste it in your mobile app settings',
            '3. Save settings and test connection'
        ]
    ]);
    
} catch (Exception $e) {
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage()
    ]);
}
?>
