# Milestone 4 UI Shell Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Ship a consistent teal-academic admin shell (sidebar, header, breadcrumb, footer, Blade UI components) plus role-specific dashboards and coming-soon placeholders for Pusat Data Milestone 4.

**Architecture:** Blade app-rail layout with shared CSS design tokens; `AdminMenu` drives sidebar by role; `DashboardStats` aggregates counts; guest/auth layouts restyled to the same system. Follow RULES B1: write code → review → test (not classic TDD). UI never replaces M3 middleware/policy/scope.

**Tech Stack:** Laravel 13, Blade, Vite 8, Tailwind CSS 4, PHP 8.5, PHPUnit. Fonts: `@fontsource-variable/source-serif-4` + `@fontsource-variable/source-sans-3` (or CDN link if npm install blocked — prefer npm).

**Spec:** `docs/superpowers/specs/2026-07-20-milestone-4-ui-shell-design.md`

**PHP CLI:** `/usr/local/Cellar/php/8.5.8/bin/php` when default `php` is 8.1.

---

## File structure

| Path | Responsibility |
|------|----------------|
| `resources/css/app.css` | Design tokens, shell, component base styles |
| `resources/js/app.js` | Import CSS + fonts; minimal drawer toggle |
| `resources/views/layouts/admin.blade.php` | Admin shell |
| `resources/views/layouts/guest.blade.php` | Auth shell (restyle) |
| `resources/views/partials/footer.blade.php` | Shared footer |
| `resources/views/partials/admin-sidebar.blade.php` | Sidebar markup |
| `resources/views/partials/admin-header.blade.php` | Header markup |
| `resources/views/components/ui/*.blade.php` | button, input, select, badge, modal, table, pagination, empty-state, skeleton |
| `resources/views/admin/dashboard.blade.php` | Role dashboards |
| `resources/views/admin/coming-soon.blade.php` | Placeholder pages |
| `app/Support/Navigation/AdminMenu.php` | Menu definitions per role |
| `app/Support/Ui/AppEnvironmentLabel.php` | Map APP_ENV → Lokal/Staging/Produksi |
| `app/Services/Dashboard/DashboardStats.php` | Aggregates for SA / Admin Lembaga |
| `app/Http/Controllers/Admin/DashboardController.php` | Pass stats + menu context |
| `app/Http/Controllers/Admin/ComingSoonController.php` | Placeholder |
| `app/Providers/AppServiceProvider.php` | Optional View::composer for footer/menu |
| `routes/web.php` | Admin placeholder routes |
| `tests/Feature/AdminShellTest.php` | Smoke, nav, footer |
| `docs/IMPLEMENTATION_TODO.md` | Check off M4 when green |

---

### Task 1: Design tokens, fonts, footer helper

**Files:**
- Modify: `package.json` / `resources/css/app.css` / `resources/js/app.js`
- Create: `app/Support/Ui/AppEnvironmentLabel.php`
- Create: `resources/views/partials/footer.blade.php`
- Modify: `resources/views/layouts/guest.blade.php` (use Vite + footer; keep login working)

- [ ] **Step 1: Install fonts**

```bash
npm install @fontsource-variable/source-serif-4 @fontsource-variable/source-sans-3
```

- [ ] **Step 2: Update `resources/js/app.js`**

```js
import '@fontsource-variable/source-serif-4';
import '@fontsource-variable/source-sans-3';
import '../css/app.css';

document.addEventListener('click', (event) => {
    const toggle = event.target.closest('[data-sidebar-toggle]');
    if (toggle) {
        document.documentElement.classList.toggle('sidebar-open');
        return;
    }
    const backdrop = event.target.closest('[data-sidebar-backdrop]');
    if (backdrop) {
        document.documentElement.classList.remove('sidebar-open');
    }
});
```

- [ ] **Step 3: Replace `resources/css/app.css` theme**

