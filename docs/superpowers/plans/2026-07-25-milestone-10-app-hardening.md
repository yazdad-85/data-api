# Milestone 10 App Hardening Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Ship production-ready app security baseline: global security headers (incl. practical CSP + conditional HSTS), CORS default deny for browser origins, session/env production notes, Monolog context redaction via `MetadataRedactor`, production error hygiene, and minimal health regression.

**Architecture:** One global `SecurityHeaders` middleware + published `config/cors.php` (empty origins) + logging `tap` that pushes a Monolog processor reusing `MetadataRedactor`. Docs in `docs/PRODUCTION_NOTES.md` and `.env.example`. Follow RULES B1: implement → review → test (not classic TDD).

**Tech Stack:** Laravel 13, PHP 8.5 (`/usr/local/Cellar/php/8.5.8/bin/php`), PHPUnit, existing `MetadataRedactor`.

**Spec:** `docs/superpowers/specs/2026-07-25-milestone-10-app-hardening-design.md`

---

## File structure

| Path | Responsibility |
|------|----------------|
| `config/security.php` | Add `headers.csp`, `headers.hsts` knobs |
| `config/cors.php` | Published; `allowed_origins` empty (deny browser) |
| `config/logging.php` | `tap` on `single` + `daily` channels |
| `config/session.php` | Ensure defaults match design (`http_only` true, `same_site` lax); document secure via env |
| `.env.example` | Production comments for `APP_DEBUG`, session secure |
| `app/Http/Middleware/SecurityHeaders.php` | Set security headers on all responses |
| `bootstrap/app.php` | Append `SecurityHeaders` globally |
| `app/Logging/RedactLogContext.php` | Logging tap: push Monolog processor |
| `app/Logging/RedactContextProcessor.php` | Processor: redact record context/extra |
| `docs/PRODUCTION_NOTES.md` | Short production checklist |
| `tests/Feature/AppHardeningTest.php` | Headers, HSTS gate, CORS deny, health, API error no stack |
| `tests/Unit/RedactContextProcessorTest.php` | Log context redaction |
| `docs/IMPLEMENTATION_TODO.md` | Mark M10 complete |
| Design status | Already **DISETUJUI** in spec file |

Reuse: `MetadataRedactor`, `HealthController`, `ApiErrorResponse`, `SecurityFoundationTest` (leave alone unless regression).

**PHP binary:** always `/usr/local/Cellar/php/8.5.8/bin/php`.

**Note:** Framework default CORS currently allows `allowed_origins: ['*']` until `config/cors.php` is published and emptied — Task 2 is mandatory for deny.

---

### Task 1: Security headers middleware

**Files:**
- Modify: `config/security.php`
- Create: `app/Http/Middleware/SecurityHeaders.php`
- Modify: `bootstrap/app.php`
- Create: `tests/Feature/AppHardeningTest.php` (headers + HSTS cases first; grow in later tasks)

- [ ] **Step 1: Config knobs**

Append to `config/security.php`:

```php
'headers' => [
    'csp' => env(
        'SECURITY_CSP',
        "default-src 'self'; script-src 'self'; style-src 'self' 'unsafe-inline'; img-src 'self' data:; font-src 'self' data:; connect-src 'self'; object-src 'none'; base-uri 'self'; frame-ancestors 'none'; form-action 'self'"
    ),
    'hsts' => 'max-age=31536000; includeSubDomains',
],
```

- [ ] **Step 2: Middleware**

```php
<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

final class SecurityHeaders
{
    /**
     * @param  Closure(Request): Response  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $response = $next($request);

        $this->setIfMissing($response, 'X-Content-Type-Options', 'nosniff');
        $this->setIfMissing($response, 'X-Frame-Options', 'DENY');
        $this->setIfMissing($response, 'Referrer-Policy', 'strict-origin-when-cross-origin');
        $this->setIfMissing($response, 'Content-Security-Policy', (string) config('security.headers.csp'));

        if (app()->environment('production') && $request->secure()) {
            $this->setIfMissing($response, 'Strict-Transport-Security', (string) config('security.headers.hsts'));
        }

        return $response;
    }

    private function setIfMissing(Response $response, string $header, string $value): void
    {
        if (! $response->headers->has($header)) {
            $response->headers->set($header, $value);
        }
    }
}
```

