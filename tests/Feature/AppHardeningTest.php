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
}
