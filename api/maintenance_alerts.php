<?php
header('Content-Type: application/json');
require_once __DIR__ . '/../includes/cors_helper.php';
setCORSHeaders(true);

require_once __DIR__ . '/../db_connection.php';

$action = $_GET['action'] ?? $_POST['action'] ?? '';

try {
    switch ($action) {
        case 'get_driver_alerts':
            getDriverAlerts();
            break;
        case 'get_vehicle_maintenance_status':
            getVehicleMaintenanceStatus();
            break;
        case 'create_maintenance_alert':
            createMaintenanceAlert();
            break;
        case 'mark_alert_read':
            markAlertRead();
            break;
        case 'get_maintenance_schedule':
            getMaintenanceSchedule();
            break;
        case 'get_all_driver_alerts':
            getAllDriverAlerts();
            break;
        default:
            throw new Exception('Invalid action');
    }
} catch (Exception $e) {
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}

function getDriverAlerts() {
    global $conn;
    
    $driverId = $_GET['driver_id'] ?? null;
    if (!$driverId) {
        throw new Exception('Driver ID is required');
    }
    
    // Get driver's assigned vehicle
    $vehicleQuery = "
        SELECT v.*, va.driver_name, va.phone_number
        FROM vehicle_assignments va
        JOIN fleet_vehicles v ON va.vehicle_id = v.id
        WHERE va.driver_id = ? AND va.status = 'active'
    ";
    
    $stmt = $conn->prepare($vehicleQuery);
    $stmt->bind_param('i', $driverId);
    $stmt->execute();
    $vehicle = $stmt->get_result()->fetch_assoc();
    
    if (!$vehicle) {
        echo json_encode(['success' => true, 'data' => [], 'message' => 'No assigned vehicle']);
        return;
    }
    
    // Calculate total mileage from trip logs
    $mileageQuery = "
        SELECT COALESCE(SUM(distance_km), 0) as total_mileage
        FROM trip_logs 
        WHERE vehicle_id = ?
    ";
    
    $stmt = $conn->prepare($mileageQuery);
    $stmt->bind_param('i', $vehicle['id']);
    $stmt->execute();
    $mileageResult = $stmt->get_result()->fetch_assoc();
    $totalMileage = $mileageResult['total_mileage'] ?? 0;
    
    // Get last maintenance record
    $lastMaintenanceQuery = "
        SELECT * FROM maintenance_schedules 
        WHERE vehicle_id = ? 
        ORDER BY scheduled_date DESC 
        LIMIT 1
    ";
    
    $stmt = $conn->prepare($lastMaintenanceQuery);
    $stmt->bind_param('i', $vehicle['id']);
    $stmt->execute();
    $lastMaintenance = $stmt->get_result()->fetch_assoc();
    
    // Calculate mileage since last maintenance
    $mileageSinceMaintenance = $totalMileage;
    if ($lastMaintenance) {
        $lastMaintenanceDate = $lastMaintenance['scheduled_date'];
        $mileageSinceMaintenanceQuery = "
            SELECT COALESCE(SUM(distance_km), 0) as mileage_since
            FROM trip_logs 
            WHERE vehicle_id = ? AND start_time >= ?
        ";
        
        $stmt = $conn->prepare($mileageSinceMaintenanceQuery);
        $stmt->bind_param('is', $vehicle['id'], $lastMaintenanceDate);
        $stmt->execute();
        $mileageSinceResult = $stmt->get_result()->fetch_assoc();
        $mileageSinceMaintenance = $mileageSinceResult['mileage_since'] ?? 0;
    }
    
    // Generate alerts based on your custom maintenance schedule
    $alerts = [];
    
    // Your maintenance schedule (km intervals and months)
    $maintenanceSchedule = [
        5000 => ['months' => 3, 'services' => ['CHANGE OIL']],
        10000 => ['months' => 6, 'services' => ['CHANGE OIL', 'TIRE ROTATION']],
        15000 => ['months' => 9, 'services' => ['CHANGE OIL']],
        20000 => ['months' => 12, 'services' => ['CHANGE OIL', 'TIRE ROTATION', 'WHEEL BALANCE', 'ALIGNMENT', 'BRAKE INSPECTION']],
        25000 => ['months' => 15, 'services' => ['CHANGE OIL']],
        30000 => ['months' => 18, 'services' => ['CHANGE OIL', 'TIRE ROTATION']],
        35000 => ['months' => 21, 'services' => ['CHANGE OIL']],
        40000 => ['months' => 24, 'services' => ['CHANGE OIL', 'TIRE ROTATION', 'WHEEL BALANCE', 'ALIGNMENT', 'BRAKE INSPECTION', 'COOLING SYSTEM']],
        45000 => ['months' => 27, 'services' => ['CHANGE OIL', 'ENGINE TUNE UP']],
        50000 => ['months' => 30, 'services' => ['CHANGE OIL', 'TIRE ROTATION']],
        55000 => ['months' => 33, 'services' => ['CHANGE OIL']],
        60000 => ['months' => 36, 'services' => ['CHANGE OIL', 'TIRE ROTATION', 'WHEEL BALANCE', 'ALIGNMENT', 'BRAKE INSPECTION']],
        65000 => ['months' => 39, 'services' => ['CHANGE OIL']],
        70000 => ['months' => 42, 'services' => ['CHANGE OIL', 'TIRE ROTATION']],
        75000 => ['months' => 45, 'services' => ['CHANGE OIL']],
        80000 => ['months' => 48, 'services' => ['CHANGE OIL', 'TIRE ROTATION', 'WHEEL BALANCE', 'ALIGNMENT', 'BRAKE INSPECTION', 'COOLING SYSTEM']],
        85000 => ['months' => 51, 'services' => ['CHANGE OIL', 'ENGINE TUNE UP']],
        90000 => ['months' => 54, 'services' => ['CHANGE OIL', 'TIRE ROTATION']],
        95000 => ['months' => 57, 'services' => ['CHANGE OIL']],
        100000 => ['months' => 60, 'services' => ['CHANGE OIL', 'TIRE ROTATION', 'WHEEL BALANCE', 'ALIGNMENT', 'BRAKE INSPECTION']]
    ];
    
    // Calculate time since last maintenance
    $monthsSinceMaintenance = 0;
    if ($lastMaintenance) {
        $lastMaintenanceDate = new DateTime($lastMaintenance['scheduled_date']);
        $currentDate = new DateTime();
        $monthsSinceMaintenance = $lastMaintenanceDate->diff($currentDate)->m + ($lastMaintenanceDate->diff($currentDate)->y * 12);
    } else {
        // If no maintenance record, use vehicle creation date
        $vehicleCreatedDate = new DateTime($vehicle['created_at']);
        $currentDate = new DateTime();
        $monthsSinceMaintenance = $vehicleCreatedDate->diff($currentDate)->m + ($vehicleCreatedDate->diff($currentDate)->y * 12);
    }
    
    // Check each maintenance interval
    foreach ($maintenanceSchedule as $kmInterval => $schedule) {
        $monthsInterval = $schedule['months'];
        $services = $schedule['services'];
        
        // Calculate next maintenance based on mileage
        $nextMaintenanceKm = ceil($mileageSinceMaintenance / $kmInterval) * $kmInterval;
        $kmUntilMaintenance = $nextMaintenanceKm - $mileageSinceMaintenance;
        
        // Calculate next maintenance based on time
        $nextMaintenanceMonths = ceil($monthsSinceMaintenance / $monthsInterval) * $monthsInterval;
        $monthsUntilMaintenance = $nextMaintenanceMonths - $monthsSinceMaintenance;
        
        // Determine which comes first (mileage or time)
        $isMileageBased = $kmUntilMaintenance <= ($monthsUntilMaintenance * 1000); // Rough conversion: 1000km per month average
        
        // Set alert thresholds
        $kmAlertThreshold = $kmInterval <= 10000 ? 500 : 1000; // Smaller intervals get smaller thresholds
        $monthAlertThreshold = $monthsInterval <= 6 ? 1 : 2; // Smaller intervals get smaller thresholds
        
        // Check if maintenance is due based on mileage
        if ($kmUntilMaintenance <= $kmAlertThreshold) {
            $servicesText = implode(', ', $services);
            $priority = $kmUntilMaintenance <= 100 ? 'critical' : ($kmUntilMaintenance <= 300 ? 'high' : 'medium');
            
            $alerts[] = [
                'type' => strtolower(str_replace(' ', '_', $services[0])),
                'priority' => $priority,
                'title' => $kmInterval . ' KM Service Required',
                'message' => "Vehicle {$vehicle['plate_number']} needs {$servicesText} in {$kmUntilMaintenance} km",
                'vehicle_id' => $vehicle['id'],
                'vehicle_name' => $vehicle['article'],
                'plate_number' => $vehicle['plate_number'],
                'km_remaining' => $kmUntilMaintenance,
                'months_remaining' => $monthsUntilMaintenance,
                'total_mileage' => $totalMileage,
                'mileage_since_maintenance' => $mileageSinceMaintenance,
                'months_since_maintenance' => $monthsSinceMaintenance,
                'services' => $services,
                'services_text' => $servicesText,
                'interval_km' => $kmInterval,
                'interval_months' => $monthsInterval,
                'trigger_type' => 'mileage',
                'recommended_action' => "Schedule {$servicesText} service",
                'created_at' => date('Y-m-d H:i:s')
            ];
        }
        
        // Check if maintenance is due based on time
        if ($monthsUntilMaintenance <= $monthAlertThreshold) {
            $servicesText = implode(', ', $services);
            $priority = $monthsUntilMaintenance <= 0 ? 'critical' : ($monthsUntilMaintenance <= 1 ? 'high' : 'medium');
            
            $alerts[] = [
                'type' => strtolower(str_replace(' ', '_', $services[0])),
                'priority' => $priority,
                'title' => $monthsInterval . ' Month Service Required',
                'message' => "Vehicle {$vehicle['plate_number']} needs {$servicesText} in {$monthsUntilMaintenance} months",
                'vehicle_id' => $vehicle['id'],
                'vehicle_name' => $vehicle['article'],
                'plate_number' => $vehicle['plate_number'],
                'km_remaining' => $kmUntilMaintenance,
                'months_remaining' => $monthsUntilMaintenance,
                'total_mileage' => $totalMileage,
                'mileage_since_maintenance' => $mileageSinceMaintenance,
                'months_since_maintenance' => $monthsSinceMaintenance,
                'services' => $services,
                'services_text' => $servicesText,
                'interval_km' => $kmInterval,
                'interval_months' => $monthsInterval,
                'trigger_type' => 'time',
                'recommended_action' => "Schedule {$servicesText} service",
                'created_at' => date('Y-m-d H:i:s')
            ];
        }
    }
    
    echo json_encode([
        'success' => true,
        'data' => [
            'vehicle' => $vehicle,
            'total_mileage' => $totalMileage,
            'mileage_since_maintenance' => $mileageSinceMaintenance,
            'last_maintenance' => $lastMaintenance,
            'alerts' => $alerts
        ]
    ]);
}

