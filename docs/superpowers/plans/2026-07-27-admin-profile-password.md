# Admin Profile & Password Change Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Add a self-service Profil page so Super Admin and Admin Lembaga can update their name and change password, invalidating other sessions.

**Architecture:** Header link to `GET /admin/profil` with two forms (name update + password change). Always operate on `auth()->user()`. Extend `SessionInvalidator` with `invalidateOtherSessions` so the current session stays logged in. Audit without secrets. Reuse existing Blade UI components and admin middleware stack.

**Tech Stack:** Laravel 13, Blade, FormRequest, `Password::min(12)`, database sessions, PHPUnit feature/unit tests.

**Spec:** `docs/superpowers/specs/2026-07-27-admin-profile-password-design.md`

---

## File map

| File | Responsibility |
|------|----------------|
| `app/Services/Auth/SessionInvalidator.php` | Add `invalidateOtherSessions` |
| `tests/Unit/SessionInvalidatorTest.php` | Cover except-current-session behavior |
| `app/Http/Controllers/Admin/ProfileController.php` | show / update / updatePassword |
| `app/Http/Requests/Admin/UpdateProfileRequest.php` | Validate name |
| `app/Http/Requests/Admin/UpdatePasswordRequest.php` | Validate current + new password |
| `resources/views/admin/profile/show.blade.php` | Profil UI |
| `resources/views/partials/admin-header.blade.php` | Name → link to profil |
| `routes/web.php` | Register profil routes |
| `app/Providers/AppServiceProvider.php` | Rate limiter `admin-profile-password` |
| `tests/Feature/AdminProfileTest.php` | Feature coverage |
| `docs/SPEC.md` | Short §5 note |
| `docs/IMPLEMENTATION_TODO.md` | Optional checklist note under M12 or ops gap |

---

### Task 1: SessionInvalidator — invalidate other sessions

**Files:**
- Modify: `app/Services/Auth/SessionInvalidator.php`
- Modify: `tests/Unit/SessionInvalidatorTest.php`

- [ ] **Step 1: Write failing unit test**

Add to `tests/Unit/SessionInvalidatorTest.php`:

```php
public function test_invalidate_other_sessions_keeps_excepted_session(): void
{
    config(['session.driver' => 'database']);

    $user = User::factory()->create();

    DB::table('sessions')->insert([
        [
            'id' => 'session-keep',
            'user_id' => $user->id,
            'ip_address' => '127.0.0.1',
            'user_agent' => 'PHPUnit',
            'payload' => base64_encode('keep'),
            'last_activity' => time(),
        ],
        [
            'id' => 'session-drop',
            'user_id' => $user->id,
            'ip_address' => '127.0.0.1',
            'user_agent' => 'PHPUnit',
            'payload' => base64_encode('drop'),
            'last_activity' => time(),
        ],
    ]);

    app(SessionInvalidator::class)->invalidateOtherSessions($user->id, 'session-keep');

    $this->assertDatabaseHas('sessions', ['id' => 'session-keep']);
    $this->assertDatabaseMissing('sessions', ['id' => 'session-drop']);
}

public function test_invalidate_other_sessions_does_not_logout_current_user(): void
{
    $user = User::factory()->create();
    Auth::login($user);

    app(SessionInvalidator::class)->invalidateOtherSessions($user->id, 'any-session-id');

    $this->assertTrue(Auth::check());
    $this->assertSame((string) $user->id, (string) Auth::id());
}
```

- [ ] **Step 2: Run test — expect fail**

```bash
/usr/local/Cellar/php/8.5.8/bin/php artisan test --filter=SessionInvalidatorTest
```

Expected: FAIL — method `invalidateOtherSessions` undefined.

- [ ] **Step 3: Implement method**

In `app/Services/Auth/SessionInvalidator.php`, add:

```php
public function invalidateOtherSessions(string $userId, string $exceptSessionId): void
{
    if (config('session.driver') !== 'database') {
        return;
    }

    DB::table(config('session.table', 'sessions'))
        ->where('user_id', $userId)
        ->where('id', '!=', $exceptSessionId)
        ->delete();
}
```

