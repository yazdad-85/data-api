# Milestone 5a Lembaga + Admin Lembaga Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Ship Super Admin Blade CRUD for Lembaga and nested Admin Lembaga (activate/deactivate with impact counts, copy-once generated passwords, session invalidation, audit without secrets) — without API client/key (M5b).

**Architecture:** Controllers + Form Requests under `admin` middleware (`auth`, `active`, `mfa`); `LembagaPolicy` / `UserPolicy` Super Admin only; detail hub on `show` holds admin forms; reuse `SessionInvalidator`, `AuditLogger`, `x-ui.*`. Follow RULES B1: write code → review → test (not classic TDD).

**Tech Stack:** Laravel 13, Blade, Form Request, PHPUnit, PHP 8.5.

**Spec:** `docs/superpowers/specs/2026-07-20-milestone-5a-lembaga-admin-design.md`

**PHP CLI:** `/usr/local/Cellar/php/8.5.8/bin/php` when default `php` is 8.1.

---

## File structure

| Path | Responsibility |
|------|----------------|
| `app/Policies/LembagaPolicy.php` | SA-only lembaga actions |
| `app/Policies/UserPolicy.php` | SA-only admin_lembaga user actions |
| `app/Services/Auth/AdminPasswordGenerator.php` | ≥12 char random password |
| `app/Http/Requests/Admin/StoreLembagaRequest.php` | Validate create lembaga |
| `app/Http/Requests/Admin/UpdateLembagaRequest.php` | Validate update lembaga |
| `app/Http/Requests/Admin/StoreLembagaAdminRequest.php` | Validate create admin |
| `app/Http/Requests/Admin/UpdateLembagaAdminRequest.php` | Validate update admin |
| `app/Http/Controllers/Admin/LembagaController.php` | Lembaga CRUD + activate/deactivate |
| `app/Http/Controllers/Admin/LembagaAdminController.php` | Nested admin actions + password-once |
| `app/Support/Navigation/AdminMenu.php` | Lembaga available; remove “Admin lembaga” |
| `app/Providers/AppServiceProvider.php` | Register Lembaga + User policies |
| `routes/web.php` | M5a routes; trim coming-soon features |
| `resources/views/admin/lembaga/*.blade.php` | index, create, edit, show, password-once |
| `database/factories/ApiClientFactory.php` | Test counts on deactivate modal |
| `tests/Unit/AdminPasswordGeneratorTest.php` | Generator length/charset |
| `tests/Feature/LembagaAdminCrudTest.php` | Full M5a feature coverage |
| `tests/Feature/AdminShellTest.php` | Menu assertions after nav change |
| `resources/views/admin/dashboard.blade.php` | Empty-state copy (no “Admin lembaga” menu promise) |
| `docs/IMPLEMENTATION_TODO.md` | Check lembaga/admin items; leave API key open |
| `docs/superpowers/specs/2026-07-20-milestone-5a-lembaga-admin-design.md` | Status → Approved |

---

### Task 1: Policies, menu, ApiClient factory

**Files:**
- Create: `app/Policies/LembagaPolicy.php`
- Create: `app/Policies/UserPolicy.php`
- Create: `database/factories/ApiClientFactory.php`
- Modify: `app/Providers/AppServiceProvider.php`
- Modify: `app/Support/Navigation/AdminMenu.php`
- Modify: `app/Models/ApiClient.php` (HasFactory if missing)
- Modify: `resources/views/admin/dashboard.blade.php` (empty-state text)
- Modify: `tests/Feature/AdminShellTest.php`

- [ ] **Step 1: Create `LembagaPolicy`**

