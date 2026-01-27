-- Emergency Maintenance Table
-- This is separate from the predictive maintenance system
-- Handles urgent maintenance requests from drivers to mechanics

CREATE TABLE emergency_maintenance (
    id INT PRIMARY KEY AUTO_INCREMENT,
    vehicle_id INT NOT NULL,
    driver_id INT NOT NULL,
    mechanic_id INT NULL,
    
    -- Request Details
    issue_title VARCHAR(255) NOT NULL,
    issue_description TEXT NOT NULL,
    urgency_level ENUM('LOW', 'MEDIUM', 'HIGH', 'CRITICAL') DEFAULT 'MEDIUM',
    location VARCHAR(500) NULL,
    
    -- Status Management
    status ENUM('pending', 'assigned', 'in_progress', 'completed', 'cancelled') DEFAULT 'pending',
    
    -- Timestamps
    requested_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    assigned_at TIMESTAMP NULL,
    started_at TIMESTAMP NULL,
    completed_at TIMESTAMP NULL,
    
    -- Additional Information
    driver_phone VARCHAR(20) NULL,
    estimated_cost DECIMAL(10,2) NULL,
    actual_cost DECIMAL(10,2) NULL,
    mechanic_notes TEXT NULL,
    completion_notes TEXT NULL,
    
    -- Images/Files (optional)
    image_path VARCHAR(500) NULL,
    
    -- Timestamps for tracking
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    
    -- Foreign Keys
    FOREIGN KEY (vehicle_id) REFERENCES fleet_vehicles(id) ON DELETE CASCADE,
    FOREIGN KEY (driver_id) REFERENCES user_table(user_id) ON DELETE CASCADE,
    FOREIGN KEY (mechanic_id) REFERENCES user_table(user_id) ON DELETE SET NULL,
    
    -- Indexes for performance
    INDEX idx_vehicle_id (vehicle_id),
    INDEX idx_driver_id (driver_id),
    INDEX idx_mechanic_id (mechanic_id),
    INDEX idx_status (status),
    INDEX idx_urgency_level (urgency_level),
    INDEX idx_requested_at (requested_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- Insert some sample data (optional)
INSERT INTO emergency_maintenance (
    vehicle_id, driver_id, issue_title, issue_description, urgency_level, location, driver_phone
) VALUES 
(1, 1, 'Engine Overheating', 'The engine temperature gauge is showing red and steam is coming from under the hood. Vehicle stopped on the roadside.', 'CRITICAL', 'Highway 101, Mile Marker 45', '09940830175'),
(2, 2, 'Flat Tire', 'Front left tire is completely flat. Cannot continue driving safely.', 'HIGH', 'Main Street, Downtown', '09123456789'),
(3, 3, 'Battery Dead', 'Vehicle won\'t start. Dashboard lights are dim. Suspect battery issue.', 'MEDIUM', 'Office Parking Lot', '09876543210');
