# AUDIT LENGKAP - Pusat Data

Tanggal audit: 18 Jul 2026  
Cakupan aktual: README.md, PLAN.md, SPEC.md, RULES.md, RENCANA.md, status git, dan struktur repository.  
Cakupan yang belum bisa diaudit: kode Laravel, migration, controller, policy, middleware, query, dependency, test, UI aktual, konfigurasi Apache/VPS, database hidup, dan deployment, karena belum ada source aplikasi di workspace.

## Verdict jujur

Repositori ini belum berisi aplikasi. Yang ada adalah dokumen perencanaan yang sudah cukup baik untuk memulai implementasi, tetapi belum bisa disebut "aplikasi data center yang aman" karena belum ada bukti kode, database, UI, test, konfigurasi server, atau deployment.

Status paling akurat saat ini: siap masuk tahap setup Laravel, belum siap untuk staging publik, belum siap produksi, dan belum bisa lulus audit teknis runtime.

## Bukti utama

- README menyebut repository sebagai "perencanaan & (nanti) implementasi" dan coding baru boleh dimulai.
- PLAN menyatakan Tahap B berikutnya adalah setup Laravel, PostgreSQL, migrasi, deploy, dan hardening.
- File aplikasi umum seperti composer.json, artisan, app/, routes/, database/, resources/, tests/, package.json tidak ada.
- Git branch main hanya berisi initial commit; working tree berisi dokumen yang belum sepenuhnya terlacak/tercommit.

## Skor kesiapan saat ini

| Area | Skor | Catatan |
|---|---:|---|
| Kesiapan produk | 6/10 | Scope fase 1 jelas, tetapi import, histori kelas, dan integrasi app masih terbatas. |
| UI/UX desain | 5/10 | Pola umum ada, belum cukup rinci untuk implementasi konsisten. |
| Backend desain | 6/10 | Entitas dan API inti ada, tetapi sync, API key, dan constraint tenant perlu dikunci lebih kuat. |
| Security desain | 6/10 | Arah keamanan benar, tetapi beberapa keputusan terlalu longgar untuk data center PII. |
| Implementasi | 0/10 | Belum ada kode aplikasi. |
| Testability | 2/10 | Checklist ada, test suite belum ada. |
| Operasional | 4/10 | Target VPS/Apache/Cloudflare ada, belum ada konfigurasi nyata, RPO/RTO, atau restore proof. |

## Temuan prioritas

### CRIT-01 - Aplikasi belum ada, audit runtime tidak mungkin

Severity: Kritis  
Area: Implementasi, backend, frontend, security  
Bukti: Struktur repo hanya berisi dokumen Markdown; PLAN masih menempatkan setup Laravel pada tahap berikutnya.  
Dampak: Tidak ada bukti bahwa auth, tenant isolation, API key, sync, CSRF, rate limit, header keamanan, query, dan UI benar-benar berjalan.  
Solusi:
- Scaffold Laravel sesuai stack terkunci.
- Tambahkan migration, model, policy, middleware API key, route web/api, Livewire pages, dan test.
- Setelah kode ada, lakukan audit kode sebelum test sesuai RULES B1/B2.

### CRIT-02 - Model API key per lembaga terlalu luas untuk data center

Severity: Kritis  
Area: Security, integrasi API  
Bukti: SPEC menetapkan satu API key aktif per lembaga dan API key memberi akses baca resource lembaga. RULES D5 menyatakan fase 1 server-to-server.  
Dampak: Jika satu app konsumen bocor key, semua resource lembaga yang diizinkan oleh key lembaga ikut terbuka. Tidak ada accountability per aplikasi, tidak ada scope per resource, dan rotasi memutus semua integrasi sekaligus.  
Solusi:
- Minimal sebelum produksi: ubah menjadi API key per aplikasi konsumen per lembaga.
- Simpan scope resource: contoh `guru:read`, `siswa:read`, `kelas:read`.
- Catat `consumer_name`, `last_used_at`, `last_used_ip`, `revoked_at`.
- Jika tetap satu key fase 1, batasi publikasi hanya untuk staging privat dan tulis risiko eksplisit di SPEC/RULES.

### CRIT-03 - Strategi hash API key belum cukup operasional

Severity: Kritis  
Area: Security, backend  
Bukti: RULES menyebut hash + prefix dan compare timing-safe, tetapi tidak menentukan algoritma lookup.  
Dampak: Jika memakai password hash biasa, server tidak bisa mencari row tanpa scan banyak lembaga. Jika memakai hash biasa tanpa pepper, hash bisa diserang bila DB bocor.  
Solusi:
- Format key: `dc_live_<prefix>_<secret>`.
- Simpan `api_key_prefix` unik dan `api_key_digest = HMAC-SHA256(secret, APP_KEY/pepper khusus)`.
- Lookup berdasarkan prefix, verifikasi digest dengan `hash_equals`.
- Jangan gunakan bcrypt/argon untuk lookup API key kecuali ada identifier terpisah.

