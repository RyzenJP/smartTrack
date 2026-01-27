<?php
header('Content-Type: application/json');

require_once __DIR__ . '/../db_connection.php';

// Get POST data
$data = json_decode(file_get_contents('php://input'), true);

if (!$data || empty($data['driver_id']) || empty($data['message'])) {
    echo json_encode(['success' => false, 'error' => 'Invalid input']);
    exit;
}

$driverId = intval($data['driver_id']);
$message = trim($data['message']);

// Get driver's phone number - use prepared statement for security
$phone_stmt = $conn->prepare("SELECT phone_number FROM user_table WHERE user_id = ?");
$phone_stmt->bind_param("i", $driverId);
$phone_stmt->execute();
$phone_result = $phone_stmt->get_result();
$phone = $phone_result->fetch_row()[0] ?? null;
$phone_stmt->close();

if (!$phone) {
    echo json_encode(['success' => false, 'error' => 'Driver phone not found']);
    exit;
}

// Save to database
$stmt = $conn->prepare("INSERT INTO driver_messages (driver_id, message_text, sent_at) VALUES (?, ?, NOW())");
$stmt->bind_param("is", $driverId, $message);

if (!$stmt->execute()) {
    echo json_encode(['success' => false, 'error' => 'Database error']);
    exit;
}

// -------------------------------
// SEND SMS VIA PAGENET API
// -------------------------------
$parameters = [
    'message'       => $message,
    'mobile_number' => $phone, // Use driver's actual phone number from database
    'device'        => '9ce7914534af4dd3',
    'device_sim'    => '2'
];

$headers = [
    'apikey: 6PLX3NFL2A2FLQ81RI7X6C4PJP68ANLJNYQ7XAR6'
];

$url = 'https://sms.pagenet.info/api/v1/sms/send';

$ch = curl_init();
curl_setopt($ch, CURLOPT_URL, $url);
curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
curl_setopt($ch, CURLOPT_POST, true);
curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($parameters));
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, false);

$result = curl_exec($ch);

if (curl_errno($ch)) {
    echo json_encode(['success' => false, 'error' => curl_error($ch)]);
} else {
    echo json_encode(['success' => true, 'response' => $result]);
}

curl_close($ch);
$stmt->close();
$conn->close();
