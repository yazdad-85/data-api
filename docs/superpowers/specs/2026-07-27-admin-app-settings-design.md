# Design: Pengaturan aplikasi (Super Admin)

Status: **DISETUJUI — 27 Jul 2026**  
Tanggal: 27 Jul 2026  
Basis: SPEC §5.1, RULES B4.3 (audit tanpa secret), DEPLOYMENT §backup operator

## 1. Tujuan

Menyediakan halaman **Pengaturan** untuk Super Admin agar dapat:

- mengubah **branding UI** (nama aplikasi + logo);
- mengunduh **backup database PostgreSQL** (dump) dengan konfirmasi password.

Favicon dihasilkan otomatis dari logo — tidak ada upload terpisah.

## 2. Keputusan yang disepakati

| Topik | Keputusan |
|-------|-----------|
| Audience | Hanya **Super Admin** |
| Entry | Item sidebar **Pengaturan** → `/admin/pengaturan` |
| Branding | Nama aplikasi + upload logo (PNG/JPEG/WebP) |
| Favicon | Otomatis dari logo (generate saat upload) |
| Penyimpanan branding | Tabel `app_settings` + file di `storage/app/public/branding/` |
| `APP_NAME` di `.env` | **Tidak** diubah dari UI (branding UI terpisah dari env mail/session) |
| Backup | Unduh dump PostgreSQL saja; **tanpa** restore UI |
| Konfirmasi backup | Wajib **password saat ini** (`current_password`) |
| Jadwal backup otomatis | Di luar scope (operator/server) |

## 3. Out of scope

- Restore database dari UI
- Backup file unggahan / arsip ZIP gabungan
- Jadwal/cron backup otomatis dari UI
- Ubah parameter `.env` (rate limit, MFA, dll.)
- Pengaturan untuk Admin Lembaga
- Upload favicon terpisah

## 4. Routes & otorisasi

| Method | Path | Name | Aksi |
|--------|------|------|------|
| GET | `/admin/pengaturan` | `admin.settings.show` | Tampilkan halaman |
| PUT | `/admin/pengaturan/branding` | `admin.settings.branding` | Simpan nama + logo |
| POST | `/admin/pengaturan/backup` | `admin.settings.backup` | Unduh dump DB |

Middleware group: `auth`, `active`, `mfa` (sama admin lainnya).

Otorisasi tambahan: gate/policy **Super Admin only** (`manage-all-lembaga` atau gate baru `manage-app-settings`). Admin Lembaga → **403** pada semua route di atas; item sidebar tidak ditampilkan.

## 5. UI

### 5.1 Sidebar

Tambahkan item ketiga untuk Super Admin di `AdminMenu`:

1. Dashboard  
2. Lembaga  
3. **Pengaturan** (`admin.settings.show`)

### 5.2 Halaman `/admin/pengaturan`

Dua bagian (dua form terpisah):

**A. Branding**

- Nama aplikasi (input teks, wajib)
- Logo (file upload, opsional pada update — kosongkan = pertahankan logo lama)
- Checkbox/toggle opsional: **Hapus logo** (kembali ke teks nama saja)
- Preview logo saat ini (jika ada)
- Preview favicon (jika logo ada)
- Tombol **Simpan perubahan**

**B. Backup database**

- Teks penjelasan singkat: dump PostgreSQL; restore manual di server; file sensitif
- Password saat ini (input password)
- Tombol **Unduh backup**
- Tidak ada field restore/upload dump

Pola visual: layout admin Blade yang ada (`page-header`, `x-ui.input`, flash success/error) — selaras halaman Profil.

### 5.3 Dampak branding di UI

Setelah disimpan, branding dipakai di:

- Sidebar brand (`admin-sidebar__brand`) — teks dan/atau logo kecil
- `<title>` halaman admin & guest (login)
- Halaman login (judul + logo jika ada)
- `<link rel="icon">` di layout admin & guest

Default jika belum pernah diset: nama **Pusat Data**, tanpa logo (perilaku saat ini).

## 6. Data model

### 6.1 Tabel `app_settings`

Satu baris singleton (id tetap `1`) atau key-value; rekomendasi kolom tetap:

| Kolom | Tipe | Keterangan |
|-------|------|------------|
| `id` | smallint PK | Selalu `1` |
| `app_name` | string(150) | Nama tampilan UI |
| `logo_path` | string nullable | Path relatif di disk public branding |
| `favicon_path` | string nullable | Path favicon hasil generate |
| `updated_at` | timestamp | |

Seeder awal: `app_name = 'Pusat Data'`, path logo/favicon null.

### 6.2 File storage

- Direktori: `storage/app/public/branding/`
- Logo: `logo.{ext}` (ext dari upload yang valid)
- Favicon: `favicon.ico` (atau `favicon-32.png` jika ICO tidak praktis) — **selalu** di-regenerate dari logo saat upload/hapus
- Symlink `public/storage` harus ada (standar Laravel)
- Hapus file lama saat logo diganti/dihapus

### 6.3 Akses branding di view

- Service `AppSettingsService` dengan cache request singkat (mis. `remember` 60 detik atau static per-request)
- Helper/view composer: `app_branding()` mengembalikan `['name' => ..., 'logo_url' => ..., 'favicon_url' => ...]`
- Ganti hardcode `'Pusat Data'` di `layouts/admin.blade.php`, `layouts/guest.blade.php`, `partials/admin-sidebar.blade.php`, `auth/login.blade.php`

