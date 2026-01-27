<?php

namespace Tests\Integration;

use PHPUnit\Framework\TestCase;

/**
 * Database Integration Tests
 * Tests database connectivity and operations
 */
class DatabaseTest extends TestCase
{
    private $conn;

    protected function setUp(): void
    {
        $this->conn = setupTestDatabase();
        $this->assertNotNull($this->conn, 'Failed to connect to test database');
    }

    protected function tearDown(): void
    {
        if ($this->conn) {
            $this->conn->close();
        }
    }

    public function testDatabaseConnection()
    {
        $this->assertInstanceOf(\mysqli::class, $this->conn);
        $this->assertTrue($this->conn->ping(), 'Database connection is not alive');
    }

    public function testCreateTable()
    {
        $sql = "CREATE TABLE IF NOT EXISTS test_table (
            id INT AUTO_INCREMENT PRIMARY KEY,
            name VARCHAR(255) NOT NULL,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
        )";
        
        $result = $this->conn->query($sql);
        $this->assertTrue($result, 'Failed to create test table');
    }

    public function testInsertData()
    {
        // Create table first
        $this->testCreateTable();
        
        // Insert data using prepared statement
        $stmt = $this->conn->prepare("INSERT INTO test_table (name) VALUES (?)");
        $name = 'Test Name';
        $stmt->bind_param("s", $name);
        $result = $stmt->execute();
        
        $this->assertTrue($result, 'Failed to insert data');
        $this->assertGreaterThan(0, $stmt->insert_id);
        $stmt->close();
    }

    public function testSelectData()
    {
        // Create and insert data
        $this->testInsertData();
        
        // Select data
        $stmt = $this->conn->prepare("SELECT * FROM test_table WHERE name = ?");
        $name = 'Test Name';
        $stmt->bind_param("s", $name);
        $stmt->execute();
        $result = $stmt->get_result();
        
        $this->assertGreaterThan(0, $result->num_rows);
        
        $row = $result->fetch_assoc();
        $this->assertEquals('Test Name', $row['name']);
        
        $stmt->close();
    }

    public function testUpdateData()
    {
        // Create and insert data
        $this->testInsertData();
        
        // Update data
        $stmt = $this->conn->prepare("UPDATE test_table SET name = ? WHERE name = ?");
        $newName = 'Updated Name';
        $oldName = 'Test Name';
        $stmt->bind_param("ss", $newName, $oldName);
        $result = $stmt->execute();
        
        $this->assertTrue($result, 'Failed to update data');
        $this->assertGreaterThan(0, $stmt->affected_rows);
        $stmt->close();
    }

    public function testDeleteData()
    {
        // Create and insert data
        $this->testInsertData();
        
        // Delete data
        $stmt = $this->conn->prepare("DELETE FROM test_table WHERE name = ?");
        $name = 'Test Name';
        $stmt->bind_param("s", $name);
        $result = $stmt->execute();
        
        $this->assertTrue($result, 'Failed to delete data');
        $stmt->close();
    }

    public function testTransaction()
    {
        $this->testCreateTable();
        
        // Start transaction
        $this->conn->begin_transaction();
        
        try {
            $stmt = $this->conn->prepare("INSERT INTO test_table (name) VALUES (?)");
            $name = 'Transaction Test';
            $stmt->bind_param("s", $name);
            $stmt->execute();
            $stmt->close();
            
            // Commit transaction
            $this->conn->commit();
            
            // Verify data was inserted
            $stmt = $this->conn->prepare("SELECT * FROM test_table WHERE name = ?");
            $stmt->bind_param("s", $name);
            $stmt->execute();
            $result = $stmt->get_result();
            
            $this->assertEquals(1, $result->num_rows);
            $stmt->close();
        } catch (\Exception $e) {
            $this->conn->rollback();
            $this->fail('Transaction failed: ' . $e->getMessage());
        }
    }

    public function testPreparedStatementPreventsInjection()
    {
        $this->testCreateTable();
        
        // Attempt SQL injection
        $maliciousInput = "'; DROP TABLE test_table; --";
        
        $stmt = $this->conn->prepare("SELECT * FROM test_table WHERE name = ?");
        $stmt->bind_param("s", $maliciousInput);
        $stmt->execute();
        $result = $stmt->get_result();
        
        // Should return 0 rows, not cause an error or drop the table
        $this->assertEquals(0, $result->num_rows);
        $stmt->close();
        
        // Verify table still exists
        $tableCheck = $this->conn->query("SHOW TABLES LIKE 'test_table'");
        $this->assertEquals(1, $tableCheck->num_rows);
    }
}

