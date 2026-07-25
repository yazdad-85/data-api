# Design — Milestone 11: Dokumentasi Integrator dan Operasional

Status: **DISETUJUI — 25 Jul 2026**  
Tanggal: 2026-07-25  
Basis: [SPEC.md](../../SPEC.md) §2.2, §4, §6; [RULES.md](../../RULES.md) A8, A13, B4, B7, B8; [IMPLEMENTATION_TODO.md](../../IMPLEMENTATION_TODO.md) Milestone 11; [PRODUCTION_NOTES.md](../../PRODUCTION_NOTES.md) (M10); M9 [2026-07-24-milestone-9-api-sync-delta-design.md](./2026-07-24-milestone-9-api-sync-delta-design.md); M10 [2026-07-25-milestone-10-app-hardening-design.md](./2026-07-25-milestone-10-app-hardening-design.md)

## 1. Tujuan

Menyediakan dua dokumen supaya fase 1 bisa dipakai pihak lain tanpa membaca kode:

1. `docs/API_INTEGRATION.md` — panduan **mandiri lengkap** untuk developer aplikasi konsumen lembaga.
2. `docs/DEPLOYMENT.md` — **runbook generik** untuk operator yang men-deploy ke VPS.

M11 tidak menambah fitur kode. Perubahan kode hanya diizinkan bila dokumentasi menemukan kontrak yang salah (lihat §8).

## 2. Keputusan yang disepakati

| Topik | Keputusan |
|-------|-----------|
| Struktur | **Dua dokumen fokus** (bukan satu handbook, bukan generator OpenAPI) |
| Kedalaman deploy | **Runbook generik** — bisa diikuti di VPS mana pun; tidak mengunci provider/hostname |
| Kedalaman API | **Mandiri lengkap** — auth, semua endpoint, contoh request/response, sync, error, retry |
| Bahasa | **Bahasa Indonesia**; contoh HTTP/JSON tetap Inggris teknis |
| Sumber kebenaran | **Perilaku kode + test yang berjalan**; SPEC/RULES disinkronkan bila tertinggal |
| Hubungan dokumen | `PRODUCTION_NOTES.md` tetap ringkas app; `DEPLOYMENT.md` detail infra; saling menunjuk, tidak menyalin SPEC penuh |

## 3. Di luar scope M11

- OpenAPI / Swagger / koleksi Postman
- CORS whitelist origin browser per lembaga (fase 2)
- Endpoint write / mutasi via API key
- Konfigurasi Apache/Cloudflare/backup spesifik provider (ditulis sebagai placeholder)
- Otomasi deploy (CI/CD, zero-downtime, container)
- Perubahan perilaku API selain koreksi kontrak di §8
- UAT dan tag release (Milestone 12)

## 4. `docs/API_INTEGRATION.md`

### 4.1 Struktur

1. Ringkasan + model akses server-to-server
2. Quick start (`/health` → `/me` → satu resource)
3. Autentikasi dan format API key
4. Daftar endpoint dan envelope response
5. Resource, scope, dan field profile
6. Tarik penuh + pagination
7. Sync delta (`since`, `watermark`, `cursor`, tombstone)
8. Error code, `request_id`, rate limit
9. Strategi retry 429/5xx
10. Checklist integrasi sebelum production

### 4.2 Fakta yang wajib akurat

| Topik | Nilai (dari kode) |
|-------|-------------------|
| Base path | `/api/v1` |
| Endpoint | `GET /health` (tanpa auth), `GET /me`, `GET /{resource}`, `GET /{resource}/sync` |
| Resource | `tahun-ajaran`, `guru`, `kelas`, `siswa`, `karyawan` |
| Header auth | `X-API-Key` (utama); `Authorization: Bearer` (alternatif); bila keduanya ada → `X-API-Key` menang |
| Format key | `dc_live_<prefix>_<secret>`; plain hanya tampil sekali saat buat/rotate |
| Scope | `tahun_ajaran:read`, `guru:read`, `kelas:read`, `siswa:read`, `karyawan:read` |
| Profil field | `minimal ⊂ academic ⊂ contact` |
| `per_page` | default 100, **clamp** 1..200 (bukan 422) |
| Urutan list | `nama ASC, id ASC`; **`tahun-ajaran` = `nama DESC, id ASC`** |
| Batas `since` | default 90 hari (`API_SYNC_MAX_SINCE_DAYS`) |
| Rate limit | default 120/menit/API key + 240/menit/IP; 429 membawa `Retry-After` |
| Header rate lain | **tidak ada** `X-RateLimit-*` |
| Method non-GET | 405 |

### 4.3 Envelope

- `/me`: objek flat (`lembaga_id`, `kode`, `nama`, `is_active`, `client_id`, `client_name`, `scopes`, `field_profile`)
- List: `{resource, lembaga_id, synced_at, data[], meta{page, per_page, total}}`
- Sync: `{resource, lembaga_id, since, watermark, synced_at, changes[], change_count, next_cursor}`
- Tombstone (soft delete): **hanya** `{id, deleted_at, changed_at}`
- Error bisnis: `{message, code, request_id}`

