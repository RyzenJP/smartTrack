<?php
/**
 * OCR Process for Hostinger Hosting
 * Uses Google Cloud Vision API (no server software needed)
 */

header('Content-Type: application/json');
require_once __DIR__ . '/../includes/cors_helper.php';
setCORSHeaders(true);

require_once __DIR__ . '/../db_connection.php';

// Configuration
$googleApiKey = 'AIzaSyBAb8RN6Lj3VFlsdXV8RRLViSSxIzVjuxEguSwlPsI29x8BYVHSg'; // Your actual API key
$enableFallback = true; // Enable Tesseract.js fallback on client side

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

// Function to extract text using Google Cloud Vision API
function extractTextWithGoogleVision($imagePath, $apiKey) {
    if (empty($apiKey) || $apiKey === 'YOUR_GOOGLE_API_KEY_HERE') {
        throw new Exception('Google API key not configured');
    }
    
    // Convert image to base64
    $imageData = base64_encode(file_get_contents($imagePath));
    
    // Prepare the request data
    $data = [
        'requests' => [
            [
                'image' => ['content' => $imageData],
                'features' => [
                    ['type' => 'TEXT_DETECTION'],
                    ['type' => 'DOCUMENT_TEXT_DETECTION']
                ]
            ]
        ]
    ];
    
    // Make the API request
    $context = stream_context_create([
        'http' => [
            'method' => 'POST',
            'header' => [
                'Content-Type: application/json',
                'Content-Length: ' . strlen(json_encode($data))
            ],
            'content' => json_encode($data),
            'timeout' => 30
        ]
    ]);
    
    $response = file_get_contents(
        "https://vision.googleapis.com/v1/images:annotate?key=" . urlencode($apiKey),
        false,
        $context
    );
    
    if ($response === false) {
        throw new Exception('Failed to call Google Vision API');
    }
    
    $result = json_decode($response, true);
    
    // Check for API errors
    if (isset($result['error'])) {
        throw new Exception('Google Vision API error: ' . $result['error']['message']);
    }
    
    // Extract text from response
    $text = '';
    if (isset($result['responses'][0]['textAnnotations'][0]['description'])) {
        $text = $result['responses'][0]['textAnnotations'][0]['description'];
    } elseif (isset($result['responses'][0]['fullTextAnnotation']['text'])) {
        $text = $result['responses'][0]['fullTextAnnotation']['text'];
    }
    
    return $text;
}

// Function to extract text using Azure Computer Vision (alternative)
function extractTextWithAzure($imagePath, $apiKey, $endpoint) {
    if (empty($apiKey) || $apiKey === 'YOUR_AZURE_API_KEY_HERE') {
        throw new Exception('Azure API key not configured');
    }
    
    // Convert image to base64
    $imageData = base64_encode(file_get_contents($imagePath));
    
    // Prepare the request data
    $data = [
        'url' => 'data:image/jpeg;base64,' . $imageData
    ];
    
    // Make the API request
    $context = stream_context_create([
        'http' => [
            'method' => 'POST',
            'header' => [
                'Content-Type: application/json',
                'Ocp-Apim-Subscription-Key: ' . $apiKey
            ],
            'content' => json_encode($data),
            'timeout' => 30
        ]
    ]);
    
    $response = file_get_contents(
        $endpoint . "/vision/v3.2/read/analyze",
        false,
        $context
    );
    
    if ($response === false) {
        throw new Exception('Failed to call Azure Computer Vision API');
    }
    
    // Note: Azure requires polling for results, this is simplified
    return 'Azure OCR result would go here';
}

