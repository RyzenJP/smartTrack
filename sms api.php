<?php
/**
 * SMS API Functions
 * 
 * SMS Limit: 3000 SMS per day
 * Rate Limit: 2 SMS per second
 */

/**
 * Send SMS using the API
 * 
 * @param string $message The message to send
 * @param string $mobileNumber The recipient's mobile number
 * @param string $device Device ID (default: '9ce7914534af4dd3')
 * @param string $deviceSim SIM slot (default: '2')
 * @return array Response with success status and details
 */
function sendSMS($message, $mobileNumber, $device = '9ce7914534af4dd3', $deviceSim = '2') {
    // Validate mobile number format (Philippine format)
    if (!preg_match('/^09\d{9}$/', $mobileNumber)) {
        return [
            'success' => false,
            'error' => 'Invalid mobile number format. Use Philippine format: 09XXXXXXXXX'
        ];
    }
    
    // Validate message length
    if (empty($message) || strlen($message) > 160) {
        return [
            'success' => false,
            'error' => 'Message is empty or too long (max 160 characters)'
        ];
    }
    
    // Define the parameters for the POST request
    $parameters = [
        'message' => $message,
        'mobile_number' => $mobileNumber,
        'device' => $device,
        'device_sim' => $deviceSim
    ];
    
    // Define the headers for the POST request
    $headers = [
        'apikey: 6PLX3NFL2A2FLQ81RI7X6C4PJP68ANLJNYQ7XAR6'
    ];
    
    // Define the URL for the POST request
    $url = 'https://sms.pagenet.info/api/v1/sms/send';
    
    // Initialize cURL session
    $ch = curl_init();
    
    // Set cURL options
    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($parameters));
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, false);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
    curl_setopt($ch, CURLOPT_TIMEOUT, 30);
    curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 10);
    
    // Execute cURL request and get the response
    $result = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $error = curl_error($ch);
    $errno = curl_errno($ch);
    
    // Close cURL session
    curl_close($ch);
    
    // Check for cURL errors
    if ($errno) {
        return [
            'success' => false,
            'error' => 'Network error: ' . $error,
            'errno' => $errno,
            'http_code' => $httpCode
        ];
    }
    
    // Parse JSON response
    $jsonResponse = json_decode($result, true);
    
    if ($jsonResponse) {
        if (isset($jsonResponse['success']) && $jsonResponse['success'] === true) {
            return [
                'success' => true,
                'message' => 'SMS sent successfully',
                'response' => $jsonResponse,
                'http_code' => $httpCode
            ];
        } else {
            return [
                'success' => false,
                'error' => isset($jsonResponse['message']) ? $jsonResponse['message'] : 'Unknown API error',
                'response' => $jsonResponse,
                'http_code' => $httpCode
            ];
        }
    } else {
        return [
            'success' => false,
            'error' => 'Invalid JSON response from API',
            'raw_response' => $result,
            'http_code' => $httpCode
        ];
    }
}

/**
 * Send password reset SMS
 * 
 * @param string $mobileNumber The recipient's mobile number
 * @param string $resetCode The 6-digit reset code
 * @return array Response with success status and details
 */
function sendPasswordResetSMS($mobileNumber, $resetCode) {
    $message = "Smart Track: Your password reset code is {$resetCode}. Valid for 15 minutes. Do not share this code.";
    return sendSMS($message, $mobileNumber);
}

/**
 * Send notification SMS
 * 
 * @param string $mobileNumber The recipient's mobile number
 * @param string $notification The notification message
 * @return array Response with success status and details
 */
function sendNotificationSMS($mobileNumber, $notification) {
    $message = "Smart Track: {$notification}";
    return sendSMS($message, $mobileNumber);
}

// Example usage (commented out to prevent accidental execution)
/*
// Send password reset SMS
$result = sendPasswordResetSMS('09945273817', '123456');
if ($result['success']) {
    echo "SMS sent successfully!";
} else {
    echo "Error: " . $result['error'];
}

// Send custom notification
$result = sendNotificationSMS('09945273817', 'Your vehicle maintenance is due tomorrow.');
if ($result['success']) {
    echo "Notification sent successfully!";
} else {
    echo "Error: " . $result['error'];
}
*/
?>