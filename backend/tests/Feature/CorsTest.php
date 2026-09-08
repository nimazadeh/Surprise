<?php

namespace Tests\Feature;

use Tests\TestCase;

class CorsTest extends TestCase
{
    public function test_allowed_frontend_origin_gets_cors_headers_on_api_routes(): void
    {
        $response = $this->get('/api/v1/ping', [
            'Origin' => 'http://localhost:5500',
        ]);

        $response->assertOk();
        $response->assertHeader('Access-Control-Allow-Origin', 'http://localhost:5500');
    }

    public function test_preflight_from_allowed_origin_is_handled(): void
    {
        $this->call('OPTIONS', '/api/v1/artists', [], [], [], [
            'HTTP_ACCESS_CONTROL_REQUEST_METHOD' => 'GET',
            'HTTP_ORIGIN' => 'http://localhost:5500',
        ])->assertOk()->assertHeader('Access-Control-Allow-Origin', 'http://localhost:5500');
    }

    public function test_foreign_origin_gets_no_allow_origin_header(): void
    {
        $response = $this->get('/api/v1/ping', [
            'Origin' => 'https://evil.example.com',
        ]);

        $response->assertOk();
        $this->assertFalse($response->headers->has('Access-Control-Allow-Origin'));
    }

    public function test_web_routes_stay_same_origin(): void
    {
        $response = $this->get('/', [
            'Origin' => 'http://localhost:5500',
        ]);

        $response->assertOk();
        $this->assertFalse($response->headers->has('Access-Control-Allow-Origin'));
    }

    public function test_credentials_are_never_allowed_cross_origin(): void
    {
        $response = $this->get('/api/v1/ping', [
            'Origin' => 'http://localhost:5500',
        ]);

        $response->assertOk();

        $allowCredentials = (string) $response->headers->get('Access-Control-Allow-Credentials');

        $this->assertNotSame('true', strtolower($allowCredentials));
    }
}