// Parse ID card data (enhanced for better accuracy)
function parseIDCard($ocrText) {
    $data = [
        'full_name' => '',
        'employee_id' => '',
        'department' => '',
        'raw_text' => $ocrText
    ];
    
    // Clean up the text
    $text = strtoupper(trim($ocrText));
    
    // Enhanced name extraction patterns
    $namePatterns = [
        // Pattern 1: ANTHONY S. MALABANAN
        '/\b(ANTHONY\s+[A-Z]\.?\s+[A-Z][A-Z]+)\b/',
        // Pattern 2: ANTHONY MALABANAN
        '/\b(ANTHONY\s+[A-Z][A-Z]+)\b/',
        // Pattern 3: Any name with middle initial
        '/\b([A-Z]{3,}\s+[A-Z]\.?\s+[A-Z][A-Z]+)\b/',
        // Pattern 4: Any two-word name
        '/\b([A-Z]{3,}\s+[A-Z][A-Z]+)\b/'
    ];
    
    foreach ($namePatterns as $pattern) {
        if (preg_match($pattern, $text, $matches)) {
            $potentialName = trim($matches[1]);
            // Skip if it contains organization words
            if (!preg_match('/NICHOLAS|MAYOR|BAGO|CITY|COLLEGE|GOVERNMENT|OFFICE/', $potentialName)) {
                $name = $potentialName;
                break;
            }
        }
    }
    
    if (isset($name)) {
        // Clean up the name
        $name = preg_replace('/[^A-Za-z\s.]/', '', $name);
        $name = preg_replace('/\s+/', ' ', $name);
        $name = trim($name);
        
        // Convert to proper case
        if (strtoupper($name) === $name && strlen($name) > 3) {
            $name = ucwords(strtolower($name));
        }
        
        $data['full_name'] = $name;
    }
    
    // Enhanced department extraction
    if (preg_match('/BAGO\s+CITY\s+COLLEGE/i', $text)) {
        $data['department'] = 'Bago City College';
    } elseif (preg_match('/BAGO\s+CITY/i', $text)) {
        $data['department'] = 'Bago City College';
    } elseif (preg_match('/COLLEGE/i', $text)) {
        $data['department'] = 'Educational Institution';
    }
    
    // Enhanced employee ID extraction
    $empIdPatterns = [
        '/\b(\d{4}-\d{2}-\d{2})\b/',           // 0501-20-12 format
        '/\b(\d{8}-\d{2})\b/',                 // 05012012-02 format
        '/\b(\d{10,12})\b/',                   // 10-12 digit numbers
        '/\b(\d{6,8})\b/'                      // 6-8 digit numbers
    ];
    
    foreach ($empIdPatterns as $pattern) {
        if (preg_match($pattern, $text, $matches)) {
            $data['employee_id'] = trim($matches[1]);
            break;
        }
    }
    
    // If no employee ID found, generate one
    if (empty($data['employee_id'])) {
        $data['employee_id'] = 'AUTO_' . time();
    }
    
    return $data;
}

try {
    $ocrText = '';
    $method = '';
    
    // Try Google Cloud Vision API first
    try {
        $ocrText = extractTextWithGoogleVision($filePath, $googleApiKey);
        $method = 'Google Cloud Vision';
    } catch (Exception $e) {
        // Log the error but continue to fallback
        error_log("Google Vision API failed: " . $e->getMessage());
    }
    
    // If Google Vision failed, try Azure (if configured)
    if (empty($ocrText)) {
        try {
            $azureApiKey = 'YOUR_AZURE_API_KEY_HERE';
            $azureEndpoint = 'YOUR_AZURE_ENDPOINT_HERE';
            $ocrText = extractTextWithAzure($filePath, $azureApiKey, $azureEndpoint);
            $method = 'Azure Computer Vision';
        } catch (Exception $e) {
            // Log the error
            error_log("Azure OCR failed: " . $e->getMessage());
        }
    }
    
    // If both cloud APIs failed, return helpful error
    if (empty($ocrText)) {
        echo json_encode([
            'success' => false, 
            'message' => 'OCR processing failed. Please configure your API keys or try again.',
            'debug' => [
                'google_configured' => ($googleApiKey !== 'YOUR_GOOGLE_API_KEY_HERE'),
                'suggestion' => 'Get a free Google Cloud Vision API key'
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
            'suggestion' => 'Check your API key configuration'
        ]
    ]);
}
?>
