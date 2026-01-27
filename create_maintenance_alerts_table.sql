-- Create maintenance_alerts table
CREATE TABLE IF NOT EXISTS `maintenance_alerts` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `vehicle_id` int(11) NOT NULL,
  `driver_id` int(11) DEFAULT NULL,
  `type` enum('oil_change','general_maintenance','major_service','inspection','repair') NOT NULL,
  `title` varchar(255) NOT NULL,
  `message` text NOT NULL,
  `priority` enum('low','medium','high','critical') NOT NULL,
  `km_remaining` int(11) DEFAULT 0,
  `status` enum('active','read','dismissed','resolved') DEFAULT 'active',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `read_at` timestamp NULL DEFAULT NULL,
  `dismissed_at` timestamp NULL DEFAULT NULL,
  `resolved_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `vehicle_id` (`vehicle_id`),
  KEY `driver_id` (`driver_id`),
  KEY `status` (`status`),
  KEY `priority` (`priority`),
  CONSTRAINT `maintenance_alerts_ibfk_1` FOREIGN KEY (`vehicle_id`) REFERENCES `fleet_vehicles` (`id`) ON DELETE CASCADE,
  CONSTRAINT `maintenance_alerts_ibfk_2` FOREIGN KEY (`driver_id`) REFERENCES `user_table` (`user_id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- Add mileage tracking fields to fleet_vehicles table
ALTER TABLE `fleet_vehicles` 
ADD COLUMN `current_mileage` int(11) DEFAULT 0 COMMENT 'Current odometer reading',
ADD COLUMN `last_maintenance_mileage` int(11) DEFAULT 0 COMMENT 'Mileage at last maintenance',
ADD COLUMN `last_maintenance_date` date DEFAULT NULL COMMENT 'Date of last maintenance',
ADD COLUMN `next_oil_change_mileage` int(11) DEFAULT 5000 COMMENT 'Mileage for next oil change',
ADD COLUMN `next_general_maintenance_mileage` int(11) DEFAULT 10000 COMMENT 'Mileage for next general maintenance',
ADD COLUMN `next_major_service_mileage` int(11) DEFAULT 20000 COMMENT 'Mileage for next major service';

-- Create maintenance_schedules table if it doesn't exist
CREATE TABLE IF NOT EXISTS `maintenance_schedules` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `vehicle_id` int(11) NOT NULL,
  `type` enum('oil_change','general_maintenance','major_service','inspection','repair') NOT NULL,
  `title` varchar(255) NOT NULL,
  `description` text DEFAULT NULL,
  `scheduled_date` date NOT NULL,
  `scheduled_mileage` int(11) DEFAULT NULL,
  `status` enum('scheduled','in_progress','completed','cancelled') DEFAULT 'scheduled',
  `assigned_to` int(11) DEFAULT NULL,
  `created_by` int(11) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `vehicle_id` (`vehicle_id`),
  KEY `assigned_to` (`assigned_to`),
  KEY `created_by` (`created_by`),
  CONSTRAINT `maintenance_schedules_ibfk_1` FOREIGN KEY (`vehicle_id`) REFERENCES `fleet_vehicles` (`id`) ON DELETE CASCADE,
  CONSTRAINT `maintenance_schedules_ibfk_2` FOREIGN KEY (`assigned_to`) REFERENCES `user_table` (`user_id`) ON DELETE SET NULL,
  CONSTRAINT `maintenance_schedules_ibfk_3` FOREIGN KEY (`created_by`) REFERENCES `user_table` (`user_id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
