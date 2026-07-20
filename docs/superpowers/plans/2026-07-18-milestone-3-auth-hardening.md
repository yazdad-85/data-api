# Milestone 3 Auth Hardening Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Implement hardened admin session auth (login/logout, throttle, MFA Super Admin digate env), active-user middleware, Gate/Policy + tenant scope, and session invalidation helpers for Pusat Data Milestone 3.

**Architecture:** Explicit session pipeline — password check via `AdminAuthenticator` → optional pending-MFA session (no full `Auth::login`) → TOTP/recovery via `TotpVerifier` → full login with session regenerate. Server-side Gates/Policies + `BelongsToLembaga` global scope enforce multi-tenant isolation. Follow project RULES B1: write code → review → test (not test-first).

**Tech Stack:** Laravel 13, PHP 8.5, PostgreSQL (tests: sqlite `:memory:`), Blade forms, session driver database (tests: array), PHPUnit Feature/Unit tests.

**Spec:** `docs/superpowers/specs/2026-07-18-milestone-3-auth-hardening-design.md`

**PHP CLI note (local iMac):** default `php` may be 8.1 — use `/usr/local/Cellar/php/8.5.8/bin/php` (or ensure PATH) for `artisan` / `composer` / tests.

---

## File structure

| Path | Responsibility |
|------|----------------|
| `config/security.php` | MFA required flag, pending TTL, TOTP window |
| `.env.example` | Document `MFA_SUPER_ADMIN_REQUIRED` |
| `phpunit.xml` | Default MFA required true in tests (override per test) |
| `app/Models/User.php` | Role helpers |
| `app/Models/Concerns/BelongsToLembaga.php` | Tenant global scope |
| `app/Models/{Guru,Siswa,Kelas,TahunAjaran,Karyawan}.php` | Use tenant trait |
| `app/Support/Security/TotpVerifier.php` | RFC 6238 TOTP + recovery codes |
| `app/Services/Auth/AdminAuthenticator.php` | Credential + active/lembaga checks |
| `app/Services/Auth/SessionInvalidator.php` | Delete sessions by user_id |
| `app/Http/Requests/Auth/LoginRequest.php` | Validate login input |
| `app/Http/Requests/Auth/MfaChallengeRequest.php` | Validate MFA code |
| `app/Http/Controllers/Auth/LoginController.php` | Show/store login |
| `app/Http/Controllers/Auth/MfaChallengeController.php` | Show/store MFA |
| `app/Http/Controllers/Auth/LogoutController.php` | Logout |
| `app/Http/Controllers/Admin/DashboardController.php` | Stub dashboard |
| `app/Http/Middleware/EnsureUserIsActive.php` | Force logout if inactive |
| `app/Http/Middleware/EnsureMfaSatisfied.php` | Block pending-only / incomplete MFA |
| `app/Policies/GuruPolicy.php` | Tenant policy pattern (Guru as representative for M3 tests) |
| `app/Providers/AppServiceProvider.php` | Gates, policy registration, rate limiters |
| `bootstrap/app.php` | Alias middleware |
| `routes/web.php` | Auth + admin routes |
| `resources/views/layouts/guest.blade.php` | Minimal guest layout |
| `resources/views/auth/login.blade.php` | Login form ID |
| `resources/views/auth/mfa.blade.php` | MFA form ID |
| `resources/views/admin/dashboard.blade.php` | Stub admin page |
| `database/factories/LembagaFactory.php` | Test lembaga |
| `database/factories/GuruFactory.php` | Test guru |
| `database/factories/UserFactory.php` | States `adminLembaga`, `inactive` |
| `tests/Unit/TotpVerifierTest.php` | TOTP/recovery unit tests |
| `tests/Feature/AdminAuthTest.php` | Login/MFA/throttle/active |
| `tests/Feature/TenantAuthorizationTest.php` | Scope + policy |
| `docs/IMPLEMENTATION_TODO.md` | Check off M3 after green |

---

### Task 1: Config security + User role helpers

**Files:**
- Create: `config/security.php`
- Modify: `.env.example`
- Modify: `phpunit.xml`
- Modify: `app/Models/User.php`
- Modify: `database/factories/UserFactory.php`
- Create: `database/factories/LembagaFactory.php`

- [ ] **Step 1: Create `config/security.php`**

