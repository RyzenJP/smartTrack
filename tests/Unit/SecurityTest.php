<?php

namespace Tests\Unit;

use PHPUnit\Framework\TestCase;

/**
 * Security Class Unit Tests
 * Tests for the Security helper class
 */
class SecurityTest extends TestCase
{
    private $security;

    protected function setUp(): void
    {
        $this->security = \Security::getInstance();
    }

    public function testSecurityClassExists()
    {
        $this->assertInstanceOf(\Security::class, $this->security);
    }

    public function testSanitizeInputString()
    {
        $input = '<script>alert("xss")</script>Hello';
        $sanitized = $this->security->sanitizeInput($input, 'string');
        
        $this->assertStringNotContainsString('<script>', $sanitized);
        $this->assertStringContainsString('Hello', $sanitized);
    }

    public function testSanitizeInputInteger()
    {
        $input = '123abc';
        $sanitized = $this->security->sanitizeInput($input, 'int');
        
        $this->assertEquals('123', $sanitized);
    }

    public function testSanitizeInputEmail()
    {
        $input = 'test@example.com<script>';
        $sanitized = $this->security->sanitizeInput($input, 'email');
        
        $this->assertEquals('test@example.com', $sanitized);
        $this->assertStringNotContainsString('<script>', $sanitized);
    }

    public function testSanitizeInputFloat()
    {
        $input = '123.45abc';
        $sanitized = $this->security->sanitizeInput($input, 'float');
        
        $this->assertEquals('123.45', $sanitized);
    }

    public function testCSRFTokenGeneration()
    {
        $token1 = $this->security->generateCSRFToken();
        $token2 = $this->security->generateCSRFToken();
        
        $this->assertNotEmpty($token1);
        $this->assertNotEmpty($token2);
        $this->assertIsString($token1);
        $this->assertIsString($token2);
    }

    public function testPasswordHashing()
    {
        $password = 'SecurePassword123!';
        $hash = password_hash($password, PASSWORD_DEFAULT);
        
        $this->assertTrue(password_verify($password, $hash));
        $this->assertFalse(password_verify('WrongPassword', $hash));
    }

    public function testSanitizeInputHandlesNull()
    {
        $sanitized = $this->security->sanitizeInput(null, 'string');
        $this->assertEmpty($sanitized);
    }

    public function testSanitizeInputHandlesEmptyString()
    {
        $sanitized = $this->security->sanitizeInput('', 'string');
        $this->assertEmpty($sanitized);
    }
}

