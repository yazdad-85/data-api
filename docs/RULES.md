# RULES — Pusat Data

Status: **DISETUJUI — 18 Jul 2026**; revisi penguatan dari audit lengkap 18 Jul 2026 sudah dimasukkan.  
Isi: aturan bisnis yang wajib ditegakkan + aturan kerja implementasi nanti.

---

## A. Aturan bisnis (product rules)

### A1. Sumber kebenaran

1. Pusat Data adalah **satu-satunya sumber kebenaran** untuk master data fase 1.
2. Aplikasi lain hanya menyimpan **salinan** hasil tarik/sinkron.
3. Jika salinan lokal berbeda dengan Pusat Data, yang benar adalah **Pusat Data**.

### A2. Siapa boleh mengubah master

1. Hanya **Super Admin** dan **Admin Lembaga** melalui dashboard Pusat Data.
2. Aplikasi konsumen **dilarang** mengubah master lewat API (API key = read-only).
3. Tidak ada “simpan perubahan guru dari app absensi ke Pusat Data” di fase 1.

### A3. Batas lembaga (multi-tenant)

1. Admin Lembaga hanya melihat/mengubah data dengan `lembaga_id` miliknya.
2. API key hanya mengembalikan data lembaga pemilik key dan sesuai scope API client.
3. Super Admin dapat mengelola semua lembaga.
4. Mencoba akses lintas lembaga harus ditolak (403), bukan dikosongkan diam-diam tanpa alasan jelas di log.

### A4. Identitas

1. Setiap record master punya **UUID pusat** yang stabil.
2. Aplikasi konsumen **wajib** menyimpan UUID pusat saat menyalin data.
3. Jangan memakai auto-increment lokal app sebagai pengganti ID pusat untuk referensi lintas sistem.

### A5. Hapus data

1. Hapus master memakai **soft delete** (`deleted_at`).
2. Soft delete harus ikut muncul di sync delta agar app konsumen bisa membersihkan salinan.
3. Hard delete fisik tidak dipakai di fase 1 (kecuali maintenance DB oleh Super Admin opsional, di luar API).
4. `is_active=false` berarti record masih ada tetapi tidak aktif untuk operasional; `deleted_at` berarti record dihapus dari master aktif.

### A6. Tahun ajaran aktif

1. Per lembaga, hanya **satu** tahun ajaran bertanda `is_aktif = true` pada satu waktu.
2. Mengaktifkan tahun ajaran baru harus menonaktifkan yang sebelumnya (dalam satu transaksi).

### A7. Relasi kelas & siswa

1. `kelas.tahun_ajaran_id` wajib valid milik lembaga yang sama.
2. Jika siswa punya `kelas_id`, maka `kelas` tersebut harus lembaga yang sama.
3. Menghapus (soft) kelas yang masih dipakai siswa: **diblok** dengan pesan jelas.
4. (Draft → **dikunci**) Siswa boleh dibuat tanpa kelas; UI wajib tampilkan peringatan/badge "belum ada kelas".
5. Jika siswa punya `kelas_id` dan `tahun_ajaran_id`, maka `kelas.tahun_ajaran_id` harus sama dengan `siswa.tahun_ajaran_id`.

### A8. API key

1. Plain API key hanya ditampilkan **sekali** saat generate/rotate (aturan UI; **bukan** kolom database).
2. API key dibuat per **API client aplikasi konsumen**, bukan satu key tunggal untuk seluruh lembaga.
3. Satu lembaga boleh punya beberapa API client aktif, masing-masing punya nama, scope resource, profil field, prefix, status, dan metadata pemakaian terakhir.
4. Format key: `dc_live_<prefix>_<secret>`.
5. Yang disimpan di DB: `api_key_prefix` unik + `api_key_digest` HMAC-SHA256 dengan pepper/env; plain key tidak disimpan.
6. Lookup memakai prefix, verifikasi digest memakai `hash_equals` / compare timing-safe.
7. Scope wajib dicek di server; contoh `siswa:read`, `guru:read`, `kelas:read`.
8. Profil field wajib meminimalkan PII; default `minimal`, `contact` hanya untuk client yang benar-benar perlu.
9. Rotate key mematikan key lama **segera**; UI wajib modal peringatan dampak ke app konsumen.
10. Semua request API tanpa key / key salah → 401 (pesan generik, jangan bocorkan detail key).
11. Semua request write master dengan API key → 403/405.
12. Jangan log header `X-API-Key` / `Authorization` di access log aplikasi.
13. Admin Lembaga hanya boleh melihat nama client, prefix, scope, dan status; tidak boleh rotate/revoke.

### A9. Sinkron delta

