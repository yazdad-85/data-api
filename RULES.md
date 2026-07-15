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
3. Menghapus (soft) kelas yang masih dipakai siswa: **diblok** atau siswa dilepas dulu — default usulan: **blok** dengan pesan jelas.
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

### B1. Proses

1. Jangan mulai kode sampai PLAN, SPEC, RULES ditandai disetujui pemilik kebutuhan.
2. Perubahan requirement setelah approve → update dulu SPEC/RULES, baru ubah kode.
3. Commit message jelas; rahasia (`.env`, API key plain) tidak masuk git.

### B2. Stack & struktur

1. Stack fase 1: **Laravel + PostgreSQL + Nginx** di VPS — **DISETUJUI pemilik kebutuhan (15 Jul 2026)**.
2. Satu aplikasi Laravel untuk dashboard admin + API konsumen.
3. Pemisahan jelas: route/web admin vs route/api.

### B3. Keamanan

1. Password admin di-hash (algoritma default Laravel).
2. API key di-hash di database.
3. HTTPS wajib di produksi VPS.
4. Authorization dicek di server (policy/scope), bukan hanya disembunyikan di UI.
5. Rate limiting dasar pada endpoint API.

### B4. Data & migrasi

1. Semua PK master: UUID.
2. Semua tabel master punya `created_at`, `updated_at`, soft delete.
3. Index untuk sync: `(lembaga_id, updated_at)`, `(lembaga_id, deleted_at)`.
4. Foreign key antar tabel dilembaga-sama harus divalidasi di aplikasi + DB bila memungkinkan.

### B5. API

1. JSON UTF-8; timestamp ISO-8601 UTC.
2. Paginasi default untuk tarik penuh (hindari response raksasa).
3. Error body konsisten (`message`, `code` opsional).
4. Versioning path `/api/v1`.

### B6. Testing minimum sebelum go-live

1. Admin lembaga A tidak bisa baca data lembaga B.
2. API key A tidak bisa baca data lembaga B.
3. Sync mengembalikan create/update/delete sesuai `since`.
4. Hanya satu tahun ajaran aktif per lembaga.

### B7. Deploy

1. Deploy target: VPS.
2. Backup database terjadwal.
3. Migrasi dijalankan secara terkendali (bukan edit skema manual di produksi tanpa jejak).

---

## C. Aturan dokumentasi & review

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