```css
@import 'tailwindcss';
@import '@fontsource-variable/source-serif-4';
@import '@fontsource-variable/source-sans-3';

@source '../../vendor/laravel/framework/src/Illuminate/Pagination/resources/views/*.blade.php';
@source '../../storage/framework/views/*.php';
@source '../views/**/*.blade.php';

@theme {
    --font-sans: 'Source Sans 3 Variable', 'Source Sans 3', ui-sans-serif, sans-serif;
    --font-display: 'Source Serif 4 Variable', 'Source Serif 4', ui-serif, Georgia, serif;
    --color-brand: #0f6b6b;
    --color-brand-deep: #0a4f4f;
    --color-brand-soft: #d8eded;
    --color-surface: #f4f7f6;
    --color-surface-elevated: #ffffff;
    --color-ink: #12221f;
    --color-muted: #5c6f6b;
    --color-border: #c9d6d3;
    --color-ok: #1b7f5a;
    --color-warn: #b45309;
    --color-danger: #b91c1c;
}

:root {
    --brand: var(--color-brand);
    --brand-deep: var(--color-brand-deep);
    --brand-soft: var(--color-brand-soft);
    --surface: var(--color-surface);
    --surface-elevated: var(--color-surface-elevated);
    --ink: var(--color-ink);
    --muted: var(--color-muted);
    --border: var(--color-border);
    --ok: var(--color-ok);
    --warn: var(--color-warn);
    --danger: var(--color-danger);
}

body {
    margin: 0;
    min-height: 100vh;
    font-family: var(--font-sans);
    color: var(--ink);
    background: var(--surface);
}

.font-display {
    font-family: var(--font-display);
}

/* Shell + drawer helpers used by layouts */
.admin-shell {
    min-height: 100vh;
    display: grid;
    grid-template-columns: 16.5rem 1fr;
}

@media (max-width: 960px) {
    .admin-shell {
        grid-template-columns: 1fr;
    }
    .admin-sidebar {
        position: fixed;
        inset: 0 auto 0 0;
        width: min(18rem, 86vw);
        transform: translateX(-105%);
        transition: transform 180ms ease;
        z-index: 40;
    }
    html.sidebar-open .admin-sidebar {
        transform: translateX(0);
    }
    .admin-sidebar-backdrop {
        display: none;
        position: fixed;
        inset: 0;
        background: rgb(18 34 31 / 0.35);
        z-index: 30;
    }
    html.sidebar-open .admin-sidebar-backdrop {
        display: block;
    }
}

.admin-main {
    display: flex;
    flex-direction: column;
    min-width: 0;
}

.admin-content {
    flex: 1;
    padding: 1.25rem 1.5rem 2rem;
    animation: content-in 220ms ease;
}

@keyframes content-in {
    from { opacity: 0; transform: translateY(4px); }
    to { opacity: 1; transform: none; }
}

.app-footer {
    border-top: 1px solid var(--border);
    padding: 0.75rem 1.5rem;
    display: flex;
    flex-wrap: wrap;
    gap: 0.75rem 1.25rem;
    align-items: center;
    justify-content: space-between;
    color: var(--muted);
    font-size: 0.875rem;
    background: var(--surface-elevated);
}

.env-badge {
    display: inline-flex;
    align-items: center;
    padding: 0.15rem 0.55rem;
    border: 1px solid var(--border);
    border-radius: 0.35rem;
    font-size: 0.75rem;
    letter-spacing: 0.02em;
    text-transform: uppercase;
}
```

Add further component utility classes in later tasks as needed (`btn`, `field`, etc.) in the same file.

- [ ] **Step 4: `AppEnvironmentLabel`**

```php
<?php

namespace App\Support\Ui;

final class AppEnvironmentLabel
{
    public static function fromEnv(?string $env = null): string
    {
        $env = strtolower((string) ($env ?? config('app.env')));

        return match ($env) {
            'local' => 'Lokal',
            'staging' => 'Staging',
            'production' => 'Produksi',
            default => $env !== '' ? $env : 'unknown',
        };
    }
}
```