```php
<?php

namespace App\Policies;

use App\Models\Lembaga;
use App\Models\User;

class LembagaPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->isSuperAdmin();
    }

    public function view(User $user, Lembaga $lembaga): bool
    {
        return $user->isSuperAdmin();
    }

    public function create(User $user): bool
    {
        return $user->isSuperAdmin();
    }

    public function update(User $user, Lembaga $lembaga): bool
    {
        return $user->isSuperAdmin();
    }

    public function activate(User $user, Lembaga $lembaga): bool
    {
        return $user->isSuperAdmin();
    }

    public function deactivate(User $user, Lembaga $lembaga): bool
    {
        return $user->isSuperAdmin();
    }
}
```

- [ ] **Step 2: Create `UserPolicy` (admin lembaga subjects only)**

```php
<?php

namespace App\Policies;

use App\Models\User;

class UserPolicy
{
    public function createAdminLembaga(User $actor): bool
    {
        return $actor->isSuperAdmin();
    }

    public function update(User $actor, User $subject): bool
    {
        return $actor->isSuperAdmin() && $subject->isAdminLembaga();
    }

    public function activate(User $actor, User $subject): bool
    {
        return $actor->isSuperAdmin() && $subject->isAdminLembaga();
    }

    public function deactivate(User $actor, User $subject): bool
    {
        return $actor->isSuperAdmin() && $subject->isAdminLembaga();
    }

    public function resetPassword(User $actor, User $subject): bool
    {
        return $actor->isSuperAdmin() && $subject->isAdminLembaga();
    }
}
```

- [ ] **Step 3: Register policies in `AppServiceProvider::boot`**

Add imports for `Lembaga`, `LembagaPolicy`, `UserPolicy`. After existing `Gate::policy(...)` lines:

```php
Gate::policy(Lembaga::class, LembagaPolicy::class);
Gate::policy(User::class, UserPolicy::class);
```

- [ ] **Step 4: Update `AdminMenu` Super Admin entries**

Replace the SA menu block with:

```php
return collect([
    ['label' => 'Dashboard', 'route' => 'admin.dashboard', 'available' => true],
    ['label' => 'Lembaga', 'route' => 'admin.lembaga.index', 'available' => true],
    ['label' => 'API client', 'route' => 'admin.coming-soon', 'params' => ['feature' => 'api-client'], 'available' => false],
]);
```

(Remove the separate “Admin lembaga” item entirely.)

- [ ] **Step 5: ApiClient factory + HasFactory**

On `ApiClient`, add `HasFactory` and factory class:

```php
<?php

namespace Database\Factories;

use App\Models\ApiClient;
use App\Models\Lembaga;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/** @extends Factory<ApiClient> */
class ApiClientFactory extends Factory
{
    protected $model = ApiClient::class;

    public function definition(): array
    {
        return [
            'lembaga_id' => Lembaga::factory(),
            'nama' => fake()->company().' Client',
            'api_key_prefix' => Str::lower(Str::random(12)),
            'api_key_digest' => hash('sha256', 'test-key'),
            'scopes' => ['guru:read'],
            'field_profile' => 'minimal',
            'is_active' => true,
            'revoked_at' => null,
        ];
    }

    public function inactive(): static
    {
        return $this->state(fn (array $attributes) => [
            'is_active' => false,
        ]);
    }

    public function revoked(): static
    {
        return $this->state(fn (array $attributes) => [
            'is_active' => false,
            'revoked_at' => now(),
        ]);
    }
}
```

- [ ] **Step 6: Dashboard empty-state copy**

Change description to something like:  
`Dashboard siap. Kelola lembaga dari menu Lembaga. API client menyusul di Milestone 5b.`

- [ ] **Step 7: Update `AdminShellTest`**

For Super Admin dashboard:
- `assertSee(route('admin.lembaga.index'), false)` or assert link text Lembaga still present
- `assertDontSee('Admin lembaga')`

Keep Admin Lembaga dashboard `assertDontSee('Admin lembaga')` as-is.

