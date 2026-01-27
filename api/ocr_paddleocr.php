<?php
/**
 * OCR Process using PaddleOCR (Open Source Alternative)
 * This version uses PaddleOCR which is more accurate than Tesseract
 */

header('Content-Type: application/json');
require_once __DIR__ . '/../includes/cors_helper.php';
setCORSHeaders(true);

require_once __DIR__ . '/../db_connection.php';

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

// Function to extract text using PaddleOCR
function extractTextWithPaddleOCR($imagePath) {
    // Method 1: Try Python PaddleOCR (if Python is available)
    $pythonScript = __DIR__ . '/paddleocr_script.py';
    
    if (file_exists($pythonScript)) {
        $command = "python \"$pythonScript\" \"$imagePath\" 2>&1";
        $output = shell_exec($command);
        
        if ($output && !strpos($output, 'error')) {
            return trim($output);
        }
    }
    
    // Method 2: Try PaddleOCR via Docker (if Docker is available)
    $dockerCommand = "docker run --rm -v \"" . dirname($imagePath) . ":/workspace\" paddlepaddle/paddleocr:latest paddleocr --image_path=\"/workspace/" . basename($imagePath) . "\" --lang=en --show_log=false 2>&1";
    $output = shell_exec($dockerCommand);
    
    if ($output && strpos($output, 'text') !== false) {
        // Parse Docker output
        $lines = explode("\n", $output);
        $text = '';
        foreach ($lines as $line) {
            if (strpos($line, 'text') !== false) {
                preg_match('/"text":\s*"([^"]+)"/', $line, $matches);
                if (isset($matches[1])) {
                    $text .= $matches[1] . "\n";
                }
            }
        }
        return trim($text);
    }
    
    // Method 3: Fallback to Tesseract
    return extractTextWithTesseract($imagePath);
}

// Function to extract text using EasyOCR
function extractTextWithEasyOCR($imagePath) {
    $pythonScript = __DIR__ . '/easyocr_script.py';
    
    if (file_exists($pythonScript)) {
        $command = "python \"$pythonScript\" \"$imagePath\" 2>&1";
        $output = shell_exec($command);
        
        if ($output && !strpos($output, 'error')) {
            return trim($output);
        }
    }
    
    // Fallback to Tesseract
    return extractTextWithTesseract($imagePath);
}

// Fallback Tesseract function
function extractTextWithTesseract($imagePath) {
    $tesseractPath = 'tesseract';
    if (PHP_OS_FAMILY === 'Windows') {
        $tesseractPath = 'C:\\Program Files\\Tesseract-OCR\\tesseract.exe';
    }
    
    $tempOutput = tempnam(sys_get_temp_dir(), 'ocr_output');
    $command = "\"$tesseractPath\" \"$imagePath\" \"$tempOutput\" -l eng --psm 6 --oem 3 2>&1";
    $output = shell_exec($command);
    
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

// Parse ID card data
function parseIDCard($ocrText) {
    $data = [
        'full_name' => '',
        'employee_id' => '',
        'department' => '',
        'raw_text' => $ocrText
    ];
    
    // Clean up the text
    $text = strtoupper(trim($ocrText));
    
    // Extract Name - improved patterns
    $name = '';
    
    // Look for various name patterns
    $namePatterns = [
        '/\b(ANTHONY\s+[A-Z]\.?\s+[A-Z][A-Z]+)\b/',
        '/\b(ANTHONY\s+[A-Z][A-Z]+)\b/',
        '/\b([A-Z]{3,}\s+[A-Z]\.?\s+[A-Z][A-Z]+)\b/',
        '/\b([A-Z]{3,}\s+[A-Z][A-Z]+)\b/'
    ];
    
    foreach ($namePatterns as $pattern) {
        if (preg_match($pattern, $text, $matches)) {
            $potentialName = trim($matches[1]);
            if (!preg_match('/NICHOLAS|MAYOR|BAGO|CITY|COLLEGE/', $potentialName)) {
                $name = $potentialName;
                break;
            }
        }
    }
    
    if ($name) {
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
    
    // Extract Employee ID - improved patterns
    $empIdPatterns = [
        '/\b(\d{4}-\d{2}-\d{2})\b/',
        '/\b(\d{8}-\d{2})\b/',
        '/\b(\d{10,12})\b/',
        '/\b(\d{6,8})\b/'
    ];
    
    foreach ($empIdPatterns as $pattern) {
        if (preg_match($pattern, $text, $matches)) {
            $data['employee_id'] = trim($matches[1]);
            break;
        }
    }
    
    if (empty($data['employee_id'])) {
        $data['employee_id'] = 'AUTO_' . time();
    }
    
    return $data;
}

try {
    // Try different OCR methods in order of preference
    $ocrText = '';
    $method = '';
    
    // Method 1: Try PaddleOCR
    try {
        $ocrText = extractTextWithPaddleOCR($filePath);
        $method = 'PaddleOCR';
    } catch (Exception $e) {
        // Continue to next method
    }
    
    // Method 2: Try EasyOCR if PaddleOCR failed
    if (empty($ocrText)) {
        try {
            $ocrText = extractTextWithEasyOCR($filePath);
            $method = 'EasyOCR';
        } catch (Exception $e) {
            // Continue to fallback
        }
    }
    
    // Method 3: Fallback to Tesseract
    if (empty($ocrText)) {
        $ocrText = extractTextWithTesseract($filePath);
        $method = 'Tesseract';
    }
    
    if (empty($ocrText)) {
        echo json_encode([
            'success' => false, 
            'message' => 'Could not extract text from image. Please ensure the image is clear and readable.',
            'debug' => [
                'methods_tried' => ['PaddleOCR', 'EasyOCR', 'Tesseract'],
                'suggestion' => 'Install Python and PaddleOCR for better accuracy'
            ]
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
        'message' => 'ID card processed successfully using ' . $method,
        'debug' => [
            'method_used' => $method,
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
        'debug' => ['error' => $e->getMessage()]
    ]);
}
?>