- [ ] **Step 5: `partials/footer.blade.php`**

```blade
@php
    use App\Support\Ui\AppEnvironmentLabel;
    $envLabel = AppEnvironmentLabel::fromEnv();
    $requestId = function_exists('request_id') ? request_id() : null;
@endphp
<footer class="app-footer" @if($requestId) title="request_id: {{ $requestId }}" @endif>
    <div>
        <strong class="font-display" style="color: var(--ink); font-weight: 600;">Pusat Data</strong>
        <span aria-hidden="true"> · </span>
        <span>&copy; {{ now()->year }}</span>
    </div>
    <span class="env-badge">{{ $envLabel }}</span>
</footer>
```

- [ ] **Step 6: Restyle guest layout to Vite + footer**

Replace inline-only guest styles with `@vite(['resources/css/app.css', 'resources/js/app.js'])`, teal tokens, brand-first heading, and `@include('partials.footer')` at bottom of a full-height column. Keep existing `@yield('content')` for login/mfa forms; update form classes to use shared field styles if already defined, else minimal teal-compatible classes.

- [ ] **Step 7: Build assets**

```bash
npm run build
```

Expected: success.

- [ ] **Step 8: Review** — no purple theme; fonts not Inter; footer has env + request_id title.

- [ ] **Step 9: Commit**

```bash
git add package.json package-lock.json resources/css/app.css resources/js/app.js resources/views/layouts/guest.blade.php resources/views/partials/footer.blade.php app/Support/Ui/AppEnvironmentLabel.php
git commit -m "$(cat <<'EOF'
Add teal design tokens, fonts, and shared app footer.

EOF
)"
```

---

### Task 2: AdminMenu + admin layout shell

**Files:**
- Create: `app/Support/Navigation/AdminMenu.php`
- Create: `resources/views/layouts/admin.blade.php`
- Create: `resources/views/partials/admin-sidebar.blade.php`
- Create: `resources/views/partials/admin-header.blade.php`
- Create: `app/Http/Controllers/Admin/ComingSoonController.php`
- Create: `resources/views/admin/coming-soon.blade.php`
- Modify: `routes/web.php`
- Modify: `resources/views/admin/dashboard.blade.php` (extend admin layout)

- [ ] **Step 1: Implement `AdminMenu`**

```php
<?php

namespace App\Support\Navigation;

use App\Models\User;
use Illuminate\Support\Collection;

final class AdminMenu
{
    /**
     * @return Collection<int, array{label: string, route: string, available: bool}>
     */
    public function forUser(User $user): Collection
    {
        if ($user->isSuperAdmin()) {
            return collect([
                ['label' => 'Dashboard', 'route' => 'admin.dashboard', 'available' => true],
                ['label' => 'Lembaga', 'route' => 'admin.coming-soon.lembaga', 'available' => false],
                ['label' => 'Admin lembaga', 'route' => 'admin.coming-soon.admin-lembaga', 'available' => false],
                ['label' => 'API client', 'route' => 'admin.coming-soon.api-client', 'available' => false],
            ]);
        }

        if ($user->isAdminLembaga()) {
            return collect([
                ['label' => 'Dashboard', 'route' => 'admin.dashboard', 'available' => true],
                ['label' => 'Tahun ajaran', 'route' => 'admin.coming-soon.tahun-ajaran', 'available' => false],
                ['label' => 'Guru', 'route' => 'admin.coming-soon.guru', 'available' => false],
                ['label' => 'Kelas', 'route' => 'admin.coming-soon.kelas', 'available' => false],
                ['label' => 'Siswa', 'route' => 'admin.coming-soon.siswa', 'available' => false],
                ['label' => 'Karyawan', 'route' => 'admin.coming-soon.karyawan', 'available' => false],
                ['label' => 'API client', 'route' => 'admin.coming-soon.api-client-ro', 'available' => false],
            ]);
        }

        return collect();
    }
}
```

