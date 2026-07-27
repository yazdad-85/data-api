# Login Modernization Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Modernize the `/login` and `/login/mfa` guest experience with a premium split layout, guardian hero artwork, and lightweight motion while preserving all existing auth behavior.

**Architecture:** Refactor the guest shell into a reusable split layout with a shared auth hero partial on the left and the existing forms on the right. Keep routes/controllers unchanged; only update Blade, CSS, one static hero asset, and light JS polish. Reuse `app_branding()` so uploaded branding flows through the new guest UI automatically.

**Tech Stack:** Laravel 13 Blade, existing Vite pipeline, CSS in `resources/css/app.css`, light vanilla JS in `resources/js/app.js`, PHPUnit feature tests.

**Spec:** `docs/superpowers/specs/2026-07-27-login-modernization-design.md`

**PHP for tests:** `/usr/local/Cellar/php/8.5.8/bin/php`

---

## File map

| File | Responsibility |
|------|----------------|
| `resources/views/layouts/guest.blade.php` | Split guest shell, dynamic title/favicon, hero/content slots |
| `resources/views/partials/auth-hero.blade.php` | Shared hero block for login + MFA |
| `resources/views/auth/login.blade.php` | Modernized login form panel copy/markup |
| `resources/views/auth/mfa.blade.php` | Modernized MFA form panel copy/markup |
| `resources/css/app.css` | Guest/auth shell, hero, form card, responsive layout, motion |
| `resources/js/app.js` | Optional page-load animation hook |
| `public/images/auth/guardian.webp` | Stylized guardian art asset |
| `tests/Feature/AdminAuthTest.php` | Guest UI regression coverage for login + MFA |

---

### Task 1: Auth page regression tests first

**Files:**
- Modify: `tests/Feature/AdminAuthTest.php`

- [ ] **Step 1: Write failing guest UI tests**

Add these tests to `tests/Feature/AdminAuthTest.php`:

```php
public function test_login_page_renders_modern_guest_shell_with_branding(): void
{
    $response = $this->get(route('login'));

    $response->assertOk()
        ->assertSee('Masuk ke panel administrasi.')
        ->assertSee('data-auth-shell', false)
        ->assertSee('data-auth-hero', false)
        ->assertSee('email')
        ->assertSee('password');
}

public function test_mfa_page_renders_shared_guest_shell(): void
{
    config(['security.mfa.super_admin_required' => true]);

    $secret = 'JBSWY3DPEHPK3PXP';
    User::factory()->withMfa($secret)->create([
        'email' => 'super@example.test',
        'password' => 'StrongPassword123',
        'role' => 'super_admin',
        'lembaga_id' => null,
    ]);

    $this->post('/login', [
        'email' => 'super@example.test',
        'password' => 'StrongPassword123',
    ])->assertRedirect(route('login.mfa'));

    $this->get(route('login.mfa'))
        ->assertOk()
        ->assertSee('Verifikasi MFA')
        ->assertSee('data-auth-shell', false)
        ->assertSee('data-auth-hero', false)
        ->assertSee('Kode autentikasi');
}
```

- [ ] **Step 2: Run tests — expect fail**

```bash
/usr/local/Cellar/php/8.5.8/bin/php artisan test --filter='test_login_page_renders_modern_guest_shell_with_branding|test_mfa_page_renders_shared_guest_shell'
```

Expected: FAIL because the current guest views do not contain the new shell markers.

- [ ] **Step 3: Commit test-only red phase only if needed**

Do **not** commit yet unless the implementer prefers explicit red/green snapshots. Default: continue straight to implementation.

---

### Task 2: Shared guest shell + auth hero partial

**Files:**
- Modify: `resources/views/layouts/guest.blade.php`
- Create: `resources/views/partials/auth-hero.blade.php`

- [ ] **Step 1: Create the reusable hero partial**

Create `resources/views/partials/auth-hero.blade.php`:

```blade
@php
    $branding = app_branding();
    $heroTitle = $heroTitle ?? $branding['name'];
    $heroBody = $heroBody ?? 'Pusat data pendidikan yang aman, terintegrasi, dan siap dipakai operasional harian.';
    $heroEyebrow = $heroEyebrow ?? 'Pusat Data Terintegrasi';
    $heroBadges = $heroBadges ?? ['Aman', 'Terintegrasi', 'Siap sinkron'];
@endphp

<section class="auth-hero" data-auth-hero>
    <div class="auth-hero__inner">
        <p class="auth-hero__eyebrow">{{ $heroEyebrow }}</p>

        <div class="auth-hero__brand">
            @if ($branding['logo_url'])
                <img src="{{ $branding['logo_url'] }}" alt="" class="auth-hero__brand-logo">
            @endif
            <span class="auth-hero__brand-name font-display">{{ $branding['name'] }}</span>
        </div>

        <h1 class="auth-hero__title font-display">{{ $heroTitle }}</h1>
        <p class="auth-hero__body">{{ $heroBody }}</p>

        <div class="auth-hero__badges" aria-label="Keunggulan sistem">
            @foreach ($heroBadges as $badge)
                <span class="auth-hero__badge">{{ $badge }}</span>
            @endforeach
        </div>

        <div class="auth-hero__visual" aria-hidden="true">
            <div class="auth-hero__orb auth-hero__orb--one"></div>
            <div class="auth-hero__orb auth-hero__orb--two"></div>
            <div class="auth-hero__orbit auth-hero__orbit--one"></div>
            <div class="auth-hero__orbit auth-hero__orbit--two"></div>
            <img src="{{ asset('images/auth/guardian.webp') }}" alt="" class="auth-hero__guardian">
        </div>
    </div>
</section>
```

- [ ] **Step 2: Refactor guest layout around split shell**

Replace `resources/views/layouts/guest.blade.php` with:

```blade
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    @php
        $branding = app_branding();
        $pageTitle = trim($__env->yieldContent('title'));
        $documentTitle = $pageTitle !== '' ? $pageTitle : $branding['name'];
    @endphp
    <title>{{ $documentTitle }}</title>
    @if ($branding['favicon_url'])
        <link rel="icon" href="{{ $branding['favicon_url'] }}">
    @endif
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body>
    <div class="auth-shell" data-auth-shell>
        @yield('hero')

        <main class="auth-main">
            <div class="auth-panel">
                @yield('content')
            </div>
        </main>
    </div>
    @include('partials.footer')
</body>
</html>
```

- [ ] **Step 3: Run tests — still expected fail**

```bash
/usr/local/Cellar/php/8.5.8/bin/php artisan test --filter='test_login_page_renders_modern_guest_shell_with_branding|test_mfa_page_renders_shared_guest_shell'
```

Expected: may still FAIL because login and MFA views do not yet provide the hero section / updated panel copy.

---

### Task 3: Modernize `login` and `mfa` views

**Files:**
- Modify: `resources/views/auth/login.blade.php`
- Modify: `resources/views/auth/mfa.blade.php`

- [ ] **Step 1: Update login view**

Replace `resources/views/auth/login.blade.php` with:

```blade
@extends('layouts.guest')

@php
    $branding = app_branding();
@endphp

@section('title', 'Masuk — '.$branding['name'])

@section('hero')
    @include('partials.auth-hero', [
        'heroTitle' => 'Kelola data lembaga dengan aman dan modern.',
        'heroBody' => 'Masuk untuk mengakses pusat data, sinkronisasi, dan administrasi lembaga dalam satu sistem terintegrasi.',
    ])
@endsection

@section('content')
    <div class="auth-panel__header">
        <h2 class="auth-panel__title font-display">Masuk</h2>
        <p class="auth-panel__subtitle">Masuk ke panel administrasi.</p>
    </div>

    @if ($errors->any())
        <p class="guest-error">{{ $errors->first() }}</p>
    @endif

    <form method="POST" action="{{ route('login') }}" class="auth-form">
        @csrf

        <label for="email">Email</label>
        <input id="email" type="email" name="email" value="{{ old('email') }}" required autofocus autocomplete="username">

        <label for="password">Password</label>
        <input id="password" type="password" name="password" required autocomplete="current-password">

        <button type="submit">Masuk</button>
    </form>
@endsection
```

- [ ] **Step 2: Update MFA view**

Replace `resources/views/auth/mfa.blade.php` with:

```blade
@extends('layouts.guest')

@php
    $branding = app_branding();
@endphp

@section('title', 'Verifikasi MFA — '.$branding['name'])

@section('hero')
    @include('partials.auth-hero', [
        'heroTitle' => 'Verifikasi akses sebelum masuk ke dashboard.',
        'heroBody' => 'Langkah tambahan ini menjaga akun Super Admin tetap aman sebelum melanjutkan ke panel administrasi.',
    ])
@endsection

@section('content')
    <div class="auth-panel__header">
        <h2 class="auth-panel__title font-display">Verifikasi MFA</h2>
        <p class="auth-panel__subtitle">Masukkan kode autentikasi dari aplikasi autentikator atau kode pemulihan.</p>
    </div>

    @if ($errors->any())
        <p class="guest-error">{{ $errors->first() }}</p>
    @endif

    <form method="POST" action="{{ route('login.mfa') }}" class="auth-form">
        @csrf

        <label for="code">Kode autentikasi</label>
        <input id="code" type="text" name="code" inputmode="text" autocomplete="one-time-code" required autofocus>

        <button type="submit">Verifikasi</button>
    </form>
@endsection
```

- [ ] **Step 3: Run tests — expect pass for new guest shell assertions**

```bash
/usr/local/Cellar/php/8.5.8/bin/php artisan test --filter='test_login_page_renders_modern_guest_shell_with_branding|test_mfa_page_renders_shared_guest_shell'
```

Expected: PASS.

- [ ] **Step 4: Commit form/shell markup**

```bash
git add resources/views/layouts/guest.blade.php \
  resources/views/partials/auth-hero.blade.php \
  resources/views/auth/login.blade.php \
  resources/views/auth/mfa.blade.php \
  tests/Feature/AdminAuthTest.php
git commit -m "$(cat <<'EOF'
feat(auth): add split guest shell for login and MFA

Move login and MFA into a shared premium guest layout with a reusable
branding hero while preserving the existing authentication flows.
EOF
)"
```

---

### Task 4: Styling, motion, and responsive polish

**Files:**
- Modify: `resources/css/app.css`
- Modify: `resources/js/app.js`

- [ ] **Step 1: Add CSS for auth shell**

Append focused auth styles to `resources/css/app.css`:

```css
.auth-shell {
    min-height: 100vh;
    display: grid;
    grid-template-columns: minmax(0, 1.15fr) minmax(360px, 0.85fr);
    background:
        radial-gradient(circle at 18% 18%, rgb(201 162 39 / 0.18), transparent 30%),
        linear-gradient(135deg, #063b37 0%, #0a4f4f 45%, #0f6b6b 100%);
}

.auth-hero {
    position: relative;
    overflow: hidden;
    color: #f3fbf9;
}

.auth-hero__inner {
    min-height: 100%;
    padding: 3rem clamp(1.5rem, 4vw, 4rem);
    display: flex;
    flex-direction: column;
    justify-content: center;
    gap: 1rem;
}

.auth-hero__eyebrow {
    margin: 0;
    text-transform: uppercase;
    letter-spacing: 0.12em;
    font-size: 0.8rem;
    color: rgb(255 255 255 / 0.72);
}

.auth-hero__brand {
    display: flex;
    align-items: center;
    gap: 0.75rem;
}

.auth-hero__brand-logo {
    max-width: 52px;
    max-height: 52px;
    object-fit: contain;
}

.auth-hero__brand-name {
    font-size: clamp(1.2rem, 2vw, 1.8rem);
    font-weight: 700;
}

.auth-hero__title {
    margin: 0;
    max-width: 10ch;
    font-size: clamp(2.5rem, 4.6vw, 4.5rem);
    line-height: 0.98;
}

.auth-hero__body {
    margin: 0;
    max-width: 34rem;
    font-size: 1.02rem;
    line-height: 1.7;
    color: rgb(255 255 255 / 0.84);
}

.auth-hero__badges {
    display: flex;
    flex-wrap: wrap;
    gap: 0.75rem;
}

.auth-hero__badge {
    border: 1px solid rgb(255 255 255 / 0.14);
    background: rgb(255 255 255 / 0.08);
    color: #fff7da;
    border-radius: 999px;
    padding: 0.45rem 0.85rem;
    font-size: 0.9rem;
}

.auth-hero__visual {
    position: relative;
    min-height: 280px;
    margin-top: 1rem;
}

.auth-hero__guardian {
    position: relative;
    z-index: 2;
    display: block;
    max-width: min(420px, 100%);
    filter: drop-shadow(0 25px 55px rgb(0 0 0 / 0.28));
    animation: auth-float 6s ease-in-out infinite;
}

.auth-hero__orb,
.auth-hero__orbit {
    position: absolute;
    border-radius: 999px;
    pointer-events: none;
}

.auth-hero__orb--one {
    width: 22px;
    height: 22px;
    top: 10%;
    left: 12%;
    background: radial-gradient(circle, #ffe08a, #c9a227);
    box-shadow: 0 0 22px rgb(201 162 39 / 0.5);
    animation: auth-pulse 3.2s ease-in-out infinite;
}

.auth-hero__orb--two {
    width: 14px;
    height: 14px;
    right: 16%;
    top: 28%;
    background: radial-gradient(circle, #fff4cb, #d4af37);
    animation: auth-pulse 4s ease-in-out infinite;
}

.auth-hero__orbit--one {
    width: 220px;
    height: 220px;
    left: 6%;
    bottom: 3%;
    border: 1px solid rgb(255 255 255 / 0.13);
    animation: auth-rotate 14s linear infinite;
}

.auth-hero__orbit--two {
    width: 320px;
    height: 320px;
    left: 18%;
    bottom: -6%;
    border: 1px solid rgb(201 162 39 / 0.22);
    animation: auth-rotate 20s linear infinite reverse;
}

.auth-main {
    display: flex;
    align-items: center;
    justify-content: center;
    background:
        linear-gradient(180deg, rgb(255 255 255 / 0.94), rgb(244 247 246 / 0.98));
    padding: clamp(1.5rem, 4vw, 3rem);
}

.auth-panel {
    width: min(100%, 28rem);
    padding: clamp(1.5rem, 3vw, 2.5rem);
    border-radius: 1.4rem;
    background: rgb(255 255 255 / 0.94);
    border: 1px solid rgb(255 255 255 / 0.7);
    box-shadow: 0 28px 70px rgb(15 38 37 / 0.14);
}

.auth-panel__header {
    margin-bottom: 1.25rem;
}

.auth-panel__title {
    margin: 0 0 0.35rem;
    font-size: 2rem;
    color: var(--brand-deep);
}

.auth-panel__subtitle {
    margin: 0;
    color: var(--muted);
}

.auth-form {
    display: flex;
    flex-direction: column;
    gap: 0.7rem;
}

.auth-form input {
    min-height: 2.9rem;
}

.auth-form button {
    margin-top: 0.4rem;
}

.auth-shell--ready .auth-hero__inner,
.auth-shell--ready .auth-panel {
    animation: auth-enter 0.7s ease-out both;
}

.auth-shell--ready .auth-panel {
    animation-delay: 0.08s;
}

@keyframes auth-enter {
    from {
        opacity: 0;
        transform: translateY(18px);
    }
    to {
        opacity: 1;
        transform: translateY(0);
    }
}

@keyframes auth-float {
    0%, 100% { transform: translateY(0); }
    50% { transform: translateY(-10px); }
}

@keyframes auth-rotate {
    from { transform: rotate(0deg); }
    to { transform: rotate(360deg); }
}

@keyframes auth-pulse {
    0%, 100% { transform: scale(1); opacity: 0.85; }
    50% { transform: scale(1.08); opacity: 1; }
}

@media (max-width: 959px) {
    .auth-shell {
        grid-template-columns: 1fr;
        min-height: auto;
    }

    .auth-hero__title {
        max-width: none;
        font-size: clamp(2rem, 8vw, 3.1rem);
    }

    .auth-hero__visual {
        min-height: 180px;
    }

    .auth-hero__guardian {
        max-width: 240px;
    }
}

@media (prefers-reduced-motion: reduce) {
    .auth-hero__guardian,
    .auth-hero__orb,
    .auth-hero__orbit,
    .auth-shell--ready .auth-hero__inner,
    .auth-shell--ready .auth-panel {
        animation: none !important;
    }
}
```

- [ ] **Step 2: Add minimal JS page-ready hook**

Append to `resources/js/app.js`:

```js
document.addEventListener('DOMContentLoaded', () => {
    const authShell = document.querySelector('[data-auth-shell]');
    if (authShell) {
        authShell.classList.add('auth-shell--ready');
    }
});
```