```php
<?php

return [
    'mfa' => [
        'super_admin_required' => (bool) env('MFA_SUPER_ADMIN_REQUIRED', true),
        'pending_ttl_minutes' => (int) env('MFA_PENDING_TTL_MINUTES', 10),
        'totp_window' => (int) env('MFA_TOTP_WINDOW', 1),
    ],
];
```

- [ ] **Step 2: Add to `.env.example` (near session vars)**

```env
MFA_SUPER_ADMIN_REQUIRED=true
```

- [ ] **Step 3: Add to `phpunit.xml` `<php>` block**

```xml
<env name="MFA_SUPER_ADMIN_REQUIRED" value="true"/>
```

- [ ] **Step 4: Add helpers on `User` (after relations)**

```php
public function isSuperAdmin(): bool
{
    return $this->role === 'super_admin';
}

public function isAdminLembaga(): bool
{
    return $this->role === 'admin_lembaga';
}

public function canAccessLembaga(string $lembagaId): bool
{
    if ($this->isSuperAdmin()) {
        return true;
    }

    return $this->isAdminLembaga()
        && $this->lembaga_id !== null
        && hash_equals((string) $this->lembaga_id, $lembagaId);
}

public function hasMfaEnabled(): bool
{
    return $this->mfa_enabled_at !== null && filled($this->mfa_secret);
}
```

- [ ] **Step 5: Extend `UserFactory` with states**

```php
public function adminLembaga(?string $lembagaId = null): static
{
    return $this->state(fn (array $attributes) => [
        'role' => 'admin_lembaga',
        'lembaga_id' => $lembagaId,
    ]);
}

public function inactive(): static
{
    return $this->state(fn (array $attributes) => [
        'is_active' => false,
    ]);
}

public function withMfa(string $secret = 'JBSWY3DPEHPK3PXP', array $recoveryCodes = ['AAAA-BBBB', 'CCCC-DDDD']): static
{
    return $this->state(fn (array $attributes) => [
        'mfa_enabled_at' => now(),
        'mfa_secret' => $secret,
        'recovery_codes_hash' => array_map(
            static fn (string $code): string => \Illuminate\Support\Facades\Hash::make($code),
            $recoveryCodes,
        ),
    ]);
}
```

Default factory password for tests should be long enough for login forms: change default hashed password to `StrongPassword123` (or document that tests pass `'password' => Hash::make('StrongPassword123')` explicitly). Prefer explicit password in auth tests.

- [ ] **Step 6: Create `LembagaFactory`**

```php
<?php

namespace Database\Factories;

use App\Models\Lembaga;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<Lembaga> */
class LembagaFactory extends Factory
{
    protected $model = Lembaga::class;

    public function definition(): array
    {
        return [
            'kode' => strtoupper(fake()->unique()->bothify('LBG-###')),
            'nama' => fake()->company(),
            'jenis' => 'sekolah',
            'is_active' => true,
        ];
    }

    public function inactive(): static
    {
        return $this->state(fn (array $attributes) => [
            'is_active' => false,
        ]);
    }
}
```

Ensure `Lembaga` model has `HasFactory` trait.

- [ ] **Step 7: Review**

Check: no secrets hardcoded beyond well-known test TOTP secret; env default `true` matches production intent.

- [ ] **Step 8: Commit**

```bash
git add config/security.php .env.example phpunit.xml app/Models/User.php app/Models/Lembaga.php database/factories/
git commit -m "$(cat <<'EOF'
Add security config and user role helpers for admin auth.

EOF
)"
```

---

### Task 2: TotpVerifier + AdminAuthenticator + SessionInvalidator

**Files:**
- Create: `app/Support/Security/TotpVerifier.php`
- Create: `app/Services/Auth/AdminAuthenticator.php`
- Create: `app/Services/Auth/SessionInvalidator.php`
- Create: `tests/Unit/TotpVerifierTest.php`
- Create: `tests/Unit/AdminAuthenticatorTest.php`

- [ ] **Step 1: Implement `TotpVerifier`**

