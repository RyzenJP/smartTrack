<?php
header('Content-Type: application/json');

// Secure CORS configuration
require_once __DIR__ . '/includes/cors_helper.php';
setCORSHeaders(true); // Allow credentials for authenticated requests

require_once 'db_connection.php';
require_once 'sms api.php';

try {
    $pdo = new PDO("mysql:host=".DB_HOST.";dbname=".DB_NAME, DB_USER, DB_PASS);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

    $action = $_POST['action'] ?? $_GET['action'] ?? '';
    $dateRange = $_GET['dateRange'] ?? 7; // Default to 7 days
    $vehicleType = $_GET['vehicleType'] ?? 'all';
    $driverId = $_GET['driverId'] ?? 'all';
    $deviceId = $_GET['deviceId'] ?? 'all'; // Add deviceId filter
    
    // Build filter conditions - use prepared statements for security
    $dateFilter = "";
    $vehicleFilter = "";
    $driverFilter = "";
    $deviceFilter = "";
    $filterParams = [];
    $filterTypes = "";
    
    // Date range filter - validate and use prepared statement
    if ($dateRange !== 'all' && is_numeric($dateRange)) {
        $dateRange = (int)$dateRange;
        $dateFilter = " AND ge.created_at >= DATE_SUB(NOW(), INTERVAL ? DAY)";
        $filterParams[] = $dateRange;
        $filterTypes .= "i";
    }
    
    // Vehicle type filter - use prepared statement
    if ($vehicleType !== 'all') {
        $vehicleType = trim($vehicleType);
        $vehicleFilter = " AND v.article = ?";
        $filterParams[] = $vehicleType;
        $filterTypes .= "s";
    }
    
    // Driver filter - use prepared statement
    if ($driverId !== 'all') {
        $driverId = (int)$driverId;
        $driverFilter = " AND ge.driver_id = ?";
        $filterParams[] = $driverId;
        $filterTypes .= "i";
    }
    
    // Device filter - use prepared statement
    if ($deviceId !== 'all') {
        $deviceId = trim($deviceId);
        $deviceFilter = " AND ge.device_id = ?";
        $filterParams[] = $deviceId;
        $filterTypes .= "s";
    }

    switch ($action) {
        case 'send_geofence_alert':
            $input = json_decode(file_get_contents('php://input'), true);
            
            if (!$input) {
                throw new Exception('Invalid JSON data');
            }
            
            $deviceId = $input['device_id'] ?? null;
            $geofenceId = $input['geofence_id'] ?? null;
            $eventType = $input['event_type'] ?? null;
            $vehicleData = $input['vehicle_data'] ?? null;
            
            if (!$deviceId || !$geofenceId || !$eventType) {
                throw new Exception('Missing required parameters');
            }
            
            // Get geofence details
            $geofenceStmt = $pdo->prepare("SELECT * FROM geofences WHERE id = ?");
            $geofenceStmt->execute([$geofenceId]);
            $geofence = $geofenceStmt->fetch(PDO::FETCH_ASSOC);
            
            if (!$geofence) {
                throw new Exception('Geofence not found');
            }
            
            // Get vehicle and driver details via gps_devices table
            $vehicleStmt = $pdo->prepare("
                SELECT v.*, u.full_name as driver_name, u.phone as driver_phone, gd.device_id
                FROM gps_devices gd
                LEFT JOIN fleet_vehicles v ON gd.vehicle_id = v.id
                LEFT JOIN vehicle_assignments va ON v.id = va.vehicle_id
                LEFT JOIN user_table u ON va.driver_id = u.user_id
                WHERE gd.device_id = ? AND va.status = 'active'
            ");
            $vehicleStmt->execute([$deviceId]);
            $vehicle = $vehicleStmt->fetch(PDO::FETCH_ASSOC);
            
            if (!$vehicle) {
                throw new Exception('Vehicle not found or not assigned');
            }
            
            // Create alert message with proper timezone
            $eventText = $eventType === 'entry' ? 'entered' : 'exited';
            // Use Asia/Manila timezone (UTC+8) for Philippines
            date_default_timezone_set('Asia/Manila');
            $alertMessage = "Smart Track Alert: Vehicle {$vehicle['article']} ({$vehicle['plate_number']}) has {$eventText} geofence '{$geofence['name']}' at " . date('Y-m-d H:i:s');
            
            // Send SMS to driver if phone number exists
            if ($vehicle['driver_phone']) {
                $smsResult = sendSMS($alertMessage, $vehicle['driver_phone']);
                if (!$smsResult['success']) {
                    error_log("Failed to send SMS to driver: " . $smsResult['error']);
                }
            }
            
            // Send SMS to Motor Pool Admin
            $adminStmt = $pdo->prepare("SELECT phone FROM user_table WHERE role = 'Motor Pool Admin' AND status = 'Active' LIMIT 1");
            $adminStmt->execute();
            $admin = $adminStmt->fetch(PDO::FETCH_ASSOC);
            
            if ($admin && $admin['phone']) {
                $adminMessage = "ADMIN ALERT: {$alertMessage}";
                $adminSmsResult = sendSMS($adminMessage, $admin['phone']);
                if (!$adminSmsResult['success']) {
                    error_log("Failed to send SMS to admin: " . $adminSmsResult['error']);
                }
            }
            
            // Check if event already exists (to prevent duplicates from gps_receiver.php)
            $checkStmt = $pdo->prepare("
                SELECT id FROM geofence_events 
                WHERE device_id = ? AND geofence_id = ? AND event_type = ? 
                AND created_at >= DATE_SUB(NOW(), INTERVAL 1 MINUTE)
                ORDER BY created_at DESC LIMIT 1
            ");
            $checkStmt->execute([$deviceId, $geofenceId, $eventType]);
            $existingEvent = $checkStmt->fetch(PDO::FETCH_ASSOC);
            
            if (!$existingEvent) {
                // Log geofence event with proper timezone (only if not already exists)
                $logStmt = $pdo->prepare("
                    INSERT INTO geofence_events 
                    (device_id, vehicle_id, driver_id, geofence_id, event_type, created_at) 
                    VALUES (?, ?, ?, ?, ?, DATE_ADD(NOW(), INTERVAL 8 HOUR))
                ");
                $logStmt->execute([
                    $deviceId,
                    $vehicle['id'],
                    $vehicle['driver_name'] ? $vehicle['driver_id'] : null,
                    $geofenceId,
                    $eventType
                ]);
                error_log("GEOFENCE ALERT API: Inserted new event for device $deviceId");
            } else {
                // Update existing event with driver_id if it was NULL
                if (!$existingEvent['driver_id'] && $vehicle['driver_id']) {
                    $updateStmt = $pdo->prepare("
                        UPDATE geofence_events 
                        SET driver_id = ?, vehicle_id = ?
                        WHERE id = ?
                    ");
                    $updateStmt->execute([
                        $vehicle['driver_id'],
                        $vehicle['id'],
                        $existingEvent['id']
                    ]);
                    error_log("GEOFENCE ALERT API: Updated existing event #{$existingEvent['id']} with driver_id");
                } else {
                    error_log("GEOFENCE ALERT API: Event already exists, skipping insert");
                }
            }
            
            // Create notification for admin users with proper timezone
            $notificationStmt = $pdo->prepare("
                INSERT INTO notifications 
                (user_id, title, message, type, data, created_at) 
                SELECT user_id, ?, ?, 'geofence_alert', ?, DATE_ADD(NOW(), INTERVAL 8 HOUR)
                FROM user_table 
                WHERE role IN ('Super Admin', 'Motor Pool Admin') AND status = 'Active'
            ");
            $notificationStmt->execute([
                "Geofence Alert - {$eventType}",
                $alertMessage,
                json_encode([
                    'device_id' => $deviceId,
                    'geofence_id' => $geofenceId,
                    'event_type' => $eventType,
                    'vehicle_data' => $vehicleData
                ])
            ]);
            
            echo json_encode([
                'success' => true,
                'message' => 'Geofence alert sent successfully',
                'alert_details' => [
                    'vehicle' => $vehicle['article'],
                    'driver' => $vehicle['driver_name'],
                    'geofence' => $geofence['name'],
                    'event_type' => $eventType,
                    'timestamp' => date('Y-m-d H:i:s') // Already using Asia/Manila timezone
                ]
            ]);
            break;
            
        case 'get_geofence_events':
            $limit = intval($_GET['limit'] ?? 50); // Convert to integer
            
            // MariaDB doesn't support parameterized LIMIT, so we use string concatenation with validation
            $limit = max(1, min(100, $limit)); // Ensure limit is between 1 and 100
            
            // Build WHERE clause for date filtering
            $dateFilterClause = "";
            if (!empty($_GET['startDate'])) {
                $startDate = $pdo->quote($_GET['startDate'] . ' 00:00:00');
                $dateFilterClause .= " AND ge.created_at >= $startDate";
            }
            if (!empty($_GET['endDate'])) {
                $endDate = $pdo->quote($_GET['endDate'] . ' 23:59:59');
                $dateFilterClause .= " AND ge.created_at <= $endDate";
            }
            
            // Also apply date range filter if provided - use prepared statement for security
            $eventsParams = [];
            $eventsWhere = ["1=1"];
            
            if (!empty($_GET['startDate'])) {
                $startDate = trim($_GET['startDate']) . ' 00:00:00';
                $eventsWhere[] = "ge.created_at >= ?";
                $eventsParams[] = $startDate;
            }
            if (!empty($_GET['endDate'])) {
                $endDate = trim($_GET['endDate']) . ' 23:59:59';
                $eventsWhere[] = "ge.created_at <= ?";
                $eventsParams[] = $endDate;
            }
            if ($dateRange !== 'all' && is_numeric($dateRange)) {
                $dateRange = (int)$dateRange;
                $eventsWhere[] = "ge.created_at >= DATE_SUB(NOW(), INTERVAL ? DAY)";
                $eventsParams[] = $dateRange;
            }
            if ($deviceId !== 'all') {
                $deviceId = trim($deviceId);
                $eventsWhere[] = "ge.device_id = ?";
                $eventsParams[] = $deviceId;
            }
            
            $eventsQuery = "
                SELECT ge.*, g.name as geofence_name, v.article as vehicle_name, v.plate_number, 
                       COALESCE(u.full_name, 'Unknown') as driver_name,
                       DATE_ADD(ge.created_at, INTERVAL 8 HOUR) as created_at_local
                FROM geofence_events ge
                LEFT JOIN geofences g ON ge.geofence_id = g.id
                LEFT JOIN fleet_vehicles v ON ge.vehicle_id = v.id
                LEFT JOIN (
                    SELECT va1.vehicle_id, va1.driver_id
                    FROM vehicle_assignments va1
                    WHERE va1.status = 'active'
                    AND va1.id = (
                        SELECT MAX(va2.id)
                        FROM vehicle_assignments va2
                        WHERE va2.vehicle_id = va1.vehicle_id
                        AND va2.status = 'active'
                    )
                ) va ON v.id = va.vehicle_id
                LEFT JOIN user_table u ON COALESCE(va.driver_id, ge.driver_id) = u.user_id
                WHERE " . implode(" AND ", $eventsWhere) . "
                ORDER BY ge.created_at DESC
                LIMIT ?";
            
            $eventsParams[] = $limit;
            $stmt = $pdo->prepare($eventsQuery);
            $stmt->execute($eventsParams);
            $events = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            echo json_encode([
                'success' => true,
                'data' => $events
            ]);
            break;
            
        case 'get_geofence_analytics':
            // Get analytics data
            $analytics = [];
            
            try {
                // Check if geofence_events table exists
                $tableCheck = $pdo->query("SHOW TABLES LIKE 'geofence_events'");
                $tableExists = $tableCheck->fetch();
                
                if (!$tableExists) {
                    // Table doesn't exist, create it
                    $pdo->exec("
                        CREATE TABLE geofence_events (
                            id INT AUTO_INCREMENT PRIMARY KEY,
                            device_id VARCHAR(50),
                            vehicle_id INT,
                            driver_id VARCHAR(50),
                            geofence_id INT,
                            event_type ENUM('entry', 'exit'),
                            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                            INDEX idx_geofence_id (geofence_id),
                            INDEX idx_vehicle_id (vehicle_id),
                            INDEX idx_created_at (created_at)
                        )
                    ");
                }
                
                // Total events today (with filters) - use prepared statement for security
                $todayParams = [];
                $todayWhere = ["DATE(ge.created_at) = CURDATE()"];
                if ($vehicleType !== 'all') {
                    $todayWhere[] = "v.article = ?";
                    $todayParams[] = trim($vehicleType);
                }
                if ($driverId !== 'all') {
                    $todayWhere[] = "ge.driver_id = ?";
                    $todayParams[] = (int)$driverId;
                }
                if ($deviceId !== 'all') {
                    $todayWhere[] = "ge.device_id = ?";
                    $todayParams[] = trim($deviceId);
                }
                $todayQuery = "
                    SELECT COUNT(*) as count FROM geofence_events ge
                    LEFT JOIN fleet_vehicles v ON ge.vehicle_id = v.id
                    WHERE " . implode(" AND ", $todayWhere);
                $todayStmt = $pdo->prepare($todayQuery);
                $todayStmt->execute($todayParams);
                $analytics['today_events'] = $todayStmt->fetch(PDO::FETCH_ASSOC)['count'];
                
                // Active geofences count
                $activeGeofenceStmt = $pdo->query("
                    SELECT COUNT(*) as count FROM geofences WHERE status = 'active'
                ");
                $analytics['active_geofences'] = $activeGeofenceStmt->fetch(PDO::FETCH_ASSOC)['count'];
                
                // Events by geofence (include all active geofences, even with 0 events) - use prepared statement for security
                $geofenceParams = [];
                $geofenceWhere = ["g.status = 'active'"];
                $geofenceJoin = "";
                
                if ($dateRange !== 'all' && is_numeric($dateRange)) {
                    $dateRange = (int)$dateRange;
                    $geofenceJoin = "AND ge.created_at >= DATE_SUB(NOW(), INTERVAL ? DAY)";
                    $geofenceParams[] = $dateRange;
                }
                if ($vehicleType !== 'all') {
                    $geofenceWhere[] = "v.article = ?";
                    $geofenceParams[] = trim($vehicleType);
                }
                if ($driverId !== 'all') {
                    $geofenceWhere[] = "ge.driver_id = ?";
                    $geofenceParams[] = (int)$driverId;
                }
                if ($deviceId !== 'all') {
                    $geofenceWhere[] = "ge.device_id = ?";
                    $geofenceParams[] = trim($deviceId);
                }
                
                $geofenceQuery = "
                    SELECT g.name, COALESCE(COUNT(ge.id), 0) as event_count
                    FROM geofences g
                    LEFT JOIN geofence_events ge ON g.id = ge.geofence_id " . ($geofenceJoin ? $geofenceJoin : "") . "
                    LEFT JOIN fleet_vehicles v ON ge.vehicle_id = v.id
                    WHERE " . implode(" AND ", $geofenceWhere) . "
                    GROUP BY g.id, g.name
                    ORDER BY event_count DESC
                ";
                $geofenceStmt = $pdo->prepare($geofenceQuery);
                $geofenceStmt->execute($geofenceParams);
                $analytics['by_geofence'] = $geofenceStmt->fetchAll(PDO::FETCH_ASSOC);
                
                // Events by vehicle (include all vehicles, even with 0 events) - use prepared statement for security
                $vehicleParams = [];
                $vehicleWhere = ["(v.status = 'active' OR v.status IS NULL)"];
                $vehicleJoin = "";
                
                if ($dateRange !== 'all' && is_numeric($dateRange)) {
                    $dateRange = (int)$dateRange;
                    $vehicleJoin = "AND ge.created_at >= DATE_SUB(NOW(), INTERVAL ? DAY)";
                    $vehicleParams[] = $dateRange;
                }
                if ($vehicleType !== 'all') {
                    $vehicleWhere[] = "v.article = ?";
                    $vehicleParams[] = trim($vehicleType);
                }
                if ($driverId !== 'all') {
                    $vehicleWhere[] = "ge.driver_id = ?";
                    $vehicleParams[] = (int)$driverId;
                }
                if ($deviceId !== 'all') {
                    $vehicleWhere[] = "ge.device_id = ?";
                    $vehicleParams[] = trim($deviceId);
                }
                
                $vehicleQuery = "
                    SELECT v.article, v.plate_number, COALESCE(COUNT(ge.id), 0) as event_count
                    FROM fleet_vehicles v
                    LEFT JOIN geofence_events ge ON v.id = ge.vehicle_id " . ($vehicleJoin ? $vehicleJoin : "") . "
                    WHERE " . implode(" AND ", $vehicleWhere) . "
                    GROUP BY v.id, v.article, v.plate_number
                    ORDER BY event_count DESC
                ";
                $vehicleStmt = $pdo->prepare($vehicleQuery);
                $vehicleStmt->execute($vehicleParams);
                $analytics['by_vehicle'] = $vehicleStmt->fetchAll(PDO::FETCH_ASSOC);
                
                echo json_encode([
                    'success' => true,
                    'data' => $analytics
                ]);
                
            } catch (Exception $e) {
                echo json_encode([
                    'success' => false,
                    'message' => 'Analytics error: ' . $e->getMessage()
                ]);
            }
            break;
            
        case 'get_timeline_data':
            // Get hourly event counts for timeline chart
            try {
                $dateRange = isset($_GET['dateRange']) ? (int)$_GET['dateRange'] : 7;
                
                // Get events grouped by hour for the last 24 hours - use prepared statement for security
                $timelineParams = [];
                $timelineWhere = ["ge.created_at >= DATE_SUB(NOW(), INTERVAL 24 HOUR)"];
                
                if ($vehicleType !== 'all') {
                    $timelineWhere[] = "v.article = ?";
                    $timelineParams[] = trim($vehicleType);
                }
                if ($driverId !== 'all') {
                    $timelineWhere[] = "ge.driver_id = ?";
                    $timelineParams[] = (int)$driverId;
                }
                if ($deviceId !== 'all') {
                    $timelineWhere[] = "ge.device_id = ?";
                    $timelineParams[] = trim($deviceId);
                }
                
                $timelineQuery = "
                    SELECT 
                        DATE_FORMAT(ge.created_at, '%Y-%m-%d %H:00:00') as hour,
                        COUNT(*) as event_count
                    FROM geofence_events ge
                    LEFT JOIN fleet_vehicles v ON ge.vehicle_id = v.id
                    WHERE " . implode(" AND ", $timelineWhere) . "
                    GROUP BY DATE_FORMAT(ge.created_at, '%Y-%m-%d %H:00:00')
                    ORDER BY hour ASC
                ";
                
                $timelineStmt = $pdo->prepare($timelineQuery);
                $timelineStmt->execute($timelineParams);
                $events = $timelineStmt->fetchAll(PDO::FETCH_ASSOC);
                
                // Create an array with all 24 hours (even if no events)
                $timeline = [];
                for ($i = 23; $i >= 0; $i--) {
                    $hour = new DateTime();
                    $hour->modify("-$i hours");
                    $hourKey = $hour->format('Y-m-d H:00:00');
                    $hourLabel = $hour->format('H:00');
                    
                    // Find matching event count
                    $count = 0;
                    foreach ($events as $event) {
                        if ($event['hour'] === $hourKey) {
                            $count = (int)$event['event_count'];
                            break;
                        }
                    }
                    
                    $timeline[] = [
                        'hour' => $hourLabel,
                        'count' => $count
                    ];
                }
                
                echo json_encode([
                    'success' => true,
                    'data' => $timeline
                ]);
                
            } catch (Exception $e) {
                echo json_encode([
                    'success' => false,
                    'message' => 'Timeline error: ' . $e->getMessage()
                ]);
            }
            break;
            
        case 'export_geofence_events':
            try {
                $format = $_GET['format'] ?? 'csv';
                $dateRange = $_GET['dateRange'] ?? 7;
                $fields = json_decode($_GET['fields'] ?? '[]', true);
                $filename = $_GET['filename'] ?? '';
                
                // Build the query to get geofence events
                $query = "
                    SELECT 
                        ge.created_at as time,
                        CONCAT(v.article, ' (', v.plate_number, ')') as vehicle,
                        d.name as driver_name,
                        g.name as geofence_name,
                        ge.event_type,
                        CASE WHEN ge.event_type = 'entry' THEN 'Entered' ELSE 'Exited' END as event_status
                    FROM geofence_events ge
                    LEFT JOIN fleet_vehicles v ON ge.vehicle_id = v.id
                    LEFT JOIN drivers d ON ge.driver_id = d.id
                    LEFT JOIN geofences g ON ge.geofence_id = g.id
                    WHERE ge.created_at >= DATE_SUB(NOW(), INTERVAL $dateRange DAY)
                    ORDER BY ge.created_at DESC
                ";
                
                $stmt = $pdo->query($query);
                $events = $stmt->fetchAll(PDO::FETCH_ASSOC);
                
                if ($format === 'csv') {
                    // Generate CSV
                    $csvContent = '';
                    
                    // Add headers based on selected fields
                    $headers = [];
                    $fieldMapping = [
                        'time' => 'Time',
                        'vehicle' => 'Vehicle',
                        'driver' => 'Driver',
                        'geofence' => 'Geofence',
                        'event' => 'Event Type',
                        'status' => 'Status'
                    ];
                    
                    foreach ($fields as $field) {
                        if (isset($fieldMapping[$field])) {
                            $headers[] = $fieldMapping[$field];
                        }
                    }
                    $csvContent .= implode(',', $headers) . "\n";
                    
                    // Add data rows
                    foreach ($events as $event) {
                        $row = [];
                        foreach ($fields as $field) {
                            switch ($field) {
                                case 'time':
                                    $row[] = '"' . date('Y-m-d H:i:s', strtotime($event['time'])) . '"';
                                    break;
                                case 'vehicle':
                                    $row[] = '"' . $event['vehicle'] . '"';
                                    break;
                                case 'driver':
                                    $row[] = '"' . ($event['driver_name'] ?: 'Unknown') . '"';
                                    break;
                                case 'geofence':
                                    $row[] = '"' . ($event['geofence_name'] ?: 'Unknown') . '"';
                                    break;
                                case 'event':
                                    $row[] = '"' . $event['event_status'] . '"';
                                    break;
                                case 'status':
                                    $row[] = '"Active"';
                                    break;
                            }
                        }
                        $csvContent .= implode(',', $row) . "\n";
                    }
                    
                    // Set headers for CSV download
                    $downloadFilename = $filename ?: 'geofence_events_' . date('Y-m-d') . '.csv';
                    header('Content-Type: text/csv');
                    header('Content-Disposition: attachment; filename="' . $downloadFilename . '"');
                    header('Cache-Control: no-cache, no-store, must-revalidate');
                    header('Pragma: no-cache');
                    header('Expires: 0');
                    
                    echo $csvContent;
                    exit;
                    
                } elseif ($format === 'pdf') {
                    // Generate PDF using a simple HTML to PDF approach
                    $html = '<html><head><title>Geofence Events Report</title></head><body>';
                    $html .= '<h1>Geofence Events Report</h1>';
                    $html .= '<p>Generated on: ' . date('Y-m-d H:i:s') . '</p>';
                    $html .= '<p>Date Range: Last ' . $dateRange . ' days</p>';
                    $html .= '<table border="1" cellpadding="5" cellspacing="0" style="width:100%; border-collapse:collapse;">';
                    
                    // Add headers
                    $html .= '<tr style="background-color:#f0f0f0;">';
                    $fieldMapping = [
                        'time' => 'Time',
                        'vehicle' => 'Vehicle',
                        'driver' => 'Driver',
                        'geofence' => 'Geofence',
                        'event' => 'Event Type',
                        'status' => 'Status'
                    ];
                    foreach ($fields as $field) {
                        if (isset($fieldMapping[$field])) {
                            $html .= '<th>' . $fieldMapping[$field] . '</th>';
                        }
                    }
                    $html .= '</tr>';
                    
                    // Add data rows
                    foreach ($events as $event) {
                        $html .= '<tr>';
                        foreach ($fields as $field) {
                            switch ($field) {
                                case 'time':
                                    $html .= '<td>' . date('Y-m-d H:i:s', strtotime($event['time'])) . '</td>';
                                    break;
                                case 'vehicle':
                                    $html .= '<td>' . htmlspecialchars($event['vehicle']) . '</td>';
                                    break;
                                case 'driver':
                                    $html .= '<td>' . htmlspecialchars($event['driver_name'] ?: 'Unknown') . '</td>';
                                    break;
                                case 'geofence':
                                    $html .= '<td>' . htmlspecialchars($event['geofence_name'] ?: 'Unknown') . '</td>';
                                    break;
                                case 'event':
                                    $html .= '<td>' . htmlspecialchars($event['event_status']) . '</td>';
                                    break;
                                case 'status':
                                    $html .= '<td>Active</td>';
                                    break;
                            }
                        }
                        $html .= '</tr>';
                    }
                    
                    $html .= '</table></body></html>';
                    
                    // For now, we'll return the HTML content
                    // In a production environment, you'd use a library like TCPDF or mPDF
                    $downloadFilename = $filename ?: 'geofence_events_' . date('Y-m-d') . '.html';
                    header('Content-Type: text/html');
                    header('Content-Disposition: attachment; filename="' . $downloadFilename . '"');
                    header('Cache-Control: no-cache, no-store, must-revalidate');
                    header('Pragma: no-cache');
                    header('Expires: 0');
                    
                    echo $html;
                    exit;
                }
                
            } catch (Exception $e) {
                echo json_encode([
                    'success' => false,
                    'message' => 'Export error: ' . $e->getMessage()
                ]);
            }
            break;
            
        default:
            echo json_encode(['success' => false, 'message' => 'Invalid action']);
    }
    
} catch (PDOException $e) {
    echo json_encode(['success' => false, 'message' => 'Database error: ' . $e->getMessage()]);
} catch (Exception $e) {
    echo json_encode(['success' => false, 'message' => 'Error: ' . $e->getMessage()]);
}
?>
