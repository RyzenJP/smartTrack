<?php
// Get APK file information
// Try multiple possible paths
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
$file_exists = false;
$file_size = 0;
$file_size_mb = 0;

// Try each path until we find the file
foreach ($possible_paths as $path) {
    // Normalize path separators
    $path = str_replace(['/', '\\'], DIRECTORY_SEPARATOR, $path);
    
    if (@file_exists($path) && @is_readable($path)) {
        $apk_file = $path;
        $file_exists = true;
        break;
    }
}

// If still not found, use the default path (for display purposes)
if (!$file_exists) {
    $apk_file = __DIR__ . '/mobile_app/SmartTrack.apk';
}

if ($file_exists && $apk_file) {
    $file_size = @filesize($apk_file);
    if ($file_size !== false && $file_size > 0) {
        $file_size_mb = round($file_size / (1024 * 1024), 2);
    } else {
        // If filesize fails, file might not be readable
        $file_exists = false;
    }
}

// App information
$app_name = "Smart Track";
$app_version = "1.0.0";
$app_description = "Vehicle Tracking and Real-Time Location Monitoring System with Predictive Analytics";
$min_android = "Android 5.0 (API 21)";
$target_android = "Android 13+";
$package_name = "com.smarttrack.webapp";
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Download Smart Track Mobile App</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css" rel="stylesheet">
    <style>
        :root {
            --primary-blue: #003566;
            --accent-blue: #00b4d8;
            --light-blue: #0096c7;
            --dark-blue: #001d3d;
            --bg-light: #f7fafc;
        }

        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            background: linear-gradient(135deg, var(--primary-blue) 0%, var(--dark-blue) 100%);
            min-height: 100vh;
            padding: 20px;
        }

        .download-container {
            max-width: 900px;
            margin: 0 auto;
            background: white;
            border-radius: 20px;
            box-shadow: 0 20px 60px rgba(0, 0, 0, 0.3);
            overflow: hidden;
            animation: fadeInUp 0.6s ease-out;
        }

        @keyframes fadeInUp {
            from {
                opacity: 0;
                transform: translateY(30px);
            }
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }

        .app-header {
            background: linear-gradient(135deg, var(--primary-blue) 0%, var(--dark-blue) 100%);
            color: white;
            padding: 40px;
            text-align: center;
            position: relative;
            overflow: hidden;
        }

        .app-header::before {
            content: '';
            position: absolute;
            top: -50%;
            right: -50%;
            width: 200%;
            height: 200%;
            background: radial-gradient(circle, rgba(0, 180, 216, 0.2) 0%, transparent 70%);
            animation: pulse 3s ease-in-out infinite;
        }

        @keyframes pulse {
            0%, 100% { transform: scale(1); opacity: 0.5; }
            50% { transform: scale(1.1); opacity: 0.8; }
        }

        .app-logo {
            width: 120px;
            height: 120px;
            border-radius: 25px;
            border: 4px solid white;
            box-shadow: 0 10px 30px rgba(0, 0, 0, 0.3);
            margin: 0 auto 20px;
            object-fit: cover;
            position: relative;
            z-index: 1;
            animation: float 3s ease-in-out infinite;
        }

        @keyframes float {
            0%, 100% { transform: translateY(0px); }
            50% { transform: translateY(-10px); }
        }

        .app-header h1 {
            font-size: 2.5rem;
            font-weight: 700;
            margin-bottom: 10px;
            position: relative;
            z-index: 1;
        }

        .app-header p {
            font-size: 1.1rem;
            opacity: 0.9;
            position: relative;
            z-index: 1;
        }

        .app-content {
            padding: 40px;
        }

        .spec-card {
            background: var(--bg-light);
            border-radius: 15px;
            padding: 25px;
            margin-bottom: 20px;
            border-left: 4px solid var(--accent-blue);
            transition: all 0.3s ease;
        }

        .spec-card:hover {
            transform: translateX(5px);
            box-shadow: 0 5px 15px rgba(0, 0, 0, 0.1);
        }

        .spec-card h5 {
            color: var(--primary-blue);
            font-weight: 600;
            margin-bottom: 15px;
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .spec-card h5 i {
            color: var(--accent-blue);
            font-size: 1.3rem;
        }

        .spec-item {
            display: flex;
            justify-content: space-between;
            padding: 10px 0;
            border-bottom: 1px solid #e0e0e0;
        }

        .spec-item:last-child {
            border-bottom: none;
        }

        .spec-label {
            color: #666;
            font-weight: 500;
        }

        .spec-value {
            color: var(--primary-blue);
            font-weight: 600;
        }

        .download-section {
            text-align: center;
            padding: 30px;
            background: linear-gradient(135deg, var(--bg-light) 0%, #ffffff 100%);
            border-radius: 15px;
            margin-top: 30px;
        }

        .btn-download {
            background: linear-gradient(135deg, var(--accent-blue) 0%, var(--light-blue) 100%);
            color: white;
            padding: 18px 50px;
            font-size: 1.2rem;
            font-weight: 600;
            border-radius: 50px;
            border: none;
            text-decoration: none;
            display: inline-flex;
            align-items: center;
            gap: 12px;
            transition: all 0.3s ease;
            box-shadow: 0 10px 30px rgba(0, 180, 216, 0.4);
            position: relative;
            overflow: hidden;
        }

        .btn-download::before {
            content: '';
            position: absolute;
            top: 0;
            left: -100%;
            width: 100%;
            height: 100%;
            background: linear-gradient(90deg, transparent, rgba(255, 255, 255, 0.3), transparent);
            transition: left 0.5s ease;
        }

        .btn-download:hover::before {
            left: 100%;
        }

        .btn-download:hover {
            transform: translateY(-3px) scale(1.05);
            box-shadow: 0 15px 40px rgba(0, 180, 216, 0.6);
            color: white;
        }

        .btn-download:active {
            transform: translateY(-1px) scale(1.02);
        }

        .btn-download:disabled {
            opacity: 0.6;
            cursor: not-allowed;
            transform: none;
        }

        .file-info {
            margin-top: 20px;
            padding: 15px;
            background: white;
            border-radius: 10px;
            display: inline-block;
        }

        .file-info .badge {
            background: var(--accent-blue);
            color: white;
            padding: 8px 15px;
            border-radius: 20px;
            font-size: 0.9rem;
            margin: 5px;
        }

        .features-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 15px;
            margin-top: 20px;
        }

        .feature-item {
            background: white;
            padding: 15px;
            border-radius: 10px;
            text-align: center;
            border: 2px solid #e0e0e0;
            transition: all 0.3s ease;
        }

        .feature-item:hover {
            border-color: var(--accent-blue);
            transform: translateY(-5px);
            box-shadow: 0 5px 15px rgba(0, 180, 216, 0.2);
        }

        .feature-item i {
            font-size: 2rem;
            color: var(--accent-blue);
            margin-bottom: 10px;
        }

        .feature-item h6 {
            color: var(--primary-blue);
            font-weight: 600;
            margin-bottom: 5px;
        }

        .back-link {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            color: var(--accent-blue);
            text-decoration: none;
            margin-bottom: 20px;
            font-weight: 500;
            transition: all 0.3s ease;
        }

        .back-link:hover {
            color: var(--light-blue);
            transform: translateX(-5px);
        }

        .alert-custom {
            border-radius: 15px;
            padding: 20px;
            margin-bottom: 20px;
            border-left: 4px solid;
        }

        @media (max-width: 768px) {
            .app-header {
                padding: 30px 20px;
            }

            .app-header h1 {
                font-size: 2rem;
            }

            .app-logo {
                width: 100px;
                height: 100px;
            }

            .app-content {
                padding: 25px 20px;
            }

            .btn-download {
                padding: 15px 35px;
                font-size: 1.1rem;
            }

            .features-grid {
                grid-template-columns: 1fr;
            }
        }

        .loading-spinner {
            display: inline-block;
            width: 20px;
            height: 20px;
            border: 3px solid rgba(255, 255, 255, 0.3);
            border-radius: 50%;
            border-top-color: white;
            animation: spin 1s ease-in-out infinite;
        }

        @keyframes spin {
            to { transform: rotate(360deg); }
        }
    </style>
</head>
<body>
    <div class="download-container">
        <div class="app-header">
            <img src="images/bago_city.jpg" alt="Smart Track Logo" class="app-logo">
            <h1><?= htmlspecialchars($app_name) ?></h1>
            <p><?= htmlspecialchars($app_description) ?></p>
        </div>

        <div class="app-content">
            <a href="index.php" class="back-link">
                <i class="fas fa-arrow-left"></i> Back to Home
            </a>

            <?php if (!$file_exists): ?>
                <div class="alert alert-warning alert-custom">
                    <i class="fas fa-exclamation-triangle me-2"></i>
                    <strong>App Not Available:</strong> The mobile app file is currently not available. Please contact the administrator.
                </div>
            <?php endif; ?>

            <!-- App Specifications -->
            <div class="spec-card">
                <h5><i class="fas fa-info-circle"></i> App Information</h5>
                <div class="spec-item">
                    <span class="spec-label">App Name</span>
                    <span class="spec-value"><?= htmlspecialchars($app_name) ?></span>
                </div>
                <div class="spec-item">
                    <span class="spec-label">Version</span>
                    <span class="spec-value"><?= htmlspecialchars($app_version) ?></span>
                </div>
                <div class="spec-item">
                    <span class="spec-label">Package Name</span>
                    <span class="spec-value"><?= htmlspecialchars($package_name) ?></span>
                </div>
                <?php if ($file_exists): ?>
                <div class="spec-item">
                    <span class="spec-label">File Size</span>
                    <span class="spec-value"><?= $file_size_mb ?> MB (<?= number_format($file_size) ?> bytes)</span>
                </div>
                <?php endif; ?>
            </div>

            <!-- System Requirements -->
            <div class="spec-card">
                <h5><i class="fas fa-mobile-alt"></i> System Requirements</h5>
                <div class="spec-item">
                    <span class="spec-label">Minimum Android Version</span>
                    <span class="spec-value"><?= htmlspecialchars($min_android) ?></span>
                </div>
                <div class="spec-item">
                    <span class="spec-label">Target Android Version</span>
                    <span class="spec-value"><?= htmlspecialchars($target_android) ?></span>
                </div>
                <div class="spec-item">
                    <span class="spec-label">Required Permissions</span>
                    <span class="spec-value">Internet, Location</span>
                </div>
            </div>

            <!-- Features -->
            <div class="spec-card">
                <h5><i class="fas fa-star"></i> Key Features</h5>
                <div class="features-grid">
                    <div class="feature-item">
                        <i class="fas fa-map-marker-alt"></i>
                        <h6>Real-Time GPS</h6>
                        <small>Track vehicles live</small>
                    </div>
                    <div class="feature-item">
                        <i class="fas fa-route"></i>
                        <h6>Route Tracking</h6>
                        <small>Monitor routes</small>
                    </div>
                    <div class="feature-item">
                        <i class="fas fa-bell"></i>
                        <h6>Notifications</h6>
                        <small>Get alerts & updates</small>
                    </div>
                    <div class="feature-item">
                        <i class="fas fa-chart-line"></i>
                        <h6>Analytics</h6>
                        <small>View reports</small>
                    </div>
                </div>
            </div>

            <!-- Download Section -->
            <div class="download-section">
                <h3 class="mb-4" style="color: var(--primary-blue);">Download Smart Track Mobile App</h3>
                <p class="text-muted mb-4">Get the full Smart Track experience on your Android device</p>
                
                <?php if ($file_exists): ?>
                    <a href="download_app.php" class="btn-download" id="downloadBtn">
                        <i class="fas fa-download"></i>
                        <span>Download APK</span>
                    </a>
                    <div class="file-info mt-4">
                        <span class="badge"><i class="fas fa-file me-1"></i>APK File</span>
                        <span class="badge"><i class="fas fa-weight me-1"></i><?= $file_size_mb ?> MB</span>
                        <span class="badge"><i class="fas fa-mobile-alt me-1"></i>Android</span>
                    </div>
                <?php else: ?>
                    <button class="btn-download" disabled>
                        <i class="fas fa-times-circle"></i>
                        <span>Download Unavailable</span>
                    </button>
                <?php endif; ?>

                <div class="mt-4">
                    <small class="text-muted">
                        <i class="fas fa-shield-alt me-1"></i>
                        Safe and secure download. Scan with antivirus if needed.
                    </small>
                </div>
            </div>

            <!-- Installation Instructions -->
            <div class="spec-card mt-4">
                <h5><i class="fas fa-question-circle"></i> Installation Instructions</h5>
                <ol style="padding-left: 20px; color: #666; line-height: 2;">
                    <li>Download the APK file by clicking the download button above</li>
                    <li>On your Android device, go to <strong>Settings → Security</strong></li>
                    <li>Enable <strong>"Install from Unknown Sources"</strong> or <strong>"Install Unknown Apps"</strong></li>
                    <li>Open the downloaded APK file from your Downloads folder</li>
                    <li>Tap <strong>"Install"</strong> and wait for the installation to complete</li>
                    <li>Open the app and log in with your credentials</li>
                </ol>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        // Download button click handler
        const downloadBtn = document.getElementById('downloadBtn');
        if (downloadBtn) {
            downloadBtn.addEventListener('click', function(e) {
                const btn = this;
                const originalHTML = btn.innerHTML;
                
                // Show loading state
                btn.innerHTML = '<span class="loading-spinner"></span> <span>Preparing Download...</span>';
                btn.disabled = true;
                
                // Reset after 3 seconds if download doesn't start
                setTimeout(() => {
                    btn.innerHTML = originalHTML;
                    btn.disabled = false;
                }, 3000);
            });
        }

        // Smooth scroll animation
        document.addEventListener('DOMContentLoaded', function() {
            const cards = document.querySelectorAll('.spec-card, .download-section');
            cards.forEach((card, index) => {
                card.style.opacity = '0';
                card.style.transform = 'translateY(20px)';
                card.style.transition = 'opacity 0.6s ease, transform 0.6s ease';
                
                setTimeout(() => {
                    card.style.opacity = '1';
                    card.style.transform = 'translateY(0)';
                }, index * 100);
            });
        });
    </script>
</body>
</html>