```php
<?php

namespace App\Support\Security;

use App\Models\User;
use Illuminate\Support\Facades\Hash;

class TotpVerifier
{
    private const BASE32_ALPHABET = 'ABCDEFGHIJKLMNOPQRSTUVWXYZ234567';

    public function verify(User $user, string $code): bool
    {
        $code = strtoupper(trim($code));

        if ($code === '') {
            return false;
        }

        if (str_contains($code, '-')) {
            return $this->consumeRecoveryCode($user, $code);
        }

        $secret = (string) $user->mfa_secret;
        if ($secret === '') {
            return false;
        }

        $normalized = preg_replace('/\s+/', '', $code) ?? '';
        if (! preg_match('/^\d{6}$/', $normalized)) {
            return false;
        }

        $window = (int) config('security.mfa.totp_window', 1);
        $timeSlice = intdiv(time(), 30);

        for ($i = -$window; $i <= $window; $i++) {
            if (hash_equals($this->hotp($secret, $timeSlice + $i), $normalized)) {
                return true;
            }
        }

        return false;
    }

    private function consumeRecoveryCode(User $user, string $code): bool
    {
        $hashes = $user->recovery_codes_hash ?? [];
        if (! is_array($hashes) || $hashes === []) {
            return false;
        }

        foreach ($hashes as $index => $hash) {
            if (is_string($hash) && Hash::check($code, $hash)) {
                unset($hashes[$index]);
                $user->forceFill([
                    'recovery_codes_hash' => array_values($hashes),
                ])->save();

                return true;
            }
        }

        return false;
    }

    public function currentCode(string $base32Secret): string
    {
        return $this->hotp($base32Secret, intdiv(time(), 30));
    }

    private function hotp(string $base32Secret, int $counter): string
    {
        $secret = $this->base32Decode($base32Secret);
        $binaryCounter = pack('N*', 0, $counter);
        $hash = hash_hmac('sha1', $binaryCounter, $secret, true);
        $offset = ord($hash[19]) & 0x0F;
        $value = (
            ((ord($hash[$offset]) & 0x7F) << 24)
            | ((ord($hash[$offset + 1]) & 0xFF) << 16)
            | ((ord($hash[$offset + 2]) & 0xFF) << 8)
            | (ord($hash[$offset + 3]) & 0xFF)
        );

        return str_pad((string) ($value % 1_000_000), 6, '0', STR_PAD_LEFT);
    }

    private function base32Decode(string $input): string
    {
        $input = strtoupper(preg_replace('/[^A-Z2-7]/', '', $input) ?? '');
        $buffer = 0;
        $bitsLeft = 0;
        $result = '';

        foreach (str_split($input) as $char) {
            $value = strpos(self::BASE32_ALPHABET, $char);
            if ($value === false) {
                continue;
            }
            $buffer = ($buffer << 5) | $value;
            $bitsLeft += 5;
            if ($bitsLeft >= 8) {
                $bitsLeft -= 8;
                $result .= chr(($buffer >> $bitsLeft) & 0xFF);
            }
        }

        return $result;
    }
}
```

- [ ] **Step 2: Implement `AdminAuthenticator`**

Return a small result object/array — do **not** throw user-facing differentiated messages.

```php
<?php

namespace App\Services\Auth;

use App\Models\User;
use App\Services\AuditLogger;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;

final class AdminAuthenticator
{
    public const FAILURE_MESSAGE = 'Email atau password salah';

    public function __construct(private readonly AuditLogger $auditLogger)
    {
    }

    /**
     * @return array{ok: true, user: User}|array{ok: false, message: string, reason: string}
     */
    public function attempt(string $email, string $password, Request $request): array
    {
        $email = strtolower(trim($email));
        $user = User::query()->where('email', $email)->first();

        if ($user === null || ! Hash::check($password, $user->password)) {
            $this->auditLogger->record('auth.login', 'failed', [
                'reason' => 'invalid_credentials',
                'email' => $email,
            ], request: $request);

            return ['ok' => false, 'message' => self::FAILURE_MESSAGE, 'reason' => 'invalid_credentials'];
        }

        if (! $user->is_active) {
            $this->auditLogger->record('auth.login', 'failed', [
                'reason' => 'user_inactive',
                'email' => $email,
            ], user: $user, lembagaId: $user->lembaga_id, request: $request);

            return ['ok' => false, 'message' => self::FAILURE_MESSAGE, 'reason' => 'user_inactive'];
        }

        if ($user->isAdminLembaga()) {
            $user->loadMissing('lembaga');
            if ($user->lembaga === null || ! $user->lembaga->is_active) {
                $this->auditLogger->record('auth.login', 'failed', [
                    'reason' => 'lembaga_inactive',
                    'email' => $email,
                ], user: $user, lembagaId: $user->lembaga_id, request: $request);

                return ['ok' => false, 'message' => self::FAILURE_MESSAGE, 'reason' => 'lembaga_inactive'];
            }
        }

        return ['ok' => true, 'user' => $user];
    }
}
```