Note: routes for lembaga do not exist yet — **do not** run full shell test asserting the new route until Task 3 registers routes. Either (a) temporarily leave menu pointing to `admin.lembaga.index` and run shell tests after Task 3, or (b) in this task only remove “Admin lembaga” and keep Lembaga as coming-soon until Task 3. **Prefer (b) for green CI between tasks:**

Task 1 menu interim:

```php
['label' => 'Lembaga', 'route' => 'admin.coming-soon', 'params' => ['feature' => 'lembaga'], 'available' => false],
```

Task 3 flips to `admin.lembaga.index` + `available => true` and drops `lembaga|admin-lembaga` from coming-soon `where`.

- [ ] **Step 8: Commit**

```bash
git add app/Policies/LembagaPolicy.php app/Policies/UserPolicy.php app/Providers/AppServiceProvider.php \
  app/Support/Navigation/AdminMenu.php app/Models/ApiClient.php database/factories/ApiClientFactory.php \
  resources/views/admin/dashboard.blade.php tests/Feature/AdminShellTest.php
git commit -m "$(cat <<'EOF'
feat(m5a): add lembaga/user policies and ApiClient factory

Prepare Super Admin authorization and test fixtures before CRUD UI.
EOF
)"
```

---

### Task 2: AdminPasswordGenerator

**Files:**
- Create: `app/Services/Auth/AdminPasswordGenerator.php`
- Create: `tests/Unit/AdminPasswordGeneratorTest.php`

- [ ] **Step 1: Implement generator**

```php
<?php

namespace App\Services\Auth;

use Illuminate\Support\Str;

final class AdminPasswordGenerator
{
    private const LENGTH = 16;

    /** URL/copy-safe alphabet (no ambiguous O/0/I/l). */
    private const ALPHABET = 'ABCDEFGHJKLMNPQRSTUVWXYZabcdefghijkmnopqrstuvwxyz23456789';

    public function generate(): string
    {
        $password = '';
        $max = strlen(self::ALPHABET) - 1;

        for ($i = 0; $i < self::LENGTH; $i++) {
            $password .= self::ALPHABET[random_int(0, $max)];
        }

        return $password;
    }
}
```

Length 16 ≥ RULES B4.2 min 12. Alphabet guarantees letters+digits.

- [ ] **Step 2: Unit test**

```php
<?php

namespace Tests\Unit;

use App\Services\Auth\AdminPasswordGenerator;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class AdminPasswordGeneratorTest extends TestCase
{
    #[Test]
    public function it_generates_password_at_least_12_chars_with_letter_and_digit(): void
    {
        $generator = new AdminPasswordGenerator;

        for ($i = 0; $i < 20; $i++) {
            $password = $generator->generate();
            $this->assertGreaterThanOrEqual(12, strlen($password));
            $this->assertMatchesRegularExpression('/[A-Za-z]/', $password);
            $this->assertMatchesRegularExpression('/[0-9]/', $password);
        }
    }
}
```

- [ ] **Step 3: Run unit test**

```bash
/usr/local/Cellar/php/8.5.8/bin/php artisan test --filter=AdminPasswordGeneratorTest
```

Expected: PASS

- [ ] **Step 4: Commit**

```bash
git add app/Services/Auth/AdminPasswordGenerator.php tests/Unit/AdminPasswordGeneratorTest.php
git commit -m "feat(m5a): add admin password generator for copy-once credentials"
```

---

### Task 3: Lembaga CRUD (list/create/edit/show) + routes + menu flip

**Files:**
- Create: Form Requests (4 files under `app/Http/Requests/Admin/`)
- Create: `app/Http/Controllers/Admin/LembagaController.php`
- Create: views `resources/views/admin/lembaga/{index,create,edit,show}.blade.php`
- Modify: `routes/web.php`
- Modify: `AdminMenu` → real index route
- Modify: coming-soon `where` — remove `lembaga|admin-lembaga`

- [ ] **Step 1: Form requests**

`StoreLembagaRequest` / `UpdateLembagaRequest` authorize via `$this->user()?->can('create', Lembaga::class)` / `update` on route lembaga.