1. Parameter `since` wajib untuk endpoint sync.
2. Perubahan dihitung memakai `changed_at = greatest(updated_at, deleted_at)`.
3. Server menetapkan `watermark` pada awal sync; semua cursor dalam satu sesi mengambil `changed_at > since` dan `changed_at <= watermark`.
4. Jika app bingung/korup, boleh fallback ke **tarik penuh**.
5. `since` wajib valid ISO-8601 UTC; invalid → 400.
6. `since` di masa depan → 400.
7. Query sync dibatasi umur maksimum `since` (default: 90 hari); lebih lama → arahkan tarik penuh.
8. Urutan delta: `(changed_at ASC, id ASC)` sebagai tie-breaker bila timestamp sama.
9. Paginasi sync memakai cursor, bukan page number.
10. App baru boleh menyimpan `watermark`/`synced_at` sebagai sync terakhir setelah semua cursor selesai.
11. Tombstone delete tidak boleh mengirim dump PII penuh; cukup field minimum untuk membersihkan salinan.

### A10. Tombol di aplikasi konsumen

1. **Tarik** = full snapshot resource yang dibutuhkan.
2. **Sinkron** = delta sejak `watermark`/`synced_at` terakhir.
3. App tidak mengubah master di Pusat Data saat tarik/sinkron.

### A11. Lembaga & akun nonaktif

1. Lembaga `is_active = false` → semua request API client/key lembaga itu → **403**.
2. Admin Lembaga lembaga nonaktif tidak bisa login.
3. Super Admin tetap bisa mengaktifkan kembali lembaga.

### A12. Bahasa & aksesibilitas admin

1. UI dashboard admin fase 1: **Bahasa Indonesia** (label, validasi, konfirmasi).
2. Pesan error login generik: "Email atau password salah" (hindari oracle email).

### A13. Minimisasi PII

1. API konsumen hanya menerima field yang sesuai kebutuhan aplikasi dan scope client.
2. Default response API memakai profil `minimal`.
3. Field kontak, alamat, tanggal lahir, dan data wali hanya keluar melalui profil `contact` yang disetujui Super Admin.
4. Audit log dan error response tidak boleh memuat PII penuh.

---

## B. Aturan implementasi (engineering rules)

Aturan ini berlaku **setelah** dokumen disetujui dan coding dimulai. Bukan undangan untuk coding sekarang.

### B1. Proses kerja wajib (urutan tidak boleh dibalik)

1. Jangan mulai kode sampai PLAN, SPEC, RULES ditandai **DISETUJUI**.
2. Perubahan requirement setelah approve → update dulu SPEC/RULES, baru ubah kode.
3. Urutan setelah menulis kode:

```
Tulis / ubah kode
        ↓
Review kode (wajib)
        ↓
Jika ada kesalahan / risiko → perbaiki langsung
        ↓
Review ulang sampai bersih
        ↓
Baru dilakukan tes
        ↓
Jika tes lulus: update IMPLEMENTATION_TODO.md
        ↓
Commit dengan pesan jelas
        ↓
Push ke remote
```

4. **Dilarang** langsung tes tanpa review kode.
5. **Dilarang** menunda perbaikan temuan review “nanti saja”; temuan harus diperbaiki sebelum tes.
6. Checklist di `docs/IMPLEMENTATION_TODO.md` hanya boleh dicentang setelah review bersih dan test terkait lulus.
7. Commit message jelas; rahasia (`.env`, API key plain) tidak masuk git.
8. Setelah commit selesai, push ke remote agar progress tersimpan di GitHub.

### B2. Aturan review kode

1. Review harus **jelas**: sebutkan file, lokasi, masalah, dampak, dan usulan perbaikan.
2. Review harus **jujur**: tidak menutup-nutupi bug, aroma keamanan, atau pintasan berbahaya sekadar agar cepat selesai.
3. Kategori temuan minimal: correctness, keamanan, otorisasi multi-tenant, performa yang berbahaya, kerahasiaan data.
4. Temuan **wajib diperbaiki** sebelum masuk tahap tes.
5. Setelah perbaikan, catat singkat apa yang diubah (agar jejak review tetap ada).
6. Yang mereview tidak boleh mengabaikan isu keamanan “karena masih fase 1” — Pusat Data = standar keamanan tinggi sejak awal.

### B3. Stack & struktur

1. Stack fase 1: **Laravel + PostgreSQL + Apache** di VPS — **DISETUJUI** (Laravel 15 Jul 2026; web server dikoreksi ke **Apache**).
2. Satu aplikasi Laravel untuk dashboard admin + API konsumen.
3. Pemisahan jelas: route/web admin vs route/api.
4. **Nginx tidak dipakai.**

### B4. Keamanan (wajib — standar data center)

Karena ini **Pusat Data**, keamanan harus dijaga dari **semua sisi**. Keamanan bukan fitur tambahan; ini syarat go-live.