## 7. Perilaku server

### 7.1 Update branding

**Validasi (`UpdateBrandingRequest`):**

- `app_name` → `required|string|max:150`
- `logo` → `nullable|image|mimes:png,jpg,jpeg,webp|max:2048` (kilobyte)
- `remove_logo` → `nullable|boolean` (jika true, hapus logo & favicon)

**Proses:**

1. Simpan `app_name`
2. Jika `remove_logo`: hapus file + null path
3. Jika ada file `logo`: validasi ulang, simpan ke branding dir, generate favicon via GD (resize ke 32×32, output ICO atau PNG)
4. Clear cache branding
5. Audit: `settings.branding_update` / `success` — metadata: `fields: ['app_name']`, `logo_changed: bool` — **tanpa** binary

**Flash:** "Pengaturan branding berhasil disimpan."

### 7.2 Unduh backup

**Validasi (`DownloadBackupRequest`):**

- `current_password` → `required|current_password`

**Throttle:** middleware `throttle:admin-settings-backup` — ketat (mis. 3/menit per user, 10/menit per IP).

**Proses:**

1. Cek `config('database.default') === 'pgsql'` — jika tidak, abort dengan pesan: backup UI hanya tersedia untuk PostgreSQL
2. Jalankan `pg_dump` via `Symfony\Component\Process\Process` memakai host/port/database/user/password dari `config('database.connections.pgsql')` — **jangan** echo credential ke response/log user-facing
3. Stream stdout langsung ke response download (`Content-Disposition: attachment`)
4. Nama file: `pusat-data-YYYYMMDD-HHmmss.sql` (plain SQL; gzip opsional jika implementasi sederhana)
5. **Jangan** menulis dump ke disk aplikasi (no temp file di web root)
6. Audit: `settings.backup_download` / `success` atau `failure` — tanpa password, tanpa isi dump

**Error handling:**

- Password salah → validation error field `current_password`
- `pg_dump` tidak ditemukan / gagal → flash/error generik + log server; audit `failure`
- Timeout proses → pesan gagal; audit `failure`

**Flash sukses:** tidak perlu (response adalah file download).

### 7.3 Generate favicon

- Gunakan ekstensi GD bawaan PHP (tanpa dependency baru)
- Input: file logo yang sudah tervalidasi
- Output: favicon 32×32; prefer `.ico` jika GD mendukung, else `.png`
- Jika GD tidak tersedia: simpan logo saja; favicon fallback ke logo URL atau skip dengan log warning (deployment production harus punya GD)

## 8. Komponen

| Unit | Tanggung jawab |
|------|----------------|
| `AppSettings` model | ORM singleton |
| `AppSettingsService` | Baca/tulis settings + cache |
| `BrandingLogoProcessor` | Simpan logo, generate favicon, hapus file |
| `DatabaseBackupExporter` | Bangun perintah `pg_dump`, stream output |
| `SettingsController` | show, updateBranding, downloadBackup |
| `UpdateBrandingRequest` | Validasi branding |
| `DownloadBackupRequest` | Validasi password + pesan Indonesia |
| `AdminMenu` | Item Pengaturan Super Admin |
| View composer / helper | Injeksi branding ke layout |

## 9. Testing

Feature tests minimal (`AdminSettingsTest`):

1. Guest → redirect login
2. Admin Lembaga → 403 pada GET `/admin/pengaturan`
3. Super Admin → 200; sidebar menu **Pengaturan** terlihat
4. Update `app_name` → terlihat di response/layout (assertSee)
5. Upload logo valid (fake image) → `logo_path` & `favicon_path` terisi; file ada di storage fake
6. Upload SVG / file > 2MB → validation error
7. `remove_logo` → path null; file terhapus
8. Backup password salah → validation error; tidak download
9. Backup sukses (mock `DatabaseBackupExporter` atau Process) → response attachment
10. Backup saat driver sqlite → pesan tidak didukung (422/redirect error)
11. Audit `settings.branding_update` dan `settings.backup_download` tercatat; tidak ada plain password di metadata

Unit tests opsional: `BrandingLogoProcessor`, `DatabaseBackupExporter` command building.

## 10. Dokumentasi ikut

- `docs/SPEC.md` §5.1: tambah bullet Pengaturan (branding + unduh backup DB)
- `docs/DEPLOYMENT.md`: catat singkat bahwa restore dump tetap manual operator
- Opsional: catat di `docs/IMPLEMENTATION_TODO.md` sebagai penutupan UX gap Super Admin

## 11. Risiko & mitigasi

| Risiko | Mitigasi |
|--------|----------|
| Dump DB sensitif bocor | Re-auth password + throttle + audit; tidak simpan file di server |
| SVG/logo berbahaya | Tolak SVG; hanya raster; serve via storage public |
| `pg_dump` tidak ada di VPS | Dokumentasi DEPLOYMENT; error jelas di UI |
| Branding tidak ter-update karena cache | Clear cache service setelah update |
| GD tidak ada | Fallback favicon = logo; log warning |
| Backup besar timeout | Process timeout wajar; dokumentasi operator untuk DB besar |

## 12. Deploy catatan

Setelah merge:

```bash
php artisan migrate
php artisan storage:link   # jika belum ada
```

Pastikan `pg_dump` tersedia di PATH user PHP/Apache di VPS (aaPanel PostgreSQL).