Rules (align migration):

```php
'kode' => ['required', 'string', 'max:30', Rule::unique('lembaga', 'kode')->ignore($this->route('lembaga'))],
'nama' => ['required', 'string', 'max:150'],
'jenis' => ['nullable', 'string', 'max:50'],
'alamat' => ['nullable', 'string'],
'kota' => ['nullable', 'string', 'max:100'],
'provinsi' => ['nullable', 'string', 'max:100'],
'telepon' => ['nullable', 'string', 'max:30'],
'email' => ['nullable', 'email', 'max:150'],
```

Store only (no `is_active` from client on create — default true in DB). Update same fields; status only via activate/deactivate actions.

`StoreLembagaAdminRequest` / `UpdateLembagaAdminRequest`:

```php
'name' => ['required', 'string', 'max:150'],
'email' => ['required', 'email', 'max:150', Rule::unique('users', 'email')->ignore($this->route('user'))],
```

Authorize: `createAdminLembaga` / `update` on subject user. Do **not** accept `lembaga_id` or `role` from input.

- [ ] **Step 2: `LembagaController`**

```php
<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\StoreLembagaRequest;
use App\Http\Requests\Admin\UpdateLembagaRequest;
use App\Models\Lembaga;
use App\Models\User;
use App\Services\AuditLogger;
use App\Services\Auth\SessionInvalidator;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class LembagaController extends Controller
{
    public function __construct(
        private readonly AuditLogger $auditLogger,
        private readonly SessionInvalidator $sessionInvalidator,
    ) {
    }

    public function index(Request $request): View
    {
        $this->authorize('viewAny', Lembaga::class);

        $q = trim((string) $request->query('q', ''));

        $lembagas = Lembaga::query()
            ->when($q !== '', function ($query) use ($q) {
                $query->where(function ($inner) use ($q) {
                    $inner->where('kode', 'ilike', '%'.$q.'%')
                        ->orWhere('nama', 'ilike', '%'.$q.'%');
                });
            })
            ->orderBy('nama')
            ->paginate(15)
            ->withQueryString();

        return view('admin.lembaga.index', compact('lembagas', 'q'));
    }

    public function create(): View
    {
        $this->authorize('create', Lembaga::class);

        return view('admin.lembaga.create');
    }

    public function store(StoreLembagaRequest $request): RedirectResponse
    {
        $lembaga = Lembaga::query()->create($request->validated());

        $this->auditLogger->record('lembaga.create', 'success', [
            'kode' => $lembaga->kode,
        ], subject: $lembaga, lembagaId: $lembaga->id, request: $request);

        return redirect()
            ->route('admin.lembaga.show', $lembaga)
            ->with('status', 'Lembaga berhasil dibuat.');
    }

    public function show(Lembaga $lembaga): View
    {
        $this->authorize('view', $lembaga);

        $admins = $lembaga->users()
            ->where('role', 'admin_lembaga')
            ->orderBy('name')
            ->get();

        $adminsAktif = $admins->where('is_active', true)->count();
        $apiClientsAktif = $lembaga->apiClients()
            ->where('is_active', true)
            ->whereNull('revoked_at')
            ->count();

        return view('admin.lembaga.show', compact(
            'lembaga',
            'admins',
            'adminsAktif',
            'apiClientsAktif',
        ));
    }

    public function edit(Lembaga $lembaga): View
    {
        $this->authorize('update', $lembaga);

        return view('admin.lembaga.edit', compact('lembaga'));
    }

    public function update(UpdateLembagaRequest $request, Lembaga $lembaga): RedirectResponse
    {
        $lembaga->update($request->validated());

        $this->auditLogger->record('lembaga.update', 'success', [
            'kode' => $lembaga->kode,
        ], subject: $lembaga, lembagaId: $lembaga->id, request: $request);

        return redirect()
            ->route('admin.lembaga.show', $lembaga)
            ->with('status', 'Lembaga berhasil diperbarui.');
    }

    public function activate(Request $request, Lembaga $lembaga): RedirectResponse
    {
        $this->authorize('activate', $lembaga);

        $lembaga->update(['is_active' => true]);

        $this->auditLogger->record('lembaga.activate', 'success', [], subject: $lembaga, lembagaId: $lembaga->id, request: $request);

        return back()->with('status', 'Lembaga diaktifkan.');
    }

    public function deactivate(Request $request, Lembaga $lembaga): RedirectResponse
    {
        $this->authorize('deactivate', $lembaga);

        $adminsAktif = User::query()
            ->where('role', 'admin_lembaga')
            ->where('lembaga_id', $lembaga->id)
            ->where('is_active', true)
            ->get(['id']);

        $apiClientsAktif = $lembaga->apiClients()
            ->where('is_active', true)
            ->whereNull('revoked_at')
            ->count();

        $lembaga->update(['is_active' => false]);

        foreach ($adminsAktif as $admin) {
            $this->sessionInvalidator->invalidateUser((string) $admin->id);
        }

        $this->auditLogger->record('lembaga.deactivate', 'success', [
            'admins_aktif' => $adminsAktif->count(),
            'api_clients_aktif' => $apiClientsAktif,
        ], subject: $lembaga, lembagaId: $lembaga->id, request: $request);

        return back()->with('status', 'Lembaga dinonaktifkan.');
    }
}
```