#### B4.1 Transport & infrastruktur

1. HTTPS wajib di produksi (TLS); HTTP diarahkan ke HTTPS.
2. Header keamanan aktif (minimal: HSTS, X-Content-Type-Options, X-Frame-Options/CSP dasar, Referrer-Policy).
3. Firewall VPS: hanya buka port yang diperlukan (80/443; SSH terbatas).
4. PostgreSQL tidak boleh expose ke publik; hanya localhost / private network.
5. Update OS & paket keamanan secara berkala.
6. Proteksi **DDoS / abuse** berlapis:
   - **Cloudflare** di depan VPS (DNS/proxy) — pemilik akan menambahkan; wajib sebelum produksi publik
   - Rate limiting aplikasi (Laravel) pada login & API
   - Limit request di **Apache** (atau modul setara)
   - Firewall VPS + fail2ban (SSH & abuse lokal)
7. Origin VPS sebaiknya hanya menerima traffic dari Cloudflare (batasi IP / Authenticated Origin Pull bila memungkinkan) agar penyerang tidak bypass Cloudflare.
8. Backup DB terjadwal + uji restore berkala; backup tidak boleh diakses publik.
9. Backup wajib terenkripsi, disimpan offsite, retention minimal 30 hari untuk fase 1.
10. RPO fase 1 maksimal 24 jam; target RTO maksimal 4 jam kecuali diputuskan berbeda sebelum go-live.

#### B4.2 Autentikasi & sesi admin

1. Password admin di-hash (algoritma default Laravel yang kuat).
2. Kebijakan password: minimal **12 karakter**; disarankan campuran huruf/angka.
3. Proteksi brute-force login (throttle / lockout): **5 percobaan / menit / email+IP** dan limit tambahan per IP.
4. Session aman: `httpOnly`, `secure`, `same_site` ketat di produksi.
5. Logout & invalidate session saat nonaktifkan user.
6. Prinsip least privilege: Super Admin vs Admin Lembaga sesuai ROLE.
7. MFA/TOTP Super Admin **wajib sebelum produksi publik**; recovery code disimpan hashed.

#### B4.3 API & data

1. API key disimpan sebagai prefix + HMAC digest; plain hanya sekali saat generate/rotate.
2. Authorization dicek di **server** (policy/scope `lembaga_id`), bukan hanya UI.
3. API key = **read-only**; write master via API key ditolak.
4. Validasi input ketat (mass assignment guarded, validasi request).
5. Lindungi dari OWASP umum: XSS, CSRF (web admin), SQL injection (Eloquent/query binding), open redirect.
6. Jangan log secrets (password, API key plain, token).
7. Error produksi tidak membocorkan stack trace ke klien.
8. Paginasi & batasan ukuran response untuk mencegah abuse resource (`per_page` max 200).
9. CORS fase 1: asumsikan integrasi **server-to-server**; default **deny** origin browser. Whitelist origin per lembaga → fase 2.
10. Field sensitif (hash password, `mfa_secret`, `recovery_codes_hash`, `api_key_digest`) tidak boleh muncul di response API/JSON admin.
11. Akses/view data PII siswa/guru oleh admin dicatat di audit log (siapa, kapan, entitas — tanpa dump data penuh).
12. Response API wajib mengikuti scope resource dan profil field API client.

#### B4.4 Operasional keamanan

1. Secret hanya di env / secret manager VPS; tidak di git.
2. Audit log tindakan kritis (buat lembaga, buat/rotate/revoke API key, nonaktifkan admin, hapus massal, akses lintas lembaga) — minimal fase 1 untuk aksi API key & user admin.
3. Pantau log akses anormal (spike 401/403/429).
4. Rencana respons insiden singkat: rotate key, nonaktifkan akun, blok IP, restore backup.
5. Audit log harus append-only; metadata wajib redacted dan dibatasi ukuran.

### B5. Data & migrasi

1. Semua PK master: UUID.
2. Semua tabel master punya `created_at`, `updated_at`, soft delete.
3. Index untuk sync: `(lembaga_id, updated_at, id)`, `(lembaga_id, deleted_at, id)`, atau index `changed_at` setara.
4. Foreign key antar tabel tenant **wajib** dijaga di aplikasi dan DB.
5. Tabel tenant wajib punya unique composite `(lembaga_id, id)`.
6. Relasi tenant wajib memakai composite FK, contoh `(lembaga_id, kelas_id)` → `kelas(lembaga_id, id)`.
7. Unique NIS/NISN partial tetap per lembaga dan hanya untuk nilai non-null.
8. Constraint role user wajib memastikan `admin_lembaga` punya `lembaga_id`.

### B6. API

