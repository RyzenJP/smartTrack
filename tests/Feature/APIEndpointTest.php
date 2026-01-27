<?php

namespace Tests\Feature;

use PHPUnit\Framework\TestCase;

/**
 * API Endpoint Feature Tests
 * End-to-end tests for API endpoints
 */
class APIEndpointTest extends TestCase
{
    private $baseUrl;

    protected function setUp(): void
    {
        // Set base URL for testing (adjust as needed)
        $this->baseUrl = 'http://localhost/trackingv2';
    }

    /**
     * Simulate HTTP request to API endpoint
     */
    private function makeRequest($endpoint, $method = 'GET', $data = [])
    {
        $url = $this->baseUrl . $endpoint;
        
        // Initialize cURL
        $ch = curl_init();
        
        if ($method === 'GET' && !empty($data)) {
            $url .= '?' . http_build_query($data);
        }
        
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
        
        if ($method === 'POST') {
            curl_setopt($ch, CURLOPT_POST, true);
            curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($data));
        }
        
        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        
        return [
            'status' => $httpCode,
            'body' => $response,
            'json' => json_decode($response, true)
        ];
    }

    public function testHealthCheck()
    {
        // Test if the application is accessible
        $response = $this->makeRequest('/index.php');
        $this->assertEquals(200, $response['status']);
    }

    public function testAPIReturnsJSON()
    {
        // Test that API endpoints return JSON
        $response = $this->makeRequest('/super_admin/reports_api.php', 'GET', [
            'action' => 'get_drivers_for_filter'
        ]);
        
        $this->assertNotNull($response['json'], 'Response is not valid JSON');
        $this->assertArrayHasKey('success', $response['json']);
    }

    public function testAPIRequiresAuthentication()
    {
        // Test that protected endpoints require authentication
        // This test assumes session-based auth
        $response = $this->makeRequest('/super_admin/homepage.php');
        
        // Should redirect to login or return 403/401
        $this->assertTrue(
            $response['status'] === 302 || 
            $response['status'] === 403 || 
            $response['status'] === 401,
            'Endpoint should require authentication'
        );
    }

    public function testInvalidAPIActionReturnsError()
    {
        $response = $this->makeRequest('/super_admin/reports_api.php', 'GET', [
            'action' => 'invalid_action_that_does_not_exist'
        ]);
        
        if ($response['json']) {
            $this->assertFalse(
                $response['json']['success'] ?? true,
                'Invalid action should return error'
            );
        }
    }
}