### 4.4 Alur sync yang didokumentasikan

1. Halaman pertama: kirim `since` saja → server menetapkan `watermark = now(UTC)`
2. Halaman lanjutan: kirim `since` **sama** + `watermark` **sama** + `cursor` dari response sebelumnya
3. Simpan watermark/`synced_at` **hanya** saat `next_cursor === null`
4. `watermark` tanpa `cursor` → `INVALID_CURSOR`
5. `SINCE_TOO_OLD` → fallback tarik penuh
6. Tidak ada endpoint sync terpisah untuk penempatan siswa; perubahan mengalir via `siswa.updated_at`

### 4.5 Contoh

- Semua contoh memakai host placeholder (mis. `https://data.example.id`), UUID dummy, dan API key contoh yang jelas bukan secret nyata.
- Minimal ada contoh `curl` + response JSON untuk: `/health`, `/me`, satu resource list, sync halaman 1, sync halaman lanjutan, satu error 401, satu error 429.
- Contoh harus konsisten dengan field katalog dan format tanggal: date-only `Y-m-d`; timestamp `Y-m-d\TH:i:s\Z`.

### 4.6 Retry

| Kondisi | Panduan |
|---------|---------|
| 429 | Tunggu `Retry-After`, ulangi request identik |
| 5xx / timeout / jaringan | Backoff eksponensial + jitter; batas percobaan diserahkan integrator |
| Retry sync | Aman: ulangi dengan `since`/`watermark`/`cursor` yang sama |
| Jangan | Menaikkan watermark sebelum `next_cursor === null` |
| 4xx bisnis (401/403/400) | Jangan retry membabi buta; perbaiki key/scope/parameter |

## 5. `docs/DEPLOYMENT.md`

### 5.1 Struktur

1. Prasyarat & versi
2. Topologi (Cloudflare → Apache → PHP → PostgreSQL)
3. Instalasi aplikasi
4. Env production
5. Migrasi & bootstrap Super Admin
6. Aset front-end & cache
7. TLS, Cloudflare, `TRUSTED_PROXIES`
8. Firewall & isolasi database
9. Backup, retensi, RPO/RTO, restore test
10. Verifikasi post-deploy
11. Incident response
12. Checklist go-live

### 5.2 Fakta yang wajib akurat

| Topik | Nilai |
|-------|-------|
| Framework | Laravel **13.x** (`composer.json` `^13.8`) |
| PHP | minimum `^8.3`; disarankan sama dengan versi yang diuji lokal/CI |
| Ekstensi | termasuk `pdo_pgsql` (tidak dideklarasikan composer, tetapi wajib untuk `DB_CONNECTION=pgsql`) |
| Database | PostgreSQL 16, hanya jaringan privat/localhost |
| Web server | Apache (mod_php atau php-fpm — pilihan operator); **Nginx tidak dipakai** |
| Document root | `public/` |
| Build aset | `npm run build` (Vite) |
| Bootstrap admin | `php artisan install:super-admin` — **bukan** `db:seed` (seeder membuat test user) |
| Queue/scheduler | **tidak wajib** fase 1 (tidak ada job/schedule di kode) |
| `storage:link` | opsional; upload publik di luar scope fase 1 |
| Health | `GET /api/v1/health` = kontrak integrator; `/up` milik framework |

### 5.3 Env production wajib

Selaras `PRODUCTION_NOTES.md`: `APP_ENV=production`, `APP_DEBUG=false`, `APP_KEY`, `APP_URL=https://…`, `TRUSTED_PROXIES`, `SESSION_SECURE_COOKIE=true`, `SESSION_SAME_SITE=lax`, `SESSION_HTTP_ONLY=true`, `API_KEY_PEPPER`, `MFA_SUPER_ADMIN_REQUIRED=true`.

Tambahan yang perlu disebut: `DB_*`, `API_RATE_PER_MINUTE`, `API_IP_RATE_PER_MINUTE`, `API_SYNC_MAX_SINCE_DAYS`, `LOG_CHANNEL`/`LOG_STACK` (redaction hanya pada `single`/`daily`).

### 5.4 Backup

| Aturan | Nilai |
|--------|-------|
| Dump DB | terjadwal |
| Enkripsi | wajib |
| Lokasi | offsite, tidak publik |
| Retensi | ≥ 30 hari (fase 1) |
| RPO | ≤ 24 jam |
| RTO | target ≤ 4 jam |
| Restore test | sebelum go-live + berkala |

Tool enkripsi, provider offsite, cron, flag `pg_dump`/`pg_restore` = **placeholder operator**.

### 5.5 Incident response

Empat langkah dari RULES B4.4: rotate API key → nonaktifkan akun → blok IP → restore backup. Ditulis merujuk kemampuan admin UI yang sudah ada; perintah firewall/Cloudflare = placeholder.

### 5.6 Placeholder yang tidak boleh dikarang

Vhost Apache & path absolut, mod_php vs php-fpm, modul rate limit Apache, zona/WAF/Authenticated Origin Pull Cloudflare, CIDR `TRUSTED_PROXIES` nyata, perintah ACME/Let's Encrypt, nama paket OS, perintah backup/restore, aturan firewall/fail2ban, permission/owner filesystem, pipeline deploy, versi Node persis, hostname staging/production.

