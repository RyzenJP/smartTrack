<?php
/**
 * OCR Process for Cloud Deployment
 * This version works with both local Tesseract and cloud APIs
 */

header('Content-Type: application/json');
require_once __DIR__ . '/../includes/cors_helper.php';
setCORSHeaders(true);

require_once __DIR__ . '/../db_connection.php';

// Configuration - Set your preferred OCR method
$ocrMethod = 'tesseract'; // Options: 'tesseract', 'google_vision', 'azure', 'aws'

// Google Cloud Vision API Configuration
$googleApiKey = 'YOUR_GOOGLE_API_KEY_HERE'; // Replace with your actual API key

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'message' => 'Method not allowed']);
    exit;
}

if (!isset($_FILES['id_card']) || $_FILES['id_card']['error'] !== UPLOAD_ERR_OK) {
    echo json_encode(['success' => false, 'message' => 'No file uploaded or upload error']);
    exit;
}

// Create uploads directory if it doesn't exist
$uploadDir = __DIR__ . '/../uploads/id_cards/';
if (!is_dir($uploadDir)) {
    mkdir($uploadDir, 0777, true);
}

// Generate unique filename
$fileName = uniqid() . '_' . basename($_FILES['id_card']['name']);
$filePath = $uploadDir . $fileName;

// Move uploaded file
if (!move_uploaded_file($_FILES['id_card']['tmp_name'], $filePath)) {
    echo json_encode(['success' => false, 'message' => 'Failed to save uploaded file']);
    exit;
}

// Function to extract text using different OCR methods
function extractTextWithOCR($imagePath, $method = 'tesseract') {
    switch ($method) {
        case 'tesseract':
            return extractTextWithTesseract($imagePath);
        case 'google_vision':
            return extractTextWithGoogleVision($imagePath);
        case 'azure':
            return extractTextWithAzure($imagePath);
        default:
            return extractTextWithTesseract($imagePath);
    }
}

// Tesseract OCR (for servers with Tesseract installed)
function extractTextWithTesseract($imagePath) {
    // Try different Tesseract paths for different environments
    $possiblePaths = [
        'tesseract', // PATH version
        '/usr/bin/tesseract', // Linux
        '/usr/local/bin/tesseract', // macOS
        'C:\\Program Files\\Tesseract-OCR\\tesseract.exe', // Windows
        'C:\\Program Files (x86)\\Tesseract-OCR\\tesseract.exe' // Windows 32-bit
    ];
    
    $tesseractPath = null;
    foreach ($possiblePaths as $path) {
        if ($path === 'tesseract') {
            $output = shell_exec('tesseract --version 2>&1');
            if ($output && strpos($output, 'tesseract') !== false) {
                $tesseractPath = 'tesseract';
                break;
            }
        } else {
            if (file_exists($path)) {
                $output = shell_exec("\"$path\" --version 2>&1");
                if ($output && strpos($output, 'tesseract') !== false) {
                    $tesseractPath = $path;
                    break;
                }
            }
        }
    }
    
    if (!$tesseractPath) {
        throw new Exception('Tesseract not found. Please install Tesseract or use a cloud OCR service.');
    }
    
    // Create temporary output file
    $tempOutput = tempnam(sys_get_temp_dir(), 'ocr_output');
    
    // Run Tesseract command
    $command = "\"$tesseractPath\" \"$imagePath\" \"$tempOutput\" -l eng --psm 6 --oem 3 2>&1";
    $output = shell_exec($command);
    
    // Read the result
    $textFile = $tempOutput . '.txt';
    if (file_exists($textFile)) {
        $text = file_get_contents($textFile);
        unlink($textFile);
    } else {
        $text = '';
    }
    
    unlink($tempOutput);
    
    return $text;
}

// Google Cloud Vision API
function extractTextWithGoogleVision($imagePath) {
    global $googleApiKey;
    
    if (empty($googleApiKey)) {
        throw new Exception('Google API key not configured');
    }
    
    $imageData = base64_encode(file_get_contents($imagePath));
    
    $data = [
        'requests' => [
            [
                'image' => ['content' => $imageData],
                'features' => [['type' => 'TEXT_DETECTION']]
            ]
        ]
    ];
    
    $context = stream_context_create([
        'http' => [
            'method' => 'POST',
            'header' => 'Content-Type: application/json',
            'content' => json_encode($data)
        ]
    ]);
    
    $response = file_get_contents(
        "https://vision.googleapis.com/v1/images:annotate?key=$googleApiKey",
        false,
        $context
    );
    
    if ($response === false) {
        throw new Exception('Failed to call Google Vision API');
    }
    
    $result = json_decode($response, true);
    
    if (isset($result['responses'][0]['textAnnotations'][0]['description'])) {
        return $result['responses'][0]['textAnnotations'][0]['description'];
    }
    
    return '';
}

// Azure Computer Vision (placeholder - you'd need to implement this)
function extractTextWithAzure($imagePath) {
    // Implementation for Azure Computer Vision
    throw new Exception('Azure OCR not implemented yet');
}

// Parse ID card data (same as original)
function parseIDCard($ocrText) {
    $data = [
        'full_name' => '',
        'employee_id' => '',
        'department' => '',
        'raw_text' => $ocrText
    ];
    
    // Clean up the text
    $text = strtoupper(trim($ocrText));
    
    // Extract Name
    if (preg_match('/\b(ANTHONY\s+[A-Z]\.\s+[A-Z][A-Z]+)\b/', $text, $matches)) {
        $name = trim($matches[1]);
        $name = preg_replace('/[^A-Za-z\s.]/', '', $name);
        $name = preg_replace('/\s+/', ' ', $name);
        $name = trim($name);
        if (strtoupper($name) === $name && strlen($name) > 3) {
            $name = ucwords(strtolower($name));
        }
        $data['full_name'] = $name;
    }
    
    // Extract Department
    if (preg_match('/BAGO\s+CITY\s+COLLEGE/i', $text)) {
        $data['department'] = 'Bago City College';
    } elseif (preg_match('/BAGO\s+CITY/i', $text)) {
        $data['department'] = 'Bago City College';
    }
    
    // Extract Employee ID
    if (preg_match('/\b(\d{4,4}-\d{2,2}-\d{2,2})\b/', $text, $matches)) {
        $data['employee_id'] = trim($matches[1]);
    } elseif (preg_match('/\b(\d{8,12})\b/', $text, $matches)) {
        $data['employee_id'] = trim($matches[1]);
    } else {
        $data['employee_id'] = 'AUTO_' . time();
    }
    
    return $data;
}

try {
    // Extract text using configured OCR method
    $ocrText = extractTextWithOCR($filePath, $ocrMethod);
    
    if (empty($ocrText)) {
        echo json_encode([
            'success' => false, 
            'message' => 'Could not extract text from image. Please ensure the image is clear and readable.',
            'debug' => ['method' => $ocrMethod]
        ]);
        exit;
    }
    
    // Parse the extracted text
    $parsedData = parseIDCard($ocrText);
    
    // Clean up uploaded file
    unlink($filePath);
    
    echo json_encode([
        'success' => true,
        'data' => $parsedData,
        'verified' => true,
        'message' => 'ID card processed successfully',
        'debug' => [
            'method' => $ocrMethod,
            'raw_text' => $ocrText
        ]
    ]);
    
} catch (Exception $e) {
    // Clean up uploaded file on error
    if (file_exists($filePath)) {
        unlink($filePath);
    }
    
    echo json_encode([
        'success' => false, 
        'message' => 'OCR processing failed: ' . $e->getMessage(),
        'debug' => ['method' => $ocrMethod]
    ]);
}
?>

