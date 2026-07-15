# SPEC — Pusat Data

Status: **DRAFT untuk koreksi bersama** (field & detail API masih bisa dikoreksi)  
Stack: **DISETUJUI** — Laravel + PostgreSQL + Nginx di VPS (15 Jul 2026)  
Tujuan: spesifikasi fungsional & teknis sebelum coding.

---

## 1. Pengertian istilah

| Istilah | Arti |
|---------|------|
| Pusat Data / Data Center | Sistem master data ini |
| Lembaga | Sekolah / institusi pemilik data |
| Master data | Lembaga, Guru, Siswa, Karyawan, Kelas, Tahun ajaran |
| Tarik | Ambil seluruh data resource untuk lembaga (full) |
| Sinkron | Ambil hanya record yang berubah sejak waktu tertentu (delta) |
| API key lembaga | Rahasia yang dipakai aplikasi konsumen untuk baca data lembaga tersebut |
| ID pusat | Primary key UUID yang dipakai semua aplikasi konsumen |

---

## 2. Autentikasi & otorisasi

### 2.1 Dashboard admin (manusia)

- Login email + password (session).
- Role:
  - `super_admin`
  - `admin_lembaga` (wajib terikat `lembaga_id`)

### 2.2 API aplikasi konsumen

- Header: `X-API-Key: <api_key_lembaga>`
- Satu lembaga bisa punya **satu API key aktif** di fase 1 (boleh dirotasi; key lama mati).
- API key hanya mengizinkan **GET** (baca/tarik/sinkron) untuk data lembaga itu.
- API key **tidak** boleh create/update/delete master.

### 2.3 Matriks izin (fase 1)

| Aksi | Super Admin | Admin Lembaga | API Key |
|------|:-----------:|:-------------:|:-------:|
| CRUD Lembaga | Ya | Tidak | Tidak |
| Buat Admin Lembaga | Ya | Tidak | Tidak |
| Rotate API key | Ya | Lihat saja (opsional) | Tidak |
| CRUD Guru/Siswa/Karyawan/Kelas/Tahun ajaran | Semua lembaga | Lembaga sendiri | Tidak |
| Tarik / Sinkron | via dashboard internal opsional | via dashboard internal opsional | Ya |

---

## 3. Model data

Konvensi umum semua tabel master:

| Field | Tipe | Keterangan |
|-------|------|------------|
| `id` | UUID | ID pusat (PK) |
| `lembaga_id` | UUID | FK ke lembaga (kecuali tabel `lembaga` sendiri) |
| `created_at` | timestamp | |
| `updated_at` | timestamp | Dipakai sinkron delta |
| `deleted_at` | timestamp null | Soft delete — ikut dikirim di sync sebagai “terhapus” |

Status record: aktif bila `deleted_at` null.

---

### 3.1 Lembaga

| Field | Tipe | Wajib | Catatan |
|-------|------|:-----:|---------|
| `id` | UUID | Ya | |
| `kode` | string(30) | Ya | Unik global, mis. `SCH-001` |
| `nama` | string(150) | Ya | |
| `jenis` | string(50) | Tidak | Mis. SD/SMP/SMA/SMK/Lainnya |
| `alamat` | text | Tidak | |
| `kota` | string(100) | Tidak | |
| `provinsi` | string(100) | Tidak | |
| `telepon` | string(30) | Tidak | |
| `email` | string(150) | Tidak | |
| `is_active` | boolean | Ya | Default true |
| `api_key_hash` | string | Ya | Simpan hash, bukan plain |
| `api_key_prefix` | string(12) | Ya | Untuk pantau key tanpa bocorkan secret |
| `api_key_plain_once` | — | — | Plain key hanya ditampilkan sekali saat buat/rotate |

---

### 3.2 User admin

| Field | Tipe | Wajib | Catatan |
|-------|------|:-----:|---------|
| `id` | UUID | Ya | |
| `name` | string(150) | Ya | |
| `email` | string(150) | Ya | Unik |
| `password` | hash | Ya | |
| `role` | enum | Ya | `super_admin` \| `admin_lembaga` |
| `lembaga_id` | UUID null | Kondisional | Wajib jika `admin_lembaga` |
| `is_active` | boolean | Ya | |

