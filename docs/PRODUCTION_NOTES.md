# Production notes (app)

Ringkas untuk operator. Detail infra (Apache, Cloudflare, firewall) → Milestone 11 `DEPLOYMENT.md`.

Basis: [SPEC.md](./SPEC.md), [RULES.md](./RULES.md).

## Wajib di env production

- `APP_ENV=production`
- `APP_DEBUG=false`
- `APP_KEY=` (32-byte base64, `php artisan key:generate`)
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
- Log context redaction (password / API key / token / PII keys) aktif pada channel `single` dan `daily`. Default `LOG_CHANNEL=stack` dengan `LOG_STACK=single` tetap ter-redact karena stack meneruskan log ke processor channel anak `single`.
- Jika `LOG_CHANNEL` / `LOG_STACK` dialihkan ke channel lain (misalnya `stderr`, `papertrail`, atau `syslog`), tambahkan tap redaction pada channel tersebut; tanpa tap, context log tidak akan ter-redact.

## Jangan

- Jangan expose PostgreSQL ke publik
- Jangan commit `.env` / secret
- Jangan mengharapkan SPA browser bisa panggil API tanpa CORS whitelist (fase 2)