When a feature becomes available in M5, flip `available` to `true` and point `route` to the real named route. Until then all non-dashboard items hit coming-soon routes (`available` false still navigable to placeholder).

Clarify: both available true/false still `route()` to a page — `available` only changes badge “Segera” vs active styling. All listed routes must exist.

- [ ] **Step 2: ComingSoonController + view**

```php
public function show(string $feature): View
{
    $titles = [
        'lembaga' => 'Lembaga',
        'admin-lembaga' => 'Admin lembaga',
        'api-client' => 'API client',
        'tahun-ajaran' => 'Tahun ajaran',
        'guru' => 'Guru',
        'kelas' => 'Kelas',
        'siswa' => 'Siswa',
        'karyawan' => 'Karyawan',
        'api-client-ro' => 'API client',
    ];
    abort_unless(isset($titles[$feature]), 404);

    return view('admin.coming-soon', [
        'title' => $titles[$feature],
        'user' => request()->user(),
    ]);
}
```

View: extends admin layout; empty-state “Segera hadir” + one sentence Bahasa Indonesia.

- [ ] **Step 3: Routes inside admin middleware group**

```php
Route::get('/coming-soon/{feature}', [ComingSoonController::class, 'show'])
    ->where('feature', 'lembaga|admin-lembaga|api-client|tahun-ajaran|guru|kelas|siswa|karyawan|api-client-ro')
    ->name('admin.coming-soon');
```

Then either name each menu route as aliases:

```php
Route::redirect('/lembaga', '/admin/coming-soon/lembaga')->name('admin.coming-soon.lembaga');
// ... one redirect named route per AdminMenu entry
```

Or change `AdminMenu` to use a single route with parameter:

```php
['label' => 'Lembaga', 'route' => 'admin.coming-soon', 'params' => ['feature' => 'lembaga'], 'available' => false],
```

**Prefer parameterized route** to avoid many redirects. Update `AdminMenu` accordingly:

```php
['label' => 'Lembaga', 'route' => 'admin.coming-soon', 'params' => ['feature' => 'lembaga'], 'available' => false],
```

Sidebar link: `route($item['route'], $item['params'] ?? [])`.

- [ ] **Step 4: Admin layout**

`layouts/admin.blade.php`:
- `@vite`
- grid shell; include sidebar + backdrop; main column = header + `@yield('content')` wrapped in `.admin-content` + footer
- Pass `$menu` from View composer or from each controller — **prefer View composer** in `AppServiceProvider`:

```php
View::composer('layouts.admin', function ($view) {
    $user = auth()->user();
    $view->with('menu', $user ? app(AdminMenu::class)->forUser($user) : collect());
    $view->with('authUser', $user);
});
```

Sidebar: brand “Pusat Data” (font-display), loop `$menu`, mark active via `request()->routeIs(...)`.
Header: sidebar toggle button (`data-sidebar-toggle`), breadcrumb `@yield('breadcrumb')`, user name, role badge, lembaga name if admin_lembaga, logout form POST.

- [ ] **Step 5: Point dashboard at admin layout**

```blade
@extends('layouts.admin')
@section('title', 'Dashboard')
@section('breadcrumb', 'Dashboard')
@section('content')
  {{-- stats in Task 4 --}}
@endsection
```

- [ ] **Step 6: Review** — menu order for Admin Lembaga matches SPEC; SA has no master-data menu; drawer works conceptually.

- [ ] **Step 7: Commit**

```bash
git commit -m "$(cat <<'EOF'
Add admin app-rail layout, role menu, and coming-soon pages.

EOF
)"
```

---

### Task 3: Blade UI components

**Files:** create under `resources/views/components/ui/`:
`button.blade.php`, `input.blade.php`, `select.blade.php`, `badge.blade.php`, `modal.blade.php`, `table.blade.php`, `pagination.blade.php`, `empty-state.blade.php`, `skeleton.blade.php`

