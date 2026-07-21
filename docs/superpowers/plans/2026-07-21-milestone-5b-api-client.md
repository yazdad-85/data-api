# Milestone 5b API Client & API Key Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Ship Super Admin nested API client management (create/edit/rotate/revoke with HMAC copy-once keys) on lembaga detail, plus Admin Lembaga read-only client list — without REST consumer endpoints (M7).

**Architecture:** Mirror M5a nested admin pattern: `LembagaApiClientController` under lembaga routes; `ApiKeyIssuer`/`ApiKeyVerifier` for `dc_live_*` + pepper HMAC; Blade section on `show` + key-once page; AL `ApiClientController@index`. Follow RULES B1: write code → review → test (not classic TDD).

**Tech Stack:** Laravel 13, Blade, Form Request, PHPUnit, PHP 8.5.

**Spec:** `docs/superpowers/specs/2026-07-21-milestone-5b-api-client-design.md`

**PHP CLI:** `/usr/local/Cellar/php/8.5.8/bin/php` when default `php` is 8.1.

---

## File structure

| Path | Responsibility |
|------|----------------|
| `config/security.php` | Add `api_key_pepper` |
| `.env.example` | Document `API_KEY_PEPPER` |
| `app/Support/Api/ApiClientScopes.php` | Whitelist scope strings |
| `app/Services/Api/ApiKeyIssuer.php` | Generate plain key, prefix, digest |
| `app/Services/Api/ApiKeyVerifier.php` | `hash_equals` digest check |
| `app/Policies/ApiClientPolicy.php` | SA manage; AL view own |
| `app/Http/Requests/Admin/StoreApiClientRequest.php` | Create validation |
| `app/Http/Requests/Admin/UpdateApiClientRequest.php` | Metadata update validation |
| `app/Http/Controllers/Admin/LembagaApiClientController.php` | Nested SA actions |
| `app/Http/Controllers/Admin/ApiClientController.php` | AL read-only index |
| `app/Providers/AppServiceProvider.php` | Register policy |
| `app/Support/Navigation/AdminMenu.php` | Drop SA API client; AL route live |
| `app/Http/Controllers/Admin/LembagaController.php` | Pass `$apiClients` to show |
| `routes/web.php` | Nested + AL routes; trim coming-soon |
| `resources/views/admin/lembaga/show.blade.php` | API client section |
| `resources/views/admin/lembaga/api-clients/key-once.blade.php` | Copy-once plain key |
| `resources/views/admin/api-clients/index.blade.php` | AL list |
| `tests/Unit/ApiKeyIssuerTest.php` | Issuer + verifier |
| `tests/Feature/ApiClientAdminTest.php` | Full M5b coverage |
| `docs/IMPLEMENTATION_TODO.md` | Check M5b items |

---

### Task 1: Pepper config + ApiKeyIssuer + ApiKeyVerifier

**Files:**
- Modify: `config/security.php`, `.env.example`
- Create: `app/Support/Api/ApiClientScopes.php`
- Create: `app/Services/Api/ApiKeyIssuer.php`
- Create: `app/Services/Api/ApiKeyVerifier.php`
- Create: `tests/Unit/ApiKeyIssuerTest.php`

- [ ] **Step 1: Extend `config/security.php`**

```php
<?php

return [
    'mfa' => [
        'super_admin_required' => (bool) env('MFA_SUPER_ADMIN_REQUIRED', true),
        'pending_ttl_minutes' => (int) env('MFA_PENDING_TTL_MINUTES', 10),
        'totp_window' => (int) env('MFA_TOTP_WINDOW', 1),
    ],

    'api_key_pepper' => (string) env('API_KEY_PEPPER', ''),
];
```

Add to `.env.example` (near MFA if present, else after `APP_KEY`):

```env
# HMAC pepper for API client keys (required in non-testing env). Generate a long random string.
API_KEY_PEPPER=
```

In local `.env` (not committed), set a long random `API_KEY_PEPPER=...` for manual browser tests. PHPUnit will set config in tests.

- [ ] **Step 2: `ApiClientScopes`**

```php
<?php

namespace App\Support\Api;

final class ApiClientScopes
{
    /** @return list<string> */
    public static function all(): array
    {
        return [
            'tahun_ajaran:read',
            'guru:read',
            'kelas:read',
            'siswa:read',
            'karyawan:read',
        ];
    }
}
```

