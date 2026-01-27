<?php
// Simple server-side geocoding proxy using multiple Nominatim mirrors with timeouts
header('Content-Type: application/json');

$q = isset($_GET['q']) ? trim($_GET['q']) : '';
$limit = isset($_GET['limit']) ? intval($_GET['limit']) : 5;
if ($limit <= 0 || $limit > 10) { $limit = 5; }

if ($q === '') {
    http_response_code(400);
    echo json_encode(['error' => 'Missing query']);
    exit;
}

// Build URLs (Philippines-bounded and broader as fallback)
$qEnc = urlencode($q);
$urls = [
    "https://nominatim.openstreetmap.org/search?format=json&q={$qEnc}&limit={$limit}&addressdetails=1&viewbox=116.0,4.0,127.0,21.0&bounded=1",
    "https://nominatim.metalinker.net/search?format=json&q={$qEnc}&limit={$limit}&addressdetails=1&viewbox=116.0,4.0,127.0,21.0&bounded=1",
    // Broader search as last resort
    "https://nominatim.openstreetmap.org/search?format=json&q={$qEnc}&limit={$limit}&addressdetails=1"
];

function http_get_json($url, $timeout = 6) {
    $ch = curl_init();
    curl_setopt_array($ch, [
        CURLOPT_URL => $url,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT => $timeout,
        CURLOPT_CONNECTTIMEOUT => $timeout,
        CURLOPT_HTTPHEADER => [
            'Accept: application/json',
            'User-Agent: SmartTrack-Geocoder/1.0'
        ]
    ]);
    $resp = curl_exec($ch);
    $status = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $err = curl_error($ch);
    curl_close($ch);
    if ($resp === false || $status < 200 || $status >= 300) {
        return null;
    }
    $json = json_decode($resp, true);
    return $json;
}

// Try each URL with retries
$result = [];
foreach ($urls as $url) {
    $json = http_get_json($url, 6);
    if (is_array($json) && count($json) > 0) {
        $result = $json;
        break;
    }
}

if (empty($result)) {
    http_response_code(502);
    echo json_encode(['error' => 'Geocoding services unavailable']);
    exit;
}

echo json_encode($result);
exit;
?>