Note: `ilike` is PostgreSQL-specific (project DB). Do not use `LIKE` with SQLite assumptions.

- [ ] **Step 3: Routes inside admin middleware group**

```php
use App\Http\Controllers\Admin\LembagaController;
use App\Http\Controllers\Admin\LembagaAdminController;

// coming-soon where: drop lembaga|admin-lembaga
->where('feature', 'api-client|tahun-ajaran|guru|kelas|siswa|karyawan|api-client-ro')

Route::get('/lembaga', [LembagaController::class, 'index'])->name('admin.lembaga.index');
Route::get('/lembaga/create', [LembagaController::class, 'create'])->name('admin.lembaga.create');
Route::post('/lembaga', [LembagaController::class, 'store'])->name('admin.lembaga.store');
Route::get('/lembaga/{lembaga}', [LembagaController::class, 'show'])->name('admin.lembaga.show');
Route::get('/lembaga/{lembaga}/edit', [LembagaController::class, 'edit'])->name('admin.lembaga.edit');
Route::put('/lembaga/{lembaga}', [LembagaController::class, 'update'])->name('admin.lembaga.update');
Route::post('/lembaga/{lembaga}/activate', [LembagaController::class, 'activate'])->name('admin.lembaga.activate');
Route::post('/lembaga/{lembaga}/deactivate', [LembagaController::class, 'deactivate'])->name('admin.lembaga.deactivate');

// Admin nested — implemented in Task 4; can stub routes now or add with Task 4
```

- [ ] **Step 4: Blade views**

**index:** search form `q`, `x-ui.table` of kode/nama/jenis/badge status, links Detail/Edit, `x-ui.button` “Tambah lembaga”, `x-ui.empty-state` when empty, `x-ui.pagination`.

**create / edit:** form fields matching Form Request; CSRF; `x-ui.input` / `x-ui.select` for jenis (free text input OK — SPEC allows free string).

**show:** lembaga detail; status badge; Edit link; Activate form **or** Deactivate button opening `x-ui.modal` id `deactivate-lembaga` with Indonesian impact text + `{{ $adminsAktif }}` / `{{ $apiClientsAktif }}`; POST deactivate inside modal. Section **Admin lembaga**: table name/email/status + actions (wire in Task 4); empty-state; create form (name/email) POSTing to `admin.lembaga.admins.store` (Task 4).