function getVehicleMaintenanceStatus() {
    global $conn;
    
    $vehicleId = $_GET['vehicle_id'] ?? null;
    if (!$vehicleId) {
        throw new Exception('Vehicle ID is required');
    }
    
    // Get vehicle details
    $vehicleQuery = "SELECT * FROM fleet_vehicles WHERE id = ?";
    $stmt = $conn->prepare($vehicleQuery);
    $stmt->bind_param('i', $vehicleId);
    $stmt->execute();
    $vehicle = $stmt->get_result()->fetch_assoc();
    
    if (!$vehicle) {
        throw new Exception('Vehicle not found');
    }
    
    // Get assigned driver
    $driverQuery = "
        SELECT va.*, u.full_name, u.phone
        FROM vehicle_assignments va
        LEFT JOIN user_table u ON va.driver_id = u.user_id
        WHERE va.vehicle_id = ? AND va.status = 'active'
    ";
    
    $stmt = $conn->prepare($driverQuery);
    $stmt->bind_param('i', $vehicleId);
    $stmt->execute();
    $driver = $stmt->get_result()->fetch_assoc();
    
    // Calculate mileage
    $mileageQuery = "
        SELECT COALESCE(SUM(distance_km), 0) as total_mileage
        FROM trip_logs 
        WHERE vehicle_id = ?
    ";
    
    $stmt = $conn->prepare($mileageQuery);
    $stmt->bind_param('i', $vehicleId);
    $stmt->execute();
    $mileageResult = $stmt->get_result()->fetch_assoc();
    $totalMileage = $mileageResult['total_mileage'] ?? 0;
    
    // Get maintenance history
    $maintenanceQuery = "
        SELECT * FROM maintenance_schedules 
        WHERE vehicle_id = ? 
        ORDER BY scheduled_date DESC
    ";
    
    $stmt = $conn->prepare($maintenanceQuery);
    $stmt->bind_param('i', $vehicleId);
    $stmt->execute();
    $maintenanceHistory = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    
    echo json_encode([
        'success' => true,
        'data' => [
            'vehicle' => $vehicle,
            'driver' => $driver,
            'total_mileage' => $totalMileage,
            'maintenance_history' => $maintenanceHistory
        ]
    ]);
}