Do **not** call `Auth::logout()` here.

- [ ] **Step 4: Run tests — expect pass**

```bash
/usr/local/Cellar/php/8.5.8/bin/php artisan test --filter=SessionInvalidatorTest
```

Expected: PASS (all methods in file).

- [ ] **Step 5: Commit**

```bash
git add app/Services/Auth/SessionInvalidator.php tests/Unit/SessionInvalidatorTest.php
git commit -m "$(cat <<'EOF'
feat(auth): invalidate other sessions without logging out current user

Support password-change flow that ends remote sessions while keeping
the active browser session authenticated.
EOF
)"
```

---

### Task 2: Profile routes, requests, controller (TDD feature shell)

**Files:**
- Create: `tests/Feature/AdminProfileTest.php`
- Create: `app/Http/Controllers/Admin/ProfileController.php`
- Create: `app/Http/Requests/Admin/UpdateProfileRequest.php`
- Create: `app/Http/Requests/Admin/UpdatePasswordRequest.php`
- Create: `resources/views/admin/profile/show.blade.php`
- Modify: `routes/web.php`
- Modify: `app/Providers/AppServiceProvider.php` (rate limiter)
- Modify: `resources/views/partials/admin-header.blade.php`

- [ ] **Step 1: Write failing feature tests**

Create `tests/Feature/AdminProfileTest.php`:

```php
<?php

namespace Tests\Feature;

use App\Models\AuditLog;
use App\Models\Lembaga;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class AdminProfileTest extends TestCase
{
    use RefreshDatabase;

    public function test_guest_is_redirected_from_profile(): void
    {
        $this->get(route('admin.profile.show'))->assertRedirect(route('login'));
    }

    public function test_super_admin_can_view_profile(): void
    {
        config(['security.mfa.super_admin_required' => false]);

        $user = User::factory()->create([
            'role' => 'super_admin',
            'lembaga_id' => null,
            'name' => 'Super Tester',
            'email' => 'super@example.test',
        ]);

        $this->actingAs($user)
            ->get(route('admin.profile.show'))
            ->assertOk()
            ->assertSee('Super Tester')
            ->assertSee('super@example.test')
            ->assertSee('Super Admin');
    }

    public function test_admin_lembaga_can_view_profile_with_lembaga_name(): void
    {
        $lembaga = Lembaga::factory()->create(['nama' => 'Sekolah Profil']);
        $user = User::factory()->adminLembaga($lembaga->id)->create([
            'name' => 'Admin Lokal',
            'email' => 'admin@example.test',
        ]);

        $this->actingAs($user)
            ->get(route('admin.profile.show'))
            ->assertOk()
            ->assertSee('Admin Lokal')
            ->assertSee('admin@example.test')
            ->assertSee('Sekolah Profil');
    }

    public function test_header_name_links_to_profile(): void
    {
        config(['security.mfa.super_admin_required' => false]);

        $user = User::factory()->create([
            'role' => 'super_admin',
            'lembaga_id' => null,
            'name' => 'Header Link User',
        ]);

        $this->actingAs($user)
            ->get(route('admin.dashboard'))
            ->assertOk()
            ->assertSee('href="'.route('admin.profile.show').'"', false)
            ->assertSee('Header Link User');
    }

    public function test_update_name_succeeds_and_ignores_email_payload(): void
    {
        config(['security.mfa.super_admin_required' => false]);

        $user = User::factory()->create([
            'role' => 'super_admin',
            'lembaga_id' => null,
            'name' => 'Nama Lama',
            'email' => 'keep@example.test',
        ]);

        $this->actingAs($user)
            ->put(route('admin.profile.update'), [
                'name' => 'Nama Baru',
                'email' => 'hacked@example.test',
            ])
            ->assertRedirect(route('admin.profile.show'))
            ->assertSessionHas('status');

        $user->refresh();
        $this->assertSame('Nama Baru', $user->name);
        $this->assertSame('keep@example.test', $user->email);

        $this->assertDatabaseHas('audit_logs', [
            'event' => 'profile.update',
            'result' => 'success',
        ]);
    }

    public function test_wrong_current_password_is_rejected(): void
    {
        config(['security.mfa.super_admin_required' => false]);

        $user = User::factory()->create([
            'role' => 'super_admin',
            'lembaga_id' => null,
            'password' => 'OldPassword123!',
        ]);

        $this->actingAs($user)
            ->from(route('admin.profile.show'))
            ->put(route('admin.profile.password'), [
                'current_password' => 'WrongPassword999!',
                'password' => 'NewPassword123!',
                'password_confirmation' => 'NewPassword123!',
            ])
            ->assertRedirect(route('admin.profile.show'))
            ->assertSessionHasErrors('current_password');

        $this->assertTrue(Hash::check('OldPassword123!', $user->fresh()->password));
    }

    public function test_password_change_succeeds_and_invalidates_other_sessions(): void
    {
        config([
            'security.mfa.super_admin_required' => false,
            'session.driver' => 'database',
        ]);

        $user = User::factory()->create([
            'role' => 'super_admin',
            'lembaga_id' => null,
            'password' => 'OldPassword123!',
        ]);

        $this->actingAs($user);
        $currentSessionId = session()->getId();

        DB::table('sessions')->insert([
            'id' => 'remote-session',
            'user_id' => $user->id,
            'ip_address' => '10.0.0.2',
            'user_agent' => 'OtherBrowser',
            'payload' => base64_encode('remote'),
            'last_activity' => time(),
        ]);

        // Ensure current session row exists for assertion after regenerate
        if (! DB::table('sessions')->where('id', $currentSessionId)->exists()) {
            DB::table('sessions')->insert([
                'id' => $currentSessionId,
                'user_id' => $user->id,
                'ip_address' => '127.0.0.1',
                'user_agent' => 'PHPUnit',
                'payload' => base64_encode('current'),
                'last_activity' => time(),
            ]);
        }

        $this->put(route('admin.profile.password'), [
            'current_password' => 'OldPassword123!',
            'password' => 'NewPassword123!',
            'password_confirmation' => 'NewPassword123!',
        ])->assertRedirect(route('admin.profile.show'))
            ->assertSessionHas('status');

        $user->refresh();
        $this->assertTrue(Hash::check('NewPassword123!', $user->password));
        $this->assertFalse(Hash::check('OldPassword123!', $user->password));
        $this->assertDatabaseMissing('sessions', ['id' => 'remote-session']);
        $this->assertAuthenticatedAs($user);

        $this->assertDatabaseHas('audit_logs', [
            'event' => 'profile.password_change',
            'result' => 'success',
        ]);

        $audit = AuditLog::query()->where('event', 'profile.password_change')->latest('created_at')->first();
        $this->assertNotNull($audit);
        $encoded = json_encode($audit->metadata ?? []);
        $this->assertStringNotContainsString('NewPassword123!', (string) $encoded);
        $this->assertStringNotContainsString('OldPassword123!', (string) $encoded);
    }
}
```

Adjust factory password hashing if the project’s `User` model already casts `password` => `hashed` (then `'password' => 'OldPassword123!'` is fine as plain assigned to cast).

- [ ] **Step 2: Run tests — expect fail (missing route/controller)**

```bash
/usr/local/Cellar/php/8.5.8/bin/php artisan test --filter=AdminProfileTest
```

Expected: FAIL (route `admin.profile.show` not defined).

- [ ] **Step 3: Add rate limiter**

In `app/Providers/AppServiceProvider.php` inside `boot()`, after `admin-login` limiter:

```php
RateLimiter::for('admin-profile-password', function (Request $request) {
    $userId = (string) ($request->user()?->getAuthIdentifier() ?? 'guest');

    return [
        Limit::perMinute(5)->by('profile-password:'.$userId),
        Limit::perMinute(20)->by('profile-password-ip:'.$request->ip()),
    ];
});
```

- [ ] **Step 4: Add FormRequests**

`app/Http/Requests/Admin/UpdateProfileRequest.php`:

```php
<?php

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;

class UpdateProfileRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:150'],
        ];
    }
}
```

`app/Http/Requests/Admin/UpdatePasswordRequest.php`:

```php
<?php

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rules\Password;
use Illuminate\Validation\Validator;

class UpdatePasswordRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'current_password' => ['required', 'current_password'],
            'password' => ['required', 'confirmed', 'different:current_password', Password::min(12)],
        ];
    }

    public function messages(): array
    {
        return [
            'current_password.current_password' => 'Password saat ini tidak sesuai.',
            'password.different' => 'Password baru harus berbeda dari password saat ini.',
        ];
    }
}
```

- [ ] **Step 5: Add ProfileController**

`app/Http/Controllers/Admin/ProfileController.php`:

```php
<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\UpdatePasswordRequest;
use App\Http\Requests\Admin\UpdateProfileRequest;
use App\Services\AuditLogger;
use App\Services\Auth\SessionInvalidator;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class ProfileController extends Controller
{
    public function __construct(
        private readonly AuditLogger $auditLogger,
        private readonly SessionInvalidator $sessionInvalidator,
    ) {
    }

    public function show(Request $request): View
    {
        return view('admin.profile.show', [
            'user' => $request->user(),
        ]);
    }

    public function update(UpdateProfileRequest $request): RedirectResponse
    {
        $user = $request->user();
        $name = $request->validated('name');

        $user->forceFill(['name' => $name])->save();

        $this->auditLogger->record('profile.update', 'success', [
            'fields' => ['name'],
        ], user: $user, lembagaId: $user->lembaga_id, request: $request);

        return redirect()
            ->route('admin.profile.show')
            ->with('status', 'Nama profil berhasil diperbarui.');
    }

    public function updatePassword(UpdatePasswordRequest $request): RedirectResponse
    {
        $user = $request->user();
        $currentSessionId = $request->session()->getId();

        $user->forceFill([
            'password' => $request->validated('password'),
        ])->save();

        $this->sessionInvalidator->invalidateOtherSessions(
            (string) $user->getAuthIdentifier(),
            $currentSessionId,
        );

        $request->session()->regenerate();

        $this->auditLogger->record('profile.password_change', 'success', [], user: $user, lembagaId: $user->lembaga_id, request: $request);

        return redirect()
            ->route('admin.profile.show')
            ->with('status', 'Password berhasil diganti. Sesi di perangkat lain telah diakhiri.');
    }
}
```

(If `User` model does **not** cast `password` to hashed, wrap with `Hash::make`. Check `app/Models/User.php` casts before implementing.)

- [ ] **Step 6: Register routes**

In `routes/web.php`, inside the `auth/active/mfa` admin group (near dashboard), add:

```php
use App\Http\Controllers\Admin\ProfileController;

Route::get('/profil', [ProfileController::class, 'show'])->name('admin.profile.show');
Route::put('/profil', [ProfileController::class, 'update'])->name('admin.profile.update');
Route::put('/profil/password', [ProfileController::class, 'updatePassword'])
    ->middleware('throttle:admin-profile-password')
    ->name('admin.profile.password');
```

- [ ] **Step 7: Blade view**

Create `resources/views/admin/profile/show.blade.php` following `admin/lembaga/edit.blade.php` patterns:

- `@extends('layouts.admin')`
- Title/breadcrumb: Profil
- Flash `@session('status')` if the layout does not already render it globally — check `admin-content` / existing pages for flash pattern and match it
- Form 1: PUT `admin.profile.update` with `x-ui.input` name; show email/role/lembaga as read-only field blocks
- Form 2: PUT `admin.profile.password` with three `x-ui.input` `type="password"` (`current_password`, `password`, `password_confirmation`); do not echo old password values (`value=""` / omit value)

Role labels:

```php
$roleLabel = match ($user->role) {
    'super_admin' => 'Super Admin',
    'admin_lembaga' => 'Admin Lembaga',
    default => $user->role,
};
```

- [ ] **Step 8: Header link**

