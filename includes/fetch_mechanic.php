<?php
include 'db_connection.php';

$search = isset($_GET['search']) ? trim($_GET['search']) : '';

// Use prepared statement for security - real_escape_string is not enough
$searchPattern = "%$search%";
$sql = "SELECT * FROM user_table
        WHERE role = 'Mechanic'
        AND (
            full_name LIKE ? 
            OR username LIKE ? 
            OR email LIKE ? 
            OR phone LIKE ?
        )
        ORDER BY created_at DESC";

$stmt = $conn->prepare($sql);
$stmt->bind_param("ssss", $searchPattern, $searchPattern, $searchPattern, $searchPattern);
$stmt->execute();
$result = $stmt->get_result();

if ($result && $result->num_rows > 0) {
    while ($row = $result->fetch_assoc()) {
        $statusBadge = $row['status'] === 'Active'
            ? '<span class="badge bg-success">Active</span>'
            : '<span class="badge bg-secondary">Inactive</span>';

        echo "<tr>
                <td>" . htmlspecialchars($row['full_name']) . "</td>
                <td>" . htmlspecialchars($row['username']) . "</td>
                <td>" . htmlspecialchars($row['email']) . "</td>
                <td>" . htmlspecialchars($row['phone']) . "</td>
                <td>$statusBadge</td>
                <td class='text-nowrap'>" . date('Y-m-d h:i A', strtotime($row['created_at'])) . "</td>
                <td class='text-nowrap'>" . date('Y-m-d h:i A', strtotime($row['updated_at'])) . "</td>
                <td class='text-nowrap'>" . ($row['last_login'] ? date('Y-m-d h:i A', strtotime($row['last_login'])) : 'Never') . "</td>
                <td class='text-center'>
                  <button class='btn btn-sm me-1' style='background-color: #00b4d8; color: #fff;' data-bs-toggle='tooltip' data-bs-placement='top' title='Edit'>
                      <i class='fas fa-edit'></i>
                  </button>
                  <button class='btn btn-sm' style='background-color: #dc3545; color: #fff;' data-bs-toggle='tooltip' data-bs-placement='top' title='Delete'>
                      <i class='fas fa-trash-alt'></i>
                  </button>
                </td>
              </tr>";
    }
} else {
    echo "<tr><td colspan='9' class='text-center text-muted'>No results found.</td></tr>";
}
$stmt->close();
?>