function createMaintenanceAlert() {
    global $conn;
    
    $data = json_decode(file_get_contents('php://input'), true);
    
    $requiredFields = ['vehicle_id', 'type', 'title', 'message', 'priority'];
    foreach ($requiredFields as $field) {
        if (!isset($data[$field])) {
            throw new Exception("Missing required field: $field");
        }
    }
    
    // Insert alert into database (you might want to create an alerts table)
    $insertQuery = "
        INSERT INTO maintenance_alerts 
        (vehicle_id, type, title, message, priority, km_remaining, created_at, status)
        VALUES (?, ?, ?, ?, ?, ?, NOW(), 'active')
    ";
    
    $stmt = $conn->prepare($insertQuery);
    $stmt->bind_param('issssi', 
        $data['vehicle_id'],
        $data['type'],
        $data['title'],
        $data['message'],
        $data['priority'],
        $data['km_remaining'] ?? 0
    );
    
    if ($stmt->execute()) {
        echo json_encode(['success' => true, 'message' => 'Alert created successfully']);
    } else {
        throw new Exception('Failed to create alert');
    }
}

function markAlertRead() {
    global $conn;
    
    $alertId = $_POST['alert_id'] ?? null;
    if (!$alertId) {
        throw new Exception('Alert ID is required');
    }
    
    $updateQuery = "UPDATE maintenance_alerts SET status = 'read', read_at = NOW() WHERE id = ?";
    $stmt = $conn->prepare($updateQuery);
    $stmt->bind_param('i', $alertId);
    
    if ($stmt->execute()) {
        echo json_encode(['success' => true, 'message' => 'Alert marked as read']);
    } else {
        throw new Exception('Failed to update alert');
    }
}