- [ ] **Step 3: `ApiKeyIssuer`**

```php
<?php

namespace App\Services\Api;

use App\Models\ApiClient;
use Illuminate\Support\Str;
use RuntimeException;

final class ApiKeyIssuer
{
    /**
     * @return array{plain: string, prefix: string, digest: string}
     */
    public function issue(): array
    {
        $pepper = (string) config('security.api_key_pepper');
        if ($pepper === '') {
            throw new RuntimeException('API_KEY_PEPPER is not configured.');
        }

        $prefix = $this->uniquePrefix();
        $secret = Str::lower(Str::random(32));
        $plain = 'dc_live_'.$prefix.'_'.$secret;
        $digest = hash_hmac('sha256', $plain, $pepper);

        return [
            'plain' => $plain,
            'prefix' => $prefix,
            'digest' => $digest,
        ];
    }

    private function uniquePrefix(): string
    {
        for ($i = 0; $i < 8; $i++) {
            // 12 chars fits DB string(16); URL-safe lowercase
            $prefix = Str::lower(Str::random(12));
            if (! ApiClient::query()->where('api_key_prefix', $prefix)->exists()) {
                return $prefix;
            }
        }

        throw new RuntimeException('Unable to allocate unique API key prefix.');
    }
}
```

- [ ] **Step 4: `ApiKeyVerifier`**

```php
<?php

namespace App\Services\Api;

final class ApiKeyVerifier
{
    public function matches(string $plainApiKey, string $storedDigest): bool
    {
        $pepper = (string) config('security.api_key_pepper');
        if ($pepper === '' || $storedDigest === '') {
            return false;
        }

        $computed = hash_hmac('sha256', $plainApiKey, $pepper);

        return hash_equals($storedDigest, $computed);
    }
}
```

- [ ] **Step 5: Unit tests**

```php
<?php

namespace Tests\Unit;

use App\Services\Api\ApiKeyIssuer;
use App\Services\Api\ApiKeyVerifier;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ApiKeyIssuerTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        config(['security.api_key_pepper' => 'test-pepper-not-for-production']);
    }

    public function test_issue_returns_dc_live_format_and_verifiable_digest(): void
    {
        $issued = app(ApiKeyIssuer::class)->issue();

        $this->assertMatchesRegularExpression('/^dc_live_[a-z0-9]{12}_[a-z0-9]+$/', $issued['plain']);
        $this->assertSame(12, strlen($issued['prefix']));
        $this->assertSame(64, strlen($issued['digest']));
        $this->assertTrue(app(ApiKeyVerifier::class)->matches($issued['plain'], $issued['digest']));
        $this->assertFalse(app(ApiKeyVerifier::class)->matches($issued['plain'].'x', $issued['digest']));
    }
}
```

- [ ] **Step 6: Run tests**

```bash
/usr/local/Cellar/php/8.5.8/bin/php vendor/bin/phpunit --filter=ApiKeyIssuerTest --colors=never
```

Expected: PASS

- [ ] **Step 7: Commit**

```bash
git add config/security.php .env.example app/Support/Api/ApiClientScopes.php \
  app/Services/Api/ApiKeyIssuer.php app/Services/Api/ApiKeyVerifier.php \
  tests/Unit/ApiKeyIssuerTest.php
git commit -m "feat(m5b): add API key issuer, verifier, and pepper config"
```

---

### Task 2: ApiClientPolicy + menu prep

**Files:**
- Create: `app/Policies/ApiClientPolicy.php`
- Modify: `app/Providers/AppServiceProvider.php`
- Modify: `app/Support/Navigation/AdminMenu.php` (SA: remove API client item; AL keep coming-soon until Task 5)

- [ ] **Step 1: Policy**

