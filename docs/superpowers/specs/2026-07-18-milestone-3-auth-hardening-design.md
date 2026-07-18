# Design — Milestone 3: Auth Admin Hardened + Multi-tenant Authorization

Status: **DRAFT — menunggu review pemilik**  
Tanggal: 2026-07-18  
Basis: [README.md](../../../README.md), [PLAN.md](../../PLAN.md), [SPEC.md](../../SPEC.md), [RULES.md](../../RULES.md), [AUDIT_LENGKAP_2026-07-18.md](../../AUDIT_LENGKAP_2026-07-18.md), [IMPLEMENTATION_TODO.md](../../IMPLEMENTATION_TODO.md)

## 1. Tujuan

Mengimplementasikan Milestone 3 (login/logout session admin, throttle, MFA Super Admin, middleware user aktif, Gate/Policy, tenant scope, invalidasi session) dengan **penguatan keamanan login Laravel** yang selaras RULES B4 / SPEC §2, tanpa menambah requirement yang belum disetujui di dokumen.

## 2. Keputusan yang disepakati

| Topik | Keputusan |
|-------|-----------|
| Scope hardening | Milestone 3 + ekstra selaras RULES B4 (session fixation, pesan generik, CSRF, remember me off, audit ringkas, tanpa fitur baru yang butuh ubah SPEC) |
| MFA enforcement | Digate env `MFA_SUPER_ADMIN_REQUIRED` (default `true` di template production; lokal boleh `false`) |
| Implementasi UI auth | Custom Blade + controller/Form Request (bukan Fortify/Breeze; bukan Livewire end-to-end) |
| Forgot-password / email reset | Di luar Milestone 3 |
| Arsitektur | Session pipeline eksplisit (password → pending MFA opsional → full auth) |

## 3. Di luar scope Milestone 3

- Forgot-password via email; reset manual Admin Lembaga (menyusul Milestone 5)
- UI shell/dashboard penuh (Milestone 4) — M3 hanya stub dashboard minimal setelah login
- Security headers / CORS penuh (Milestone 10)
- Auth API key konsumen (Milestone 7)
- Lockout akun permanen, CAPTCHA, IP allowlist Super Admin (perlu update SPEC/RULES jika diinginkan nanti)
- MFA wajib untuk Admin Lembaga (fase 2 per SPEC)

## 4. Arsitektur

### 4.1 Komponen

| Komponen | Tanggung jawab |
|----------|----------------|
| `LoginController` | Tampil form login, terima POST, orkestrasi autentikasi |
| `MfaChallengeController` | Form + verifikasi TOTP/recovery saat pending MFA |
| `LogoutController` | POST logout, invalidate session, regenerate |
| `LoginRequest` | Validasi `email` + `password`; tidak mengungkap keberadaan email |
| `AdminAuthenticator` | Cek kredensial, `is_active`, lembaga aktif; hasil internal terstruktur; pesan ke user selalu generik |
| `TotpVerifier` | Verifikasi TOTP (window ±1) dan recovery code (hash_equals); konsumsi recovery code terpakai |
| `SessionInvalidator` | Hapus session store untuk `user_id` (siap dipanggil M5 saat nonaktifkan user) |
| `EnsureUserIsActive` | Middleware: user/lembaga nonaktif → logout + redirect |
| `EnsureMfaSatisfied` | Middleware: Super Admin dengan MFA required harus sudah melewati challenge |
| `BelongsToLembaga` (trait) | Global scope tenant untuk Admin Lembaga |
| Gates / Policies | Enforcement server-side role + kepemilikan lembaga |
| `config/security.php` | `mfa.super_admin_required` dari env |

### 4.2 Alur login (sukses)

```
POST /login
  → throttle: 5/menit per email+IP; 20/menit per IP
  → AdminAuthenticator
       - password salah / user tidak ada → gagal generik + audit ringkas
       - user nonaktif / Admin Lembaga lembaga null|nonaktif → gagal generik + audit internal (reason terpisah)
  → session()->regenerate()
  → jika Super Admin && MFA required && mfa_enabled_at terisi:
       simpan pending MFA di session (user_id + expiry 10 menit)
       JANGAN Auth::login penuh
       redirect /login/mfa
  → jika Super Admin && MFA required && MFA belum enabled:
       tolak masuk dashboard; tampilkan pesan operasional aman (bootstrap sudah set MFA; kasus ini edge)
  → selain itu: Auth::login → regenerate → audit sukses → redirect stub dashboard
```