### HIGH-01 - Sync delta masih rawan saat pagination

Severity: Tinggi  
Area: Backend, integrasi data  
Bukti: SPEC sync memakai `since`, `synced_at`, page/per_page, urutan `(updated_at ASC, id ASC)`.  
Dampak: Jika perubahan banyak dan client memakai `synced_at` sebelum semua page selesai, record bisa terlewat. Page number juga rawan berubah saat ada update baru di tengah proses sync.  
Solusi:
- Response sync wajib punya `watermark` tetap dari server pada awal request.
- Query: ambil perubahan `> since` dan `<= watermark`.
- Gunakan cursor pagination berbasis `(changed_at, id)`, bukan page number untuk sync.
- `changed_at = greatest(updated_at, deleted_at)` agar delete terurut benar.

### HIGH-02 - Constraint DB multi-tenant terlalu lemah

Severity: Tinggi  
Area: Backend, data integrity, security  
Bukti: RULES B5 menyebut FK antar tabel divalidasi di aplikasi + DB "bila memungkinkan".  
Dampak: Bug controller atau import internal bisa memasukkan `kelas_id` dari lembaga lain ke siswa lembaga berbeda. Ini melanggar isolasi tenant.  
Solusi:
- Di PostgreSQL, buat unique composite `(lembaga_id, id)` pada tabel referensi.
- Buat composite FK: `(lembaga_id, kelas_id)` referensi `kelas(lembaga_id, id)`, dan pola yang sama untuk `tahun_ajaran_id`, `wali_kelas_guru_id`.
- Tambahkan check constraint role user: `admin_lembaga` wajib punya `lembaga_id`; `super_admin` harus null atau aturan eksplisit.

### HIGH-03 - MFA Super Admin ditunda, ini tidak layak untuk produksi PII

Severity: Tinggi  
Area: Security, auth admin  
Bukti: RULES B4.2 dan SPEC fase berikutnya menaruh MFA Super Admin di fase 2.  
Dampak: Super Admin memiliki akses lintas lembaga dan PII. Password saja terlalu lemah untuk produksi data center.  
Solusi:
- Jadikan MFA Super Admin blocker sebelum produksi publik.
- Minimal TOTP untuk Super Admin; recovery code disimpan hashed.
- Admin Lembaga bisa fase 2, tetapi Super Admin jangan.

### HIGH-04 - Data PII terlalu luas untuk semua integrasi

Severity: Tinggi  
Area: Privacy, API design  
Bukti: SPEC resource siswa/guru/karyawan mencakup alamat, telepon, tanggal lahir, wali, email. API key lembaga membaca resource.  
Dampak: App absensi mungkin hanya butuh id, nama, kelas; tidak perlu alamat dan telepon wali. Overexposure PII menaikkan dampak kebocoran.  
Solusi:
- Tambah profil response/scope field per integrasi: `minimal`, `academic`, `contact`.
- Default API response minimal; field sensitif perlu scope eksplisit.
- Tombstone delete cukup kirim `id`, `deleted_at`, `changed_at`, bukan dump PII penuh.

### HIGH-05 - UI/UX masih terlalu konseptual untuk implementasi konsisten

Severity: Tinggi  
Area: UI/UX  
Bukti: SPEC UI berisi pola umum, tetapi belum mengunci kolom tabel, field form per layar, state error spesifik, dan workflow copy key.  
Dampak: Implementasi bisa inkonsisten, validasi sulit diuji, dan operator sekolah bisa bingung saat input data besar.  
Solusi:
- Tambah file UI_SPEC.md: layout, sidebar per role, tabel per entitas, filter/search, form field order, empty/error/loading state, modal destruktif.
- Tulis acceptance criteria per layar.
- Tambahkan seed/demo data untuk screenshot review.

### HIGH-06 - Backup belum punya RPO/RTO dan uji restore

Severity: Tinggi  
Area: Operasional, disaster recovery  
Bukti: SPEC/RULES menyebut dump harian dan uji restore berkala, tetapi tidak mengunci RPO/RTO, lokasi backup, enkripsi, dan prosedur restore.  
Dampak: Saat DB rusak atau ransomware, tim belum tahu batas kehilangan data dan waktu pemulihan.  
Solusi:
- Tetapkan RPO maksimal 24 jam untuk fase 1, RTO maksimal 4 jam atau angka yang disepakati.
- Backup terenkripsi, offsite, retention minimal 30 hari.
- Buat runbook restore dan wajib uji restore sebelum go-live.

### MED-01 - `is_active` dan `deleted_at` belum jelas semantik bisnisnya

Severity: Sedang  
Area: Data model  
Bukti: Entitas person punya `is_active`; semua master punya soft delete.  
Dampak: Operator bisa bingung kapan memakai nonaktif vs hapus. API konsumen juga bisa salah menafsirkan siswa nonaktif sebagai deleted.  
Solusi:
- Definisikan: `is_active=false` berarti masih ada tapi tidak aktif; `deleted_at` berarti dihapus dari master aktif dan harus disingkirkan dari salinan.
- Tambahkan filter API `active_only` atau aturan default jelas.