Plus CSS classes in `app.css` for `.btn`, `.field`, `.badge`, `.table-wrap`, `.modal`, etc.

- [ ] **Step 1: Implement components** with props:

**button:** `variant` = primary|secondary|danger|ghost, `type`, `href` (optional → `<a>`), `disabled`, slot = label.

**input:** `name`, `label`, `type`, `value`, `required`, `error` (message), `hint`.

**select:** same pattern + slot options.

**badge:** `tone` = ok|warn|danger|neutral|brand, slot.

**modal:** `id`, `title`, slots: default body, `actions`. Hidden by default; `open` class toggled later / `x-show` not required in M4 — use native `<dialog>` if comfortable, else hidden div with `[open]` attribute documented for M5.

**table:** slot thead + tbody; if empty slot `empty` provided show empty-state.

**pagination:** `{{ $paginator->links() }}` inside styled wrapper — ensure Tailwind pagination views sourced.

**empty-state:** `title`, `description`, slot CTA.

**skeleton:** `rows` default 3.

- [ ] **Step 2: Showcase usage on coming-soon** (use `<x-ui.empty-state>`) and prepare dashboard to use `<x-ui.badge>`.

- [ ] **Step 3: Review** — Indonesian labels in examples; focus styles present; no card spam.

- [ ] **Step 4: Commit**

```bash
git commit -m "$(cat <<'EOF'
Add shared Blade UI components for admin forms and lists.

EOF
)"
```

---

### Task 4: DashboardStats + real dashboards

**Files:**
- Create: `app/Services/Dashboard/DashboardStats.php`
- Modify: `DashboardController.php`
- Modify: `resources/views/admin/dashboard.blade.php`
- Create: `tests/Feature/AdminShellTest.php`

- [ ] **Step 1: `DashboardStats`**

```php
<?php

namespace App\Services\Dashboard;

use App\Models\ApiClient;
use App\Models\Guru;
use App\Models\Karyawan;
use App\Models\Lembaga;
use App\Models\Siswa;
use App\Models\User;
use App\Models\TahunAjaran;
use App\Models\Kelas;

final class DashboardStats
{
    /** @return array<string, mixed> */
    public function for(User $user): array
    {
        if ($user->isSuperAdmin()) {
            return [
                'role' => 'super_admin',
                'lembaga_aktif' => Lembaga::query()->where('is_active', true)->count(),
                'lembaga_nonaktif' => Lembaga::query()->where('is_active', false)->count(),
                'api_client_aktif' => ApiClient::query()->where('is_active', true)->whereNull('revoked_at')->count(),
                'guru' => Guru::query()->count(), // Super Admin: no tenant scope filter
                'siswa' => Siswa::query()->count(),
                'karyawan' => Karyawan::query()->count(),
            ];
        }

        // Admin Lembaga: global scope applies when authenticated
        return [
            'role' => 'admin_lembaga',
            'lembaga_nama' => $user->lembaga?->nama,
            'tahun_ajaran' => TahunAjaran::query()->count(),
            'guru' => Guru::query()->count(),
            'kelas' => Kelas::query()->count(),
            'siswa' => Siswa::query()->count(),
            'karyawan' => Karyawan::query()->count(),
            'urutan' => [
                ['step' => 1, 'label' => 'Tahun ajaran', 'count_key' => 'tahun_ajaran'],
                ['step' => 2, 'label' => 'Guru', 'count_key' => 'guru'],
                ['step' => 3, 'label' => 'Kelas', 'count_key' => 'kelas'],
                ['step' => 4, 'label' => 'Siswa', 'count_key' => 'siswa'],
                ['step' => 5, 'label' => 'Karyawan', 'count_key' => 'karyawan'],
            ],
        ];
    }
}
```

Note: when calling counts as Super Admin, Auth user is Super Admin so `BelongsToLembaga` does not filter. When Admin Lembaga, scope filters automatically — do **not** call `withoutGlobalScopes()` here.