- [ ] **Step 3: Register** in `bootstrap/app.php` append alongside `AssignRequestId`:

```php
use App\Http\Middleware\SecurityHeaders;

$middleware->append([
    AssignRequestId::class,
    SecurityHeaders::class,
]);
```

(If `append` currently takes a single class, switch to array form or call `append` twice — both fine.)

- [ ] **Step 4: Tests** in `tests/Feature/AppHardeningTest.php`:

```php
public function test_api_health_has_security_headers_without_hsts_in_local(): void
{
    $response = $this->getJson('/api/v1/health');

    $response->assertOk()
        ->assertExactJson(['status' => 'ok'])
        ->assertHeader('X-Content-Type-Options', 'nosniff')
        ->assertHeader('X-Frame-Options', 'DENY')
        ->assertHeader('Referrer-Policy', 'strict-origin-when-cross-origin');

    $this->assertNotEmpty($response->headers->get('Content-Security-Policy'));
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
        ->assertHeader('X-Frame-Options', 'DENY');
}
```

If `https://localhost/...` does not mark request secure in this Laravel version, use:

```php
$response = $this->call('GET', '/api/v1/health', server: [
    'HTTPS' => 'on',
    'SERVER_NAME' => 'localhost',
]);
```

Run:

```bash
/usr/local/Cellar/php/8.5.8/bin/php artisan test --filter=AppHardeningTest
```

Expected: PASS.

- [ ] **Step 5: Commit**

```bash
git add config/security.php app/Http/Middleware/SecurityHeaders.php bootstrap/app.php tests/Feature/AppHardeningTest.php
git commit -m "feat(m10): add global security headers middleware"
```

---

### Task 2: CORS default deny

**Files:**
- Create: `config/cors.php` (copy from framework, then deny)
- Modify: `tests/Feature/AppHardeningTest.php`

- [ ] **Step 1: Publish/create config**

```bash
/usr/local/Cellar/php/8.5.8/bin/php artisan config:publish cors
```

Or create `config/cors.php` manually from vendor defaults, then set:

```php
'paths' => ['api/*', 'sanctum/csrf-cookie'],
'allowed_methods' => ['*'],
'allowed_origins' => [],
'allowed_origins_patterns' => [],
'allowed_headers' => ['*'],
'exposed_headers' => [],
'max_age' => 0,
'supports_credentials' => false,
```

Critical: `allowed_origins` must be **`[]`**, not `['*']`.

- [ ] **Step 2: Test**

```php
public function test_api_with_browser_origin_has_no_cors_allow_origin(): void
{
    $response = $this->withHeaders([
        'Origin' => 'https://evil.example',
    ])->getJson('/api/v1/health');

    $response->assertOk();
    $this->assertNull($response->headers->get('Access-Control-Allow-Origin'));
}

public function test_api_options_preflight_does_not_allow_evil_origin(): void
{
    $response = $this->call('OPTIONS', '/api/v1/health', server: [
        'HTTP_ORIGIN' => 'https://evil.example',
        'HTTP_ACCESS_CONTROL_REQUEST_METHOD' => 'GET',
    ]);

    $this->assertNull($response->headers->get('Access-Control-Allow-Origin'));
}
```

Run filter `AppHardeningTest`. Expected: PASS.

- [ ] **Step 3: Commit**

```bash
git commit -m "feat(m10): deny browser CORS origins by default"
```

---

### Task 3: Session env notes + PRODUCTION_NOTES

**Files:**
- Modify: `.env.example`
- Modify: `config/session.php` only if needed (defaults already `http_only=true`, `same_site=lax`; set `secure` default documentation)
- Create: `docs/PRODUCTION_NOTES.md`

- [ ] **Step 1: `.env.example`**

Near `APP_DEBUG`:

```
# Local may use true. Production MUST be false.
APP_DEBUG=true
```

Near session block, add explicit keys + comments:

```
SESSION_DRIVER=database
SESSION_LIFETIME=120
SESSION_ENCRYPT=false
SESSION_PATH=/
SESSION_DOMAIN=null
SESSION_HTTP_ONLY=true
SESSION_SAME_SITE=lax
# Production MUST be true (HTTPS only cookies).
SESSION_SECURE_COOKIE=false
```

