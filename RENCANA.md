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
| **Admin Lembaga** | Ditunjuk oleh Super Admin | Kelola data master **milik lembaganya saja** (guru, karyawan, kelas, tahun ajaran, dll.) |
| **Aplikasi konsumen** | Sistem lain | Hanya **tarik / sinkron** data; tidak boleh ubah master di Data Center lewat UI app tersebut |

---

## Master data (fase 1)

Data yang dikelola di Data Center:

1. **Lembaga** — sekolah / institusi
2. **Guru**
3. **Karyawan**
4. **Kelas**
5. **Tahun ajaran**

Setiap entitas punya **ID pusat unik** (contoh: `lembaga_id`, `guru_id`, `karyawan_id`, `kelas_id`, `tahun_ajaran_id`) yang dipakai semua aplikasi.

### Relasi inti (konsep)

- Satu **Lembaga** memiliki banyak Guru, Karyawan, Kelas, Tahun ajaran
- **Kelas** dan data personal terkait ke **Lembaga** (+ Tahun ajaran bila relevan)

*(Detail field per entitas ditentukan saat desain skema berikutnya.)*

---

## Aturan perubahan data

1. **Ubah data master hanya di Data Center** (oleh Super Admin atau Admin Lembaga).
2. Aplikasi lain **tidak boleh** mengedit master guru/siswa/dll. sebagai sumber utama.
3. Setelah ada perubahan di Data Center, operator di aplikasi lain menekan **tombol Sinkron** untuk memperbarui salinan lokal.
4. Aplikasi boleh menyimpan **salinan lokal** hasil tarik/sinkron untuk dipakai offline/cepat, tetapi salinan itu mengikuti Data Center.

---

## Cara aplikasi mengambil data

Di aplikasi konsumen (absensi, perangkat, administrasi, dll.):

1. Tombol **"Tarik data dari Data Center"** — ambil data yang dibutuhkan pertama kali / ulang penuh.
2. Tombol **"Sinkron"** — perbarui salinan lokal mengikuti perubahan terbaru di Data Center.

Teknisnya: aplikasi memanggil **API baca** Data Center (dengan kredensial lembaga/aplikasi), bukan API “push master” dari app ke pusat.

---

## Batas ruang lingkup fase 1

**Masuk ruang lingkup**

- Autentikasi Super Admin & Admin Lembaga
- CRUD master: Lembaga, Guru, Karyawan, Kelas, Tahun ajaran
- API baca untuk aplikasi konsumen (filter per lembaga)
- Mekanisme kredensial aplikasi / lembaga untuk tarik & sinkron
- Dokumen cara integrasi tombol Tarik / Sinkron

**Belum masuk fase 1 (bisa fase berikutnya)**

- Data siswa (belum ada di daftar kesepakatan fase 1)
- Push otomatis realtime ke semua app tanpa tombol sinkron
- Perubahan master dari dalam aplikasi konsumen
- Modul transaksi bisnis spesifik (nilai, absensi harian, inventaris perangkat, dll.) — itu milik masing-masing app

---

## Alur kerja ringkas

### A. Pengisian data

1. Super Admin membuat **Lembaga**.
2. Super Admin membuat **Admin Lembaga**.
3. Admin Lembaga mengisi **Guru, Karyawan, Kelas, Tahun ajaran**.

### B. Pemakaian di aplikasi lain

1. App meminta akses/kredensial ke Data Center (per lembaga).
2. User di app klik **Tarik data** → salinan lokal terisi.
3. Jika master berubah di Data Center → user klik **Sinkron** di app.

---

## Keputusan terbuka (untuk dikunci sebelum coding)

| No | Topik | Opsi | Status |
|----|--------|------|--------|
| 1 | Siswa apakah ditambah ke master fase 1? | Ya / Tidak (saat ini belum) | Belum dikunci |
| 2 | Format kredensial app | API key per lembaga / OAuth / token app | Belum dikunci |
| 3 | Sinkron | Full replace vs hanya yang berubah (delta) | Belum dikunci — default usulan: delta bila ada `updated_at` |
| 4 | Stack teknis | Mis. API + DB + dashboard admin | Belum dikunci |
| 5 | Deployment | Lokal / VPS / cloud | Belum dikunci |

---

## Urutan kerja berikutnya (setelah dokumen ini disetujui)

1. Kunci keputusan terbuka di atas
2. Desain skema field per entitas (kolom Guru, Kelas, dll.)
3. Desain kontrak API baca (endpoint tarik/sinkron)
4. Wireframe kasar: dashboard Super Admin & Admin Lembaga
5. Baru mulai implementasi kode

---

## Status kesepakatan

Disepakati oleh pemilik kebutuhan (Seinarth Ar):

- [x] Data Center adalah sumber kebenaran
- [x] Pengisi data: Super Admin + Admin Lembaga
- [x] Master fase 1: Guru, Karyawan, Lembaga, Kelas, Tahun ajaran
- [x] App lain tarik/sinkron lewat tombol, bukan kirim master ke pusat
- [x] Ubah master hanya di Data Center
- [x] ID unik pusat dipakai semua aplikasi

Dokumen ini **bukan implementasi**. Coding baru dilakukan setelah rencana dan keputusan terbuka disetujui.
