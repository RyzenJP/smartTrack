-- Smart Track Mobile App Database Setup
-- This script creates the necessary tables for the mobile tracking app

-- 1. Create gps_devices table (if it doesn't exist)
CREATE TABLE IF NOT EXISTS `gps_devices` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `device_id` varchar(50) NOT NULL,
  `imei` varchar(20) DEFAULT NULL,
  `device_name` varchar(100) NOT NULL,
  `device_type` enum('esp32','mobile','gps_tracker') DEFAULT 'mobile',
  `api_key` varchar(255) DEFAULT NULL,
  `vehicle_id` int(11) DEFAULT NULL,
  `status` enum('active','inactive','maintenance') DEFAULT 'active',
  `model` varchar(50) DEFAULT NULL,
  `manufacturer` varchar(50) DEFAULT NULL,
  `purchase_date` date DEFAULT NULL,
  `last_known_latitude` double DEFAULT NULL,
  `last_known_longitude` double DEFAULT NULL,
  `last_known_speed` decimal(5,2) DEFAULT NULL,
  `last_known_battery_level` int(3) DEFAULT NULL,
  `last_activity_at` datetime DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `device_id` (`device_id`),
  UNIQUE KEY `api_key` (`api_key`),
  KEY `vehicle_id` (`vehicle_id`),
  KEY `status` (`status`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- 2. Verify gps_logs table structure matches your existing schema
-- Your existing gps_logs table should have these columns:
-- id, device_id, imei, vehicle_id, status, last_update, battery_level, 
-- created_at, updated_at, lat, lng, speed

-- 3. Add indexes for better performance (if they don't exist)
ALTER TABLE `gps_logs` 
ADD INDEX IF NOT EXISTS `idx_device_timestamp` (`device_id`, `created_at`),
ADD INDEX IF NOT EXISTS `idx_vehicle_id` (`vehicle_id`),
ADD INDEX IF NOT EXISTS `idx_status` (`status`);

-- 4. Add foreign key constraints (uncomment if you have fleet_vehicles table)
-- ALTER TABLE `gps_devices` 
-- ADD CONSTRAINT `fk_gps_devices_vehicle_id` 
-- FOREIGN KEY (`vehicle_id`) REFERENCES `fleet_vehicles` (`id`) 
-- ON DELETE SET NULL ON UPDATE CASCADE;

-- 5. Insert sample mobile device (optional)
INSERT IGNORE INTO `gps_devices` (
  `device_id`, 
  `device_name`, 
  `device_type`, 
  `api_key`, 
  `status`
) VALUES (
  'MOBILE-SAMPLE-001', 
  'Sample Mobile Device', 
  'mobile', 
  'sample-api-key-12345', 
  'active'
);

-- 6. Create a view for easy device monitoring (optional)
CREATE OR REPLACE VIEW `device_status_view` AS
SELECT 
  d.id,
  d.device_id,
  d.device_name,
  d.device_type,
  d.status,
  d.last_known_latitude,
  d.last_known_longitude,
  d.last_known_speed,
  d.last_known_battery_level,
  d.last_activity_at,
  CASE 
    WHEN d.last_activity_at IS NULL THEN 'Never'
    WHEN d.last_activity_at < DATE_SUB(NOW(), INTERVAL 5 MINUTE) THEN 'Offline'
    ELSE 'Online'
  END as connection_status,
  TIMESTAMPDIFF(MINUTE, d.last_activity_at, NOW()) as minutes_since_last_update
FROM `gps_devices` d
ORDER BY d.last_activity_at DESC;

-- 7. Create a view for recent GPS logs (optional)
CREATE OR REPLACE VIEW `recent_gps_logs` AS
SELECT 
  l.id,
  l.device_id,
  l.imei,
  l.vehicle_id,
  l.status,
  l.lat as latitude,
  l.lng as longitude,
  l.speed,
  l.battery_level,
  l.last_update,
  l.created_at,
  d.device_name,
  d.device_type
FROM `gps_logs` l
LEFT JOIN `gps_devices` d ON l.device_id = d.device_id
WHERE l.created_at >= DATE_SUB(NOW(), INTERVAL 24 HOUR)
ORDER BY l.created_at DESC;
