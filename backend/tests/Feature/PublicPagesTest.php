<?php

namespace Tests\Feature;

use Tests\TestCase;

class PublicPagesTest extends TestCase
{
    public function test_home_and_legal_pages_render(): void
    {
        $this->get('/')->assertOk();
        $this->get('/terms')->assertOk();
        $this->get('/privacy')->assertOk();
        $this->get('/takedown')->assertOk();
    }

    public function test_security_headers_are_present(): void
    {
        $response = $this->get('/');

        $response->assertHeader('X-Content-Type-Options', 'nosniff');
        $response->assertHeader('X-Frame-Options', 'SAMEORIGIN');
        $response->assertHeader('Referrer-Policy', 'strict-origin-when-cross-origin');

        $this->assertStringContainsString(
            "script-src 'self'",
            $response->headers->get('Content-Security-Policy', '')
        );
    }

    public function test_unknown_web_route_renders_friendly_404(): void
    {
        $this->get('/this-page-does-not-exist')->assertNotFound();
    }
}