- [ ] **Step 3: Implement `SessionInvalidator`**

```php
<?php

namespace App\Services\Auth;

use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Session;

class SessionInvalidator
{
    public function invalidateUser(string $userId): void
    {
        if (config('session.driver') === 'database') {
            DB::table(config('session.table', 'sessions'))
                ->where('user_id', $userId)
                ->delete();
        }

        if (Auth::id() === $userId) {
            Auth::logout();
            Session::invalidate();
            Session::regenerateToken();
        }
    }
}
```

- [ ] **Step 4: Review services**

Checklist: generic failure message only; audit uses redactor (email becomes `[REDACTED]`); recovery consume persists; no TOTP logged.

- [ ] **Step 5: Write `tests/Unit/TotpVerifierTest.php`**

```php
<?php

namespace Tests\Unit;

use App\Models\User;
use App\Support\Security\TotpVerifier;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class TotpVerifierTest extends TestCase
{
    use RefreshDatabase;

    public function test_verifies_current_totp_code(): void
    {
        $secret = 'JBSWY3DPEHPK3PXP';
        $user = User::factory()->withMfa($secret, [])->create([
            'role' => 'super_admin',
            'lembaga_id' => null,
        ]);
        $verifier = app(TotpVerifier::class);

        $this->assertTrue($verifier->verify($user, $verifier->currentCode($secret)));
        $this->assertFalse($verifier->verify($user, '000000'));
    }

    public function test_consumes_recovery_code_once(): void
    {
        $user = User::factory()->withMfa('JBSWY3DPEHPK3PXP', ['ABCD-EFGH'])->create([
            'role' => 'super_admin',
            'lembaga_id' => null,
        ]);
        $verifier = app(TotpVerifier::class);

        $this->assertTrue($verifier->verify($user, 'ABCD-EFGH'));
        $user->refresh();
        $this->assertSame([], $user->recovery_codes_hash);
        $this->assertFalse($verifier->verify($user->fresh(), 'ABCD-EFGH'));
    }
}
```

- [ ] **Step 6: Write `tests/Unit/AdminAuthenticatorTest.php`** covering invalid password, inactive user, inactive lembaga — all assert same `FAILURE_MESSAGE` and distinct audit `reason`.

- [ ] **Step 7: Run tests**

```bash
/usr/local/Cellar/php/8.5.8/bin/php artisan test --filter='TotpVerifierTest|AdminAuthenticatorTest'
```

Expected: PASS

- [ ] **Step 8: Commit**

```bash
git add app/Support/Security/TotpVerifier.php app/Services/Auth tests/Unit
git commit -m "$(cat <<'EOF'
Add admin authenticator, TOTP verifier, and session invalidator.

EOF
)"
```

---

### Task 3: Auth controllers, views, throttle, MFA pending session

**Files:**
- Create controllers/requests/views listed in file structure
- Modify: `routes/web.php`
- Modify: `bootstrap/app.php`
- Modify: `app/Providers/AppServiceProvider.php` (rate limiters)

- [ ] **Step 1: Register rate limiters in `AppServiceProvider::boot`**

```php
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Str;

RateLimiter::for('admin-login', function (Request $request) {
    $email = Str::lower((string) $request->input('email', ''));

    return [
        Limit::perMinute(5)->by($email.'|'.$request->ip()),
        Limit::perMinute(20)->by('ip:'.$request->ip()),
    ];
});
```

- [ ] **Step 2: Session key constants** — put on a small class `App\Support\Security\MfaPendingSession`:

```php
<?php

namespace App\Support\Security;

use App\Models\User;
use Illuminate\Support\Facades\Session;

class MfaPendingSession
{
    public const USER_ID = 'auth.mfa_pending_user_id';
    public const EXPIRES_AT = 'auth.mfa_pending_expires_at';

    public static function put(User $user): void
    {
        Session::put(self::USER_ID, $user->id);
        Session::put(
            self::EXPIRES_AT,
            now()->addMinutes((int) config('security.mfa.pending_ttl_minutes', 10))->timestamp
        );
    }

    public static function clear(): void
    {
        Session::forget([self::USER_ID, self::EXPIRES_AT]);
    }

    public static function user(): ?User
    {
        $userId = Session::get(self::USER_ID);
        $expires = Session::get(self::EXPIRES_AT);
        if (! is_string($userId) || ! is_int($expires) || $expires < now()->timestamp) {
            self::clear();

            return null;
        }

        return User::query()->find($userId);
    }
}
```

