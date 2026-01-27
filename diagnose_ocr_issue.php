<?php
/**
 * OCR Upload Diagnostic Tool
 * This script will help identify why OCR upload is failing
 */

header('Content-Type: text/html; charset=utf-8');
?>
<!DOCTYPE html>
<html>
<head>
    <title>OCR Upload Diagnostic</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css" rel="stylesheet">
    <style>
        body { padding: 30px; background: #f8f9fa; }
        .diagnostic-card { margin-bottom: 20px; }
        .status-ok { color: #28a745; }
        .status-warning { color: #ffc107; }
        .status-error { color: #dc3545; }
        .code-block { background: #2d2d2d; color: #f8f8f2; padding: 15px; border-radius: 5px; overflow-x: auto; }
    </style>
</head>
<body>
    <div class="container">
        <h1 class="mb-4"><i class="fas fa-stethoscope"></i> OCR Upload Diagnostic Tool</h1>
        
        <?php
        $issues = [];
        $warnings = [];
        $success = [];
        
        // 1. Check PHP Upload Settings
        echo '<div class="card diagnostic-card">';
        echo '<div class="card-header"><h5><i class="fas fa-upload"></i> PHP Upload Configuration</h5></div>';
        echo '<div class="card-body">';
        
        $uploadMaxFilesize = ini_get('upload_max_filesize');
        $postMaxSize = ini_get('post_max_size');
        $fileUploads = ini_get('file_uploads');
        
        echo '<table class="table table-sm">';
        echo '<tr><td><strong>file_uploads</strong></td><td>' . ($fileUploads ? '<span class="status-ok">✓ Enabled</span>' : '<span class="status-error">✗ Disabled</span>') . '</td></tr>';
        echo '<tr><td><strong>upload_max_filesize</strong></td><td>' . $uploadMaxFilesize . '</td></tr>';
        echo '<tr><td><strong>post_max_size</strong></td><td>' . $postMaxSize . '</td></tr>';
        echo '<tr><td><strong>max_file_uploads</strong></td><td>' . ini_get('max_file_uploads') . '</td></tr>';
        echo '</table>';
        
        if (!$fileUploads) {
            $issues[] = 'File uploads are disabled in php.ini';
        } else {
            $success[] = 'PHP file uploads are enabled';
        }
        
        echo '</div></div>';
        
        // 2. Check Uploads Directory
        echo '<div class="card diagnostic-card">';
        echo '<div class="card-header"><h5><i class="fas fa-folder"></i> Uploads Directory</h5></div>';
        echo '<div class="card-body">';
        
        $uploadDir = __DIR__ . '/uploads/id_cards/';
        $uploadsExists = is_dir($uploadDir);
        $uploadsWritable = is_writable(dirname($uploadDir));
        
        echo '<table class="table table-sm">';
        echo '<tr><td><strong>Directory Path</strong></td><td>' . htmlspecialchars($uploadDir) . '</td></tr>';
        echo '<tr><td><strong>Directory Exists</strong></td><td>' . ($uploadsExists ? '<span class="status-ok">✓ Yes</span>' : '<span class="status-warning">⚠ No</span>') . '</td></tr>';
        echo '<tr><td><strong>Parent Writable</strong></td><td>' . ($uploadsWritable ? '<span class="status-ok">✓ Yes</span>' : '<span class="status-error">✗ No</span>') . '</td></tr>';
        echo '</table>';
        
        if (!$uploadsExists) {
            echo '<div class="alert alert-warning mt-2">';
            echo '<i class="fas fa-info-circle"></i> Directory doesn\'t exist yet. Attempting to create it...';
            if (mkdir($uploadDir, 0777, true)) {
                echo '<div class="mt-2 alert alert-success">✓ Successfully created directory!</div>';
                $success[] = 'Created uploads directory';
            } else {
                echo '<div class="mt-2 alert alert-danger">✗ Failed to create directory. Check permissions.</div>';
                $issues[] = 'Cannot create uploads directory - check folder permissions';
            }
            echo '</div>';
        } else {
            $success[] = 'Uploads directory exists';
            
            // Check if writable
            if (is_writable($uploadDir)) {
                $success[] = 'Uploads directory is writable';
            } else {
                $issues[] = 'Uploads directory exists but is not writable';
            }
        }
        
        echo '</div></div>';
        
        // 3. Check Tesseract OCR
        echo '<div class="card diagnostic-card">';
        echo '<div class="card-header"><h5><i class="fas fa-robot"></i> Tesseract OCR</h5></div>';
        echo '<div class="card-body">';
        
        $tesseractPath = 'tesseract';
        if (PHP_OS_FAMILY === 'Windows') {
            $tesseractPath = 'C:\\Program Files\\Tesseract-OCR\\tesseract.exe';
        }
        
        echo '<table class="table table-sm">';
        echo '<tr><td><strong>Expected Path</strong></td><td>' . htmlspecialchars($tesseractPath) . '</td></tr>';
        echo '<tr><td><strong>File Exists</strong></td><td>';
        
        $tesseractExists = file_exists($tesseractPath);
        if ($tesseractExists) {
            echo '<span class="status-ok">✓ Yes</span></td></tr>';
            $success[] = 'Tesseract executable found';
        } else {
            echo '<span class="status-error">✗ No</span></td></tr>';
            $issues[] = 'Tesseract OCR is not installed at the expected location';
        }
        
        echo '</table>';
        
        // Try to run tesseract version command
        $output = shell_exec("\"$tesseractPath\" --version 2>&1");
        if ($output) {
            echo '<div class="alert alert-success mt-2">';
            echo '<strong><i class="fas fa-check-circle"></i> Tesseract is working!</strong><br>';
            echo '<pre class="mb-0 mt-2" style="font-size: 0.85rem;">' . htmlspecialchars($output) . '</pre>';
            echo '</div>';
            $success[] = 'Tesseract is functional';
        } else {
            echo '<div class="alert alert-danger mt-2">';
            echo '<strong><i class="fas fa-times-circle"></i> Tesseract is not responding</strong><br>';
            echo 'Command output: ' . htmlspecialchars($output ?: 'No output');
            echo '</div>';
            $issues[] = 'Tesseract is not responding to commands';
        }
        
        echo '</div></div>';
        
        // 4. Check OCR API File
        echo '<div class="card diagnostic-card">';
        echo '<div class="card-header"><h5><i class="fas fa-code"></i> OCR API File</h5></div>';
        echo '<div class="card-body">';
        
        $ocrApiPath = __DIR__ . '/api/ocr_process.php';
        $ocrApiExists = file_exists($ocrApiPath);
        
        echo '<table class="table table-sm">';
        echo '<tr><td><strong>API File Path</strong></td><td>' . htmlspecialchars($ocrApiPath) . '</td></tr>';
        echo '<tr><td><strong>File Exists</strong></td><td>' . ($ocrApiExists ? '<span class="status-ok">✓ Yes</span>' : '<span class="status-error">✗ No</span>') . '</td></tr>';
        echo '</table>';
        
        if ($ocrApiExists) {
            $success[] = 'OCR API file exists';
        } else {
            $issues[] = 'OCR API file is missing';
        }
        
        echo '</div></div>';
        
        // 5. Check Database Connection
        echo '<div class="card diagnostic-card">';
        echo '<div class="card-header"><h5><i class="fas fa-database"></i> Database Connection</h5></div>';
        echo '<div class="card-body">';
        
        try {
            require_once 'db_connection.php';
            if ($conn && !$conn->connect_error) {
                echo '<div class="alert alert-success"><i class="fas fa-check-circle"></i> Database connection successful</div>';
                $success[] = 'Database connection is working';
                
                // Check for OCR columns - use prepared statement for consistency
                $column_check_stmt = $conn->prepare("SHOW COLUMNS FROM reservation_users LIKE 'employee_id'");
                $column_check_stmt->execute();
                $result = $column_check_stmt->get_result();
                if ($result && $result->num_rows > 0) {
                    $column_check_stmt->close();
                    echo '<div class="alert alert-success"><i class="fas fa-check-circle"></i> OCR columns exist in database</div>';
                    $success[] = 'OCR database columns are present';
                } else {
                    echo '<div class="alert alert-danger"><i class="fas fa-times-circle"></i> OCR columns missing in database</div>';
                    $issues[] = 'Database is missing OCR columns (employee_id, department)';
                    $column_check_stmt->close();
                }
            } else {
                echo '<div class="alert alert-danger"><i class="fas fa-times-circle"></i> Database connection failed</div>';
                $issues[] = 'Database connection failed';
            }
        } catch (Exception $e) {
            echo '<div class="alert alert-danger"><i class="fas fa-times-circle"></i> Database error: ' . htmlspecialchars($e->getMessage()) . '</div>';
            $issues[] = 'Database error: ' . $e->getMessage();
        }
        
        echo '</div></div>';
        
        // 6. Test Upload (if form submitted)
        if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_FILES['test_upload'])) {
            echo '<div class="card diagnostic-card">';
            echo '<div class="card-header"><h5><i class="fas fa-vial"></i> Test Upload Result</h5></div>';
            echo '<div class="card-body">';
            
            if ($_FILES['test_upload']['error'] === UPLOAD_ERR_OK) {
                echo '<div class="alert alert-success"><i class="fas fa-check-circle"></i> File uploaded successfully to PHP</div>';
                echo '<p><strong>File Details:</strong></p>';
                echo '<ul>';
                echo '<li>Name: ' . htmlspecialchars($_FILES['test_upload']['name']) . '</li>';
                echo '<li>Type: ' . htmlspecialchars($_FILES['test_upload']['type']) . '</li>';
                echo '<li>Size: ' . number_format($_FILES['test_upload']['size'] / 1024, 2) . ' KB</li>';
                echo '<li>Temp Path: ' . htmlspecialchars($_FILES['test_upload']['tmp_name']) . '</li>';
                echo '</ul>';
                
                // Try to move the file
                $testUploadDir = __DIR__ . '/uploads/id_cards/';
                if (!is_dir($testUploadDir)) {
                    mkdir($testUploadDir, 0777, true);
                }
                
                $testFileName = 'test_' . time() . '_' . basename($_FILES['test_upload']['name']);
                $testFilePath = $testUploadDir . $testFileName;
                
                if (move_uploaded_file($_FILES['test_upload']['tmp_name'], $testFilePath)) {
                    echo '<div class="alert alert-success mt-2"><i class="fas fa-check-circle"></i> File successfully moved to: ' . htmlspecialchars($testFilePath) . '</div>';
                    
                    // Clean up test file
                    unlink($testFilePath);
                } else {
                    echo '<div class="alert alert-danger mt-2"><i class="fas fa-times-circle"></i> Failed to move uploaded file</div>';
                }
            } else {
                $errorMessages = [
                    UPLOAD_ERR_INI_SIZE => 'File exceeds upload_max_filesize',
                    UPLOAD_ERR_FORM_SIZE => 'File exceeds MAX_FILE_SIZE',
                    UPLOAD_ERR_PARTIAL => 'File was only partially uploaded',
                    UPLOAD_ERR_NO_FILE => 'No file was uploaded',
                    UPLOAD_ERR_NO_TMP_DIR => 'Missing temporary folder',
                    UPLOAD_ERR_CANT_WRITE => 'Failed to write file to disk',
                    UPLOAD_ERR_EXTENSION => 'PHP extension stopped the upload'
                ];
                
                $errorCode = $_FILES['test_upload']['error'];
                $errorMessage = $errorMessages[$errorCode] ?? 'Unknown error';
                
                echo '<div class="alert alert-danger"><i class="fas fa-times-circle"></i> Upload Error: ' . htmlspecialchars($errorMessage) . ' (Code: ' . $errorCode . ')</div>';
            }
            
            echo '</div></div>';
        }
        
        // Summary
        echo '<div class="card diagnostic-card">';
        echo '<div class="card-header"><h5><i class="fas fa-clipboard-list"></i> Diagnostic Summary</h5></div>';
        echo '<div class="card-body">';
        
        if (count($issues) === 0) {
            echo '<div class="alert alert-success"><h5><i class="fas fa-check-circle"></i> All Checks Passed!</h5>';
            echo '<p>Your OCR upload system should be working correctly.</p>';
            echo '<p><strong>Successful Checks:</strong></p><ul>';
            foreach ($success as $item) {
                echo '<li>' . htmlspecialchars($item) . '</li>';
            }
            echo '</ul></div>';
        } else {
            echo '<div class="alert alert-danger"><h5><i class="fas fa-exclamation-triangle"></i> Issues Found</h5>';
            echo '<p>Please fix the following issues:</p><ol>';
            foreach ($issues as $issue) {
                echo '<li>' . htmlspecialchars($issue) . '</li>';
            }
            echo '</ol></div>';
            
            if (count($success) > 0) {
                echo '<div class="alert alert-info mt-3"><p><strong>Working Components:</strong></p><ul>';
                foreach ($success as $item) {
                    echo '<li>' . htmlspecialchars($item) . '</li>';
                }
                echo '</ul></div>';
            }
        }
        
        echo '</div></div>';
        
        // Recommended Solutions
        if (count($issues) > 0) {
            echo '<div class="card diagnostic-card">';
            echo '<div class="card-header"><h5><i class="fas fa-wrench"></i> Recommended Solutions</h5></div>';
            echo '<div class="card-body">';
            
            foreach ($issues as $issue) {
                if (strpos($issue, 'Tesseract') !== false) {
                    echo '<div class="alert alert-warning">';
                    echo '<h6><i class="fas fa-download"></i> Install Tesseract OCR</h6>';
                    echo '<p><strong>For Windows:</strong></p>';
                    echo '<ol>';
                    echo '<li>Download from: <a href="https://github.com/UB-Mannheim/tesseract/wiki" target="_blank">https://github.com/UB-Mannheim/tesseract/wiki</a></li>';
                    echo '<li>Install to: <code>C:\\Program Files\\Tesseract-OCR\\</code></li>';
                    echo '<li>Restart your web server (Apache/XAMPP)</li>';
                    echo '</ol>';
                    echo '</div>';
                }
                
                if (strpos($issue, 'directory') !== false) {
                    echo '<div class="alert alert-warning">';
                    echo '<h6><i class="fas fa-folder-plus"></i> Fix Directory Permissions</h6>';
                    echo '<p>Run this command in your terminal:</p>';
                    echo '<div class="code-block">mkdir -p ' . htmlspecialchars(__DIR__ . '/uploads/id_cards/') . '<br>chmod -R 777 ' . htmlspecialchars(__DIR__ . '/uploads/') . '</div>';
                    echo '</div>';
                }
                
                if (strpos($issue, 'OCR columns') !== false) {
                    echo '<div class="alert alert-warning">';
                    echo '<h6><i class="fas fa-database"></i> Add OCR Columns to Database</h6>';
                    echo '<p>Run this SQL command:</p>';
                    echo '<div class="code-block">';
                    echo 'ALTER TABLE reservation_users ADD COLUMN IF NOT EXISTS employee_id VARCHAR(50);<br>';
                    echo 'ALTER TABLE reservation_users ADD COLUMN IF NOT EXISTS department VARCHAR(100);';
                    echo '</div>';
                    echo '<p>Or import the file: <code>add_ocr_columns.sql</code></p>';
                    echo '</div>';
                }
            }
            
            echo '</div></div>';
        }
        
        // Test Upload Form
        echo '<div class="card diagnostic-card">';
        echo '<div class="card-header"><h5><i class="fas fa-flask"></i> Test File Upload</h5></div>';
        echo '<div class="card-body">';
        echo '<form method="POST" enctype="multipart/form-data">';
        echo '<div class="mb-3">';
        echo '<label for="test_upload" class="form-label">Select an image file to test upload:</label>';
        echo '<input type="file" class="form-control" id="test_upload" name="test_upload" accept="image/*" required>';
        echo '</div>';
        echo '<button type="submit" class="btn btn-primary"><i class="fas fa-upload"></i> Test Upload</button>';
        echo '</form>';
        echo '</div></div>';
        ?>
        
        <div class="text-center mt-4">
            <a href="register.php" class="btn btn-success"><i class="fas fa-arrow-right"></i> Go to Registration Page</a>
            <a href="debug_ocr.php" class="btn btn-info"><i class="fas fa-bug"></i> Test OCR Processing</a>
        </div>
    </div>
</body>
</html>