```php
<?php

namespace App\Policies;

use App\Models\ApiClient;
use App\Models\User;

class ApiClientPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->isSuperAdmin() || $user->isAdminLembaga();
    }

    public function view(User $user, ApiClient $apiClient): bool
    {
        if ($user->isSuperAdmin()) {
            return true;
        }

        return $user->isAdminLembaga()
            && $user->lembaga_id !== null
            && hash_equals((string) $user->lembaga_id, (string) $apiClient->lembaga_id);
    }

    public function create(User $user): bool
    {
        return $user->isSuperAdmin();
    }

    public function update(User $user, ApiClient $apiClient): bool
    {
        return $user->isSuperAdmin();
    }

    public function rotate(User $user, ApiClient $apiClient): bool
    {
        return $user->isSuperAdmin() && $apiClient->revoked_at === null && $apiClient->is_active;
    }

    public function revoke(User $user, ApiClient $apiClient): bool
    {
        return $user->isSuperAdmin() && $apiClient->revoked_at === null;
    }
}
```

Register: `Gate::policy(ApiClient::class, ApiClientPolicy::class);`

- [ ] **Step 2: SA menu — remove API client entry**

```php
return collect([
    ['label' => 'Dashboard', 'route' => 'admin.dashboard', 'available' => true],
    ['label' => 'Lembaga', 'route' => 'admin.lembaga.index', 'available' => true],
]);
```

Leave AL `API client` as coming-soon until Task 5 (avoid broken route). Update `AdminShellTest` if it asserted “API client” for SA — use `assertDontSee` for SA sidebar API client or assert only Lembaga/Dashboard.

- [ ] **Step 3: Commit**

```bash
git commit -m "feat(m5b): add ApiClientPolicy and drop SA sidebar API client link"
```

---

### Task 3: Nested create / update / key-once + show section

**Files:**
- Create: Form requests, `LembagaApiClientController`
- Modify: `routes/web.php`, `LembagaController@show`, `show.blade.php`
- Create: `key-once.blade.php`

- [ ] **Step 1: Form requests**

`StoreApiClientRequest` / `UpdateApiClientRequest`:

```php
public function authorize(): bool
{
    return $this->user()?->can('create', ApiClient::class) // store
        || $this->user()?->can('update', $this->route('apiClient')); // update — split per class
}

public function rules(): array
{
    return [
        'nama' => ['required', 'string', 'max:150'],
        'scopes' => ['required', 'array', 'min:1'],
        'scopes.*' => ['string', Rule::in(ApiClientScopes::all())],
        'field_profile' => ['required', Rule::in(['minimal', 'academic', 'contact'])],
    ];
}
```

- [ ] **Step 2: Controller skeleton (store, update, keyOnce)**

Follow `LembagaAdminController` patterns:

```php
public function store(StoreApiClientRequest $request, Lembaga $lembaga): RedirectResponse
{
    $this->authorize('create', ApiClient::class);

    $issued = $this->issuer->issue();

    $client = ApiClient::query()->create([
        'lembaga_id' => $lembaga->id,
        'nama' => $request->validated('nama'),
        'scopes' => $request->validated('scopes'),
        'field_profile' => $request->validated('field_profile'),
        'api_key_prefix' => $issued['prefix'],
        'api_key_digest' => $issued['digest'],
        'is_active' => true,
        'revoked_at' => null,
    ]);

    $this->auditLogger->record('api_client.create', 'success', [
        'nama' => $client->nama,
        'prefix' => $client->api_key_prefix,
        'scopes' => $client->scopes,
    ], subject: $client, lembagaId: $lembaga->id, apiKeyPrefix: $client->api_key_prefix, request: $request);

    return redirect()
        ->route('admin.lembaga.api-clients.key-once', [$lembaga, $client])
        ->with('generated_api_key', [
            'api_client_id' => (string) $client->id,
            'plain_key' => $issued['plain'],
        ]);
}

public function keyOnce(Request $request, Lembaga $lembaga, ApiClient $apiClient): View|RedirectResponse
{
    $this->assertClientBelongsToLembaga($lembaga, $apiClient);
    $this->authorize('view', $apiClient); // SA only effectively for key-once UX

    $flash = $request->session()->pull('generated_api_key');
    $plain = is_array($flash)
        && (string) ($flash['api_client_id'] ?? '') === (string) $apiClient->id
        && is_string($flash['plain_key'] ?? null)
        && $flash['plain_key'] !== ''
            ? $flash['plain_key']
            : null;

    if ($plain === null) {
        return redirect()
            ->route('admin.lembaga.show', $lembaga)
            ->with('status', 'API key satu kali sudah tidak tersedia. Rotate ulang jika perlu.');
    }

    return view('admin.lembaga.api-clients.key-once', [
        'lembaga' => $lembaga,
        'apiClient' => $apiClient,
        'plainKey' => $plain,
    ]);
}

private function assertClientBelongsToLembaga(Lembaga $lembaga, ApiClient $apiClient): void
{
    abort_unless(
        hash_equals((string) $apiClient->lembaga_id, (string) $lembaga->id),
        404
    );
}
```