- [ ] **Step 3: `LoginController`**

```php
// store():
// 1. $request validated via LoginRequest
// 2. $result = AdminAuthenticator->attempt(...)
// 3. if !ok → back with errors(['email' => message]) — do not use 'email' existence oracle beyond same message
// 4. $request->session()->regenerate()
// 5. if super_admin && config mfa required:
//      if !hasMfaEnabled → clear, back with FAILURE_MESSAGE + audit reason mfa_not_enabled
//      else MfaPendingSession::put($user); redirect route('login.mfa')
// 6. Auth::login($user, false); audit success; redirect route('admin.dashboard')
```

`LoginRequest` rules: `email` required|email, `password` required|string — **no** password min on login (avoid leaking policy on login).

- [ ] **Step 4: `MfaChallengeController`**

```php
// show: if MfaPendingSession::user() null → redirect login
// store: verify code; on fail audit auth.mfa failed + generic error
// on success: Auth::login($user, false); session regenerate; MfaPendingSession::clear(); audit success; redirect dashboard
```

MFA form field name: `code` (TOTP 6 digit or recovery with hyphen).

- [ ] **Step 5: `LogoutController`** — POST only; Auth::logout; session invalidate; regenerateToken; audit `auth.logout`.

- [ ] **Step 6: Blade views** — Bahasa Indonesia, minimal, CSRF `@csrf`, no remember-me checkbox.

- [ ] **Step 7: Routes in `web.php`**

```php
use App\Http\Controllers\Auth\LoginController;
use App\Http\Controllers\Auth\MfaChallengeController;
use App\Http\Controllers\Auth\LogoutController;
use App\Http\Controllers\Admin\DashboardController;

Route::middleware('guest')->group(function () {
    Route::get('/login', [LoginController::class, 'create'])->name('login');
    Route::post('/login', [LoginController::class, 'store'])
        ->middleware('throttle:admin-login');
    Route::get('/login/mfa', [MfaChallengeController::class, 'create'])->name('login.mfa');
    Route::post('/login/mfa', [MfaChallengeController::class, 'store'])
        ->middleware('throttle:admin-login');
});

Route::post('/logout', LogoutController::class)
    ->middleware('auth')
    ->name('logout');

Route::middleware(['auth', 'active', 'mfa'])->prefix('admin')->group(function () {
    Route::get('/', [DashboardController::class, 'show'])->name('admin.dashboard');
});
```

Redirect `/` guests to login optional; stub dashboard returns view with user name + role.

- [ ] **Step 8: Alias middleware in `bootstrap/app.php`**

```php
$middleware->alias([
    'active' => \App\Http\Middleware\EnsureUserIsActive::class,
    'mfa' => \App\Http\Middleware\EnsureMfaSatisfied::class,
]);
```

Implement middleware stubs in this task if not already:
- `EnsureUserIsActive`: if auth user inactive or admin lembaga lembaga inactive → `SessionInvalidator` + redirect login with FAILURE_MESSAGE
- `EnsureMfaSatisfied`: if `MfaPendingSession` has user and route is not MFA → redirect MFA; if auth super admin && mfa required && !hasMfaEnabled → logout + redirect login

- [ ] **Step 9: Review auth HTTP layer**

CSRF present; remember false; regenerate called; no secrets in views; throttle attached.

- [ ] **Step 10: Write `tests/Feature/AdminAuthTest.php`** (core cases)

Cover at minimum:
1. Valid Admin Lembaga login → dashboard 200; session regenerated (compare session id before/after if practical via `$this->withSession` / cookie)
2. Wrong password → 302 back + same generic message; no Auth
3. Inactive lembaga admin → generic message
4. Inactive user → generic message
5. Super Admin with MFA required → after password, guest on dashboard, can access MFA page; wrong code fails generically; correct TOTP logs in
6. `MFA_SUPER_ADMIN_REQUIRED=false` → Super Admin enters without MFA
7. Throttle: 5 failed attempts for same email+IP → 429 on 6th (use `RateLimiter::clear` between unrelated tests)
8. Logout clears auth
9. Audit success/fail does not contain password or TOTP plaintext