### 4.3 Alur MFA challenge

```
GET/POST /login/mfa  (hanya jika session pending valid)
  → TotpVerifier (TOTP atau recovery code)
  → gagal: pesan generik "Kode autentikasi tidak valid" + audit
  → sukses: Auth::login, regenerate, clear pending, audit, redirect stub dashboard
```

Catatan: `install:super-admin` (Milestone 2) sudah menulis `mfa_secret`, `recovery_codes_hash`, dan `mfa_enabled_at`. Challenge M3 memakai kredensial itu. UI setup/rotate MFA penuh boleh minimal; polish di M4 jika perlu.

### 4.4 Hardening scope B (wajib di M3)

1. CSRF pada semua POST auth (middleware `web` Laravel).
2. `remember me` **dimatikan** (tidak ada checkbox; `Auth::login($user, false)`).
3. Session ID di-regenerate setelah login password lolos dan setelah MFA sukses.
4. Pending MFA menyimpan hanya `user_id` + timestamp expiry — bukan password, bukan secret.
5. Pesan login gagal ke klien selalu: `Email atau password salah`.
6. Pesan MFA gagal selalu: `Kode autentikasi tidak valid`.
7. Jangan log password, TOTP, recovery code, atau `mfa_secret`.
8. Audit events (ringkas, metadata sudah lewat `MetadataRedactor`):  
   `auth.login` (success/failed), `auth.mfa` (success/failed), `auth.logout` (success), `auth.session_revoked` (saat middleware menonaktifkan).
9. Rate limit: **5/menit/email+IP** dan **20/menit/IP** (angka IP eksplisit untuk menutup celah “limit tambahan per IP” di RULES tanpa mengubah makna dokumen).

## 5. Otorisasi & tenant isolation

### 5.1 Role helpers

Pada `User`:

- `isSuperAdmin(): bool`
- `isAdminLembaga(): bool`
- `canAccessLembaga(string $lembagaId): bool` — Super Admin selalu true; Admin Lembaga hanya jika `lembaga_id` cocok

### 5.2 Gates

- `access-admin` — user terautentikasi dengan role `super_admin` atau `admin_lembaga`
- `manage-all-lembaga` — Super Admin
- `manage-own-lembaga` — Admin Lembaga (scoped)

### 5.3 Policies (model master tenant)

Untuk `Guru`, `Siswa`, `Kelas`, `TahunAjaran`, `Karyawan` (dan siap untuk `Lembaga`/`User` di M5):

- `view` / `viewAny` / `create` / `update` / `delete`: Super Admin boleh; Admin Lembaga hanya record dengan `lembaga_id` sendiri
- Deny default untuk role lain

### 5.4 Global scope

Trait pada model tenant:

- Jika auth user adalah Admin Lembaga → `where lembaga_id = auth.lembaga_id`
- Super Admin → tanpa filter otomatis
- Guest/unauthenticated → scope ketat (tidak mengembalikan data) atau tidak dipakai di luar auth

Lookup by UUID tetap melalui policy: manipulasi URL lembaga lain → **403**, plus audit percobaan lintas lembaga (tanpa dump PII).

### 5.5 Middleware & routes

**Guest**

- `GET/POST /login`
- `GET/POST /login/mfa`

**Authenticated + EnsureUserIsActive + EnsureMfaSatisfied**

- `GET /admin` (atau `/dashboard`) — stub minimal Bahasa Indonesia
- `POST /logout`

`EnsureUserIsActive`: jika `!user.is_active` atau (Admin Lembaga dan lembaga hilang/nonaktif) → `SessionInvalidator` + logout + redirect login.

`EnsureMfaSatisfied`: jika Super Admin, MFA required, dan session belum menandai MFA selesai untuk login ini → redirect challenge atau logout pending kedaluwarsa.

