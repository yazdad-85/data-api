# Design — Milestone 10: Hardening Aplikasi

Status: **DISETUJUI — 25 Jul 2026**  
Tanggal: 2026-07-25  
Basis: [SPEC.md](../../SPEC.md) §6.1; [RULES.md](../../RULES.md) B4.1–B4.3; [IMPLEMENTATION_TODO.md](../../IMPLEMENTATION_TODO.md) Milestone 10; M3 [2026-07-18-milestone-3-auth-hardening-design.md](./2026-07-18-milestone-3-auth-hardening-design.md); M7 [2026-07-23-milestone-7-api-client-auth-design.md](./2026-07-23-milestone-7-api-client-auth-design.md)

## 1. Tujuan

Mengunci **baseline keamanan aplikasi** sebelum staging publik: security headers, CORS default deny (server-to-server), session cookie production, `APP_DEBUG` aman, redaction log otomatis, error production tanpa stack trace, health tetap minimal, dan catatan env production. Infrastruktur VPS/Cloudflare/Apache tetap di M11 (`DEPLOYMENT.md`).

## 2. Keputusan yang disepakati

| Topik | Keputusan |
|-------|-----------|
| Pendekatan | **Middleware + config terpusat** (bukan paket pihak ketiga CSP/headers) |
| CSP | **Praktis fase 1**: `default-src 'self'`; `style-src 'self' 'unsafe-inline'`; script/font/img/connect terbatas self (+ `data:` untuk img/font) |
| Session production | `same_site=lax`, `secure=true`, `http_only=true` |
| Logging | **Monolog processor global** reuse `MetadataRedactor` pada channel utama |
| HSTS | Hanya `production` **dan** request HTTPS; `max-age=31536000; includeSubDomains`; **tanpa** `preload` |
| CORS | Default deny untuk browser; whitelist per lembaga → fase 2 / M11+ |
| Docs M10 | `docs/PRODUCTION_NOTES.md` ringkas + update `.env.example`; full deploy → M11 |

## 3. Di luar scope M10

- Cloudflare, firewall VPS, fail2ban, Apache limit, origin pull
- CORS whitelist origin per lembaga
- CSP Report-Only / nonce ketat / enforce tanpa `'unsafe-inline'` style
- Scan substring secret di pesan log mentah (hanya context key/array)
- Perubahan UI admin selain yang diperlukan agar CSP tidak merusak
- MFA enforce production (sudah dikontrol `MFA_SUPER_ADMIN_REQUIRED`)
- Full `docs/DEPLOYMENT.md` dan `docs/API_INTEGRATION.md` (M11)

## 4. Security headers

Middleware global `App\Http\Middleware\SecurityHeaders` di-append di `bootstrap/app.php` (setelah `AssignRequestId` atau berdampingan sebagai append).

Header selalu:

| Header | Nilai |
|--------|--------|
| `X-Content-Type-Options` | `nosniff` |
| `X-Frame-Options` | `DENY` |
| `Referrer-Policy` | `strict-origin-when-cross-origin` |
| `Content-Security-Policy` | lihat §4.1 |

Header kondisional:

| Header | Kondisi | Nilai |
|--------|---------|--------|
| `Strict-Transport-Security` | `app()->environment('production')` **dan** `$request->secure()` | `max-age=31536000; includeSubDomains` |

Jangan menimpa header yang sudah di-set secara eksplisit oleh response sebelumnya (opsional: set hanya jika belum ada).

### 4.1 CSP (praktis)

```
default-src 'self';
script-src 'self';
style-src 'self' 'unsafe-inline';
img-src 'self' data:;
font-src 'self' data:;
connect-src 'self';
object-src 'none';
base-uri 'self';
frame-ancestors 'none';
form-action 'self'
```

Alasan `'unsafe-inline'` pada style: kompatibilitas shell admin Blade/Vite fase 1 tanpa migrasi nonce. Script tetap `'self'` saja.

Nilai CSP boleh dipusatkan di `config/security.php` (`security.headers.csp`) agar mudah diuji/diubah tanpa edit middleware.

## 5. CORS

Publish/atur `config/cors.php`:

- `paths` → `['api/*']` (atau setara yang dipakai framework)
- `allowed_origins` → `[]`
- `allowed_origins_patterns` → `[]`
- `allowed_methods` / `allowed_headers` boleh default Laravel
- `supports_credentials` → `false`

Efek: browser dengan `Origin` asing **tidak** mendapat `Access-Control-Allow-Origin`. Integrasi fase 1 diasumsikan **server-to-server** (RULES B4.3 / SPEC §6.1).