Example MFA login fragment:

```php
config(['security.mfa.super_admin_required' => true]);
$secret = 'JBSWY3DPEHPK3PXP';
$user = User::factory()->withMfa($secret)->create([
    'email' => 'super@example.test',
    'password' => 'StrongPassword123',
    'role' => 'super_admin',
    'lembaga_id' => null,
]);

$this->post('/login', [
    'email' => 'super@example.test',
    'password' => 'StrongPassword123',
])->assertRedirect(route('login.mfa'));

$this->assertGuest();

$code = app(\App\Support\Security\TotpVerifier::class)->currentCode($secret);

$this->post('/login/mfa', ['code' => $code])
    ->assertRedirect(route('admin.dashboard'));

$this->assertAuthenticatedAs($user);
```

- [ ] **Step 11: Run tests**

```bash
/usr/local/Cellar/php/8.5.8/bin/php artisan test --filter=AdminAuthTest
```

Expected: PASS

- [ ] **Step 12: Commit**

```bash
git add app/Http app/Support/Security/MfaPendingSession.php app/Providers/AppServiceProvider.php bootstrap/app.php routes/web.php resources/views tests/Feature/AdminAuthTest.php
git commit -m "$(cat <<'EOF'
Implement hardened admin login, MFA challenge, and logout flow.

EOF
)"
```

---

### Task 4: Tenant scope, Gates, GuruPolicy

**Files:**
- Create: `app/Models/Concerns/BelongsToLembaga.php`
- Modify: `Guru`, `Siswa`, `Kelas`, `TahunAjaran`, `Karyawan`
- Create: `app/Policies/GuruPolicy.php`
- Create: `database/factories/GuruFactory.php`
- Modify: `AppServiceProvider` (Gate + Policy)
- Create: `tests/Feature/TenantAuthorizationTest.php`

- [ ] **Step 1: Trait `BelongsToLembaga`**

```php
<?php

namespace App\Models\Concerns;

use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Auth;

trait BelongsToLembaga
{
    public static function bootBelongsToLembaga(): void
    {
        static::addGlobalScope('lembaga', function (Builder $builder): void {
            $user = Auth::user();
            if (! $user instanceof User) {
                $builder->whereRaw('1 = 0');

                return;
            }

            if ($user->isSuperAdmin()) {
                return;
            }

            if ($user->isAdminLembaga() && $user->lembaga_id) {
                $builder->where(
                    $builder->getModel()->getTable().'.lembaga_id',
                    $user->lembaga_id
                );

                return;
            }

            $builder->whereRaw('1 = 0');
        });
    }
}
```

Add `use BelongsToLembaga;` on the five tenant models.

- [ ] **Step 2: `GuruPolicy`**

```php
<?php

namespace App\Policies;

use App\Models\Guru;
use App\Models\User;

class GuruPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->isSuperAdmin() || $user->isAdminLembaga();
    }

    public function view(User $user, Guru $guru): bool
    {
        return $user->canAccessLembaga((string) $guru->lembaga_id);
    }

    public function create(User $user): bool
    {
        return $user->isSuperAdmin() || $user->isAdminLembaga();
    }

    public function update(User $user, Guru $guru): bool
    {
        return $user->canAccessLembaga((string) $guru->lembaga_id);
    }

    public function delete(User $user, Guru $guru): bool
    {
        return $user->canAccessLembaga((string) $guru->lembaga_id);
    }
}
```

Register: `Gate::policy(Guru::class, GuruPolicy::class);`

Optional thin route for test only is discouraged — instead test via `Gate::forUser($admin)->allows('view', $guru)` and `Guru::all()` count under `actingAs`.

- [ ] **Step 3: Gates in `AppServiceProvider`**

```php
Gate::define('access-admin', fn (User $user) => $user->isSuperAdmin() || $user->isAdminLembaga());
Gate::define('manage-all-lembaga', fn (User $user) => $user->isSuperAdmin());
Gate::define('manage-own-lembaga', fn (User $user) => $user->isAdminLembaga());
```

