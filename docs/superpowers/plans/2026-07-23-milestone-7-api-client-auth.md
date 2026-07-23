# Milestone 7 API Client Authentication Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Ship API key authentication middleware (`X-API-Key` + Bearer), rate limits, and smoke endpoints `GET /api/v1/health` + `GET /api/v1/me` — without resource list endpoints (M8).

**Architecture:** Thin middleware calls `ApiClientAuthenticator` (parse → lookup prefix → `ApiKeyVerifier` → status checks → `last_used_*`). Separate `ThrottleApiClient` (120/min/key + 240/min/IP). JSON errors via SPEC §4.5 envelope. Follow RULES B1: implement → review → test (not classic TDD).

**Tech Stack:** Laravel 13, PHP 8.5 (`/usr/local/Cellar/php/8.5.8/bin/php`), PHPUnit, RateLimiter + cache, existing `ApiKeyIssuer`/`ApiKeyVerifier`.

**Spec:** `docs/superpowers/specs/2026-07-23-milestone-7-api-client-auth-design.md`

---

## File structure

| Path | Responsibility |
|------|----------------|
| `app/Support/Api/ApiErrorResponse.php` | JSON `{message,code,request_id}` |
| `app/Services/Api/ApiKeyParser.php` | Extract key from request; parse `dc_live_*` |
| `app/Services/Api/ApiClientAuthenticator.php` | Auth orchestration + last_used |
| `app/Http/Middleware/AuthenticateApiClient.php` | Bind client or abort JSON |
| `app/Http/Middleware/ThrottleApiClient.php` | Rate limit key + IP |
| `app/Http/Controllers/Api/V1/HealthController.php` | Public health |
| `app/Http/Controllers/Api/V1/MeController.php` | Authenticated `/me` |
| `app/Support/Api/ApiClientContext.php` (or request macro) | Read bound `ApiClient` |
| `routes/api.php` | `/api/v1/health`, `/api/v1/me` |
| `bootstrap/app.php` | Register `api` routes + middleware aliases |
| `app/Providers/AppServiceProvider.php` | `RateLimiter::for('api-client')` (+ IP) |
| `tests/Unit/ApiKeyParserTest.php` | Parse / extract |
| `tests/Feature/ApiClientAuthTest.php` | Auth + me + health + rate limit |
| `docs/IMPLEMENTATION_TODO.md` | Centang M7 setelah hijau |

Reuse: `app/Services/Api/ApiKeyVerifier.php`, `ApiKeyIssuer.php`, `App\Models\ApiClient`, `AssignRequestId` (global).

**Test helper pattern:** never trust `ApiClientFactory` digest alone — create client with issuer:

```php
config(['security.api_key_pepper' => 'test-pepper-not-for-production']);
$issued = app(ApiKeyIssuer::class)->issue();
$client = ApiClient::factory()->create([
    'api_key_prefix' => $issued['prefix'],
    'api_key_digest' => $issued['digest'],
    'lembaga_id' => $lembaga->id,
]);
$plain = $issued['plain'];
```

---

### Task 1: ApiErrorResponse + ApiKeyParser

**Files:**
- Create: `app/Support/Api/ApiErrorResponse.php`
- Create: `app/Services/Api/ApiKeyParser.php`
- Create: `tests/Unit/ApiKeyParserTest.php`

- [ ] **Step 1: `ApiErrorResponse`**

```php
<?php

namespace App\Support\Api;

use Illuminate\Http\JsonResponse;

final class ApiErrorResponse
{
    public static function make(string $code, string $message, int $status): JsonResponse
    {
        return response()->json([
            'message' => $message,
            'code' => $code,
            'request_id' => function_exists('request_id') ? request_id() : null,
        ], $status);
    }
}
```

Indonesian messages (examples):
- `UNAUTHENTICATED` → `Autentikasi gagal.`
- `API_CLIENT_INACTIVE` → `API client tidak aktif.`
- `LEMBAGA_INACTIVE` → `Lembaga tidak aktif.`
- `RATE_LIMITED` → `Terlalu banyak permintaan.`

- [ ] **Step 2: `ApiKeyParser`**

```php
final class ApiKeyParser
{
    /** Prefer X-API-Key over Bearer when both present. */
    public function extractFromRequest(\Illuminate\Http\Request $request): ?string

    /**
     * @return array{prefix: string, secret: string}|null null if invalid format
     */
    public function parse(string $plain): ?array
}
```

Rules:
- `extractFromRequest`: trim `X-API-Key`; else `Authorization` matching `/^Bearer\s+(\S+)/i`; empty → null.
- `parse`: must match `/^dc_live_([a-z0-9]+)_([a-z0-9]+)$/i` (normalize prefix/secret to lowercase if issuer uses lower); return null on mismatch.

- [ ] **Step 3: Unit tests**

```php
public function test_extract_prefers_x_api_key_over_bearer(): void
public function test_extract_bearer_when_no_x_api_key(): void
public function test_parse_valid_dc_live_key(): void
public function test_parse_invalid_returns_null(): void
```

Run:

```bash
/usr/local/Cellar/php/8.5.8/bin/php artisan test --filter=ApiKeyParser
```

- [ ] **Step 4: Commit**

```bash
git commit -m "feat(m7): add API error envelope and key parser"
```

---

### Task 2: ApiClientAuthenticator

**Files:**
- Create: `app/Services/Api/ApiClientAuthenticator.php`
- Create: `tests/Unit/ApiClientAuthenticatorTest.php` (or Feature if easier with DB)

- [ ] **Step 1: Result type / methods**

```php
final class ApiClientAuthenticator
{
    public function __construct(
        private readonly ApiKeyParser $parser,
        private readonly ApiKeyVerifier $verifier,
    ) {}

    /**
     * @return array{ok: true, client: ApiClient}|array{ok: false, code: string, status: int, message: string}
     */
    public function authenticate(\Illuminate\Http\Request $request): array
}
```

Flow inside `authenticate`:
1. `$plain = $this->parser->extractFromRequest($request)`; null → unauthenticated.
2. `$parts = $this->parser->parse($plain)`; null → unauthenticated.
3. `$client = ApiClient::query()->where('api_key_prefix', $parts['prefix'])->first()`; null → unauthenticated.
4. If `! $this->verifier->matches($plain, (string) $client->api_key_digest)` → unauthenticated.
5. If `! $client->is_active || $client->revoked_at !== null` → `API_CLIENT_INACTIVE` 403.
6. `$client->loadMissing('lembaga')`; if lembaga missing or `! $client->lembaga->is_active` → `LEMBAGA_INACTIVE` 403.
7. Best-effort: `$client->forceFill(['last_used_at' => now(), 'last_used_ip' => $request->ip()])->save()`; swallow exceptions / ignore failure.
8. Return `{ok: true, client: $client}`.

Do **not** log `$plain`.

- [ ] **Step 2: Tests** (RefreshDatabase + pepper + issuer)

Cover: success updates last_used; bad key; revoked; inactive lembaga.

Run:

```bash
/usr/local/Cellar/php/8.5.8/bin/php artisan test --filter=ApiClientAuthenticator
```

- [ ] **Step 3: Commit**

```bash
git commit -m "feat(m7): add ApiClientAuthenticator service"
```

---

### Task 3: Middleware + RateLimiter + bootstrap api routes

**Files:**
- Create: `app/Http/Middleware/AuthenticateApiClient.php`
- Create: `app/Http/Middleware/ThrottleApiClient.php`
- Create: `app/Support/Api/ApiClientContext.php`
- Modify: `bootstrap/app.php`
- Modify: `app/Providers/AppServiceProvider.php`

- [ ] **Step 1: Context helper**

```php
final class ApiClientContext
{
    public const ATTR = 'api_client';

    public static function set(\Illuminate\Http\Request $request, \App\Models\ApiClient $client): void
    {
        $request->attributes->set(self::ATTR, $client);
    }

    public static function get(\Illuminate\Http\Request $request): ?\App\Models\ApiClient
    {
        $client = $request->attributes->get(self::ATTR);

        return $client instanceof \App\Models\ApiClient ? $client : null;
    }
}
```

- [ ] **Step 2: `AuthenticateApiClient`**

```php
public function handle(Request $request, Closure $next)
{
    $result = app(ApiClientAuthenticator::class)->authenticate($request);
    if (! ($result['ok'] ?? false)) {
        return ApiErrorResponse::make($result['code'], $result['message'], $result['status']);
    }
    ApiClientContext::set($request, $result['client']);

    return $next($request);
}
```

- [ ] **Step 3: Rate limiters in `AppServiceProvider`**

```php
RateLimiter::for('api-client-key', function (Request $request) {
    $client = ApiClientContext::get($request);
    $key = $client?->api_key_prefix ?? 'unknown';

    return Limit::perMinute(120)->by('api-key:'.$key);
});

RateLimiter::for('api-client-ip', function (Request $request) {
    return Limit::perMinute(240)->by('api-ip:'.$request->ip());
});
```

`ThrottleApiClient`: attempt both limiters; on too many → `ApiErrorResponse::make('RATE_LIMITED', '...', 429)` with `Retry-After` header when available (`$limiter->availableIn(...)`).

Alternatively use Laravel `throttle:api-client-key` twice — custom middleware is clearer for JSON body.

- [ ] **Step 4: `bootstrap/app.php`**

```php
->withRouting(
    web: __DIR__.'/../routes/web.php',
    api: __DIR__.'/../routes/api.php',
    commands: __DIR__.'/../routes/console.php',
    health: '/up',
    apiPrefix: 'api',
)
```

Alias:

```php
'api.client' => AuthenticateApiClient::class,
'api.throttle' => ThrottleApiClient::class,
```

`AssignRequestId` already global — keeps `request_id` on API too.

- [ ] **Step 5: Commit**

```bash
git commit -m "feat(m7): register API client auth and throttle middleware"
```

---

### Task 4: Health + Me controllers and routes