- [ ] **Step 2: `docs/PRODUCTION_NOTES.md`**

```markdown
# Production notes (app)

Ringkas untuk operator. Detail infra (Apache, Cloudflare, firewall) → Milestone 11 `DEPLOYMENT.md`.

## Wajib di env production

- `APP_ENV=production`
- `APP_DEBUG=false`
- `APP_URL=https://...` (HTTPS)
- `SESSION_SECURE_COOKIE=true`
- `SESSION_SAME_SITE=lax`
- `SESSION_HTTP_ONLY=true`
- `API_KEY_PEPPER=` (panjang, acak, rahasia)
- `MFA_SUPER_ADMIN_REQUIRED=true` sebelum publik

## Keamanan app yang otomatis

- Security headers (CSP dasar, nosniff, frame deny, referrer) pada semua response
- HSTS aktif saat `production` **dan** request HTTPS
- CORS fase 1: **server-to-server**; browser origin default deny (tidak ada whitelist per lembaga)
- Log context di-redact untuk password / API key / token / PII keys

## Jangan

- Jangan expose PostgreSQL ke publik
- Jangan commit `.env` / secret
- Jangan mengharapkan SPA browser bisa panggil API tanpa CORS whitelist (fase 2)
```

- [ ] **Step 3: Optional unit/config assert** (lightweight) in `AppHardeningTest`:

```php
public function test_session_defaults_are_hardened(): void
{
    $this->assertTrue((bool) config('session.http_only'));
    $this->assertSame('lax', config('session.same_site'));
}
```

- [ ] **Step 4: Commit**

```bash
git commit -m "docs(m10): add production notes and session env defaults"
```

---

### Task 4: Monolog log context redaction

**Files:**
- Create: `app/Logging/RedactContextProcessor.php`
- Create: `app/Logging/RedactLogContext.php`
- Modify: `config/logging.php`
- Create: `tests/Unit/RedactContextProcessorTest.php`
- Optionally extend `AppHardeningTest` with a Log facade integration test

- [ ] **Step 1: Processor**

```php
<?php

namespace App\Logging;

use App\Support\Security\MetadataRedactor;
use Monolog\LogRecord;
use Monolog\Processor\ProcessorInterface;

final class RedactContextProcessor implements ProcessorInterface
{
    public function __construct(
        private readonly MetadataRedactor $redactor = new MetadataRedactor(),
    ) {}

    public function __invoke(LogRecord $record): LogRecord
    {
        $context = $record->context === []
            ? []
            : $this->redactor->redact($record->context);

        $extra = $record->extra === []
            ? []
            : $this->redactor->redact($record->extra);

        return $record->with(context: $context, extra: $extra);
    }
}
```

If DI into processor via `new MetadataRedactor()` is fine for tests; alternatively resolve from container inside the tap.

- [ ] **Step 2: Tap**

```php
<?php

namespace App\Logging;

use Illuminate\Log\Logger;
use Monolog\Logger as MonologLogger;