Breadcrumb examples: `Dashboard / Lembaga`, `Dashboard / Lembaga / {nama}`.

- [ ] **Step 5: Flip AdminMenu Lembaga to available index**

- [ ] **Step 6: Smoke — Super Admin can open index**

```bash
/usr/local/Cellar/php/8.5.8/bin/php artisan test --filter=AdminShellTest
```

Expected: PASS (after menu + assertDontSee Admin lembaga).

- [ ] **Step 7: Commit**

```bash
git commit -m "feat(m5a): add lembaga Blade CRUD and activate/deactivate"
```

---

### Task 4: Nested Admin Lembaga + copy-once password

**Files:**
- Create: `app/Http/Controllers/Admin/LembagaAdminController.php`
- Create: `resources/views/admin/lembaga/password-once.blade.php`
- Complete show.blade.php admin forms/actions
- Add nested routes

- [ ] **Step 1: Controller**

```php
<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\StoreLembagaAdminRequest;
use App\Http\Requests\Admin\UpdateLembagaAdminRequest;
use App\Models\Lembaga;
use App\Models\User;
use App\Services\AuditLogger;
use App\Services\Auth\AdminPasswordGenerator;
use App\Services\Auth\SessionInvalidator;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class LembagaAdminController extends Controller
{
    public function __construct(
        private readonly AdminPasswordGenerator $passwordGenerator,
        private readonly SessionInvalidator $sessionInvalidator,
        private readonly AuditLogger $auditLogger,
    ) {
    }

    public function store(StoreLembagaAdminRequest $request, Lembaga $lembaga): RedirectResponse
    {
        $this->authorize('createAdminLembaga', User::class);

        $plain = $this->passwordGenerator->generate();

        $admin = User::query()->create([
            ...$request->validated(),
            'role' => 'admin_lembaga',
            'lembaga_id' => $lembaga->id,
            'is_active' => true,
            'password' => $plain,
        ]);

        $this->auditLogger->record('admin.create', 'success', [
            'email' => $admin->email,
        ], subject: $admin, lembagaId: $lembaga->id, request: $request);

        return redirect()
            ->route('admin.lembaga.admins.password-once', [$lembaga, $admin])
            ->with('generated_password', $plain);
    }

    public function update(UpdateLembagaAdminRequest $request, Lembaga $lembaga, User $user): RedirectResponse
    {
        $this->assertAdminBelongsToLembaga($lembaga, $user);
        $this->authorize('update', $user);

        $user->update($request->validated());

        $this->auditLogger->record('admin.update', 'success', [
            'email' => $user->email,
        ], subject: $user, lembagaId: $lembaga->id, request: $request);

        return redirect()
            ->route('admin.lembaga.show', $lembaga)
            ->with('status', 'Admin lembaga diperbarui.');
    }

    public function activate(Request $request, Lembaga $lembaga, User $user): RedirectResponse
    {
        $this->assertAdminBelongsToLembaga($lembaga, $user);
        $this->authorize('activate', $user);

        $user->update(['is_active' => true]);

        $this->auditLogger->record('admin.activate', 'success', [], subject: $user, lembagaId: $lembaga->id, request: $request);

        return back()->with('status', 'Admin lembaga diaktifkan.');
    }

    public function deactivate(Request $request, Lembaga $lembaga, User $user): RedirectResponse
    {
        $this->assertAdminBelongsToLembaga($lembaga, $user);
        $this->authorize('deactivate', $user);

        $user->update(['is_active' => false]);
        $this->sessionInvalidator->invalidateUser((string) $user->id);

        $this->auditLogger->record('admin.deactivate', 'success', [], subject: $user, lembagaId: $lembaga->id, request: $request);

        return back()->with('status', 'Admin lembaga dinonaktifkan.');
    }

    public function resetPassword(Request $request, Lembaga $lembaga, User $user): RedirectResponse
    {
        $this->assertAdminBelongsToLembaga($lembaga, $user);
        $this->authorize('resetPassword', $user);

        $plain = $this->passwordGenerator->generate();
        $user->update(['password' => $plain]);
        $this->sessionInvalidator->invalidateUser((string) $user->id);

        $this->auditLogger->record('admin.reset_password', 'success', [], subject: $user, lembagaId: $lembaga->id, request: $request);

        return redirect()
            ->route('admin.lembaga.admins.password-once', [$lembaga, $user])
            ->with('generated_password', $plain);
    }

    public function passwordOnce(Lembaga $lembaga, User $user): View|RedirectResponse
    {
        $this->assertAdminBelongsToLembaga($lembaga, $user);
        $this->authorize('view', $lembaga);

        $plain = session('generated_password');
        if (! is_string($plain) || $plain === '') {
            return redirect()
                ->route('admin.lembaga.show', $lembaga)
                ->with('status', 'Password satu kali sudah tidak tersedia. Reset ulang jika perlu.');
        }

        // Pull so refresh cannot re-read (flash already one-shot; pull is extra safety)
        session()->forget('generated_password');

        return view('admin.lembaga.password-once', [
            'lembaga' => $lembaga,
            'admin' => $user,
            'plainPassword' => $plain,
        ]);
    }

    private function assertAdminBelongsToLembaga(Lembaga $lembaga, User $user): void
    {
        abort_unless(
            $user->isAdminLembaga()
                && hash_equals((string) $user->lembaga_id, (string) $lembaga->id),
            404
        );
    }
}
```