- [ ] **Step 4: `GuruFactory`** — requires `lembaga_id`, minimal required columns from migration (`nama`, `jenis_kelamin`, etc.). Read migration for NOT NULL columns and fill them.

- [ ] **Step 5: Review** — Super Admin not scoped out; guest queries return empty; policy never trusts client `lembaga_id` alone for Admin without matching auth lembaga.

- [ ] **Step 6: `TenantAuthorizationTest`**

```php
// Admin A sees only guru A; cannot Gate::allows view guru B
// Super Admin sees both
// Optional: actingAs admin B hitting a future show URL pattern — for M3, Gate deny is enough; add audit helper call if you add an authorize() wrapper used by controllers later
```

Also assert cross-tenant attempt can be audited when using a small helper `TenantAccess::assertCanView($user, $model)` if introduced — **YAGNI**: Gate deny in tests is sufficient for M3; audit of cross-tenant UI attempts lands with CRUD controllers in M5/M6. Spec mentions audit on cross-tenant attempts — add a thin `App\Support\Authorization\TenantAuthorizer` used by future controllers:

```php
public function authorizeView(User $user, Model $model): void
{
    if (! Gate::forUser($user)->allows('view', $model)) {
        app(AuditLogger::class)->record('authz.cross_tenant', 'blocked', [
            'subject_type' => $model::class,
            'subject_id' => $model->getKey(),
        ], user: $user, lembagaId: $user->lembaga_id);
        abort(403);
    }
}
```

Test that calling it with foreign guru aborts 403 and writes audit.

- [ ] **Step 7: Run tests**

```bash
/usr/local/Cellar/php/8.5.8/bin/php artisan test --filter=TenantAuthorizationTest
```

Expected: PASS

- [ ] **Step 8: Commit**

```bash
git add app/Models app/Policies app/Support/Authorization app/Providers/AppServiceProvider.php database/factories/GuruFactory.php tests/Feature/TenantAuthorizationTest.php
git commit -m "$(cat <<'EOF'
Add tenant global scope, gates, and Guru policy enforcement.

EOF
)"
```

---

### Task 5: Full suite, IMPLEMENTATION_TODO, final review

**Files:**
- Modify: `docs/IMPLEMENTATION_TODO.md`
- Modify: `docs/superpowers/specs/2026-07-18-milestone-3-auth-hardening-design.md` (status → DISETUJUI jika belum)

- [ ] **Step 1: Full review checklist (RULES B2)**

Walk: login controllers, middleware, TotpVerifier, AdminAuthenticator, BelongsToLembaga, policies. Fix any findings before claiming done.

- [ ] **Step 2: Run full test suite**

```bash
/usr/local/Cellar/php/8.5.8/bin/php artisan test
```

Expected: all green (including existing `SecurityFoundationTest`).

- [ ] **Step 3: Update Milestone 3 checkboxes in `IMPLEMENTATION_TODO.md`** only for items proven by review + tests. Leave unchecked anything not actually done.

- [ ] **Step 4: Commit + push**

```bash
git add docs/IMPLEMENTATION_TODO.md docs/superpowers/
git commit -m "$(cat <<'EOF'
Complete Milestone 3 auth hardening checklist after verified tests.

EOF
)"
git push
```

---

## Spec coverage checklist (self-review)

| Spec requirement | Task |
|------------------|------|
| Login/logout session | Task 3 |
| Throttle 5/email+IP + 20/IP | Task 3 |
| MFA env-gated | Task 1 + 3 |
| Middleware user aktif | Task 3 |
| Admin lembaga nonaktif ditolak | Task 2 + 3 |
| Gate/Policy | Task 4 |
| Tenant scope CRUD master | Task 4 |
| SessionInvalidator | Task 2 (+ M5 hook later) |
| Pesan generik | Task 2 + 3 |
| Remember me off / regenerate / CSRF | Task 3 |
| Audit ringkas tanpa secret | Task 2 + 3 |
| Forgot-password out of scope | — (not in plan) |
| Tests listed in design §8 | Task 3 + 4 + 5 |

## Placeholder / ambiguity fixes locked here

- IP throttle = **20/minute** (from approved design).
- Pending MFA TTL = **10 minutes**.
- Create identical Policy classes for **Guru, Siswa, Kelas, TahunAjaran, Karyawan** in Task 4 (same rules as `GuruPolicy`); trait applied to all five models in the same task.
