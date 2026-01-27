<?php
/**
 * Production Setup Verification Script
 * Tests database connection and verifies .env configuration
 * 
 * ⚠️ SECURITY: Delete this file after testing or restrict access
 * 
 * For production testing, you can access this file directly:
 * https://smarttrack.bccbsis.com/trackingv2/trackingv2/test_production_setup.php
 */

// Optional: Add IP restriction for production (uncomment and add your IP)
// $allowedIPs = ['YOUR_IP_ADDRESS_HERE'];
// if (!in_array($_SERVER['REMOTE_ADDR'], $allowedIPs)) {
//     http_response_code(403);
//     die('Access denied. This script is restricted to specific IP addresses.');
// }

// Optional: Add simple password protection (uncomment and set password)
// $testPassword = 'your_test_password_here';
// if (!isset($_GET['key']) || $_GET['key'] !== $testPassword) {
//     http_response_code(403);
//     die('Access denied. Provide ?key=your_test_password_here');
// }

// Load environment variables
require_once __DIR__ . '/includes/env_loader.php';
$envLoaded = loadEnv(__DIR__ . '/.env');

header('Content-Type: text/html; charset=utf-8');
?>
<!DOCTYPE html>
<html>
<head>
    <title>Production Setup Verification</title>
    <style>
        body { font-family: Arial, sans-serif; margin: 20px; background: #f5f5f5; }
        .container { max-width: 800px; margin: 0 auto; background: white; padding: 20px; border-radius: 8px; box-shadow: 0 2px 4px rgba(0,0,0,0.1); }
        h1 { color: #333; border-bottom: 2px solid #4CAF50; padding-bottom: 10px; }
        .test-item { margin: 15px 0; padding: 15px; border-left: 4px solid #ddd; background: #f9f9f9; }
        .pass { border-left-color: #4CAF50; background: #e8f5e9; }
        .fail { border-left-color: #f44336; background: #ffebee; }
        .warning { border-left-color: #ff9800; background: #fff3e0; }
        .status { font-weight: bold; margin-right: 10px; }
        .pass .status { color: #4CAF50; }
        .fail .status { color: #f44336; }
        .warning .status { color: #ff9800; }
        pre { background: #f5f5f5; padding: 10px; border-radius: 4px; overflow-x: auto; }
        .info { color: #666; font-size: 0.9em; margin-top: 5px; }
    </style>
</head>
<body>
    <div class="container">
        <h1>🔍 Production Setup Verification</h1>
        
        <?php
        $allPassed = true;
        
        // Test 1: .env file exists
        echo '<div class="test-item ' . ($envLoaded ? 'pass' : 'fail') . '">';
        echo '<span class="status">' . ($envLoaded ? '✅' : '❌') . '</span>';
        echo '<strong>Test 1: .env File Exists</strong><br>';
        if ($envLoaded) {
            echo '<span class="info">✅ .env file found and loaded successfully</span>';
        } else {
            echo '<span class="info">❌ .env file not found. Please create it.</span>';
            $allPassed = false;
        }
        echo '</div>';
        
        // Test 2: Required environment variables
        $requiredVars = ['DB_HOST', 'DB_NAME', 'DB_USER', 'DB_PASS', 'ENVIRONMENT', 'BASE_URL'];
        $missingVars = [];
        foreach ($requiredVars as $var) {
            if (!defined($var) || empty(constant($var))) {
                $missingVars[] = $var;
            }
        }
        
        echo '<div class="test-item ' . (empty($missingVars) ? 'pass' : 'fail') . '">';
        echo '<span class="status">' . (empty($missingVars) ? '✅' : '❌') . '</span>';
        echo '<strong>Test 2: Required Environment Variables</strong><br>';
        if (empty($missingVars)) {
            echo '<span class="info">✅ All required variables are set</span>';
            echo '<pre>';
            foreach ($requiredVars as $var) {
                $value = constant($var);
                if ($var === 'DB_PASS') {
                    $value = str_repeat('*', strlen($value)); // Hide password
                }
                echo "$var = $value\n";
            }
            echo '</pre>';
        } else {
            echo '<span class="info">❌ Missing variables: ' . implode(', ', $missingVars) . '</span>';
            $allPassed = false;
        }
        echo '</div>';
        
        // Test 3: Database Connection
        if (defined('DB_HOST') && defined('DB_NAME') && defined('DB_USER') && defined('DB_PASS')) {
            $conn = @new mysqli(DB_HOST, DB_USER, DB_PASS, DB_NAME);
            
            if ($conn->connect_error) {
                echo '<div class="test-item fail">';
                echo '<span class="status">❌</span>';
                echo '<strong>Test 3: Database Connection</strong><br>';
                echo '<span class="info">❌ Connection failed: ' . htmlspecialchars($conn->connect_error) . '</span>';
                $allPassed = false;
            } else {
                echo '<div class="test-item pass">';
                echo '<span class="status">✅</span>';
                echo '<strong>Test 3: Database Connection</strong><br>';
                echo '<span class="info">✅ Successfully connected to database</span>';
                
                // Test query
                $result = $conn->query("SELECT 1 as test");
                if ($result) {
                    echo '<span class="info">✅ Test query executed successfully</span>';
                } else {
                    echo '<span class="info">⚠️ Connection OK but query failed</span>';
                }
                
                $conn->close();
            }
            echo '</div>';
        } else {
            echo '<div class="test-item fail">';
            echo '<span class="status">❌</span>';
            echo '<strong>Test 3: Database Connection</strong><br>';
            echo '<span class="info">❌ Cannot test - database credentials not set</span>';
            $allPassed = false;
            echo '</div>';
        }
        
        // Test 4: CORS Configuration
        if (defined('CORS_ALLOWED_ORIGINS')) {
            echo '<div class="test-item pass">';
            echo '<span class="status">✅</span>';
            echo '<strong>Test 4: CORS Configuration</strong><br>';
            echo '<span class="info">✅ CORS_ALLOWED_ORIGINS is set</span>';
            echo '<pre>CORS_ALLOWED_ORIGINS = ' . htmlspecialchars(CORS_ALLOWED_ORIGINS) . '</pre>';
        } else {
            echo '<div class="test-item warning">';
            echo '<span class="status">⚠️</span>';
            echo '<strong>Test 4: CORS Configuration</strong><br>';
            echo '<span class="info">⚠️ CORS_ALLOWED_ORIGINS not set (will use defaults)</span>';
        }
        echo '</div>';
        
        // Test 5: Environment Setting
        $env = defined('ENVIRONMENT') ? ENVIRONMENT : 'not set';
        echo '<div class="test-item ' . ($env === 'production' ? 'pass' : 'warning') . '">';
        echo '<span class="status">' . ($env === 'production' ? '✅' : '⚠️') . '</span>';
        echo '<strong>Test 5: Environment Setting</strong><br>';
        echo '<span class="info">Current environment: <strong>' . htmlspecialchars($env) . '</strong></span>';
        if ($env !== 'production') {
            echo '<span class="info"><br>⚠️ Not set to production. For production deployment, set ENVIRONMENT=production</span>';
        }
        echo '</div>';
        
        // Test 6: Base URL
        if (defined('BASE_URL')) {
            echo '<div class="test-item pass">';
            echo '<span class="status">✅</span>';
            echo '<strong>Test 6: Base URL Configuration</strong><br>';
            echo '<span class="info">✅ BASE_URL is set</span>';
            echo '<pre>BASE_URL = ' . htmlspecialchars(BASE_URL) . '</pre>';
        } else {
            echo '<div class="test-item warning">';
            echo '<span class="status">⚠️</span>';
            echo '<strong>Test 6: Base URL Configuration</strong><br>';
            echo '<span class="info">⚠️ BASE_URL not set</span>';
        }
        echo '</div>';
        
        // Summary
        echo '<div class="test-item ' . ($allPassed ? 'pass' : 'fail') . '" style="margin-top: 30px; padding: 20px; font-size: 1.1em;">';
        echo '<strong>Summary:</strong><br>';
        if ($allPassed) {
            echo '<span class="info">✅ All critical tests passed! Your production setup looks good.</span>';
        } else {
            echo '<span class="info">❌ Some tests failed. Please fix the issues above before deployment.</span>';
        }
        echo '</div>';
        
        // Security Warning
        echo '<div class="test-item warning" style="margin-top: 20px;">';
        echo '<strong>⚠️ Security Reminder:</strong><br>';
        echo '<span class="info">';
        echo '1. Delete this file (test_production_setup.php) after testing<br>';
        echo '2. Or add IP restriction/password protection (see file comments)<br>';
        echo '3. Never leave this file accessible in production without protection<br>';
        echo '4. Current access: ' . htmlspecialchars($_SERVER['REMOTE_ADDR']) . ' (' . htmlspecialchars($_SERVER['HTTP_HOST'] ?? 'unknown') . ')';
        echo '</span>';
        echo '</div>';
        ?>
    </div>
</body>
</html>