Verify `ApiClient` model field names (`is_active`, `revoked_at`) against model before coding.

- [ ] **Step 2: Controller**

```php
public function show(Request $request, DashboardStats $stats): View
{
    return view('admin.dashboard', [
        'user' => $request->user(),
        'stats' => $stats->for($request->user()),
    ]);
}
```

- [ ] **Step 3: Dashboard Blade**

Super Admin: metric panels (lembaga aktif/nonaktif, API client aktif, guru/siswa/karyawan).
Admin Lembaga: ordered checklist 1–5 with counts + short copy.
Use `font-display` for page title “Dashboard”.

- [ ] **Step 4: `AdminShellTest`**

```php
// 1. super admin login → get admin.dashboard 200; assertSee Pusat Data; assertSee Lembaga; assertDontSee in order that Admin-only 'Tahun ajaran' is NOT in sidebar for SA — wait SA should NOT see Tahun ajaran
// 2. admin lembaga → assertSee Tahun ajaran before Guru (strpos); assertDontSee sidebar label only-SA if 'Lembaga' menu for SA — Admin should not see top-level 'Lembaga' management the same way — Admin menu has no 'Lembaga' item
// 3. footer: assertSee 'Lokal' when APP_ENV local; assertSee © year
// 4. get coming-soon feature guru as admin → 200 assertSee Segera hadir
// 5. guest get admin → redirect login
```

Use existing auth helpers from `AdminAuthTest` patterns (post login / actingAs with MFA config false for speed):

```php
config(['security.mfa.super_admin_required' => false]);
$user = User::factory()->create([...]);
$this->actingAs($user)->get(route('admin.dashboard'))->assertOk();
```

For Admin Lembaga always `Lembaga::factory()` + `adminLembaga($id)`.

- [ ] **Step 5: Run tests**

```bash
/usr/local/Cellar/php/8.5.8/bin/php artisan test --filter=AdminShellTest
npm run build
```

Expected: PASS.

- [ ] **Step 6: Review** — stats scoped; UI not sole authz; Indonesian copy.

- [ ] **Step 7: Commit**

```bash
git commit -m "$(cat <<'EOF'
Add role dashboards with live stats and admin shell tests.

EOF
)"
```

---

### Task 5: Full suite, TODO checklist, polish

- [ ] **Step 1:** Run full suite `/usr/local/Cellar/php/8.5.8/bin/php artisan test` — all green; fix regressions (login views still work with new guest layout).

- [ ] **Step 2:** Manual CSS check — `npm run build`; ensure login form usable.

- [ ] **Step 3:** Update `docs/IMPLEMENTATION_TODO.md` Milestone 4 to **Selesai** and check all boxes proven by review+tests. Note footer explicitly if needed in checklist comments.

- [ ] **Step 4:** Mark design spec already DISETUJUI (done).

- [ ] **Step 5: Commit + push branch/worktree per execution mode**

```bash
git commit -m "$(cat <<'EOF'
Complete Milestone 4 UI shell checklist after verified tests.

EOF
)"
```

---

## Spec coverage checklist

| Spec item | Task |
|-----------|------|
| Teal tokens + fonts | 1 |
| Footer B (env + request_id title) | 1 |
| Guest restyle | 1 |
| Admin rail + drawer | 2 |
| Sidebar per role + coming soon | 2 |
| UI components | 3 |
| Dashboards SA / Admin | 4 |
| Tests smoke/nav/footer | 4–5 |
| IMPLEMENTATION_TODO | 5 |

## Locked ambiguities

- Menu placeholders use **one** parameterized `admin.coming-soon` route + `params.feature`.
- `available` flag is for badge/styling only; items remain clickable to coming-soon.
- Fonts via npm `@fontsource-variable/*`; if install fails, fall back to Google Fonts `<link>` in layouts (document in commit message).