final class RedactLogContext
{
    public function __invoke(Logger $logger): void
    {
        $processor = app(RedactContextProcessor::class);

        foreach ($logger->getHandlers() as $handler) {
            $handler->pushProcessor($processor);
        }

        // Also push on underlying Monolog if needed:
        $monolog = $logger->getLogger();
        if ($monolog instanceof MonologLogger) {
            $monolog->pushProcessor($processor);
        }
    }
}
```

**Prefer one path only** (either handlers **or** monolog logger pushProcessor — not double). Recommended:

```php
public function __invoke(Logger $logger): void
{
    $processor = app(RedactContextProcessor::class);
    $monolog = $logger->getLogger();
    if ($monolog instanceof MonologLogger) {
        $monolog->pushProcessor($processor);
    }
}
```

- [ ] **Step 3: Wire `config/logging.php`**

On `single` and `daily`:

```php
'tap' => [App\Logging\RedactLogContext::class],
```

- [ ] **Step 4: Unit test**

```php
public function test_processor_redacts_secret_keys(): void
{
    $processor = new RedactContextProcessor(new MetadataRedactor());
    $record = new LogRecord(
        datetime: new \DateTimeImmutable(),
        channel: 'test',
        level: Level::Info,
        message: 'login attempt',
        context: [
            'password' => 'secret',
            'authorization' => 'Bearer abc',
            'safe' => 'ok',
        ],
    );

    $out = $processor($record);

    $this->assertSame('[REDACTED]', $out->context['password']);
    $this->assertSame('[REDACTED]', $out->context['authorization']);
    $this->assertSame('ok', $out->context['safe']);
    $this->assertSame('login attempt', $out->message);
}
```

Add integration: write to a temp single channel or use `Log::channel('single')` with `Log::shareContext` / spy — simplest reliable approach: unit processor + one feature that logs then reads last lines of `storage/logs/laravel.log` **only if** test env uses file channel; otherwise stick to unit + assert `Log::listen` if available.

Preferred extra feature test without brittle file IO:

```php
public function test_logger_tap_redacts_when_logging(): void
{
    $records = [];
    $monolog = Log::getLogger(); // may need channel
    // Simpler: resolve processor from container and assert same as unit — enough if tap is wired.
    $this->assertContains(
        \App\Logging\RedactLogContext::class,
        config('logging.channels.single.tap') ?? [],
    );
}
```

Plus keep the real processor unit test for behavior.

- [ ] **Step 5: Commit**

```bash
git commit -m "feat(m10): redact secrets in application log context"
```

---

### Task 5: Production API errors + health + TODO

**Files:**
- Modify: `bootstrap/app.php` only if needed to harden JSON exception rendering
- Modify: `tests/Feature/AppHardeningTest.php`
- Modify: `docs/IMPLEMENTATION_TODO.md`
- Confirm design status DISETUJUI

- [ ] **Step 1: Exception rendering check**

In `bootstrap/app.php` `withExceptions`, ensure production API errors do not include trace. Laravel default already hides details when `APP_DEBUG=false`. Add explicit test:

```php
public function test_api_exception_in_production_does_not_leak_stack(): void
{
    config(['app.debug' => false]);
    $this->app['env'] = 'production';

    Route::get('/api/v1/__boom', function () {
        throw new \RuntimeException('secret-internal-detail');
    });

    $response = $this->getJson('/api/v1/__boom');

    $response->assertStatus(500);
    $body = $response->getContent();
    $this->assertStringNotContainsString('secret-internal-detail', $body);
    $this->assertStringNotContainsString('RuntimeException', $body);
    $this->assertStringNotContainsString('stacktrace', strtolower($body));
}
```

If Laravel still returns `message: Server Error` only — good. If test route registration in feature test needs `Route::middleware` — use `Illuminate\Support\Facades\Route` inside the test method after boot.

If default already sufficient, **do not** over-engineer custom renderer.

- [ ] **Step 2: Health already asserted in Task 1** — keep exact JSON.

- [ ] **Step 3: Full suite**

```bash
/usr/local/Cellar/php/8.5.8/bin/php artisan test
```

Expected: all green.

- [ ] **Step 4: Mark `IMPLEMENTATION_TODO.md` Milestone 10** Status **Selesai**, check all proven boxes (headers, CORS deny, APP_DEBUG notes, session, log redaction, error production, health, production docs, reviews, tests).

- [ ] **Step 5: Commit**

```bash
git commit -m "docs(m10): mark app hardening milestone complete"
```

---

## Self-review (plan vs spec)

| Spec requirement | Task |
|------------------|------|
| SecurityHeaders + CSP praktis + HSTS gate | 1 |
| CORS deny empty origins | 2 |
| Session lax/httpOnly + secure prod notes | 3 |
| `PRODUCTION_NOTES.md` + `.env.example` | 3 |
| Monolog processor + MetadataRedactor | 4 |
| Production error no stack | 5 |
| Health minimal | 1 / 5 |
| IMPLEMENTATION_TODO M10 | 5 |
| Out of scope infra/Cloudflare | not implemented ✓ |

**Placeholder scan:** clean.

**Type consistency:** `RedactContextProcessor` / `RedactLogContext` names stable across Task 4.

---

## Execution notes

- Worktree recommended: `.worktrees/m10-hardening` branch `feature/m10-app-hardening`.
- Do not push unless asked.
- After merge: remove worktree like M9.