## 6. Prinsip anti-duplikasi

- `API_INTEGRATION.md` boleh mengulang fakta kontrak API (itu tujuannya), tetapi **tidak** menyalin skema DB penuh dari SPEC §3.
- `DEPLOYMENT.md` **tidak** mengulang isi `PRODUCTION_NOTES.md` secara utuh; cukup rujuk + tambahkan langkah infra.
- Keduanya menautkan SPEC/RULES sebagai dokumen normatif.

## 7. Testing / verifikasi M11

M11 dokumentasi, jadi verifikasi berupa pemeriksaan kebenaran, bukan test baru:

1. Setiap fakta API di dokumen dicocokkan ke kode/test (endpoint, envelope, kode error, parameter, default).
2. Contoh `curl` dan JSON konsisten dengan katalog field dan format tanggal.
3. Tidak ada secret nyata di contoh.
4. Tidak ada perintah/nama provider yang dikarang di `DEPLOYMENT.md`; semua yang belum pasti berbentuk placeholder.
5. `DEPLOYMENT.md` tidak mengarahkan operator membuka PostgreSQL ke publik (review wajib TODO M11).
6. Semua tautan relatif antar dokumen valid.
7. Full test suite tetap hijau bila ada perubahan kode koreksi (§8).

## 8. Koreksi kontrak yang disinkronkan

Dokumentasi mengikuti kode; SPEC/RULES diperbarui pada titik berikut agar tidak ada dua kontrak berbeda:

| # | Isu | Tindakan |
|---|-----|----------|
| 1 | RULES A13.2 menyebut default response = `minimal`, tetapi runtime memakai profil milik client | Perjelas: default = profil client; `minimal` hanya default DB untuk client baru |
| 2 | `fields` didukung di endpoint sync, tidak tercantum di SPEC §4.4 | Tambahkan ke SPEC |
| 3 | `active_only` tidak berpengaruh pada `kelas` | Dokumentasikan per resource |
| 4 | Limit 120/menit dan 90 hari tertulis seolah konstan | Nyatakan sebagai default yang dapat dikonfigurasi |
| 5 | SPEC §4.5 mencantumkan `VALIDATION_FAILED` 422 untuk dashboard/admin; API tidak pernah mengirimnya | Bukan kontradiksi, tetapi integrator perlu tahu 422 di API berbentuk Laravel default (`message` + `errors`), bukan envelope bisnis |
| 6 | Slug tidak dikenal → 404 framework, bukan `NOT_FOUND` envelope | Dokumentasikan apa adanya; `NOT_FOUND` tetap konstanta internal |
| 7 | Pesan contoh error di SPEC beda dengan pesan kode | Selaraskan ke pesan kode |
| 8 | Watermark tidak disimpan server (bukan sesi ber-state) | Jelaskan integrator wajib mengirim ulang watermark apa adanya |
| 9 | RULES B4.2 menyebut session `same_site` "ketat" | Rujuk keputusan M10: `lax` |

Catatan self-review: RULES B3.1 yang berbunyi "Laravel 15 Jul 2026" adalah **tanggal keputusan** (15 Jul 2026), bukan versi Laravel 15. Tidak ada kontradiksi versi; `DEPLOYMENT.md` cukup menyebut Laravel 13.x sesuai `composer.json`.

Perubahan kode hanya dilakukan jika koreksi menuntutnya; default posisi M11 = **hanya dokumentasi**.

## 9. Urutan implementasi (ringkas)

1. `docs/API_INTEGRATION.md` bagian auth + endpoint + envelope + resource/scope/profil.
2. `docs/API_INTEGRATION.md` bagian tarik penuh + sync + error + retry + checklist.
3. `docs/DEPLOYMENT.md` bagian prasyarat → instalasi → env → migrasi/bootstrap → aset.
4. `docs/DEPLOYMENT.md` bagian TLS/Cloudflare/proxy → firewall/DB → backup/RPO/RTO → verifikasi → incident → checklist go-live.
5. Sinkronisasi SPEC/RULES sesuai §8 + pointer dari `PRODUCTION_NOTES.md`.
6. Update `IMPLEMENTATION_TODO.md` Milestone 11.

## 10. Risiko

- **Dokumen cepat basi** bila kontrak API berubah → mitigasi: setiap fakta dirujuk ke perilaku kode, dan M12 memverifikasi ulang saat UAT.
- **Placeholder disalahartikan sebagai perintah siap pakai** → mitigasi: tandai eksplisit sebagai keputusan operator.
- **Duplikasi SPEC** membuat dua sumber kebenaran → mitigasi: prinsip §6 + tautan normatif.
- **Contoh JSON tidak akurat** menyesatkan integrator → mitigasi: verifikasi §7 poin 1–2 terhadap katalog field dan test.
- **Runbook generik terasa kurang konkret** saat go-live → mitigasi: struktur checklist agar operator bisa mengisi nilai lingkungannya sendiri.