1. JSON UTF-8; timestamp ISO-8601 UTC.
2. Paginasi default untuk tarik penuh.
3. Error body konsisten (`message`, `code`, `request_id`).
4. Versioning path `/api/v1`.
5. Rate limit API konsumen: **120 request / menit / API key** + limit tambahan per IP dan endpoint berat; login admin: lihat B4.2.
6. Health endpoint tidak boleh mengekspos versi stack atau info internal.
7. Kode error resmi fase 1 minimal: `UNAUTHENTICATED`, `FORBIDDEN`, `LEMBAGA_INACTIVE`, `API_CLIENT_INACTIVE`, `RATE_LIMITED`, `INVALID_SINCE`, `SINCE_TOO_OLD`, `INVALID_CURSOR`, `VALIDATION_FAILED`.
8. Sync wajib memakai watermark + cursor; page number tidak dipakai untuk sync delta.

### B7. Testing (hanya setelah review bersih)

1. Tes baru dijalankan setelah review kode selesai dan temuan diperbaiki.
2. Minimum sebelum go-live:
   - Admin lembaga A tidak bisa baca data lembaga B
   - API client/key A tidak bisa baca data lembaga B
   - API client/key dengan scope terbatas tidak bisa membaca resource/field di luar scope
   - Sync mengembalikan create/update/delete sesuai `since`
   - Sync cursor tidak melewatkan data saat perubahan lebih dari satu halaman
   - Hanya satu tahun ajaran aktif per lembaga
   - Login throttle bekerja
   - API tanpa/invalid key ditolak
   - MFA Super Admin aktif sebelum produksi publik
   - HTTPS & header keamanan terpasang di VPS
   - Backup restore sudah diuji
3. Tes keamanan dasar (authz, throttle, tidak ada secret/PII berlebih di response) termasuk checklist go-live.

### B8. Deploy

1. Deploy target: **VPS**.
2. Web server: **Apache** (+ PHP sesuai requirement Laravel).
3. Backup database terjadwal.
4. Backup terenkripsi, offsite, retention minimal 30 hari, dan restore test sebelum go-live.
5. Migrasi terkendali (bukan edit skema manual di produksi tanpa jejak).

---

## C. Aturan dokumentasi & review dokumen

1. Koreksi ditulis merujuk bagian (`RULES A7`, `SPEC §3.5`, dll.).
2. Setelah koreksi digabung, status dokumen diubah: `DRAFT` → `DISETUJUI` + tanggal.
3. Kode baru boleh dimulai hanya jika ketiga dokumen berstatus **DISETUJUI**.

---

## D. Keputusan terkunci (audit 18 Jul 2026)

| Kode | Topik | Keputusan |
|------|-------|-----------|
| D1 | Siswa wajib punya kelas saat create? | **Tidak wajib**; UI tampilkan peringatan jika kosong |
| D2 | Admin Lembaga boleh rotate API key? | **Tidak**; hanya Super Admin. Admin Lembaga **lihat nama client, prefix, scope, status saja** |
| D3 | Hapus kelas yang masih berisi siswa? | **Diblok** |
| D4 | Unik NIS/NISN per lembaga? | **Wajib** (partial unique) jika field terisi |
| D5 | CORS browser ke API | Fase 1 **server-to-server**; whitelist fase 2 |
| D6 | Cloudflare kapan aktif? | **Wajib sebelum produksi publik** (orange cloud / full proxy) |
| D7 | Bahasa UI admin | **Bahasa Indonesia** |
| D8 | Lembaga nonaktif | Login admin ditolak; API key → **403** |
| D9 | Import Excel massal fase 1? | **Tidak** — ditunda **fase 2**; fase 1 input manual via form |
| D10 | Nama field entitas | **Disetujui** sesuai SPEC §3 & §3.0 (snake_case standar Indonesia) |
| D11 | API key fase 1 | **Per API client aplikasi konsumen**, bukan satu key tunggal per lembaga |
| D12 | Scope API | **Wajib** resource scope + profil field (`minimal`, `academic`, `contact`) |
| D13 | Penyimpanan API key | Prefix unik + **HMAC-SHA256 digest**; plain hanya copy-once |
| D14 | Sync delta | **Watermark + cursor** berbasis `(changed_at, id)` |
| D15 | Constraint tenant DB | **Wajib** composite FK/unique untuk relasi antar tabel tenant |
| D16 | MFA Super Admin | **Wajib sebelum produksi publik** |
| D17 | Backup | Encrypted offsite backup; RPO max 24 jam, RTO target max 4 jam |
| D18 | Audit log | Append-only; include request_id/result/IP/user_agent tanpa secret/PII penuh |

Semua keputusan §D terkunci. Perubahan setelah tanggal approve → update SPEC/RULES dulu, baru kode.