Request API tanpa Origin (curl, server app) tidak terpengaruh kebijakan CORS.

## 6. Session & APP_DEBUG

### 6.1 Session

Di `config/session.php` / env:

| Key | Default / production |
|-----|----------------------|
| `SESSION_HTTP_ONLY` | `true` (sudah) |
| `SESSION_SAME_SITE` | `lax` |
| `SESSION_SECURE_COOKIE` | local: `false` / null-ok; **production: `true`** (wajib di catatan) |

Tidak mengubah `same_site` ke `strict` di M10.

### 6.2 Debug

- `.env.example`: dokumentasikan bahwa production wajib `APP_DEBUG=false` (nilai contoh local boleh `true` untuk DX, dengan komentar tegas).
- `config/app.php` tetap `env('APP_DEBUG', false)` — default aman jika env hilang.

### 6.3 Error production

- API (`api/*`): tetap JSON error tanpa stack (Laravel `shouldRenderJsonWhen` sudah ada); pastikan production tidak mengekspos `trace` / exception class detail ke klien.
- Web: halaman error generik saat `APP_DEBUG=false`.
- Tidak mengubah kontrak `ApiErrorResponse` M7.

## 7. Logging redaction

### 7.1 Komponen

| Komponen | Peran |
|----------|--------|
| `App\Logging\RedactLogContext` (atau nama setara) | Monolog processor: redact `$record->context` (+ `extra` bila relevan) via `MetadataRedactor` |
| `config/logging.php` | `tap` pada channel `single` / `daily` (dan stack yang memakainya) |

Reuse `MetadataRedactor` existing (secret keys sudah mencakup `password`, `authorization`, `x-api-key`, `token`, dll.). Tambah alias key bila perlu untuk bentuk umum log Laravel (`api_key`, `apiKey`) — hanya jika normalisasi strip underscore belum menangkap.

### 7.2 Batasan M10

- Redact **context/extra array**, bukan full-text scan pada message string.
- Audit log tetap memakai redactor seperti sekarang (tidak diganti).

## 8. Health

- `GET /api/v1/health` → `{ "status": "ok" }` saja (sudah ada) — tidak menambah DB/redis checks di M10.
- Laravel `/up` boleh tetap; bukan kontrak integrator.

## 9. Dokumentasi

Buat `docs/PRODUCTION_NOTES.md` singkat berisi:

1. `APP_ENV=production`, `APP_DEBUG=false`
2. `SESSION_SECURE_COOKIE=true`, `SESSION_SAME_SITE=lax`, `SESSION_HTTP_ONLY=true`
3. HTTPS wajib; HSTS aktif otomatis di app saat production+HTTPS
4. CORS fase 1 server-to-server; jangan mengharapkan browser SPA tanpa whitelist
5. Secret hanya di env; `API_KEY_PEPPER` wajib
6. Pointer: detail infra → M11 `DEPLOYMENT.md`

Update `.env.example` dengan komentar production untuk session secure + APP_DEBUG.

## 10. Testing (wajib)

- Response web/API membawa header keamanan (minimal nosniff, frame deny, referrer, CSP).
- HSTS **tidak** muncul di local non-HTTPS; muncul saat environment production + secure request (simulasi di test).
- Request dengan `Origin: https://evil.example` ke `api/*` **tidak** mendapat `Access-Control-Allow-Origin`.
- `Log::info('x', ['password' => 'secret', 'authorization' => 'Bearer x'])` → konteks ter-redact di channel test/log.
- Exception API production tidak membocorkan stack di body JSON.
- `GET /api/v1/health` exact `{ "status": "ok" }`.
- Regresi auth/API smoke tetap hijau.

## 11. Urutan implementasi (ringkas)

1. `config/security.php` CSP/HSTS knobs + `SecurityHeaders` middleware + feature test headers.
2. `config/cors.php` deny + test Origin.
3. Session/env notes + `PRODUCTION_NOTES.md`.
4. Monolog tap + unit/feature redaction test.
5. Production exception assertions + health regression.
6. Update `IMPLEMENTATION_TODO` M10.

## 12. Risiko

- CSP terlalu ketat mematahkan Vite/admin → mitigasi: `'unsafe-inline'` style; uji halaman login + dashboard.
- HSTS salah aktif di HTTP staging → gate `production` + `$request->secure()`.
- CORS kosong membingungkan developer SPA lokal → dokumentasikan server-to-server; whitelist fase 2.
- Processor log menambah overhead kecil → acceptable; jangan scan message penuh.
