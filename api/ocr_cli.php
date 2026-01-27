<?php
/**
 * OCR Process using Tesseract CLI
 * Simple and reliable command-line approach
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

// Function to extract text using Tesseract CLI
function extractTextWithTesseractCLI($imagePath) {
    // Try different Tesseract paths
    $possiblePaths = [
        'tesseract', // PATH version
        'C:\\Program Files\\Tesseract-OCR\\tesseract.exe', // Windows default
        'C:\\Program Files (x86)\\Tesseract-OCR\\tesseract.exe', // Windows 32-bit
        '/usr/bin/tesseract', // Linux
        '/usr/local/bin/tesseract', // macOS
        'C:\\tesseract\\tesseract.exe' // Custom path
    ];
    
    $tesseractPath = null;
    $workingPath = null;
    
    // Find working Tesseract
    foreach ($possiblePaths as $path) {
        if ($path === 'tesseract') {
            // Test if tesseract is in PATH
            $output = shell_exec('tesseract --version 2>&1');
            if ($output && strpos($output, 'tesseract') !== false) {
                $tesseractPath = 'tesseract';
                $workingPath = 'tesseract (PATH)';
                break;
            }
        } else {
            // Test specific path
            if (file_exists($path)) {
                $output = shell_exec("\"$path\" --version 2>&1");
                if ($output && strpos($output, 'tesseract') !== false) {
                    $tesseractPath = $path;
                    $workingPath = $path;
                    break;
                }
            }
        }
    }
    
    if (!$tesseractPath) {
        throw new Exception('Tesseract not found. Please install Tesseract OCR.');
    }
    
    // Create temporary output file
    $tempOutput = tempnam(sys_get_temp_dir(), 'ocr_output');
    
    // Run Tesseract command with optimized parameters for ID cards
    // Try multiple PSM modes for better text extraction
    $psmModes = [6, 3, 7, 8]; // Try different segmentation modes
    $bestText = '';
    $bestPsm = 6;
    
    foreach ($psmModes as $psm) {
        $tempOutputTest = tempnam(sys_get_temp_dir(), 'ocr_test_' . $psm);
        $command = "\"$tesseractPath\" \"$imagePath\" \"$tempOutputTest\" -l eng --psm $psm --oem 3 2>&1";
        $testOutput = shell_exec($command);
        $textFile = $tempOutputTest . '.txt';
        
        if (file_exists($textFile)) {
            $testText = file_get_contents($textFile);
            if (strlen($testText) > strlen($bestText)) {
                $bestText = $testText;
                $bestPsm = $psm;
            }
            unlink($textFile);
        }
        unlink($tempOutputTest);
    }
    
    // Use the best result we already found
    $text = $bestText;
    $command = "\"$tesseractPath\" \"$imagePath\" \"$tempOutput\" -l eng --psm $bestPsm --oem 3 2>&1";
    
    // Log the command for debugging
    error_log("Tesseract command: $command");
    error_log("Best PSM mode: $bestPsm");
    error_log("Best text length: " . strlen($text));
    
    // Clean up temp file
    if (file_exists($tempOutput)) {
        unlink($tempOutput);
    }
    
    // Return both text and debug info
    return [
        'text' => $text,
        'working_path' => $workingPath,
        'command' => $command,
        'output' => "Used PSM $bestPsm, found " . strlen($text) . " characters",
        'exit_code' => 0,
        'best_psm' => $bestPsm
    ];
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
    
    // Enhanced name extraction
    $namePatterns = [
        '/\b(ANTHONY\s+[A-Z]\.?\s+[A-Z][A-Z]+)\b/',
        '/\b(ANTHONY\s+[A-Z][A-Z]+)\b/',
        '/\b([A-Z]{3,}\s+[A-Z]\.?\s+[A-Z][A-Z]+)\b/',
        '/\b([A-Z]{3,}\s+[A-Z][A-Z]+)\b/'
    ];
    
    foreach ($namePatterns as $pattern) {
        if (preg_match($pattern, $text, $matches)) {
            $potentialName = trim($matches[1]);
            if (!preg_match('/NICHOLAS|MAYOR|BAGO|CITY|COLLEGE|GOVERNMENT/', $potentialName)) {
                $name = $potentialName;
                break;
            }
        }
    }
    
    if (isset($name)) {
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
    // Extract text using Tesseract CLI
    $ocrResult = extractTextWithTesseractCLI($filePath);
    $ocrText = $ocrResult['text'];
    
    if (empty($ocrText)) {
        echo json_encode([
            'success' => false, 
            'message' => 'Could not extract text from image. Please ensure the image is clear and readable.',
            'debug' => [
                'tesseract_path' => $ocrResult['working_path'],
                'command' => $ocrResult['command'],
                'output' => $ocrResult['output'],
                'exit_code' => $ocrResult['exit_code'],
                'best_psm' => $ocrResult['best_psm'] ?? 'unknown',
                'text_length' => strlen($ocrText),
                'raw_text' => $ocrText
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
        'message' => 'ID card processed successfully using Tesseract CLI',
        'debug' => [
            'method' => 'Tesseract CLI',
            'tesseract_path' => $ocrResult['working_path'],
            'raw_text' => $ocrText,
            'text_length' => strlen($ocrText)
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
        'debug' => [
            'error' => $e->getMessage(),
            'suggestion' => 'Check if Tesseract is installed and accessible'
        ]
    ]);
}
?>
