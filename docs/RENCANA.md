# Rencana Pusat Data (Data Center)

Dokumen ini adalah **riwayat kesepakatan diskusi awal**.

Untuk review & koreksi aktif, gunakan:

- [PLAN.md](./PLAN.md)
- [SPEC.md](./SPEC.md)
- [RULES.md](./RULES.md)
- [AUDIT_LENGKAP_2026-07-18.md](./AUDIT_LENGKAP_2026-07-18.md) - audit lanjutan sebelum coding

Kode **belum** dimulai sampai dokumen aktif sudah disetujui dan revisi audit lengkap dimasukkan.

## Tujuan

Membangun **Data Center** sebagai **sumber kebenaran (single source of truth)** untuk data master lembaga pendidikan.

Aplikasi lain (administrasi guru, perangkat, absensi, dll.) **menarik / menyinkronkan** data dari Data Center — **bukan** mengirim data master ke Data Center, dan **bukan** mengubah data master di dalam aplikasi masing-masing.

```
                    ┌──────────────────────────────┐
                    │         DATA CENTER          │
                    │  Super Admin + Admin Lembaga │
                    │  (satu-satunya tempat ubah)  │
                    └──────────────┬───────────────┘
                                   │
                    Tombol "Tarik / Sinkron data"
                                   │
           ┌───────────────────────┼───────────────────────┐
           ▼                       ▼                       ▼
   App Administrasi          App Absensi            App Perangkat
   (API client sendiri)      (API client sendiri)   (API client sendiri)
   (baca + salinan lokal)    (baca + salinan lokal) (baca + salinan lokal)
```

---

## Peran pengguna

| Peran | Siapa | Kewenangan |
|-------|--------|------------|
| **Super Admin Data Center** | Pengelola pusat | Kelola seluruh sistem; buat/ubah lembaga; buat Admin Lembaga; kelola data lintas lembaga bila perlu |
| **Admin Lembaga** | Ditunjuk oleh Super Admin | Kelola data master **milik lembaganya saja** (guru, siswa, karyawan, kelas, tahun ajaran) |
| **Aplikasi konsumen** | Sistem lain | Hanya **tarik / sinkron** data lewat API client/key sesuai scope; tidak boleh ubah master di Data Center |

---

## Master data (fase 1)

1. **Lembaga**
2. **Guru**
3. **Siswa**
4. **Karyawan**
5. **Kelas**
6. **Tahun ajaran**

Setiap entitas punya **ID pusat unik** (`lembaga_id`, `guru_id`, `siswa_id`, `karyawan_id`, `kelas_id`, `tahun_ajaran_id`) yang dipakai semua aplikasi.

### Relasi inti (konsep)

- Satu **Lembaga** memiliki banyak Guru, Siswa, Karyawan, Kelas, Tahun ajaran
- **Kelas** terkait **Lembaga** dan **Tahun ajaran**
- **Siswa** dapat tertaut ke **Kelas** (+ Tahun ajaran)

*(Detail field per entitas ditentukan di langkah desain skema berikutnya.)*

---

## Aturan perubahan data

1. Ubah data master **hanya di Data Center** (Super Admin atau Admin Lembaga).
2. Aplikasi lain **tidak boleh** mengedit master.
3. Setelah perubahan di Data Center, operator di app menekan **Sinkron**.
4. App boleh menyimpan salinan lokal; salinan itu mengikuti Data Center.

---

## Cara aplikasi mengambil data

| Tombol | Fungsi |
|--------|--------|
| **Tarik data dari Data Center** | Ambil data pertama kali / ulang penuh |
| **Sinkron** | Ambil **hanya yang berubah** sejak sinkron terakhir (delta, berbasis `changed_at`, `watermark`, dan cursor) |

Kredensial: **API key per API client aplikasi konsumen**. Satu lembaga boleh punya beberapa client, misalnya Absensi, Perangkat, dan Administrasi. Setiap client punya scope resource dan profil field sendiri agar PII tidak terbuka berlebihan.

---

## Keputusan terkunci

| No | Topik | Keputusan |
|----|--------|-----------|
| 1 | Siswa di fase 1 | **Ya, ikut** |
| 2 | Kredensial app | **API key per API client aplikasi konsumen**, terikat ke satu lembaga |
| 3 | Strategi sinkron | **Delta — hanya yang berubah**, memakai watermark + cursor |
| 4 | Stack teknis | **Laravel + PostgreSQL + Apache — DISETUJUI** |
| 5 | Deployment | **VPS** |
| 6 | Keamanan Super Admin | **MFA/TOTP wajib sebelum produksi publik** |
| 7 | Minimisasi PII | **Default field minimal; kontak/wali/alamat hanya dengan scope eksplisit** |

---

## Stack resmi (untuk VPS)

**Disetujui pemilik kebutuhan** (Laravel; web server dikoreksi ke **Apache**).

