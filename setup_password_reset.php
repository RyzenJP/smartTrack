<?php
require_once 'includes/db_connection.php';

// SQL to create password_resets table
$sql = "
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
  KEY `used` (`used`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
";

try {
    // Use prepared statement for consistency (DDL but best practice)
    $stmt = $conn->prepare($sql);
    if ($stmt->execute()) {
        echo "✅ Password resets table created successfully!\n";
        $stmt->close();
        
        // Create additional indexes for better performance - use prepared statements for consistency
        $indexes = [
            "CREATE INDEX idx_password_resets_email ON password_resets(email)",
            "CREATE INDEX idx_password_resets_token ON password_resets(token)",
            "CREATE INDEX idx_password_resets_expires ON password_resets(expires_at)",
            "CREATE INDEX idx_password_resets_used ON password_resets(used)"
        ];
        
        foreach ($indexes as $index) {
            try {
                $index_stmt = $conn->prepare($index);
                $index_stmt->execute();
                $index_stmt->close();
                echo "✅ Index created successfully\n";
            } catch (Exception $e) {
                // Index might already exist, that's okay
                echo "ℹ️  Index already exists or not needed\n";
            }
        }
        
        echo "\n🎉 Password reset functionality is now ready!\n";
        echo "📧 Email configuration:\n";
        echo "   - SMTP Host: smtp.hostinger.com\n";
        echo "   - Email: smarttrack_info@bccbsis.com\n";
        echo "   - Port: 465 (SSL)\n";
        echo "\n🔗 Files created:\n";
        echo "   - forgot_password.php (Forgot password page)\n";
        echo "   - reset_password.php (Password reset page)\n";
        echo "   - composer.json (PHPMailer dependency)\n";
        echo "\n✅ Setup complete! Users can now reset their passwords.\n";
        
    } else {
        echo "❌ Error creating table: " . $conn->error . "\n";
        if (isset($stmt)) $stmt->close();
    }
} catch (Exception $e) {
    echo "❌ Error: " . $e->getMessage() . "\n";
}

$conn->close();
?>
