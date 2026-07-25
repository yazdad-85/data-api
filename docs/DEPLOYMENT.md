# Deployment (fase 1)

Runbook generik untuk operator VPS. Ringkasan env app: [PRODUCTION_NOTES.md](./PRODUCTION_NOTES.md). Basis: [SPEC.md](./SPEC.md) §6, [RULES.md](./RULES.md) B3–B4 / B8. Panduan konsumen API: [API_INTEGRATION.md](./API_INTEGRATION.md).

Langkah yang bergantung pada provider/OS ditandai **(pilihan operator)** — sesuaikan dengan lingkungan Anda.

## 1. Prasyarat

- Laravel 13.x (`laravel/framework ^13.8`) dengan PHP `^8.3`.
- Ekstensi PHP standar Laravel dan yang dibutuhkan PhpSpreadsheet harus tersedia pada runtime PHP web dan CLI, termasuk `pdo_pgsql`. `pdo_pgsql` tidak dideklarasikan oleh Composer, tetapi wajib untuk `DB_CONNECTION=pgsql`.
- PostgreSQL 16 tersedia hanya melalui localhost atau jaringan privat; jangan buka database ke internet publik.
- Apache sebagai web server, dengan mod_php atau php-fpm **(pilihan operator)**. Nginx tidak dipakai. Konfigurasi Apache harus meneruskan request ke front controller Laravel di `public/index.php`; aturan rewrite tersedia di `public/.htaccess`.
- Node.js diperlukan untuk membangun aset dengan `npm run build`. Versi Node.js adalah **(pilihan operator)**; Vite 8 umumnya membutuhkan Node.js 20 atau lebih baru.
- Document root virtual host harus mengarah ke direktori `public/`, bukan ke root repository.

## 2. Topologi

```text
Internet → Cloudflare (full proxy) → Apache/VPS → PHP (Laravel) → PostgreSQL (privat)
```

Cloudflare menjadi edge publik untuk DNS/proxy dan proteksi trafik. Cloudflare wajib aktif sebagai full proxy sebelum aplikasi dibuka ke publik; pengaturan detailnya dibahas pada §7.

Apache di VPS menyajikan `public/` dan meneruskan request aplikasi ke PHP/Laravel. Laravel menjalankan dashboard admin dan API dalam satu aplikasi, lalu mengakses PostgreSQL yang hanya dapat dijangkau dari localhost atau jaringan privat.

## 3. Instalasi aplikasi

Clone atau unggah release aplikasi ke server, kemudian atur document root Apache ke `public/`. Dari root release, jalankan:

```bash
composer install --no-dev --optimize-autoloader
cp .env.example .env   # isi nilai production (lihat §4)
php artisan key:generate
```

Direktori `storage/` dan `bootstrap/cache` harus writable oleh user web server. Cara mengatur owner dan permission bergantung pada OS dan model proses Apache/PHP, sehingga merupakan **(pilihan operator / OS)**.

## 4. Env production

Lengkapi `.env` dengan nilai production. Arti dan batasan keamanan setiap nilai diringkas di [PRODUCTION_NOTES.md](./PRODUCTION_NOTES.md); jangan commit `.env` atau secret ke git.

| Kelompok | Nilai yang wajib diperiksa |
|---|---|
| Aplikasi | `APP_ENV=production`, `APP_DEBUG=false`, `APP_KEY`, `APP_URL=https://…` |
| Proxy dan sesi | `TRUSTED_PROXIES`, `SESSION_SECURE_COOKIE=true`, `SESSION_SAME_SITE=lax`, `SESSION_HTTP_ONLY=true` |
| Keamanan | `API_KEY_PEPPER`, `MFA_SUPER_ADMIN_REQUIRED=true` |
| Database | `DB_CONNECTION=pgsql`, `DB_HOST`, `DB_PORT`, `DB_DATABASE`, `DB_USERNAME`, `DB_PASSWORD` |
| Batas API | `API_RATE_PER_MINUTE`, `API_IP_RATE_PER_MINUTE`, `API_SYNC_MAX_SINCE_DAYS` |

`TRUSTED_PROXIES` hanya memuat IP/CIDR ingress Apache/Cloudflare, bukan IP laptop atau PC lembaga. Nilai `API_KEY_PEPPER` harus berupa rahasia acak yang panjang dan sudah tersedia sebelum API client dibuat.

Periksa juga `LOG_CHANNEL` dan `LOG_STACK`. Redaction context log dipasang pada channel `single` dan `daily`; bila log diarahkan ke channel lain, operator perlu memastikan channel tersebut juga memiliki redaction yang setara.

## 5. Migrasi dan Super Admin

Setelah database production tersedia dan `.env` sudah lengkap, jalankan:

```bash
php artisan migrate --force
php artisan install:super-admin
```

**JANGAN** jalankan `php artisan db:seed` di production: `DatabaseSeeder` membuat akun `Test User`.

Command `install:super-admin` hanya membuat akun apabila belum ada user dengan role `super_admin`; jika sudah ada, command gagal dan tidak menimpa akun tersebut. Command meminta nama, email, dan password bila opsi tidak diberikan; password minimal 12 karakter. Saat berhasil, command membuat MFA aktif, lalu mencetak MFA secret dan recovery codes satu kali. Simpan semua nilai tersebut di tempat aman sebelum menutup terminal.

Pastikan `API_KEY_PEPPER` sudah diisi sebelum membuat API client. Terapkan migrasi production secara terkendali melalui file migration yang ditinjau; jangan mengedit skema database manual tanpa jejak migrasi.

## 6. Aset front-end dan cache

Setelah dependency aplikasi tersedia, bangun aset dan cache Laravel:

```bash
npm ci        # atau npm install — pilihan operator
npm run build
php artisan config:cache
php artisan route:cache
php artisan view:cache
```

Jalankan ulang `php artisan config:cache` setiap kali `.env` berubah. Pada fase 1, `queue:work` dan `schedule:run` tidak wajib karena kode tidak memiliki job atau schedule aplikasi. `php artisan storage:link` bersifat opsional; upload publik berada di luar scope fase 1.

## 7. TLS, Cloudflare, dan trusted proxies

_(diisi pada bagian berikutnya)_

## 8. Firewall dan isolasi database

_(diisi pada bagian berikutnya)_

## 9. Backup, RPO/RTO, restore test

_(diisi pada bagian berikutnya)_

## 10. Verifikasi post-deploy

_(diisi pada bagian berikutnya)_

## 11. Incident response

_(diisi pada bagian berikutnya)_

## 12. Checklist go-live

_(diisi pada bagian berikutnya)_
