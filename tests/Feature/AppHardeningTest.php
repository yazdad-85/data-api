<?php

namespace Tests\Feature;

use App\Logging\RedactLogContext;
use Illuminate\Http\Middleware\TrustProxies;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Route;
use Tests\TestCase;

class AppHardeningTest extends TestCase
{
    private string|false $originalTrustedProxies;

    public function createApplication()
    {
        TrustProxies::flushState();

        $this->originalTrustedProxies = getenv('TRUSTED_PROXIES');

        if ($this->name() === 'test_hsts_set_when_forwarded_proto_is_sent_by_a_trusted_proxy') {
            putenv('TRUSTED_PROXIES=127.0.0.1');
            $_ENV['TRUSTED_PROXIES'] = '127.0.0.1';
            $_SERVER['TRUSTED_PROXIES'] = '127.0.0.1';
        } else {
            putenv('TRUSTED_PROXIES');
            unset($_ENV['TRUSTED_PROXIES'], $_SERVER['TRUSTED_PROXIES']);
        }

        return parent::createApplication();
    }

    protected function tearDown(): void
    {
        TrustProxies::flushState();

        if ($this->originalTrustedProxies === false) {
            putenv('TRUSTED_PROXIES');
            unset($_ENV['TRUSTED_PROXIES'], $_SERVER['TRUSTED_PROXIES']);
        } else {
            putenv("TRUSTED_PROXIES={$this->originalTrustedProxies}");
            $_ENV['TRUSTED_PROXIES'] = $this->originalTrustedProxies;
            $_SERVER['TRUSTED_PROXIES'] = $this->originalTrustedProxies;
        }

        parent::tearDown();
    }

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

    public function test_hsts_set_when_forwarded_proto_is_sent_by_a_trusted_proxy(): void
    {
        $this->app['env'] = 'production';

        $response = $this->call('GET', '/api/v1/health', server: [
            'HTTP_X_FORWARDED_PROTO' => 'https',
            'REMOTE_ADDR' => '127.0.0.1',
        ]);

        $response->assertOk()
            ->assertHeader('Strict-Transport-Security', 'max-age=31536000; includeSubDomains');
    }

    public function test_forwarded_proto_is_ignored_without_trusted_proxies(): void
    {
        $this->app['env'] = 'production';

        $response = $this->call('GET', '/api/v1/health', server: [
            'HTTP_X_FORWARDED_PROTO' => 'https',
            'REMOTE_ADDR' => '127.0.0.1',
        ]);

        $response->assertOk();

        $this->assertNull($response->headers->get('Strict-Transport-Security'));
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

    public function test_login_response_sets_hardened_session_cookie(): void
    {
        $response = $this->get('/login');

        $response->assertOk();

        $cookie = collect($response->headers->getCookies())
            ->first(fn ($c) => $c->getName() === config('session.cookie'));

        $this->assertNotNull($cookie);
        $this->assertTrue($cookie->isHttpOnly());
        $this->assertSame('lax', strtolower((string) $cookie->getSameSite()));
    }

    public function test_login_response_sets_secure_session_cookie_when_configured(): void
    {
        config(['session.secure' => true]);

        $response = $this->get('/login');

        $response->assertOk();

        $cookie = collect($response->headers->getCookies())
            ->first(fn ($c) => $c->getName() === config('session.cookie'));

        $this->assertNotNull($cookie);
        $this->assertTrue($cookie->isSecure());
    }

    public function test_log_context_redaction_is_wired_and_reaches_the_log_sink(): void
    {
        $this->assertContains(RedactLogContext::class, config('logging.channels.single.tap', []));
        $this->assertContains(RedactLogContext::class, config('logging.channels.daily.tap', []));

        $path = storage_path('logs/m10-redaction-test.log');

        @unlink($path);

        config([
            'logging.channels.m10_test' => [
                'driver' => 'single',
                'path' => $path,
                'level' => 'debug',
                'tap' => [RedactLogContext::class],
            ],
        ]);

        try {
            Log::channel('m10_test')->info('login attempt', [
                'password' => 'SuperSecret123',
            ]);

            $contents = file_get_contents($path);

            $this->assertNotFalse($contents);
            $this->assertStringNotContainsString('SuperSecret123', $contents);
            $this->assertStringContainsString('[REDACTED]', $contents);
        } finally {
            Log::forgetChannel('m10_test');
            @unlink($path);
        }
    }

    public function test_stack_channel_inherits_redaction_from_single_child_channel(): void
    {
        $path = storage_path('logs/m10-stack-redaction-test.log');

        @unlink($path);
        Log::forgetChannel('stack');
        Log::forgetChannel('single');
        config([
            'logging.channels.stack.channels' => ['single'],
            'logging.channels.single.path' => $path,
        ]);

        try {
            Log::channel('stack')->info('login attempt', [
                'password' => 'SuperSecret123',
            ]);

            $contents = file_get_contents($path);

            $this->assertNotFalse($contents);
            $this->assertStringNotContainsString('SuperSecret123', $contents);
            $this->assertStringContainsString('[REDACTED]', $contents);
        } finally {
            Log::forgetChannel('stack');
            Log::forgetChannel('single');
            @unlink($path);
        }
    }

    public function test_blade_views_do_not_contain_inline_event_handler_attributes(): void
    {
        $violations = [];

        foreach (new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator(resource_path('views'))) as $file) {
            if (! $file->isFile() || $file->getExtension() !== 'php') {
                continue;
            }

            $contents = file_get_contents($file->getPathname());

            if ($contents !== false && preg_match('/\bon[a-z0-9_-]+\s*=\s*["\']/i', $contents)) {
                $violations[] = $file->getPathname();
            }
        }

        $this->assertSame([], $violations, 'Inline event handler attributes found in: '.implode(', ', $violations));
    }

    public function test_api_exception_in_production_does_not_leak_stack(): void
    {
        config(['app.debug' => false]);
        $this->app['env'] = 'production';

        Route::get('/api/v1/__boom', function () {
            throw new \RuntimeException('secret-internal-detail');
        });

        $response = $this->withExceptionHandling()->getJson('/api/v1/__boom');

        $response->assertStatus(500)
            ->assertExactJson(['message' => 'Server Error']);

        $body = $response->getContent();
        $this->assertStringNotContainsString('secret-internal-detail', $body);
        $this->assertStringNotContainsString('RuntimeException', $body);
        $this->assertStringNotContainsString('stacktrace', strtolower($body));
    }
}
