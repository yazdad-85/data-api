<?php

namespace Tests\Feature;

use Tests\TestCase;

class AppHardeningTest extends TestCase
{
    public function test_api_health_has_security_headers_without_hsts_in_local(): void
    {
        $response = $this->getJson('/api/v1/health');

        $response->assertOk()
            ->assertExactJson(['status' => 'ok'])
            ->assertHeader('X-Content-Type-Options', 'nosniff')
            ->assertHeader('X-Frame-Options', 'DENY')
            ->assertHeader('Referrer-Policy', 'strict-origin-when-cross-origin')
            ->assertHeader('Content-Security-Policy', config('security.headers.csp'));

        $this->assertNull($response->headers->get('Strict-Transport-Security'));
    }

    public function test_hsts_set_in_production_when_request_is_secure(): void
    {
        $this->app['env'] = 'production';

        $response = $this->getJson('https://localhost/api/v1/health');

        $response->assertOk()
            ->assertHeader('Strict-Transport-Security', 'max-age=31536000; includeSubDomains');
    }

    public function test_web_login_page_has_security_headers(): void
    {
        $this->get('/login')
            ->assertOk()
            ->assertHeader('X-Content-Type-Options', 'nosniff')
            ->assertHeader('X-Frame-Options', 'DENY')
            ->assertHeader('Referrer-Policy', 'strict-origin-when-cross-origin')
            ->assertHeader('Content-Security-Policy', config('security.headers.csp'));
    }

    public function test_api_with_browser_origin_has_no_cors_allow_origin(): void
    {
        $response = $this->withHeaders(['Origin' => 'https://evil.example'])
            ->getJson('/api/v1/health');

        $response->assertOk()
            ->assertExactJson(['status' => 'ok']);

        $this->assertNull($response->headers->get('Access-Control-Allow-Origin'));
    }

    public function test_api_options_preflight_does_not_allow_evil_origin(): void
    {
        $response = $this->call('OPTIONS', '/api/v1/health', server: [
            'HTTP_ORIGIN' => 'https://evil.example',
            'HTTP_ACCESS_CONTROL_REQUEST_METHOD' => 'GET',
        ]);

        $response->assertStatus(204);
        $this->assertNull($response->headers->get('Access-Control-Allow-Origin'));
    }

    public function test_session_defaults_are_hardened(): void
    {
        $this->assertTrue((bool) config('session.http_only'));
        $this->assertSame('lax', config('session.same_site'));
    }
}
