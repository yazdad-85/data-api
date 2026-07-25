# Production notes (app)

Ringkas untuk operator. Detail infra (Apache, Cloudflare, firewall) → Milestone 11 `DEPLOYMENT.md`.

Basis: [SPEC.md](./SPEC.md), [RULES.md](./RULES.md).

## Wajib di env production

- `APP_ENV=production`
- `APP_DEBUG=false`
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
- Log context di-redact untuk password / API key / token / PII keys

## Jangan

- Jangan expose PostgreSQL ke publik
- Jangan commit `.env` / secret
- Jangan mengharapkan SPA browser bisa panggil API tanpa CORS whitelist (fase 2)