**Files:**
- Create: `routes/api.php`
- Create: `app/Http/Controllers/Api/V1/HealthController.php`
- Create: `app/Http/Controllers/Api/V1/MeController.php`

- [ ] **Step 1: Routes**

```php
<?php

use App\Http\Controllers\Api\V1\HealthController;
use App\Http\Controllers\Api\V1\MeController;
use Illuminate\Support\Facades\Route;

Route::prefix('v1')->group(function () {
    Route::get('/health', HealthController::class)->name('api.v1.health');

    Route::middleware(['api.client', 'api.throttle'])->group(function () {
        Route::get('/me', MeController::class)->name('api.v1.me');
    });
});
```

Final paths: `/api/v1/health`, `/api/v1/me`.

- [ ] **Step 2: Controllers**

`HealthController`:

```php
public function __invoke()
{
    return response()->json(['status' => 'ok']);
}
```

`MeController`:

```php
public function __invoke(Request $request)
{
    $client = ApiClientContext::get($request);
    abort_if($client === null, 500); // should not happen behind middleware
    $client->loadMissing('lembaga');
    $lembaga = $client->lembaga;

    return response()->json([
        'lembaga_id' => $lembaga->id,
        'kode' => $lembaga->kode,
        'nama' => $lembaga->nama,
        'is_active' => $lembaga->is_active,
        'client_id' => $client->id,
        'client_name' => $client->nama,
        'scopes' => $client->scopes,
        'field_profile' => $client->field_profile,
    ]);
}
```

- [ ] **Step 3: Smoke manually optional; Feature tests in Task 5**

- [ ] **Step 4: Commit**

```bash
git commit -m "feat(m7): add /api/v1/health and /api/v1/me"
```

---

### Task 5: Feature tests ApiClientAuthTest

**Files:**
- Create: `tests/Feature/ApiClientAuthTest.php`

- [ ] **Step 1: Cases**

```php
public function test_health_without_key_returns_ok(): void
public function test_me_without_key_returns_401_unauthenticated(): void
public function test_me_with_invalid_key_returns_401(): void
public function test_me_with_x_api_key_succeeds(): void
public function test_me_with_bearer_succeeds(): void
public function test_me_prefers_x_api_key_when_both_present(): void // valid X-API-Key wins even if Bearer wrong — or both valid same client
public function test_revoked_client_returns_403_api_client_inactive(): void
public function test_inactive_lembaga_returns_403_lembaga_inactive(): void
public function test_me_scoped_to_own_lembaga_only(): void // assert JSON lembaga_id
public function test_rate_limit_returns_429(): void // loop 121 GETs /me with same key; clear RateLimiter between tests
```

Set pepper in `setUp`. Use issuer for digests. For rate limit test, either hit 121 times or temporarily bind lower limit via `RateLimiter::for` override in that test — prefer config/constants extracted to `config/security.php`:

```php
'api_rate_per_minute' => (int) env('API_RATE_PER_MINUTE', 120),
'api_ip_rate_per_minute' => (int) env('API_IP_RATE_PER_MINUTE', 240),
```

In the 429 test: `config(['security.api_rate_per_minute' => 3])` then 4 requests → 429. Re-register limiter or read config inside `RateLimiter::for` closure (closures capture at boot — **important**: define limiters to read `config()` at call time, not bind-time constant).

- [ ] **Step 2: Run**

```bash
/usr/local/Cellar/php/8.5.8/bin/php artisan test --filter=ApiClientAuth
```

Expected: PASS.

- [ ] **Step 3: Commit**

```bash
git commit -m "test(m7): cover API client auth health me and rate limit"
```

---

### Task 6: Docs checklist + full suite + optional log note

**Files:**
- Modify: `docs/IMPLEMENTATION_TODO.md` (centang item M7 yang selesai; link spek)
- Optional note in SPEC if IP 240 not listed — spek M7 sudah cukup; skip SPEC unless gap

- [ ] **Step 1: Update TODO** — status M7 selesai; checklist dari spek §8.

- [ ] **Step 2: Full suite**

```bash
/usr/local/Cellar/php/8.5.8/bin/php artisan test
```

Expected: all green.

- [ ] **Step 3: Commit**

```bash
git commit -m "docs(m7): mark API client authentication milestone complete"
```

---

## Spec coverage check

| Spek | Task |
|------|------|
| Dual header + priority | 1, 5 |
| Parse + verify + hash_equals | 1–2 |
| 401/403 codes | 2–3, 5 |
| last_used_* | 2, 5 |
| Rate limit 120 + IP 240 | 3, 5 |
| health + me | 4, 5 |
| No resource endpoints | (none added) |
| No auth header in logs | 2 (don't log); optional assert if logger spy easy — skip if costly |
| IMPLEMENTATION_TODO | 6 |

## Self-review notes

- Factory digest is **not** HMAC — always use `ApiKeyIssuer` in auth tests.
- RateLimiter closures must read config **per request** so tests can lower limits.
- Commit steps: only when user/session allows commits; do not push unless asked.
- PHP binary: `/usr/local/Cellar/php/8.5.8/bin/php`.