| Lapisan | Pilihan | Alasan |
|---------|---------|--------|
| Backend API | **Laravel (PHP)** | Cepat bangun CRUD admin + API; cocok VPS |
| Database | **PostgreSQL** | Kuat untuk master & sync delta |
| Dashboard admin | **Laravel Blade + Livewire** | Satu codebase dengan API; **DIKUNCI fase 1** |
| Auth admin | Session login + role | Super Admin / Admin Lembaga |
| Auth aplikasi | Header `X-API-Key` per API client | Read-only, scoped, field profile |
| Sync delta | `GET /api/v1/{resource}/sync?since=...` + cursor/watermark | Hanya yang berubah (path resmi di SPEC) |
| Web server | **Apache** | Sesuai lingkungan pemilik (bukan Nginx) |
| Process | PHP sesuai setup Apache VPS | Fase 1 |
| Keamanan | HTTPS, Cloudflare (akan ditambah), MFA Super Admin, scoped API client, rate limit, firewall, harden origin, backup offsite | Wajib data center |

**Alternatif Python/Node tidak dipakai** — stack sudah dikunci ke Laravel.

---

## Batas ruang lingkup fase 1

**Masuk**

- Login Super Admin & Admin Lembaga
- CRUD: Lembaga, Guru, Siswa, Karyawan, Kelas, Tahun ajaran
- API client/key per aplikasi konsumen, lengkap dengan scope resource dan profil field
- API tarik penuh + API sinkron delta watermark/cursor
- Deploy ke VPS
- Dokumentasi integrasi tombol Tarik / Sinkron untuk app lain

**Belum (fase berikutnya)**

- Push realtime otomatis tanpa tombol sinkron
- Ubah master dari aplikasi konsumen
- OAuth / SSO antar app
- Modul bisnis spesifik app (absensi, inventaris, nilai, dll.)

---

## Alur kerja ringkas

### A. Pengisian data

1. Super Admin buat **Lembaga**.
2. Super Admin buat **API client/key** untuk tiap aplikasi konsumen lembaga.
3. Super Admin buat **Admin Lembaga**.
4. Admin Lembaga isi **Guru, Siswa, Karyawan, Kelas, Tahun ajaran**.

### B. Pemakaian di aplikasi lain

1. App menyimpan API client/key miliknya.
2. Klik **Tarik data** → salinan lokal terisi.
3. Ada perubahan di Data Center → klik **Sinkron** → hanya data yang berubah ikut diperbarui.
4. App menyimpan watermark terakhir hanya setelah semua cursor sync selesai.

---

## Urutan kerja berikutnya

1. ~~Kunci keputusan terbuka~~ ✅
2. ~~Desain skema field per entitas~~ ✅ (SPEC §3 — masih bisa dikoreksi field)
3. ~~Desain kontrak API~~ ✅ (SPEC §4)
4. ~~Wireframe dashboard~~ ✅ (SPEC §5 — diperkaya audit 18 Jul 2026)
5. ~~Audit UI/UX/backend/keamanan~~ ✅ ([AUDIT_LENGKAP_2026-07-18.md](./AUDIT_LENGKAP_2026-07-18.md))
6. ~~Konfirmasi pemilik: field lokal & import Excel~~ ✅ D9 fase 2; D10 SPEC §3
7. ~~Audit lengkap sebelum coding dan koreksi dokumen~~ ✅ ([AUDIT_LENGKAP_2026-07-18.md](./AUDIT_LENGKAP_2026-07-18.md))
8. ~~Tanda **DISETUJUI** pada PLAN/SPEC/RULES~~ ✅ 18 Jul 2026 + revisi penguatan audit lengkap
9. Setup proyek + deploy awal ke VPS ← **berikutnya**
10. Implementasi kode

---

## Status kesepakatan

- [x] Data Center = sumber kebenaran
- [x] Pengisi: Super Admin + Admin Lembaga
- [x] Master fase 1: Lembaga, Guru, **Siswa**, Karyawan, Kelas, Tahun ajaran
- [x] App tarik/sinkron via tombol; sinkron **delta**
- [x] Kredensial: **API key per API client aplikasi konsumen**
- [x] Scope API + profil field untuk minimisasi PII
- [x] Sync watermark + cursor
- [x] Deploy: **VPS**
- [x] Stack **disetujui**: **Laravel + PostgreSQL + Apache** di VPS
- [x] Proses kode: review jujur & jelas → perbaiki → baru tes
- [x] Keamanan data center wajib (termasuk proteksi DDoS/abuse)
- [x] MFA Super Admin wajib sebelum produksi publik
- [x] Backup terenkripsi offsite + uji restore sebelum go-live
- [x] Ubah master hanya di Data Center
- [x] ID unik pusat untuk semua aplikasi

Dokumen ini **riwayat kesepakatan awal**. Koreksi aktif & audit ada di PLAN/SPEC/RULES/AUDIT.