`update`: assert belong; authorize update; only validated metadata; audit `api_client.update`; redirect show. Reject update if `revoked_at` set (403/422).

- [ ] **Step 3: Routes**

```php
use App\Http\Controllers\Admin\LembagaApiClientController;

Route::post('/lembaga/{lembaga}/api-clients', [LembagaApiClientController::class, 'store'])
    ->name('admin.lembaga.api-clients.store');
Route::put('/lembaga/{lembaga}/api-clients/{apiClient}', [LembagaApiClientController::class, 'update'])
    ->name('admin.lembaga.api-clients.update');
Route::get('/lembaga/{lembaga}/api-clients/{apiClient}/key-once', [LembagaApiClientController::class, 'keyOnce'])
    ->name('admin.lembaga.api-clients.key-once');
```

- [ ] **Step 4: `LembagaController@show`**

Load `$apiClients = $lembaga->apiClients()->orderBy('nama')->get();` and pass to view (alongside admins).

- [ ] **Step 5: Blade section on show**

Below Admin lembaga: table + form tambah (checkboxes for scopes, select field_profile) + per-row Ubah modal. Rotate/revoke buttons can be placeholders disabled until Task 4 **or** wire routes in Task 4 only — prefer add buttons in Task 4 to avoid dead POSTs.

`key-once.blade.php`: warning Indonesian, readonly key, copy button (`data-copy-target` like password-once), link back to show. Optional: `Cache-Control: no-store` on response.

- [ ] **Step 6: Smoke**

```bash
/usr/local/Cellar/php/8.5.8/bin/php vendor/bin/phpunit --filter=AdminShellTest --colors=never
```

- [ ] **Step 7: Commit**

```bash
git commit -m "feat(m5b): nested API client create/update with copy-once key"
```

---

### Task 4: Rotate + revoke

**Files:**
- Modify: `LembagaApiClientController`, `routes/web.php`, `show.blade.php`

- [ ] **Step 1: `rotate` method**

```php
public function rotate(Request $request, Lembaga $lembaga, ApiClient $apiClient): RedirectResponse
{
    $this->assertClientBelongsToLembaga($lembaga, $apiClient);
    $this->authorize('rotate', $apiClient);

    $oldPrefix = $apiClient->api_key_prefix;
    $issued = $this->issuer->issue();

    $apiClient->update([
        'api_key_prefix' => $issued['prefix'],
        'api_key_digest' => $issued['digest'],
        'last_used_at' => null,
        'last_used_ip' => null,
    ]);

    $this->auditLogger->record('api_key.rotate', 'success', [
        'old_prefix' => $oldPrefix,
        'new_prefix' => $issued['prefix'],
    ], subject: $apiClient, lembagaId: $lembaga->id, apiKeyPrefix: $issued['prefix'], request: $request);

    return redirect()
        ->route('admin.lembaga.api-clients.key-once', [$lembaga, $apiClient])
        ->with('generated_api_key', [
            'api_client_id' => (string) $apiClient->id,
            'plain_key' => $issued['plain'],
        ]);
}
```

Do **not** set `revoked_at` on rotate.

- [ ] **Step 2: `revoke` method**

```php
public function revoke(Request $request, Lembaga $lembaga, ApiClient $apiClient): RedirectResponse
{
    $this->assertClientBelongsToLembaga($lembaga, $apiClient);
    $this->authorize('revoke', $apiClient);

    $apiClient->update([
        'is_active' => false,
        'revoked_at' => now(),
    ]);

    $this->auditLogger->record('api_client.revoke', 'success', [
        'prefix' => $apiClient->api_key_prefix,
    ], subject: $apiClient, lembagaId: $lembaga->id, apiKeyPrefix: $apiClient->api_key_prefix, request: $request);

    return redirect()
        ->route('admin.lembaga.show', $lembaga)
        ->with('status', 'API client dicabut.');
}
```