function getMaintenanceSchedule() {
    global $conn;
    
    $vehicleId = $_GET['vehicle_id'] ?? null;
    
    $query = "SELECT * FROM maintenance_schedules";
    $params = [];
    $types = "";
    
    if ($vehicleId) {
        $query .= " WHERE vehicle_id = ?";
        $params[] = $vehicleId;
        $types .= "i";
    }
    
    $query .= " ORDER BY scheduled_date DESC";
    
    $stmt = $conn->prepare($query);
    if (!empty($params)) {
        $stmt->bind_param($types, ...$params);
    }
    $stmt->execute();
    $schedules = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    
    echo json_encode(['success' => true, 'data' => $schedules]);
}

function getAllDriverAlerts() {
    global $conn;
    
    // Get all active vehicle assignments
    $stmt = $conn->prepare("
        SELECT va.*, fv.article, fv.plate_number, fv.status as vehicle_status, fv.created_at,
               fv.current_mileage, fv.last_maintenance_mileage, fv.last_maintenance_date
        FROM vehicle_assignments va
        LEFT JOIN fleet_vehicles fv ON va.vehicle_id = fv.id
        WHERE va.status = 'active' AND fv.status = 'active'
    ");
    $stmt->execute();
    $assignments = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    
    $allAlerts = [];
    
    foreach ($assignments as $assignment) {
        $vehicle = [
            'id' => $assignment['vehicle_id'],
            'article' => $assignment['article'],
            'plate_number' => $assignment['plate_number'],
            'status' => $assignment['vehicle_status'],
            'created_at' => $assignment['created_at'],
            'current_mileage' => $assignment['current_mileage'],
            'last_maintenance_mileage' => $assignment['last_maintenance_mileage'],
            'last_maintenance_date' => $assignment['last_maintenance_date']
        ];
        
        $driver = [
            'driver_id' => $assignment['driver_id'],
            'driver_name' => $assignment['driver_name'],
            'phone_number' => $assignment['phone_number']
        ];
        
        // Calculate total mileage from trip logs, fallback to current_mileage from fleet_vehicles
        $mileageQuery = "
            SELECT COALESCE(SUM(distance_km), 0) as total_mileage
            FROM trip_logs 
            WHERE vehicle_id = ?
        ";
        
        $stmt = $conn->prepare($mileageQuery);
        $stmt->bind_param('i', $vehicle['id']);
        $stmt->execute();
        $mileageResult = $stmt->get_result()->fetch_assoc();
        $totalMileage = $mileageResult['total_mileage'] ?? 0;
        
        // If no trip logs, use current_mileage from fleet_vehicles table
        if ($totalMileage == 0 && $vehicle['current_mileage'] > 0) {
            $totalMileage = $vehicle['current_mileage'];
        }
        
        // Get last maintenance record
        $lastMaintenanceQuery = "
            SELECT * FROM maintenance_schedules 
            WHERE vehicle_id = ? 
            ORDER BY scheduled_date DESC 
            LIMIT 1
        ";
        
        $stmt = $conn->prepare($lastMaintenanceQuery);
        $stmt->bind_param('i', $vehicle['id']);
        $stmt->execute();
        $lastMaintenance = $stmt->get_result()->fetch_assoc();
        
        // Calculate mileage since last maintenance
        $mileageSinceMaintenance = $totalMileage;
        if ($lastMaintenance) {
            $lastMaintenanceDate = $lastMaintenance['scheduled_date'];
            $mileageSinceMaintenanceQuery = "
                SELECT COALESCE(SUM(distance_km), 0) as mileage_since
                FROM trip_logs 
                WHERE vehicle_id = ? AND start_time >= ?
            ";
            
            $stmt = $conn->prepare($mileageSinceMaintenanceQuery);
            $stmt->bind_param('is', $vehicle['id'], $lastMaintenanceDate);
            $stmt->execute();
            $mileageSinceResult = $stmt->get_result()->fetch_assoc();
            $mileageSinceMaintenance = $mileageSinceResult['mileage_since'] ?? 0;
            
            // If no trip logs since maintenance, calculate from current mileage and last maintenance mileage
            if ($mileageSinceMaintenance == 0 && $vehicle['last_maintenance_mileage'] > 0) {
                $mileageSinceMaintenance = $totalMileage - $vehicle['last_maintenance_mileage'];
            }
        } else {
            // If no maintenance record, use total mileage
            $mileageSinceMaintenance = $totalMileage;
        }
        
        // Generate alerts based on your custom maintenance schedule
        $alerts = [];
        
        // Your maintenance schedule (km intervals and months)
        $maintenanceSchedule = [
            5000 => ['months' => 3, 'services' => ['CHANGE OIL']],
            10000 => ['months' => 6, 'services' => ['CHANGE OIL', 'TIRE ROTATION']],
            15000 => ['months' => 9, 'services' => ['CHANGE OIL']],
            20000 => ['months' => 12, 'services' => ['CHANGE OIL', 'TIRE ROTATION', 'WHEEL BALANCE', 'ALIGNMENT', 'BRAKE INSPECTION']],
            25000 => ['months' => 15, 'services' => ['CHANGE OIL']],
            30000 => ['months' => 18, 'services' => ['CHANGE OIL', 'TIRE ROTATION']],
            35000 => ['months' => 21, 'services' => ['CHANGE OIL']],
            40000 => ['months' => 24, 'services' => ['CHANGE OIL', 'TIRE ROTATION', 'WHEEL BALANCE', 'ALIGNMENT', 'BRAKE INSPECTION', 'COOLING SYSTEM']],
            45000 => ['months' => 27, 'services' => ['CHANGE OIL', 'ENGINE TUNE UP']],
            50000 => ['months' => 30, 'services' => ['CHANGE OIL', 'TIRE ROTATION']],
            55000 => ['months' => 33, 'services' => ['CHANGE OIL']],
            60000 => ['months' => 36, 'services' => ['CHANGE OIL', 'TIRE ROTATION', 'WHEEL BALANCE', 'ALIGNMENT', 'BRAKE INSPECTION']],
            65000 => ['months' => 39, 'services' => ['CHANGE OIL']],
            70000 => ['months' => 42, 'services' => ['CHANGE OIL', 'TIRE ROTATION']],
            75000 => ['months' => 45, 'services' => ['CHANGE OIL']],
            80000 => ['months' => 48, 'services' => ['CHANGE OIL', 'TIRE ROTATION', 'WHEEL BALANCE', 'ALIGNMENT', 'BRAKE INSPECTION', 'COOLING SYSTEM']],
            85000 => ['months' => 51, 'services' => ['CHANGE OIL', 'ENGINE TUNE UP']],
            90000 => ['months' => 54, 'services' => ['CHANGE OIL', 'TIRE ROTATION']],
            95000 => ['months' => 57, 'services' => ['CHANGE OIL']],
            100000 => ['months' => 60, 'services' => ['CHANGE OIL', 'TIRE ROTATION', 'WHEEL BALANCE', 'ALIGNMENT', 'BRAKE INSPECTION']]
        ];
        
        // Calculate time since last maintenance
        $monthsSinceMaintenance = 0;
        if ($lastMaintenance) {
            $lastMaintenanceDate = new DateTime($lastMaintenance['scheduled_date']);
            $currentDate = new DateTime();
            $monthsSinceMaintenance = $lastMaintenanceDate->diff($currentDate)->m + ($lastMaintenanceDate->diff($currentDate)->y * 12);
        } else {
            // If no maintenance record, use vehicle creation date
            $vehicleCreatedDate = new DateTime($vehicle['created_at']);
            $currentDate = new DateTime();
            $monthsSinceMaintenance = $vehicleCreatedDate->diff($currentDate)->m + ($vehicleCreatedDate->diff($currentDate)->y * 12);
        }
        
        // Check each maintenance interval
        foreach ($maintenanceSchedule as $kmInterval => $schedule) {
            $monthsInterval = $schedule['months'];
            $services = $schedule['services'];
            
            // Calculate next maintenance based on mileage
            $nextMaintenanceKm = ceil($mileageSinceMaintenance / $kmInterval) * $kmInterval;
            $kmUntilMaintenance = $nextMaintenanceKm - $mileageSinceMaintenance;
            
            // Calculate next maintenance based on time
            $nextMaintenanceMonths = ceil($monthsSinceMaintenance / $monthsInterval) * $monthsInterval;
            $monthsUntilMaintenance = $nextMaintenanceMonths - $monthsSinceMaintenance;
            
            // Set alert thresholds
            $kmAlertThreshold = $kmInterval <= 10000 ? 500 : 1000; // Smaller intervals get smaller thresholds
            $monthAlertThreshold = $monthsInterval <= 6 ? 1 : 2; // Smaller intervals get smaller thresholds
            
            // Check if maintenance is due based on mileage
            if ($kmUntilMaintenance <= $kmAlertThreshold) {
                $servicesText = implode(', ', $services);
                $priority = $kmUntilMaintenance <= 100 ? 'critical' : ($kmUntilMaintenance <= 300 ? 'high' : 'medium');
                
                $alerts[] = [
                    'type' => strtolower(str_replace(' ', '_', $services[0])),
                    'priority' => $priority,
                    'title' => $kmInterval . ' KM Service Required',
                    'message' => "Vehicle {$vehicle['plate_number']} needs {$servicesText} in {$kmUntilMaintenance} km",
                    'vehicle_id' => $vehicle['id'],
                    'vehicle_name' => $vehicle['article'],
                    'plate_number' => $vehicle['plate_number'],
                    'driver_id' => $driver['driver_id'],
                    'driver_name' => $driver['driver_name'],
                    'phone_number' => $driver['phone_number'],
                    'km_remaining' => $kmUntilMaintenance,
                    'months_remaining' => $monthsUntilMaintenance,
                    'total_mileage' => $totalMileage,
                    'mileage_since_maintenance' => $mileageSinceMaintenance,
                    'months_since_maintenance' => $monthsSinceMaintenance,
                    'services' => $services,
                    'services_text' => $servicesText,
                    'interval_km' => $kmInterval,
                    'interval_months' => $monthsInterval,
                    'trigger_type' => 'mileage',
                    'recommended_action' => "Schedule {$servicesText} service",
                    'created_at' => date('Y-m-d H:i:s')
                ];
            }
            
            // Check if maintenance is due based on time
            if ($monthsUntilMaintenance <= $monthAlertThreshold) {
                $servicesText = implode(', ', $services);
                $priority = $monthsUntilMaintenance <= 0 ? 'critical' : ($monthsUntilMaintenance <= 1 ? 'high' : 'medium');
                
                $alerts[] = [
                    'type' => strtolower(str_replace(' ', '_', $services[0])),
                    'priority' => $priority,
                    'title' => $monthsInterval . ' Month Service Required',
                    'message' => "Vehicle {$vehicle['plate_number']} needs {$servicesText} in {$monthsUntilMaintenance} months",
                    'vehicle_id' => $vehicle['id'],
                    'vehicle_name' => $vehicle['article'],
                    'plate_number' => $vehicle['plate_number'],
                    'driver_id' => $driver['driver_id'],
                    'driver_name' => $driver['driver_name'],
                    'phone_number' => $driver['phone_number'],
                    'km_remaining' => $kmUntilMaintenance,
                    'months_remaining' => $monthsUntilMaintenance,
                    'total_mileage' => $totalMileage,
                    'mileage_since_maintenance' => $mileageSinceMaintenance,
                    'months_since_maintenance' => $monthsSinceMaintenance,
                    'services' => $services,
                    'services_text' => $servicesText,
                    'interval_km' => $kmInterval,
                    'interval_months' => $monthsInterval,
                    'trigger_type' => 'time',
                    'recommended_action' => "Schedule {$servicesText} service",
                    'created_at' => date('Y-m-d H:i:s')
                ];
            }
        }
        
        // Add alerts to the main array
        $allAlerts = array_merge($allAlerts, $alerts);
    }
    
    echo json_encode([
        'success' => true,
        'data' => $allAlerts
    ]);
}
?>
