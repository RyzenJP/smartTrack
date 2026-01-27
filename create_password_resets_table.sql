-- Create password_resets table for storing password reset tokens
CREATE TABLE IF NOT EXISTS `password_resets` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `email` varchar(255) NOT NULL,
  `token` varchar(255) NOT NULL,
  `user_type` varchar(50) NOT NULL,
  `user_id` varchar(50) NOT NULL,
  `expires_at` datetime NOT NULL,
  `used` tinyint(1) NOT NULL DEFAULT 0,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `token` (`token`),
  KEY `email` (`email`),
  KEY `expires_at` (`expires_at`),
  KEY `used` (`used`),
  FOREIGN KEY (`user_id`) REFERENCES `user_table`(`user_id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- Add indexes for better performance
CREATE INDEX idx_password_resets_email ON password_resets(email);
CREATE INDEX idx_password_resets_token ON password_resets(token);
CREATE INDEX idx_password_resets_expires ON password_resets(expires_at);
CREATE INDEX idx_password_resets_used ON password_resets(used);
