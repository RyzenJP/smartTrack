<?php
require_once __DIR__ . '/db_connection.php'; // adjust path

try {
    // Start transaction
    $conn->begin_transaction();

    // Step 1: Insert old logs into archive (older than 30 days) - use prepared statement for consistency
    $insertSql = "INSERT INTO gps_logs_archive (device_id, latitude, longitude, timestamp, is_deleted)
                  SELECT device_id, latitude, longitude, timestamp, is_deleted
                  FROM gps_logs
                  WHERE timestamp < NOW() - INTERVAL 30 DAY";
    $insert_stmt = $conn->prepare($insertSql);
    $insert_stmt->execute();
    $insertedRows = $conn->affected_rows;
    $insert_stmt->close();

    // Step 2: Delete the same old logs from main table - use prepared statement for consistency
    $deleteSql = "DELETE FROM gps_logs
                  WHERE timestamp < NOW() - INTERVAL 30 DAY";
    $delete_stmt = $conn->prepare($deleteSql);
    $delete_stmt->execute();
    $deletedRows = $conn->affected_rows;
    $delete_stmt->close();

    // Commit transaction
    $conn->commit();

    echo "Archived $insertedRows rows and deleted $deletedRows rows successfully.";

} catch (Exception $e) {
    $conn->rollback();
    echo "Error: " . $e->getMessage();
}

$conn->close();