**Flash caveat:** `redirect()->with('generated_password')` then `passwordOnce` that `forget`s works for first GET. Do **not** call `forget` before the view is rendered if that drops the value mid-request — reading into `$plain` first then forget is correct as above.

Alternative safer pattern: use `session()->pull('generated_password')` once at the start of `passwordOnce`.

- [ ] **Step 2: Routes**

```php
Route::post('/lembaga/{lembaga}/admins', [LembagaAdminController::class, 'store'])
    ->name('admin.lembaga.admins.store');
Route::put('/lembaga/{lembaga}/admins/{user}', [LembagaAdminController::class, 'update'])
    ->name('admin.lembaga.admins.update');
Route::post('/lembaga/{lembaga}/admins/{user}/activate', [LembagaAdminController::class, 'activate'])
    ->name('admin.lembaga.admins.activate');
Route::post('/lembaga/{lembaga}/admins/{user}/deactivate', [LembagaAdminController::class, 'deactivate'])
    ->name('admin.lembaga.admins.deactivate');
Route::post('/lembaga/{lembaga}/admins/{user}/reset-password', [LembagaAdminController::class, 'resetPassword'])
    ->name('admin.lembaga.admins.reset-password');
Route::get('/lembaga/{lembaga}/admins/{user}/password-once', [LembagaAdminController::class, 'passwordOnce'])
    ->name('admin.lembaga.admins.password-once');
```

- [ ] **Step 3: `password-once.blade.php`**

Show email, plain password in readonly `x-ui.input`, warning in Indonesian: simpan sekarang, tidak bisa dilihat lagi. Button “Salin” via small JS `navigator.clipboard.writeText`. Link kembali ke show.

- [ ] **Step 4: Wire show admin section**

Forms: create; per-row edit (inline or small modal); activate/deactivate; reset-password POST.

- [ ] **Step 5: Commit**

```bash
git commit -m "feat(m5a): nested admin lembaga CRUD with copy-once passwords"
```

---

### Task 5: Feature tests (RULES B1 after code review)

**Files:**
- Create: `tests/Feature/LembagaAdminCrudTest.php`
- Adjust: `AdminShellTest` if needed

- [ ] **Step 1: Write `LembagaAdminCrudTest`** covering spec §7:

Helper:

```php
private function superAdmin(): User
{
    config(['security.mfa.super_admin_required' => false]);

    return User::factory()->create(['role' => 'super_admin', 'lembaga_id' => null]);
}
```

Cases (each as its own test method):

1. SA creates lembaga → appears on index/show; audit `lembaga.create`.
2. SA updates lembaga fields.
3. Deactivate: create 2 active admins + 1 active api client + 1 revoked client; POST deactivate; assert `is_active` false; show/modal counts path via GET show sees counts before deactivate; audit metadata includes counts; admin login fails with generic message (`AdminAuthenticator::FAILURE_MESSAGE`).
4. Activate lembaga: admin with `is_active=true` can login again.
5. Create admin: follow redirect to password-once; see plain in HTML once; `Hash::check` against DB; audit `admin.create` metadata must not contain the plain password string; second GET password-once redirects without password.
6. Deactivate admin: `is_active` false; with database sessions, assert session row deleted (mirror `SessionInvalidatorTest` pattern) OR assert subsequent authenticated request as that user is logged out after middleware — prefer login attempt fails.
7. Reset password: new plain shown; old password fails login; new works.
8. `actingAs` admin lembaga → `get(route('admin.lembaga.index'))` → 403.
9. Admin of lembaga A cannot be updated via lembaga B URL → 404.

Also: search filter `q` matches kode.

- [ ] **Step 2: Run tests**

```bash
/usr/local/Cellar/php/8.5.8/bin/php artisan test --filter=LembagaAdminCrudTest
/usr/local/Cellar/php/8.5.8/bin/php artisan test --filter=AdminShellTest
/usr/local/Cellar/php/8.5.8/bin/php artisan test --filter=AdminPasswordGeneratorTest
```

Expected: all PASS. Then full suite:

```bash
/usr/local/Cellar/php/8.5.8/bin/php artisan test
```

- [ ] **Step 3: Commit**

```bash
git commit -m "test(m5a): cover lembaga and admin lembaga CRUD security paths"
```

---

### Task 6: Docs + design status

**Files:**
- `docs/IMPLEMENTATION_TODO.md`
- `docs/superpowers/specs/2026-07-20-milestone-5a-lembaga-admin-design.md`

- [ ] **Step 1: Mark Milestone 5 checklist**

Check only lembaga/admin items:

- [x] CRUD Lembaga  
- [x] Aktif/nonaktif Lembaga (modal dampak + counts)  
- [x] CRUD Admin Lembaga  
- [x] Aktif/nonaktif Admin Lembaga  
- [x] Generate password Admin Lembaga copy-once (+ reset)  
- [x] Audit log untuk aksi kritis terkait lembaga/admin  

Leave API client/key items unchecked. Update status note: **M5a selesai; M5b API client belum**.

Add review/test bullets for lembaga/admin as done if listed separately.

- [ ] **Step 2: Spec status → `Approved`**

- [ ] **Step 3: Commit**

```bash
git commit -m "docs: mark Milestone 5a lembaga/admin complete; M5b still open"
```

---

## Spec coverage checklist (self-review)

| Spec requirement | Task |
|------------------|------|
| Blade + Form Request, SA only | 1, 3, 4 |
| Detail hub + admins on show | 3, 4 |
| Remove sidebar “Admin lembaga” | 1, 3 |
| Deactivate modal + admin/API counts | 3 |
| No delete UI | 3 (no routes) |
| Password generate + copy-once | 2, 4 |
| Session invalidate on lembaga/admin deactivate + reset | 3, 4 |
| Audit without secrets | 3, 4, 5 |
| IDOR 404 cross-lembaga | 4, 5 |
| Admin Lembaga 403 on routes | 5 |
| No API key CRUD | all (out of scope) |
| IMPLEMENTATION_TODO partial | 6 |

## Out of scope (do not implement)

API client create/rotate/revoke, soft-delete lembaga UI, MFA for Admin Lembaga, Livewire hybrid, master CRUD M6.