### MED-02 - Histori kelas siswa ditunda, tetapi risiko operasionalnya tinggi

Severity: Sedang  
Area: Product, data model  
Bukti: SPEC fase berikutnya menunda histori perpindahan kelas.  
Dampak: Setelah pergantian tahun ajaran, data "kelas aktif saat ini" bisa menimpa konteks historis. App nilai/absensi lama bisa kehilangan referensi kelas masa lalu.  
Solusi:
- Jika fase 1 tetap tanpa enrollment history, tulis batasan eksplisit: hanya current placement.
- Rancang migration masa depan sejak awal agar tidak mematahkan API.
- Pertimbangkan tabel `siswa_kelas`/`enrollments` lebih awal jika data historis penting.

### MED-03 - Error contract belum cukup lengkap untuk integrator

Severity: Sedang  
Area: API developer experience  
Bukti: SPEC hanya memberi bentuk umum `message` dan `code`.  
Dampak: App konsumen sulit membuat handling yang stabil untuk key invalid, lembaga nonaktif, rate limit, since expired, dan validation error.  
Solusi:
- Tambah daftar kode error resmi: `UNAUTHENTICATED`, `FORBIDDEN`, `LEMBAGA_INACTIVE`, `RATE_LIMITED`, `INVALID_SINCE`, `SINCE_TOO_OLD`, `VALIDATION_FAILED`.
- Sertakan `request_id`.
- Dokumentasikan retry behavior untuk 429/5xx.

### MED-04 - Rate limit perlu berlapis, bukan hanya per key atau per IP

Severity: Sedang  
Area: Security, availability  
Bukti: SPEC/RULES mengunci login 5/menit/IP dan API 120/menit/key.  
Dampak: Satu IP NAT sekolah bisa kena limit login; key bocor bisa disalahgunakan dari banyak IP; endpoint berat butuh limit berbeda.  
Solusi:
- Login throttle per email+IP dan per IP.
- API throttle per key, per IP, dan endpoint berat.
- Tambah limit response size dan timeout query.

### MED-05 - Audit log belum cukup untuk forensik

Severity: Sedang  
Area: Security, compliance  
Bukti: SPEC audit log hanya minimal field dan wajib log beberapa aksi.  
Dampak: Sulit investigasi insiden jika tidak ada result/status, user agent, request id, dan last known key prefix/consumer.  
Solusi:
- Tambah `event`, `result`, `request_id`, `user_agent`, `api_key_prefix`, `consumer_id`.
- Audit log append-only; tidak diedit dari UI.
- Metadata harus redacted dan dibatasi ukuran.

### MED-06 - Git hygiene belum siap sebagai repo implementasi

Severity: Sedang  
Area: Process, release  
Bukti: `git status` menunjukkan dokumen modified/added saat audit dilakukan.  
Dampak: Riwayat approval dan baseline desain mudah hilang atau tertimpa.  
Solusi:
- Commit baseline dokumen yang disetujui sebelum mulai coding.
- Tambah `.gitignore` Laravel sejak scaffold.
- Tambah branch protection atau minimal aturan PR/review jika tim lebih dari satu.

## Checklist go-live minimum

Tidak boleh produksi publik sebelum semua item ini hijau:

- Kode Laravel tersedia dan sudah direview.
- Migration PostgreSQL lengkap dengan UUID, FK, composite tenant constraints, index sync, partial unique.
- Middleware API key memakai prefix + HMAC digest + hash_equals.
- Policy/Gate membuktikan Admin Lembaga tidak bisa lintas lembaga.
- Super Admin MFA aktif.
- Test authz multi-tenant, sync create/update/delete, login throttle, API key invalid, lembaga nonaktif, secret tidak bocor.
- CORS default deny.
- HTTPS, security headers, Apache limit, firewall, fail2ban, Cloudflare, origin hardening aktif.
- Backup terenkripsi, offsite, dan restore sudah diuji.
- Dokumentasi integrator API dan error code selesai.
- UI admin punya state empty/loading/error, konfirmasi destruktif, dan copy-once API key.

## Rekomendasi urutan kerja

1. Commit baseline dokumen saat ini.
2. Tambah revisi SPEC/RULES untuk API key per-app/scope, sync watermark/cursor, MFA Super Admin, dan DB composite tenant constraints.
3. Scaffold Laravel + PostgreSQL.
4. Implement migration dan model constraints dulu.
5. Implement auth admin, MFA Super Admin, policy tenant.
6. Implement API key middleware dan endpoint read/sync.
7. Implement Livewire UI.
8. Review kode jujur, perbaiki, baru test.
9. Jalankan test suite dan audit security dasar.
10. Deploy staging privat, lalu hardening VPS/Cloudflare sebelum publik.