- [ ] **Step 3: Run guest/auth regression tests**

```bash
/usr/local/Cellar/php/8.5.8/bin/php artisan test --filter='AdminAuthTest|AppHardeningTest'
```

Expected: PASS. The guest layout changes should not break auth behavior or hardening tests.

- [ ] **Step 4: Commit styling + motion**

```bash
git add resources/css/app.css resources/js/app.js
git commit -m "$(cat <<'EOF'
feat(auth): style guest shell with premium hero and motion

Add the split auth layout styling, guardian hero visuals, and
lightweight CSS animation for login and MFA pages.
EOF
)"
```

---

### Task 5: Add guardian asset and finish regression coverage

**Files:**
- Create: `public/images/auth/guardian.webp`
- Modify: `tests/Feature/AdminAuthTest.php`

- [ ] **Step 1: Add production-safe placeholder guardian art**

Create `public/images/auth/guardian.webp`.

Requirements:

- transparent background;
- stylized guardian / shield visual;
- optimized for web;
- max roughly `400-600px` wide;
- institutionally appropriate.

If final art is not yet available, a temporary placeholder is acceptable as long as the file path and proportions match the final design.

- [ ] **Step 2: Expand tests to cover branding fallback**

Add one more assertion to `test_login_page_renders_modern_guest_shell_with_branding()`:

```php
$response->assertSee('Pusat Data');
```

If branding is dynamic in test setup, adjust to assert the seeded/default name returned by `app_branding()`.

- [ ] **Step 3: Run focused auth tests**

```bash
/usr/local/Cellar/php/8.5.8/bin/php artisan test --filter=AdminAuthTest
```

Expected: PASS.

- [ ] **Step 4: Commit hero asset**

```bash
git add public/images/auth/guardian.webp tests/Feature/AdminAuthTest.php
git commit -m "$(cat <<'EOF'
feat(auth): add guardian hero artwork for guest pages

Introduce the stylized guardian illustration used by the modernized
login and MFA hero panels.
EOF
)"
```

---

### Task 6: Final verification and docs note

**Files:**
- Optionally modify: `docs/SPEC.md`

- [ ] **Step 1: Optional SPEC note**

If the project wants a documented UI note, add under the login/admin UI section:

```markdown
- Halaman login dan verifikasi MFA menggunakan layout split modern dengan branding dinamis dan hero visual ringan, tanpa mengubah alur autentikasi.
```

Skip this if the team wants SPEC changes only for functional behavior.

- [ ] **Step 2: Full suite**

```bash
/usr/local/Cellar/php/8.5.8/bin/php artisan test
```

Expected: all green.

- [ ] **Step 3: Manual smoke**

1. Open `/login`
2. Confirm split layout appears on desktop
3. Submit invalid login and verify error still shows in panel
4. Login as Super Admin with MFA enabled and confirm `/login/mfa` uses the same shell
5. Resize to mobile width and confirm hero stacks above form
6. If OS/browser enables reduced motion, verify layout remains usable with no distracting loops

- [ ] **Step 4: Commit docs note if changed**

```bash
git add docs/SPEC.md
git commit -m "$(cat <<'EOF'
docs: note premium guest shell for login and MFA

Record the visual refresh of the guest authentication pages while
keeping the existing security flow unchanged.
EOF
)"
```

Only do this commit if `docs/SPEC.md` actually changed.

---

## Spec coverage checklist

| Spec requirement | Task |
|------------------|------|
| Split layout hero left + form right | Tasks 2-4 |
| Guardian stylized hero | Tasks 2, 5 |
| CSS orbit / particle motion | Task 4 |
| Login + MFA both updated | Tasks 1-4 |
| Branding via `app_branding()` | Tasks 2-3 |
| Auth logic unchanged | Tasks 1, 3, 4, 6 |
| Reduced motion support | Task 4 |
| Responsive mobile stack | Task 4 |

---

## Execution handoff

Plan complete and saved to `docs/superpowers/plans/2026-07-27-login-modernization.md`.

**Two execution options:**

1. **Subagent-Driven (recommended)** - I dispatch a fresh subagent per task, review between tasks, fast iteration
2. **Inline Execution** - Execute tasks in this session using executing-plans, batch execution with checkpoints

**Which approach?**
