-- Create gps_devices table if it doesn't exist
-- This table stores device information and current status

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

-- Add foreign key constraint if fleet_vehicles table exists
-- ALTER TABLE `gps_devices` 
-- ADD CONSTRAINT `fk_gps_devices_vehicle_id` 
-- FOREIGN KEY (`vehicle_id`) REFERENCES `fleet_vehicles` (`id`) 
-- ON DELETE SET NULL ON UPDATE CASCADE;
