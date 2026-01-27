<?php

namespace Tests\Unit;

use PHPUnit\Framework\TestCase;

/**
 * Advanced Security Class Unit Tests
 * Additional tests for Security class methods not covered in SecurityTest
 */
class SecurityAdvancedTest extends TestCase
{
    private $security;

    protected function setUp(): void
    {
        // Start session for CSRF and rate limiting tests
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }
        $this->security = \Security::getInstance();
    }

    protected function tearDown(): void
    {
        // Clean up session
        $_SESSION = [];
    }

    public function testValidateInputInteger()
    {
        $this->assertTrue($this->security->validateInput('123', 'int'));
        $this->assertTrue($this->security->validateInput('0', 'int'));
        $this->assertFalse($this->security->validateInput('abc', 'int'));
        $this->assertFalse($this->security->validateInput('12.5', 'int'));
    }

    public function testValidateInputIntegerWithMinMax()
    {
        $this->assertTrue($this->security->validateInput('50', 'int', ['min' => 0, 'max' => 100]));
        $this->assertFalse($this->security->validateInput('150', 'int', ['min' => 0, 'max' => 100]));
        $this->assertFalse($this->security->validateInput('-10', 'int', ['min' => 0, 'max' => 100]));
    }

    public function testValidateInputFloat()
    {
        $this->assertTrue($this->security->validateInput('123.45', 'float'));
        $this->assertTrue($this->security->validateInput('0.5', 'float'));
        $this->assertFalse($this->security->validateInput('abc', 'float'));
    }

    public function testValidateInputEmail()
    {
        $this->assertTrue($this->security->validateInput('test@example.com', 'email'));
        $this->assertFalse($this->security->validateInput('invalid-email', 'email'));
        $this->assertFalse($this->security->validateInput('test@', 'email'));
    }

    public function testValidateInputUsername()
    {
        $this->assertTrue($this->security->validateInput('user123', 'username'));
        $this->assertTrue($this->security->validateInput('user_name', 'username'));
        $this->assertFalse($this->security->validateInput('ab', 'username')); // Too short
        $this->assertFalse($this->security->validateInput('user@name', 'username')); // Invalid char
    }

    public function testValidateInputPhone()
    {
        $this->assertTrue($this->security->validateInput('09123456789', 'phone'));
        $this->assertFalse($this->security->validateInput('1234567890', 'phone')); // Wrong format
        $this->assertFalse($this->security->validateInput('0912345678', 'phone')); // Too short
    }

    public function testValidateInputPassword()
    {
        $this->assertTrue($this->security->validateInput('SecurePass123!', 'password'));
        $this->assertFalse($this->security->validateInput('short', 'password')); // Too short
        $this->assertTrue($this->security->validateInput('LongEnoughPassword', 'password', ['min_length' => 8]));
    }

    public function testValidateInputStringWithLength()
    {
        $this->assertTrue($this->security->validateInput('test', 'string', ['min_length' => 2, 'max_length' => 10]));
        $this->assertFalse($this->security->validateInput('a', 'string', ['min_length' => 2]));
        $this->assertFalse($this->security->validateInput('this is too long', 'string', ['max_length' => 10]));
    }

    public function testValidateInputRequired()
    {
        $this->assertTrue($this->security->validateInput('value', 'string', ['required' => true]));
        $this->assertFalse($this->security->validateInput('', 'string', ['required' => true]));
        $this->assertTrue($this->security->validateInput('', 'string', ['required' => false]));
    }

    public function testGetGet()
    {
        $_GET['test_key'] = '<script>alert("xss")</script>test';
        $value = $this->security->getGet('test_key', 'string');
        
        $this->assertStringNotContainsString('<script>', $value);
        $this->assertStringContainsString('test', $value);
    }

    public function testGetPost()
    {
        $_POST['test_key'] = 'test_value';
        $value = $this->security->getPost('test_key', 'string');
        
        $this->assertEquals('test_value', $value);
    }

    public function testGetPostWithDefault()
    {
        unset($_POST['non_existent']);
        $value = $this->security->getPost('non_existent', 'string', 'default_value');
        
        $this->assertEquals('default_value', $value);
    }

    public function testGetRequest()
    {
        $_REQUEST['test_key'] = 'test_value';
        $value = $this->security->getRequest('test_key', 'string');
        
        $this->assertEquals('test_value', $value);
    }

    public function testSanitizeArray()
    {
        $inputs = [
            'name' => '<script>alert("xss")</script>John',
            'age' => '25',
            'email' => 'test@example.com'
        ];
        
        $rules = [
            'name' => ['type' => 'string'],
            'age' => ['type' => 'int'],
            'email' => ['type' => 'email']
        ];
        
        $sanitized = $this->security->sanitizeArray($inputs, $rules);
        
        $this->assertStringNotContainsString('<script>', $sanitized['name']);
        $this->assertEquals('25', $sanitized['age']);
        $this->assertEquals('test@example.com', $sanitized['email']);
    }

    public function testEscapeOutput()
    {
        $output = '<script>alert("xss")</script>Hello';
        $escaped = $this->security->escapeOutput($output);
        
        $this->assertStringNotContainsString('<script>', $escaped);
        $this->assertStringContainsString('Hello', $escaped);
    }

    public function testValidateTableName()
    {
        $this->assertTrue($this->security->validateTableName('users'));
        $this->assertTrue($this->security->validateTableName('user_table'));
        $this->assertTrue($this->security->validateTableName('table123'));
        $this->assertFalse($this->security->validateTableName('table-name'));
        $this->assertFalse($this->security->validateTableName('table name'));
        $this->assertFalse($this->security->validateTableName('table; DROP TABLE users; --'));
    }

    public function testValidateColumnName()
    {
        $this->assertTrue($this->security->validateColumnName('user_id'));
        $this->assertTrue($this->security->validateColumnName('column123'));
        $this->assertFalse($this->security->validateColumnName('column-name'));
        $this->assertFalse($this->security->validateColumnName('column name'));
    }

    public function testValidateCSRFToken()
    {
        $token = $this->security->generateCSRFToken();
        
        $this->assertTrue($this->security->validateCSRFToken($token));
        $this->assertFalse($this->security->validateCSRFToken('invalid_token'));
    }

    public function testCheckRateLimit()
    {
        // First request should pass
        $this->assertTrue($this->security->checkRateLimit('test_action', 5, 60));
        
        // Make 4 more requests (total 5)
        for ($i = 0; $i < 4; $i++) {
            $this->assertTrue($this->security->checkRateLimit('test_action', 5, 60));
        }
        
        // 6th request should fail
        $this->assertFalse($this->security->checkRateLimit('test_action', 5, 60));
    }

    public function testCheckRateLimitDifferentActions()
    {
        // Different actions should have separate limits
        $this->assertTrue($this->security->checkRateLimit('action1', 5, 60));
        $this->assertTrue($this->security->checkRateLimit('action2', 5, 60));
    }
}