---

### 3.3 Tahun ajaran

| Field | Tipe | Wajib | Catatan |
|-------|------|:-----:|---------|
| `id` | UUID | Ya | |
| `lembaga_id` | UUID | Ya | |
| `nama` | string(50) | Ya | Mis. `2025/2026` |
| `tanggal_mulai` | date | Ya | |
| `tanggal_selesai` | date | Ya | |
| `is_aktif` | boolean | Ya | Max 1 aktif per lembaga (lihat RULES) |

Unik: (`lembaga_id`, `nama`)

---

### 3.4 Kelas

| Field | Tipe | Wajib | Catatan |
|-------|------|:-----:|---------|
| `id` | UUID | Ya | |
| `lembaga_id` | UUID | Ya | |
| `tahun_ajaran_id` | UUID | Ya | |
| `nama` | string(50) | Ya | Mis. `VII-A` |
| `tingkat` | string(20) | Tidak | Mis. `7`, `X` |
| `wali_kelas_guru_id` | UUID null | Tidak | FK opsional ke guru |

Unik: (`lembaga_id`, `tahun_ajaran_id`, `nama`)

---

### 3.5 Guru

| Field | Tipe | Wajib | Catatan |
|-------|------|:-----:|---------|
| `id` | UUID | Ya | |
| `lembaga_id` | UUID | Ya | |
| `nip` | string(40) | Tidak | |
| `nuptk` | string(40) | Tidak | |
| `nama` | string(150) | Ya | |
| `jenis_kelamin` | enum | Tidak | `L` \| `P` |
| `tempat_lahir` | string(100) | Tidak | |
| `tanggal_lahir` | date | Tidak | |
| `email` | string(150) | Tidak | |
| `telepon` | string(30) | Tidak | |
| `alamat` | text | Tidak | |
| `status_kepegawaian` | string(40) | Tidak | PNS/Honorer/dll. |
| `is_active` | boolean | Ya | Default true |

---

### 3.6 Karyawan

| Field | Tipe | Wajib | Catatan |
|-------|------|:-----:|---------|
| `id` | UUID | Ya | |
| `lembaga_id` | UUID | Ya | |
| `nik_pegawai` | string(40) | Tidak | Kode internal lembaga |
| `nama` | string(150) | Ya | |
| `jenis_kelamin` | enum | Tidak | `L` \| `P` |
| `jabatan` | string(100) | Tidak | |
| `email` | string(150) | Tidak | |
| `telepon` | string(30) | Tidak | |
| `alamat` | text | Tidak | |
| `is_active` | boolean | Ya | |

---

### 3.7 Siswa

| Field | Tipe | Wajib | Catatan |
|-------|------|:-----:|---------|
| `id` | UUID | Ya | |
| `lembaga_id` | UUID | Ya | |
| `nis` | string(40) | Tidak | |
| `nisn` | string(20) | Tidak | |
| `nama` | string(150) | Ya | |
| `jenis_kelamin` | enum | Tidak | `L` \| `P` |
| `tempat_lahir` | string(100) | Tidak | |
| `tanggal_lahir` | date | Tidak | |
| `kelas_id` | UUID null | Tidak | Kelas aktif saat ini |
| `tahun_ajaran_id` | UUID null | Tidak | Tahun ajaran terkait penempatan |
| `email` | string(150) | Tidak | |
| `telepon` | string(30) | Tidak | |
| `alamat` | text | Tidak | |
| `nama_wali` | string(150) | Tidak | |
| `telepon_wali` | string(30) | Tidak | |
| `is_active` | boolean | Ya | |

Unik disarankan: (`lembaga_id`, `nis`) jika nis diisi; (`lembaga_id`, `nisn`) jika nisn diisi.

---

## 4. API aplikasi konsumen

Base path usulan: `/api/v1`

Auth: header `X-API-Key`

### 4.1 Health

