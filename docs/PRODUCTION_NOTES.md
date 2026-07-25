# Production notes (app)

Ringkas untuk operator. Detail infra (Apache, Cloudflare, firewall) → Milestone 11 `DEPLOYMENT.md`.

Basis: [SPEC.md](./SPEC.md), [RULES.md](./RULES.md).

## Akses lembaga, TRUSTED_PROXIES, dan Cloudflare

Tiga hal ini sering tercampur — fungsinya berbeda:

| Hal | Apa artinya | Siapa yang diisi / didaftarkan |
|-----|-------------|-------------------------------|
| **API key** | Identitas aplikasi konsumen lembaga | Client di admin UI (bukan IP laptop) |
| **`TRUSTED_PROXIES`** | IP/CIDR proxy di depan Laravel (Cloudflare / Apache) | Hanya ingress server Anda |
| **Cloudflare** | Edge protection + HTTPS di depan VPS | Satu setup DNS/proxy untuk domain | 

**Fase 1 — alur yang benar**

```text
Laptop / PC admin lembaga
        ↓
Aplikasi lokal lembaga (server backend)
        ↓  X-API-Key
Cloudflare → VPS (data-api)
```

- Laptop/PC lembaga **tidak** perlu didaftarkan ke `TRUSTED_PROXIES` atau whitelist IP.
- Browser di laptop **tidak** memanggil data-api langsung (CORS default deny).
- Akses API = **server-to-server** dengan API key + scope (lihat SPEC §2.2).
- Cloudflare (orange cloud / full proxy) **wajib sebelum produksi publik** (RULES D6).
- Whitelist CORS origin per lembaga → **fase 2** (jika nanti ada SPA browser).

## Wajib di env production

- `APP_ENV=production`
- `APP_DEBUG=false`
- `APP_KEY=` (32-byte base64, `php artisan key:generate`)
- `APP_URL=https://...` (HTTPS)
- `TRUSTED_PROXIES=` — IP/CIDR **ingress** Apache/Cloudflare saja (bukan laptop lembaga). Gunakan `*` hanya jika app selalu hanya bisa diakses lewat satu proxy tepercaya.
- `SESSION_SECURE_COOKIE=true`
- `SESSION_SAME_SITE=lax`
- `SESSION_HTTP_ONLY=true`
- `API_KEY_PEPPER=` (panjang, acak, rahasia)
- `MFA_SUPER_ADMIN_REQUIRED=true` sebelum publik

## Keamanan app yang otomatis

- Security headers (CSP dasar, nosniff, frame deny, referrer) pada semua response
- HSTS aktif saat `production` **dan** request HTTPS (termasuk lewat `X-Forwarded-Proto` bila `TRUSTED_PROXIES` diisi)
- Tanpa `TRUSTED_PROXIES`, `X-Forwarded-Proto: https` diabaikan; HSTS tidak dikirim dan Laravel dapat menganggap request HTTPS tidak aman
- CORS fase 1: **server-to-server**; browser origin default deny (tidak ada whitelist IP/origin per lembaga)
- Log context redaction (password / API key / token / PII keys) aktif pada channel `single` dan `daily`. Default `LOG_CHANNEL=stack` dengan `LOG_STACK=single` tetap ter-redact karena stack meneruskan log ke processor channel anak `single`
- Jika `LOG_CHANNEL` / `LOG_STACK` dialihkan ke channel lain (misalnya `stderr`, `papertrail`, atau `syslog`), tambahkan tap redaction pada channel tersebut; tanpa tap, context log tidak akan ter-redact

## Jangan

- Jangan expose PostgreSQL ke publik
- Jangan commit `.env` / secret
- Jangan mengisi `TRUSTED_PROXIES` dengan IP laptop/PC lembaga
- Jangan mengharapkan SPA browser bisa panggil API tanpa CORS whitelist (fase 2)