In `resources/views/partials/admin-header.blade.php`, replace the plain name span with:

```blade
<a href="{{ route('admin.profile.show') }}" class="admin-header__name">{{ $authUser->name }}</a>
```

Keep existing CSS class so styling stays; if link underline needed, add minimal CSS only if existing header styles break.

- [ ] **Step 9: Run feature tests**

```bash
/usr/local/Cellar/php/8.5.8/bin/php artisan test --filter=AdminProfileTest
```

Expected: PASS. Fix any session-row edge cases carefully (regenerate changes session id — assert authenticated + remote session missing; don’t hard-require old current session id still present after regenerate).

- [ ] **Step 10: Run related shell/auth tests**

```bash
/usr/local/Cellar/php/8.5.8/bin/php artisan test --filter='AdminProfileTest|SessionInvalidatorTest|AdminShellTest|AdminAuthTest'
```

Expected: PASS.

- [ ] **Step 11: Commit**

```bash
git add routes/web.php app/Http/Controllers/Admin/ProfileController.php \
  app/Http/Requests/Admin/UpdateProfileRequest.php \
  app/Http/Requests/Admin/UpdatePasswordRequest.php \
  app/Providers/AppServiceProvider.php \
  resources/views/admin/profile/show.blade.php \
  resources/views/partials/admin-header.blade.php \
  tests/Feature/AdminProfileTest.php
git commit -m "$(cat <<'EOF'
feat(admin): add profile page for name and password change

Let Super Admin and Admin Lembaga update their name and password from
the header, ending other sessions after a successful password change.
EOF
)"
```

---

### Task 3: Docs sync (SPEC + TODO note)

**Files:**
- Modify: `docs/SPEC.md` (§5.0 or §5.1/§5.2)
- Modify: `docs/superpowers/specs/2026-07-27-admin-profile-password-design.md` (status already approved)
- Optionally: `docs/IMPLEMENTATION_TODO.md` under M12 or a short “UX gap” note

- [ ] **Step 1: Update SPEC**

Under §5.0 Layout umum (after header bullet), add:

```markdown
- Header: nama user adalah tautan ke **Profil** (ubah nama + ganti password; email read-only). Ganti password mengakhiri sesi di perangkat lain.
```

Under §5.1 and §5.2, add one line each: “Profil akun sendiri (nama, ganti password).”

- [ ] **Step 2: Commit**

```bash
git add docs/SPEC.md docs/IMPLEMENTATION_TODO.md
git commit -m "$(cat <<'EOF'
docs: document admin profile and password change in SPEC

Record the self-service profile entry in the header and the
password-change session rules for phase-1 admin UI.
EOF
)"
```

---

### Task 4: Full verification

- [ ] **Step 1: Full suite**

```bash
/usr/local/Cellar/php/8.5.8/bin/php artisan test
```

Expected: all green (baseline was 287 tests; expect +N from new tests).

- [ ] **Step 2: Manual smoke (local)**

1. Login as Super Admin (MFA off in local config if needed)
2. Click name in header → Profil
3. Change name → flash OK
4. Change password → flash OK; old password login fails
5. Repeat briefly as Admin Lembaga

- [ ] **Step 3: Push when owner requests**

Do not push unless asked. After merge to main, production deploy:

```bash
cd /www/wwwroot/pusdatin.yasmumanyar.or.id
git pull origin main
/www/server/php/85/bin/php /usr/bin/composer install --no-dev --optimize-autoloader
# npm only if views/js/css changed beyond Blade — Blade-only: skip
/www/server/php/85/bin/php artisan optimize:clear
/www/server/php/85/bin/php artisan config:cache
/www/server/php/85/bin/php artisan route:cache
/www/server/php/85/bin/php artisan view:cache
```

---

## Execution handoff

Plan complete and saved to `docs/superpowers/plans/2026-07-27-admin-profile-password.md`.

**Two execution options:**

1. **Subagent-Driven (recommended)** — fresh subagent per task + two-stage review  
2. **Inline Execution** — execute tasks in this session with checkpoints  

Which approach?