`GET /api/v1/health` → `{ "status": "ok" }` (tanpa key, opsional)

### 4.2 Profil lembaga (dari key)

`GET /api/v1/me`

Response ringkas: `lembaga_id`, `kode`, `nama`

### 4.3 Tarik penuh

`GET /api/v1/{resource}`

Resource diizinkan:

- `guru`
- `siswa`
- `karyawan`
- `kelas`
- `tahun-ajaran`

Query opsional:

| Query | Arti |
|-------|------|
| `include_deleted` | `true`/`false` (default false) |
| `page`, `per_page` | Paginasi |

Response:

```json
{
  "resource": "guru",
  "lembaga_id": "...",
  "synced_at": "2026-07-15T13:00:00Z",
  "data": [ { "...field sesuai SPEC..." } ],
  "meta": { "page": 1, "per_page": 100, "total": 250 }
}
```

### 4.4 Sinkron delta

`GET /api/v1/{resource}/sync?since=2026-07-15T10:00:00Z`

Aturan:

- Wajib `since` (ISO-8601 UTC).
- Kembalikan record dengan `updated_at > since` **atau** `deleted_at > since`.
- Sertakan field soft-delete agar app bisa menghapus/nonaktifkan salinan lokal.

Response:

```json
{
  "resource": "siswa",
  "lembaga_id": "...",
  "since": "2026-07-15T10:00:00Z",
  "synced_at": "2026-07-15T13:05:00Z",
  "changes": [
    { "id": "...", "deleted_at": null, "...": "..." },
    { "id": "...", "deleted_at": "2026-07-15T12:00:00Z", "...": "..." }
  ],
  "change_count": 2
}
```

App menyimpan `synced_at` terakhir untuk panggilan sinkron berikutnya.

### 4.5 Tarik banyak resource sekali (opsional fase 1)

`GET /api/v1/export?resources=guru,siswa,kelas`

Boleh ditunda jika ingin scope lebih kecil; default cukup per-resource.

---

## 5. UI dashboard (wireframe konsep)

### 5.1 Super Admin

- Login
- Daftar lembaga → buat/edit/aktif-nonaktif
- Detail lembaga → buat Admin Lembaga, generate/rotate API key
- (Opsional) pantau jumlah master per lembaga

### 5.2 Admin Lembaga

- Login
- Menu: Tahun ajaran, Kelas, Guru, Siswa, Karyawan
- Form create/edit + soft delete
- Tidak melihat lembaga lain
- Tidak bisa membuat API key (kecuali dikoreksi: boleh lihat prefix saja)

### 5.3 Integrasi di app lain (bukan UI Pusat Data)

- Tombol **Tarik data dari Data Center** → panggil tarik penuh
- Tombol **Sinkron** → panggil sync delta dengan `since` terakhir

---

## 6. Deploy VPS (spesifikasi operasional)

| Komponen | Spesifikasi target |
|----------|--------------------|
| OS | Ubuntu LTS |
| Web | Nginx |
| App | Laravel (PHP 8.3+) |
| DB | PostgreSQL 16 |
| TLS | HTTPS (Let's Encrypt) |
| Backup | Dump DB harian |

ENV rahasia: `APP_KEY`, DB password, tidak commit ke git.

---

## 7. Hal yang sengaja belum di-spec (fase berikutnya)

- Histori perpindahan kelas siswa antar tahun (tabel enrollments)
- Foto/dokumen upload
- Multi API key per lembaga / per aplikasi
- Webhook push perubahan
- Import Excel massal (bisa prioritas tinggi jika diminta)

---

## 8. Poin koreksi yang diharapkan dari Anda

Mohon cek terutama:

1. Field tiap entitas — kurang / lebih / salah nama?
2. Siswa wajib punya `kelas_id` sejak awal, atau boleh kosong?
3. Apakah Admin Lembaga boleh melihat/rotate API key?
4. Apakah `export` multi-resource perlu di fase 1?
5. Nama field lokal (mis. lebih suka `no_induk` daripada `nis`)?

Tandai koreksi dengan merujuk **§ nomor bagian**.