- [ ] **Step 3: Routes + modals**

```php
Route::post('/lembaga/{lembaga}/api-clients/{apiClient}/rotate', ...)->name('admin.lembaga.api-clients.rotate');
Route::post('/lembaga/{lembaga}/api-clients/{apiClient}/revoke', ...)->name('admin.lembaga.api-clients.revoke');
```

UI: for active non-revoked clients — button Rotate opens modal (impact text), button Cabut opens modal. Hide mutate actions if revoked.

- [ ] **Step 4: Commit**

```bash
git commit -m "feat(m5b): rotate and revoke API client keys with confirmation modals"
```

---

### Task 5: Admin Lembaga read-only index

**Files:**
- Create: `ApiClientController`, `resources/views/admin/api-clients/index.blade.php`
- Modify: `AdminMenu`, `routes/web.php`, coming-soon `where` (drop `api-client|api-client-ro`)

- [ ] **Step 1: Controller**

```php
public function index(Request $request): View
{
    $user = $request->user();
    $this->authorize('viewAny', ApiClient::class);

    abort_unless($user->isAdminLembaga(), 403);

    $clients = ApiClient::query()
        ->where('lembaga_id', $user->lembaga_id)
        ->orderBy('nama')
        ->get();

    return view('admin.api-clients.index', compact('clients'));
}
```

- [ ] **Step 2: Menu AL**

```php
['label' => 'API client', 'route' => 'admin.api-clients.index', 'available' => true],
```

- [ ] **Step 3: View** — table only; Bahasa Indonesia; empty state; status badge Aktif / Dicabut.

- [ ] **Step 4: Commit**

```bash
git commit -m "feat(m5b): Admin Lembaga read-only API client list"
```

---

### Task 6: Feature tests

**Files:**
- Create: `tests/Feature/ApiClientAdminTest.php`
- Adjust: `AdminShellTest`, `phpunit.xml` / TestCase if pepper needed globally

- [ ] **Step 1: In `Tests\TestCase::setUp` or per-test:**

```php
config(['security.api_key_pepper' => 'test-pepper-not-for-production']);
config(['security.mfa.super_admin_required' => false]);
```

- [ ] **Step 2: Cover spec §7**

1. SA create → follow key-once → see plain; `ApiKeyVerifier` true; audit no plain; second GET key-once redirects.
2. Rotate → new plain; old plain fails verifier; same id.
3. Update metadata → digest unchanged.
4. Revoke → `is_active` false, `revoked_at` set.
5. AL index 200; AL POST store/rotate/revoke → 403.
6. Cross-lembaga update/rotate → 404.
7. Flash leak: create A (skip key-once), create B, GET key-once A → unavailable.

Also assert SA sidebar no longer shows coming-soon API client as available menu item.

```bash
/usr/local/Cellar/php/8.5.8/bin/php vendor/bin/phpunit --filter=ApiClientAdminTest --colors=never
/usr/local/Cellar/php/8.5.8/bin/php vendor/bin/phpunit --colors=never
```

Expected: all PASS

- [ ] **Step 3: Commit**

```bash
git commit -m "test(m5b): cover API client admin security and copy-once flows"
```

---

### Task 7: Docs

**Files:**
- `docs/IMPLEMENTATION_TODO.md`
- Spec status → `Approved`

- [ ] Check: buat client, generate copy-once, rotate modal, revoke, AL read-only, audit API client/key, review bullets for API key as done after tests green.
- [ ] Status Milestone 5: **M5a+M5b selesai** (M7 API auth masih terbuka).
- [ ] Commit: `docs: mark Milestone 5b API client complete; M7 still open`

---

## Spec coverage checklist

| Spec requirement | Task |
|------------------|------|
| Nested Detail lembaga UI | 3, 4 |
| Remove SA sidebar API client | 2 |
| Scope checklist whitelist | 1, 3 |
| Rotate in-place, no revoked_at | 4 |
| Edit metadata without key change | 3 |
| AL read-only menu | 5 |
| HMAC pepper + copy-once flash binding | 1, 3, 6 |
| No REST /api consumer | all |
| IMPLEMENTATION_TODO | 7 |

## Out of scope

REST endpoints, rate limits, key history table, un-revoke, Livewire.