### 5.6 Invalidasi session saat user dinonaktifkan

- M3 menyediakan `SessionInvalidator` (hapus baris `sessions` untuk `user_id` bila driver database; clear current session on logout/middleware).
- Pemanggilan dari CRUD nonaktifkan user diintegrasikan di Milestone 5; di M3 cukup teruji via middleware + logout + helper unit/feature test.

## 6. Konfigurasi

```env
MFA_SUPER_ADMIN_REQUIRED=true
```

`config/security.php`:

```php
'mfa' => [
    'super_admin_required' => (bool) env('MFA_SUPER_ADMIN_REQUIRED', true),
    'pending_ttl_minutes' => 10,
    'totp_window' => 1,
],
```

Session production tetap mengikuti RULES (httpOnly / secure / same_site ketat) — nilai production dikunci penuh di Milestone 10; M3 tidak melemahkan default aman.

## 7. Error handling (kontrak user-facing)

| Situasi | Respons user |
|---------|----------------|
| Email/password salah, user nonaktif, lembaga Admin nonaktif | `Email atau password salah` |
| Throttle | HTTP 429 + pesan rate limit |
| MFA salah / kedaluwarsa pending | `Kode autentikasi tidak valid` atau kembali ke login jika pending habis |
| Akses tenant asing | HTTP 403 |

Detail reason hanya di audit log internal (`reason` machine-readable), bukan di flash message.

## 8. Testing (setelah review kode bersih)

Sesuai `IMPLEMENTATION_TODO` Milestone 3 + hardening B:

1. Super Admin bisa mengakses data lintas lembaga (via policy/scope).
2. Admin Lembaga hanya lembaga sendiri; ID lembaga lain → 403.
3. Admin Lembaga lembaga nonaktif ditolak login (pesan generik).
4. Throttle 5/menit/email+IP dan 20/menit/IP bekerja.
5. Dengan `MFA_SUPER_ADMIN_REQUIRED=true`, Super Admin tidak mendapat full session tanpa challenge; setelah TOTP/recovery benar, masuk.
6. Dengan `MFA_SUPER_ADMIN_REQUIRED=false`, Super Admin bisa login tanpa challenge (lokal/dev).
7. Session ID berubah setelah login (dan setelah MFA sukses).
8. Password/TOTP/recovery/`mfa_secret` tidak muncul di response atau metadata audit.
9. User yang dinonaktifkan (simulasi) dipaksa logout oleh middleware.

## 9. Urutan implementasi yang disarankan

1. Config security + helper role di `User`
2. `AdminAuthenticator`, `TotpVerifier`, `SessionInvalidator`
3. Controllers + views login/MFA/logout + throttle routes
4. Middleware active + MFA satisfied; stub dashboard
5. Gates/Policies + trait tenant scope
6. Audit events auth
7. Feature tests
8. Review kode (RULES B2) → perbaiki → tes → update `IMPLEMENTATION_TODO.md` → commit/push

## 10. Risiko & mitigasi

| Risiko | Mitigasi |
|--------|----------|
| Partial auth bocor ke route admin | Pending MFA ≠ `Auth::login`; middleware MFA + guest-only pada challenge |
| Timing oracle email | Alur dan pesan seragam; hindari cabang yang jelas beda latency bila praktis |
| Recovery code reuse | Hapus hash kode yang sudah dipakai setelah sukses |
| Scope global mengagetkan Super Admin query | Super Admin dikecualikan dari filter; dokumentasikan di trait |
| Angka throttle IP “20” tidak tertulis di RULES | Dianggap konkretisasi “limit tambahan per IP”; dicatat di spec ini; jika pemilik ingin angka lain, ubah di sini sebelum coding |

## 11. Acceptance criteria

Milestone 3 dianggap siap lanjut hanya jika:

- Semua item checklist Milestone 3 di `IMPLEMENTATION_TODO.md` dapat dicentang setelah review + test hijau
- Hardening scope B di §4.4 terpenuhi
- Tidak ada temuan keamanan/tenant isolation yang ditunda
- Tidak menambah forgot-password atau fitur di luar §3 tanpa update SPEC/RULES
