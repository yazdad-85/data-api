# Rencana Pusat Data (Data Center)

Dokumen ini adalah kesepakatan desain sebelum implementasi kode.

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
   (baca + salinan lokal)    (baca + salinan lokal) (baca + salinan lokal)
```

---

## Peran pengguna

| Peran | Siapa | Kewenangan |
|-------|--------|------------|
| **Super Admin Data Center** | Pengelola pusat | Kelola seluruh sistem; buat/ubah lembaga; buat Admin Lembaga; kelola data lintas lembaga bila perlu |
| **Admin Lembaga** | Ditunjuk oleh Super Admin | Kelola data master **milik lembaganya saja** (guru, siswa, karyawan, kelas, tahun ajaran) |
| **Aplikasi konsumen** | Sistem lain | Hanya **tarik / sinkron** data lewat API key lembaga; tidak boleh ubah master di Data Center |

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
| **Sinkron** | Ambil **hanya yang berubah** sejak sinkron terakhir (delta, berbasis `updated_at` / cursor) |

Kredensial: **API key per lembaga** (cukup untuk fase 1).

---

## Keputusan terkunci

| No | Topik | Keputusan |
|----|--------|-----------|
| 1 | Siswa di fase 1 | **Ya, ikut** |
| 2 | Kredensial app | **API key per lembaga** |
| 3 | Strategi sinkron | **Delta — hanya yang berubah** |
| 4 | Stack teknis | **Lihat usulan di bawah** |
| 5 | Deployment | **VPS** |

---

## Usulan stack (untuk VPS)

Dipilih agar sederhana dioperasikan di satu VPS, tahan lama, dan mudah diintegrasikan aplikasi lain.

| Lapisan | Pilihan | Alasan |
|---------|---------|--------|
| Backend API | **Laravel (PHP)** | Cepat bangun CRUD admin + API, ekosistem matang, mudah di VPS biasa (Nginx + PHP-FPM) |
| Database | **PostgreSQL** | Kuat untuk data master & query sync delta (`updated_at`) |
| Dashboard admin | **Laravel Blade + Livewire** (atau Inertia sederhana) | Satu codebase dengan API; Super Admin & Admin Lembaga tanpa proyek frontend terpisah di fase 1 |
| Auth admin | Session login + role (super_admin / admin_lembaga) | Sesuai alur pengelola manusia |
| Auth aplikasi | Header `X-API-Key` per lembaga | Sesuai keputusan “cukup API key” |
| Sync delta | Endpoint `GET /sync/{resource}?since=ISO8601` | App kirim waktu sinkron terakhir; pusat kembalikan record berubah |
| Web server | **Nginx** | Standar VPS |
| Process | **PHP-FPM** + queue opsional nanti | Sederhana untuk fase 1 |
| Contahener opsional | Docker Compose (Nginx + app + Postgres) | Memudahkan install ulang di VPS |

**Alternatif yang juga bagus:** FastAPI (Python) + Postgres + dashboard terpisah. Tidak dipilih sebagai default karena untuk tim yang fokus “admin lembaga + banyak CRUD master”, Laravel biasanya lebih cepat selesai dan lebih mudah di-maintain di VPS shared/umum Indonesia.

Jika Anda lebih nyaman Python/Node, stack bisa diganti sebelum coding — inti desain (API key, delta sync, role) tetap sama.

---

## Batas ruang lingkup fase 1

**Masuk**

- Login Super Admin & Admin Lembaga
- CRUD: Lembaga, Guru, Siswa, Karyawan, Kelas, Tahun ajaran
- API key per lembaga
- API tarik penuh + API sinkron delta
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

1. Super Admin buat **Lembaga** + **API key lembaga**.
2. Super Admin buat **Admin Lembaga**.
3. Admin Lembaga isi **Guru, Siswa, Karyawan, Kelas, Tahun ajaran**.

### B. Pemakaian di aplikasi lain

1. App menyimpan API key lembaga.
2. Klik **Tarik data** → salinan lokal terisi.
3. Ada perubahan di Data Center → klik **Sinkron** → hanya data yang berubah ikut diperbarui.

---

## Urutan kerja berikutnya

1. ~~Kunci keputusan terbuka~~ ✅
2. Desain skema field per entitas (kolom Guru, Siswa, Kelas, dll.)
3. Desain kontrak API (tarik penuh + sync delta)
4. Wireframe kasar dashboard Super Admin & Admin Lembaga
5. Setup proyek + deploy awal ke VPS
6. Implementasi kode

---

## Status kesepakatan

- [x] Data Center = sumber kebenaran
- [x] Pengisi: Super Admin + Admin Lembaga
- [x] Master fase 1: Lembaga, Guru, **Siswa**, Karyawan, Kelas, Tahun ajaran
- [x] App tarik/sinkron via tombol; sinkron **delta**
- [x] Kredensial: **API key per lembaga**
- [x] Deploy: **VPS**
- [x] Stack diusulkan: **Laravel + PostgreSQL + Nginx** (bisa diganti sebelum coding)
- [x] Ubah master hanya di Data Center
- [x] ID unik pusat untuk semua aplikasi

Dokumen ini **masih bukan implementasi kode**. Langkah berikutnya: desain field & kontrak API — konfirmasi jika setuju stack Laravel, atau minta ganti stack dulu.
