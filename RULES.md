# RULES — Pusat Data

Status: **DRAFT untuk koreksi bersama**  
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
2. API key hanya mengembalikan data lembaga pemilik key.
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

### A6. Tahun ajaran aktif

1. Per lembaga, hanya **satu** tahun ajaran bertanda `is_aktif = true` pada satu waktu.
2. Mengaktifkan tahun ajaran baru harus menonaktifkan yang sebelumnya (dalam satu transaksi).

### A7. Relasi kelas & siswa

1. `kelas.tahun_ajaran_id` wajib valid milik lembaga yang sama.
2. Jika siswa punya `kelas_id`, maka `kelas` tersebut harus lembaga yang sama.
3. Menghapus (soft) kelas yang masih dipakai siswa: **diblok** dengan pesan jelas.
4. (Draft) Siswa boleh dibuat tanpa kelas dulu — koreksi jika harus wajib.

### A8. API key

1. Plain API key hanya ditampilkan **sekali** saat generate/rotate.
2. Yang disimpan di DB: hash (+ prefix untuk identifikasi).
3. Rotate key mematikan key lama segera.
4. Semua request API tanpa key / key salah → 401.
5. Semua request write master dengan API key → 403/405.

### A9. Sinkron delta

1. Parameter `since` wajib untuk endpoint sync.
2. Perubahan yang dihitung: `updated_at > since` ATAU `deleted_at > since`.
3. Response memuat `synced_at` server; app wajib menyimpan nilai itu untuk sync berikutnya.
4. Jika app bingung/korup, boleh fallback ke **tarik penuh**.

### A10. Tombol di aplikasi konsumen

1. **Tarik** = full snapshot resource yang dibutuhkan.
2. **Sinkron** = delta sejak `synced_at` terakhir.
3. App tidak mengubah master di Pusat Data saat tarik/sinkron.

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
```

4. **Dilarang** langsung tes tanpa review kode.
5. **Dilarang** menunda perbaikan temuan review “nanti saja”; temuan harus diperbaiki sebelum tes.
6. Commit message jelas; rahasia (`.env`, API key plain) tidak masuk git.

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
   - Rate limiting aplikasi (Laravel) pada login & API
   - Limit request di **Apache** (atau modul setara)
   - Proteksi jaringan/VPS (firewall rate, fail2ban, dan/atau layanan anti-DDoS provider bila tersedia)
7. Backup DB terjadwal + uji restore berkala; backup tidak boleh diakses publik.

#### B4.2 Autentikasi & sesi admin

1. Password admin di-hash (algoritma default Laravel yang kuat).
2. Proteksi brute-force login (throttle / lockout).
3. Session aman: `httpOnly`, `secure`, `same_site` ketat di produksi.
4. Logout & invalidate session saat nonaktifkan user.
5. Prinsip least privilege: Super Admin vs Admin Lembaga sesuai ROLE.

#### B4.3 API & data

1. API key di-hash di database; plain hanya sekali saat generate/rotate.
2. Authorization dicek di **server** (policy/scope `lembaga_id`), bukan hanya UI.
3. API key = **read-only**; write master via API key ditolak.
4. Validasi input ketat (mass assignment guarded, validasi request).
5. Lindungi dari OWASP umum: XSS, CSRF (web admin), SQL injection (Eloquent/query binding), open redirect.
6. Jangan log secrets (password, API key plain, token).
7. Error produksi tidak membocorkan stack trace ke klien.
8. Paginasi & batasan ukuran response untuk mencegah abuse resource.

#### B4.4 Operasional keamanan

1. Secret hanya di env / secret manager VPS; tidak di git.
2. Audit log tindakan kritis (buat lembaga, rotate API key, nonaktifkan admin, hapus massal) — minimal fase 1 untuk aksi API key & user admin.
3. Pantau log akses anormal (spike 401/403/429).
4. Rencana respons insiden singkat: rotate key, nonaktifkan akun, blok IP, restore backup.

### B5. Data & migrasi

1. Semua PK master: UUID.
2. Semua tabel master punya `created_at`, `updated_at`, soft delete.
3. Index untuk sync: `(lembaga_id, updated_at)`, `(lembaga_id, deleted_at)`.
4. Foreign key antar tabel dilembaga-sama divalidasi di aplikasi + DB bila memungkinkan.

### B6. API

1. JSON UTF-8; timestamp ISO-8601 UTC.
2. Paginasi default untuk tarik penuh.
3. Error body konsisten (`message`, `code` opsional).
4. Versioning path `/api/v1`.
5. Rate limit per API key / IP tercatat di SPEC teknis saat implementasi.

### B7. Testing (hanya setelah review bersih)

1. Tes baru dijalankan setelah review kode selesai dan temuan diperbaiki.
2. Minimum sebelum go-live:
   - Admin lembaga A tidak bisa baca data lembaga B
   - API key A tidak bisa baca data lembaga B
   - Sync mengembalikan create/update/delete sesuai `since`
   - Hanya satu tahun ajaran aktif per lembaga
   - Login throttle bekerja
   - API tanpa/invalid key ditolak
   - HTTPS & header keamanan terpasang di VPS
3. Tes keamanan dasar (authz, throttle, tidak ada secret di response) termasuk checklist go-live.

### B8. Deploy

1. Deploy target: **VPS**.
2. Web server: **Apache** (+ PHP sesuai requirement Laravel).
3. Backup database terjadwal.
4. Migrasi terkendali (bukan edit skema manual di produksi tanpa jejak).

---

## C. Aturan dokumentasi & review dokumen

1. Koreksi ditulis merujuk bagian (`RULES A7`, `SPEC §3.5`, dll.).
2. Setelah koreksi digabung, status dokumen diubah: `DRAFT` → `DISETUJUI` + tanggal.
3. Kode baru boleh dimulai hanya jika ketiga dokumen berstatus **DISETUJUI**.

---

## D. Keputusan pending di dalam rules (minta koreksi)

| Kode | Pertanyaan | Default sementara |
|------|------------|-------------------|
| D1 | Siswa wajib punya kelas saat create? | Tidak wajib |
| D2 | Admin Lembaga boleh rotate API key? | Tidak; hanya Super Admin |
| D3 | Hapus kelas yang masih berisi siswa? | Diblok |
| D4 | Unik NIS/NISN per lembaga? | Ya, jika field terisi |
| D5 | Import Excel di fase 1? | Tidak |

Isi koreksi Anda terhadap tabel D (dan bagian lain) untuk kita revisi sebelum approve.
