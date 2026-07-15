# PLAN — Pusat Data

Status: **DRAFT untuk koreksi bersama** (detail field/API masih bisa dikoreksi)  
Stack: **DISETUJUI** — Laravel + PostgreSQL + Nginx di VPS (15 Jul 2026)  
Kode: **belum dimulai** sampai Plan, Spec, Rule disetujui keseluruhan

---

## 1. Ringkasan produk

Pusat Data adalah sistem **master data** lembaga pendidikan.

- Data diisi & diubah hanya di Pusat Data (Super Admin + Admin Lembaga).
- Aplikasi lain (absensi, perangkat, administrasi, dll.) **menarik** dan **menyinkronkan** data.
- Sinkron memakai mode **delta** (hanya yang berubah).
- Akses aplikasi: **API key per lembaga**.

Dokumen terkait:

| Dokumen | Isi |
|---------|-----|
| [SPEC.md](./SPEC.md) | Entitas, field, API, auth, sync |
| [RULES.md](./RULES.md) | Aturan bisnis & aturan implementasi |
| [RENCANA.md](./RENCANA.md) | Catatan kesepakatan awal (riwayat diskusi) |

---

## 2. Tujuan fase 1

1. Super Admin dapat mengelola lembaga dan Admin Lembaga.
2. Admin Lembaga dapat mengelola master: Guru, Siswa, Karyawan, Kelas, Tahun ajaran.
3. Setiap lembaga punya API key.
4. Aplikasi konsumen bisa **tarik penuh** dan **sinkron delta**.
5. Sistem ter-deploy di **VPS**.

Di luar fase 1: realtime push, ubah master dari app lain, OAuth/SSO, modul bisnis app (nilai, absensi harian, dll.).

---

## 3. Pengguna & tanggung jawab

| Peran | Tanggung jawab fase 1 |
|-------|------------------------|
| Super Admin | Buat lembaga, buat/nonaktifkan Admin Lembaga, lihat semua lembaga, kelola API key lembaga |
| Admin Lembaga | CRUD master data miliknya: Guru, Siswa, Karyawan, Kelas, Tahun ajaran |
| Integrator app | Pakai API key + tombol Tarik / Sinkron di aplikasi masing-masing |

---

## 4. Tahapan kerja (tanpa coding dulu → lalu coding)

### Tahap A — Desain (sekarang)

- [x] Klarifikasi kebutuhan
- [ ] Koreksi bersama **PLAN / SPEC / RULES** (dokumen ini)
- [ ] Revisi sesuai koreksi
- [ ] Tanda “disetujui” dari pemilik kebutuhan

### Tahap B — Persiapan teknis

- Setup repo Laravel
- Setup database PostgreSQL
- Desain migrasi sesuai SPEC
- Setup deploy VPS (Nginx + PHP-FPM + Postgres)

### Tahap C — Inti Admin

- Auth Super Admin & Admin Lembaga
- CRUD Lembaga + API key
- CRUD Guru, Siswa, Karyawan, Kelas, Tahun ajaran (scoped lembaga)

### Tahap D — API konsumsi

- Endpoint tarik penuh
- Endpoint sinkron delta (`since`)
- Dokumentasi integrasi untuk app lain

### Tahap E — UAT & go-live VPS

- Uji alur Super Admin → Admin Lembaga → App tarik/sinkron
- Hardening dasar (HTTPS, backup DB, env secrets)

---

## 5. Deliverable fase 1

1. Dashboard web admin (Super Admin & Admin Lembaga)
2. REST API baca + sync untuk aplikasi konsumen
3. Database PostgreSQL berisi master data
4. Dokumentasi cara paket tombol Tarik / Sinkron
5. Instance hidup di VPS

---

## 6. Risiko & mitigasi

| Risiko | Mitigasi |
|--------|----------|
| Field master kurang/berlebih | Koreksi SPEC sebelum coding |
| App lama sulit pakai delta | Sediakan juga tarik penuh |
| API key bocor | Rotate key di Super Admin; HTTPS wajib di VPS |
| Admin lembaga lihat data lembaga lain | Enforce scope `lembaga_id` di RULES |

---

## 7. Cara koreksi dokumen ini

Saat review, sebutkan: **file + nomor bagian + usulan perubahan**.  
Contoh: `SPEC.md §3.2 Guru — tambah field NUPTK`.

Tidak ada implementasi kode sampai Plan, Spec, Rule ditandai disetujui.
