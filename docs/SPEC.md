# SPEC — Pusat Data

Status: **DISETUJUI — 18 Jul 2026**; revisi penguatan dari [AUDIT_LENGKAP_2026-07-18.md](./AUDIT_LENGKAP_2026-07-18.md) sudah dimasukkan.  
Stack: **DISETUJUI** — Laravel + PostgreSQL + **Apache** di VPS; UI admin **Blade + Livewire**  
Tujuan: spesifikasi fungsional & teknis sebelum coding.  
Keamanan operasional: lihat juga RULES B4 (wajib untuk data center).

---

## 1. Pengertian istilah

| Istilah | Arti |
|---------|------|
| Pusat Data / Data Center | Sistem master data ini |
| Lembaga | Sekolah / institusi pemilik data |
| Master data | Lembaga, Guru, Siswa, Karyawan, Kelas, Tahun ajaran |
| Tarik | Ambil seluruh data resource untuk lembaga (full) |
| Sinkron | Ambil hanya record yang berubah sejak waktu tertentu (delta) |
| API client | Aplikasi konsumen yang diberi akses baca untuk satu lembaga |
| API key client | Rahasia milik satu API client; satu lembaga boleh punya beberapa client |
| ID pusat | Primary key UUID yang dipakai semua aplikasi konsumen |

---

## 2. Autentikasi & otorisasi

### 2.1 Dashboard admin (manusia)

- Login email + password (session).
- MFA/TOTP **wajib untuk Super Admin sebelum produksi publik**; Admin Lembaga dapat menyusul fase 2.
- Bootstrap Super Admin pertama via command/seeder (`install:super-admin`) — hanya saat belum ada super admin.
- Reset password: Super Admin dapat reset manual Admin Lembaga; forgot-password via email jika mail server tersedia.
- Role:
  - `super_admin`
  - `admin_lembaga` (wajib terikat `lembaga_id`)
- UI: **Bahasa Indonesia**; desktop-first; usable di tablet.

### 2.2 API aplikasi konsumen

- Header utama: `X-API-Key: <api_key_client>`  
  Alternatif dokumentasi integrator: `Authorization: Bearer <api_key_client>` (implementasi boleh dukung keduanya; jangan log header auth).
- Satu lembaga boleh punya beberapa **API client** aktif, masing-masing untuk aplikasi konsumen tertentu (mis. Absensi, Perangkat, Administrasi).
- API key hanya mengizinkan **GET** sesuai scope resource dan profil field client tersebut.
- API key **tidak** boleh create/update/delete master.
- Lembaga `is_active = false` → semua request API key → **403**.
- Key format: `dc_live_<prefix>_<secret>`; DB menyimpan prefix unik + digest HMAC, bukan plain key.

### 2.3 Matriks izin (fase 1)

| Aksi | Super Admin | Admin Lembaga | API Key |
|------|:-----------:|:-------------:|:-------:|
| CRUD Lembaga | Ya | Tidak | Tidak |
| Buat Admin Lembaga | Ya | Tidak | Tidak |
| Kelola API client/key | Ya | **Tidak** (lihat nama client + prefix saja) | Tidak |
| CRUD Guru/Siswa/Karyawan/Kelas/Tahun ajaran | Semua lembaga | Lembaga sendiri | Tidak |
| Tarik / Sinkron via API | Tidak (gunakan dashboard admin) | Tidak | Ya |

### 2.4 Urutan pengisian data (UX wajib)

Admin Lembaga mengisi master mengikuti dependensi:

1. **Tahun ajaran** (aktifkan satu)
2. **Guru**
3. **Kelas** (butuh tahun ajaran; wali kelas opsional dari guru)
4. **Siswa** (kelas opsional saat create; lihat RULES A7)
5. **Karyawan**

Menu sidebar mengikuti urutan di atas.

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

### 3.0 Konvensi penamaan field (D10 — disetujui 18 Jul 2026)

Nama kolom database & API memakai **snake_case** bahasa Indonesia/standar pendidikan nasional:

| Entitas | Field kunci | Catatan |
|---------|-------------|---------|
| Guru | `niy`, `nuptk`, `nama`, `status_kepegawaian` | `niy` = Nomor Induk Yayasan; tidak pakai `no_induk` |
| Siswa | `nis`, `nisn`, `nama_wali`, `telepon_wali` | Tidak pakai `no_induk` |
| Karyawan | `nik_pegawai`, `jabatan` | Kode internal lembaga |
| Kelas | `tingkat`, `wali_kelas_guru_id` | |
| Tahun ajaran | `is_aktif`, `tanggal_mulai`, `tanggal_selesai` | Bukan `is_active` |
| Umum | `lembaga_id`, `jenis_kelamin`, `is_active` | `is_active` untuk entitas person |

Perubahan nama field setelah approve hanya lewat revisi SPEC + migrasi terkendali.

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

**Catatan:** key tidak disimpan di tabel lembaga. API key disimpan di tabel API client (§3.2).

---

### 3.2 API client / API key aplikasi konsumen

| Field | Tipe | Wajib | Catatan |
|-------|------|:-----:|---------|
| `id` | UUID | Ya | |
| `lembaga_id` | UUID | Ya | Client hanya untuk satu lembaga |
| `nama` | string(150) | Ya | Mis. `Absensi`, `Perangkat`, `Administrasi` |
| `api_key_prefix` | string(16) | Ya | Unik global; untuk lookup dan identifikasi tanpa secret |
| `api_key_digest` | string(64) | Ya | HMAC-SHA256 dari secret memakai pepper/env; bukan password hash |
| `scopes` | json | Ya | Daftar scope, mis. `["siswa:read","kelas:read"]` |
| `field_profile` | enum | Ya | `minimal` \| `academic` \| `contact`; default `minimal` |
| `is_active` | boolean | Ya | Default true |
| `last_used_at` | timestamp null | Tidak | Untuk audit operasional |
| `last_used_ip` | string(45) null | Tidak | Simpan IP terakhir, bukan daftar lengkap |
| `revoked_at` | timestamp null | Tidak | Terisi saat dicabut/rotate |
| `created_at` | timestamp | Ya | |
| `updated_at` | timestamp | Ya | |

**Catatan UI:** plain API key hanya ditampilkan sekali saat buat/rotate. Admin Lembaga hanya melihat nama client, prefix, scope, dan waktu terakhir dipakai.

---

### 3.3 User admin

| Field | Tipe | Wajib | Catatan |
|-------|------|:-----:|---------|
| `id` | UUID | Ya | |
| `name` | string(150) | Ya | |
| `email` | string(150) | Ya | Unik |
| `password` | hash | Ya | |
| `role` | enum | Ya | `super_admin` \| `admin_lembaga` |
| `lembaga_id` | UUID null | Kondisional | Wajib jika `admin_lembaga` |
| `is_active` | boolean | Ya | |
| `mfa_enabled_at` | timestamp null | Kondisional | Wajib untuk Super Admin sebelum produksi publik |
| `mfa_secret` | encrypted text null | Tidak | Tidak pernah tampil di response |
| `recovery_codes_hash` | json null | Tidak | Recovery code disimpan hashed |

Constraint wajib:

- `admin_lembaga` wajib punya `lembaga_id`.
- `super_admin` tidak boleh terikat lembaga kecuali ada revisi SPEC/RULES eksplisit.

---

### 3.4 Tahun ajaran

| Field | Tipe | Wajib | Catatan |
|-------|------|:-----:|---------|
| `id` | UUID | Ya | |
| `lembaga_id` | UUID | Ya | |
| `nama` | string(50) | Ya | Format baku `YYYY/YYYY+1` (mis. `2025/2026`); aplikasi membentuk otomatis dari tahun mulai |
| `tanggal_mulai` | date | Ya | |
| `tanggal_selesai` | date | Ya | |
| `is_aktif` | boolean | Ya | Max 1 aktif per lembaga (lihat RULES) |

Unik: (`lembaga_id`, `nama`)  
Validasi: `tanggal_selesai` > `tanggal_mulai`

---

### 3.5 Kelas

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

### 3.6 Guru

| Field | Tipe | Wajib | Catatan |
|-------|------|:-----:|---------|
| `id` | UUID | Ya | |
| `lembaga_id` | UUID | Ya | |
| `niy` | string(40) | Tidak | Nomor Induk Yayasan (NIY); digenerate otomatis |
| `tahun_masuk` | smallint | Ya (create/import) | Tahun masuk pegawai; dipakai generate NIY |
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

### 3.7 Karyawan

| Field | Tipe | Wajib | Catatan |
|-------|------|:-----:|---------|
| `id` | UUID | Ya | |
| `lembaga_id` | UUID | Ya | |
| `nik_pegawai` | string(40) | Tidak | Digenerate otomatis (format NIY sama dengan guru) |
| `tahun_masuk` | smallint | Ya (create/import) | Tahun masuk; dipakai generate NIK/NIY |
| `nama` | string(150) | Ya | |
| `jenis_kelamin` | enum | Tidak | `L` \| `P` |
| `jabatan` | string(100) | Tidak | |
| `email` | string(150) | Tidak | |
| `telepon` | string(30) | Tidak | |
| `alamat` | text | Tidak | |
| `is_active` | boolean | Ya | |

---

### 3.8 Siswa

| Field | Tipe | Wajib | Catatan |
|-------|------|:-----:|---------|
| `id` | UUID | Ya | |
| `lembaga_id` | UUID | Ya | |
| `nis` | string(40) | Tidak | Kolom DB nullable (sync/compat); **Admin Lembaga UI wajib NIS** saat create/import |
| `nisn` | string(20) | Tidak | |
| `nama` | string(150) | Ya | |
| `jenis_kelamin` | enum | Tidak | `L` \| `P` |
| `tempat_lahir` | string(100) | Tidak | |
| `tanggal_lahir` | date | Tidak | |
| `kelas_id` | UUID null | Tidak | **Snapshot** kelas aktif; di-mirror dari penempatan terbuka (§3.8.1) |
| `tahun_ajaran_id` | UUID null | Tidak | **Snapshot** tahun ajaran aktif; di-mirror dari penempatan terbuka (§3.8.1) |
| `email` | string(150) | Tidak | |
| `telepon` | string(30) | Tidak | |
| `alamat` | text | Tidak | |
| `nama_wali` | string(150) | Tidak | |
| `telepon_wali` | string(30) | Tidak | |
| `is_active` | boolean | Ya | Diselaraskan otomatis oleh aksi lifecycle menurut `status_siswa` |
| `status_siswa` | string(30) | Ya | Status lifecycle operasional; default `aktif`. Enum: `calon` \| `mutasi_masuk` \| `aktif` \| `mutasi_keluar` \| `lulus` |
| `status_at` | date null | Tidak | Tanggal efektif status terkini |
| `status_alasan` | string(255) null | Tidak | Alasan singkat (mis. sebab mutasi keluar) |
| `status_asal` | string(150) null | Tidak | Asal (mis. nama sekolah untuk mutasi masuk) |
| `status_tujuan` | string(150) null | Tidak | Tujuan (mis. nama sekolah untuk mutasi keluar) |

Unik (**wajib**, partial): (`lembaga_id`, `nis`) WHERE nis IS NOT NULL; (`lembaga_id`, `nisn`) WHERE nisn IS NOT NULL  
Index tambahan: (`lembaga_id`, `status_siswa`) untuk filter list/API.  
Validasi: jika `kelas_id` terisi, `tahun_ajaran_id` wajib terisi dan harus cocok dengan `kelas.tahun_ajaran_id`

Semantik status:

- `is_active = false` berarti record masih valid secara historis, tetapi tidak aktif untuk operasional.
- `deleted_at != null` berarti record dihapus dari master aktif dan harus dikirim sebagai tombstone di sync.
- `status_siswa` adalah status lifecycle (berbeda dari soft delete). Transisi diizinkan (fase 1):
  - `calon` → `mutasi_masuk` \| `aktif`
  - `mutasi_masuk` → `aktif` \| `mutasi_keluar`
  - `aktif` → `aktif` (pindah/naik kelas) \| `mutasi_keluar` \| `lulus`
  - `mutasi_keluar` / `lulus` → (tidak dibuka di fase 1)
- `kelas_id` / `tahun_ajaran_id` **selalu** merupakan snapshot dari penempatan terbuka (`siswa_penempatan.selesai_at IS NULL`); dikosongkan untuk `mutasi_keluar`, `lulus`, dan `calon` tanpa kelas.
- Perubahan kelas siswa (naik/pindah/keluar/lulus) **hanya** boleh lewat aksi lifecycle (§7.x), bukan edit master biasa, agar histori penempatan tetap konsisten.

#### 3.8.1 Siswa penempatan (enrollment / histori kelas)

Tabel `siswa_penempatan` menyimpan riwayat penempatan siswa antar kelas/tahun ajaran. Snapshot `siswa.kelas_id` / `siswa.tahun_ajaran_id` selalu mirror dari baris terbuka.

| Field | Tipe | Wajib | Catatan |
|-------|------|:-----:|---------|
| `id` | UUID | Ya | PK |
| `lembaga_id` | UUID | Ya | Tenant |
| `siswa_id` | UUID | Ya | Composite FK `(lembaga_id, siswa_id)` → `siswa(lembaga_id, id)` |
| `tahun_ajaran_id` | UUID null | Tidak | Composite FK; null bila penempatan tanpa TA |
| `kelas_id` | UUID null | Tidak | Composite FK `(lembaga_id, tahun_ajaran_id, kelas_id)`; null bila tanpa kelas |
| `mulai_at` | date | Ya | Mulai penempatan |
| `selesai_at` | date null | Tidak | `null` = masih berjalan (terbuka) |
| `jenis` | string(30) | Ya | `awal` \| `kenaikan` \| `pindah_kelas` \| `mutasi_masuk` \| `mutasi_keluar` \| `lulus` |
| `keterangan` | text null | Tidak | Opsional |
| `created_at` / `updated_at` | timestamp | Ya | |

Aturan:

- Paling banyak **satu** baris terbuka (`selesai_at IS NULL`) per siswa — ditegakkan partial unique index `(lembaga_id, siswa_id) WHERE selesai_at IS NULL` (PostgreSQL & SQLite) **dan** di service layer.
- Naik/pindah/keluar/lulus: tutup baris terbuka (`selesai_at` = tanggal efektif, jejak historis dipertahankan) → buat baris baru bila masih ditempatkan.
- Index: `(lembaga_id, siswa_id)`, `(lembaga_id, updated_at, id)`, unik `(lembaga_id, id)`.
- Backfill migrasi: setiap siswa lama dengan `kelas_id` terisi → satu baris `jenis=awal` terbuka; siswa tanpa kelas tidak dibuatkan baris.

---

### 3.9 Audit log

| Field | Tipe | Wajib | Catatan |
|-------|------|:-----:|---------|
| `id` | UUID | Ya | |
| `user_id` | UUID null | Tidak | Null jika aksi sistem |
| `lembaga_id` | UUID null | Tidak | Scope jika relevan |
| `event` | string(80) | Ya | Mis. `api_key.rotate`, `user.deactivate`, `lembaga.create` |
| `result` | string(20) | Ya | `success` \| `failed` \| `blocked` |
| `subject_type` | string(80) | Tidak | Polymorphic type |
| `subject_id` | UUID null | Tidak | |
| `api_key_prefix` | string(16) null | Tidak | Jika event terkait API client |
| `request_id` | string(64) null | Tidak | Untuk korelasi log |
| `metadata` | json | Tidak | Tanpa secret/PII penuh; ukuran dibatasi |
| `ip_address` | string(45) | Tidak | |
| `user_agent` | string(255) null | Tidak | Dipotong agar tidak membesar |
| `created_at` | timestamp | Ya | |

Wajib log: buat/rotate/revoke API key, buat/nonaktifkan admin, buat/nonaktifkan lembaga, login gagal berulang (ringkas), akses/view data PII oleh admin, dan percobaan akses lintas lembaga. Audit log bersifat append-only.

### 3.10 Constraint multi-tenant DB

PostgreSQL wajib ikut menjaga batas lembaga, bukan hanya policy aplikasi:

- Setiap tabel tenant punya unique composite `(lembaga_id, id)`.
- Relasi tenant memakai composite FK, contoh `(lembaga_id, kelas_id)` → `kelas(lembaga_id, id)`.
- Berlaku untuk `kelas.tahun_ajaran_id`, `kelas.wali_kelas_guru_id`, `siswa.kelas_id`, `siswa.tahun_ajaran_id`, dan relasi sejenis.
- Index sync wajib: `(lembaga_id, updated_at, id)` dan `(lembaga_id, deleted_at, id)`.
- Untuk sync, implementasi boleh memakai generated expression/index `changed_at = greatest(updated_at, deleted_at)` bila memudahkan cursor.

---

## 4. API aplikasi konsumen

Base path resmi fase 1: `/api/v1`

Auth: header `X-API-Key` dari API client aktif.

### 4.1 Health

`GET /api/v1/health` → `{ "status": "ok" }` (tanpa key; **tanpa** versi/stack/app info)

### 4.2 Profil lembaga (dari key)

`GET /api/v1/me`

Response ringkas: `lembaga_id`, `kode`, `nama`, `is_active`, `client_id`, `client_name`, `scopes`, `field_profile`

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
| `active_only` | `true`/`false` (default false; memfilter `is_active`) |
| `fields` | `minimal`/`academic`/`contact` jika diizinkan oleh client |
| `page` | Halaman (default 1) |
| `per_page` | Item per halaman (default **100**, max **200**) |

Rate limit dasar: **120 request / menit / API key**, dengan limit tambahan per IP dan endpoint berat (429 jika melebihi).

> Catatan implementasi (M8): `per_page` di-**clamp** ke rentang 1..200 (nilai > 200 → 200), bukan ditolak 422. Kolom bertipe tanggal (mis. `tanggal_lahir`, `tanggal_mulai`, `mulai_at`) dikirim sebagai `Y-m-d`; timestamp/datetime (`created_at`, `updated_at`, `deleted_at`) sebagai ISO-8601 UTC `Z`.

Profil field:

- `minimal`: field identitas operasional saja, mis. `id`, `lembaga_id`, `nama`, kode induk utama, relasi kelas/tahun ajaran, `is_active`, timestamp.
- `academic`: `minimal` + field akademik/kepegawaian yang dibutuhkan aplikasi sekolah.
- `contact`: `academic` + kontak/alamat/wali. Profil ini hanya diberikan jika aplikasi konsumen benar-benar membutuhkan PII kontak.

Embed lifecycle siswa (M8): profil `academic` menambahkan objek `penempatan_aktif` (`id`, `kelas_id`, `tahun_ajaran_id`, `mulai_at`, `jenis`) atau `null`; profil `contact` menambahkan juga array `riwayat_penempatan` (urut `mulai_at` asc, plus `selesai_at`).

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

Jika response punya `next_cursor`, client melanjutkan dengan:

`GET /api/v1/{resource}/sync?since=2026-07-15T10:00:00Z&cursor=<next_cursor>&watermark=<watermark>`

Aturan:

- Wajib `since` (ISO-8601 UTC); format invalid → **400**.
- `since` di masa depan → **400**.
- Umur `since` > **90 hari** → **400** dengan pesan gunakan tarik penuh.
- Server menetapkan `watermark` UTC pada awal sync; semua page/cursor dalam satu sesi hanya mengambil perubahan `> since` dan `<= watermark`.
- Kembalikan record dengan `changed_at > since` dan `changed_at <= watermark`, dengan `changed_at = greatest(updated_at, deleted_at)`.
- Urutan hasil: `(changed_at ASC, id ASC)`.
- Paginasi sync memakai cursor berbasis `(changed_at, id)`, bukan page number.
- `per_page` default 100, max 200.
- Tombstone soft-delete cukup memuat `id`, `deleted_at`, `changed_at`, dan relasi minimum; jangan dump PII penuh untuk record terhapus.
- Tidak ada endpoint sync terpisah untuk `siswa_penempatan`. Perubahan enrollment/status penempatan tersinkron lewat `GET /api/v1/siswa/sync` karena lifecycle M6c selalu menyentuh `siswa.updated_at` saat mutasi penempatan.

Response:

```json
{
  "resource": "siswa",
  "lembaga_id": "...",
  "since": "2026-07-15T10:00:00Z",
  "watermark": "2026-07-15T13:05:00Z",
  "synced_at": "2026-07-15T13:05:00Z",
  "changes": [
    { "id": "...", "changed_at": "2026-07-15T12:01:00Z", "deleted_at": null, "...": "..." },
    { "id": "...", "changed_at": "2026-07-15T12:02:00Z", "deleted_at": "2026-07-15T12:02:00Z" }
  ],
  "change_count": 2,
  "next_cursor": null
}
```

App menyimpan `watermark`/`synced_at` terakhir hanya setelah semua cursor selesai (`next_cursor = null`).

### 4.5 Error response (konsisten)

```json
{
  "message": "since tidak valid",
  "code": "INVALID_SINCE",
  "request_id": "..."
}
```

HTTP: 401 (auth), 403 (lembaga nonaktif / forbidden), 429 (rate limit), 400 (validasi).

Kode error resmi fase 1:

| Code | HTTP | Arti |
|------|:---:|------|
| `UNAUTHENTICATED` | 401 | API key tidak ada/salah |
| `FORBIDDEN` | 403 | Scope tidak cukup atau akses ditolak |
| `LEMBAGA_INACTIVE` | 403 | Lembaga nonaktif |
| `API_CLIENT_INACTIVE` | 403 | API client dicabut/nonaktif |
| `RATE_LIMITED` | 429 | Terlalu banyak request |
| `INVALID_SINCE` | 400 | Format `since` salah |
| `SINCE_TOO_OLD` | 400 | `since` melewati batas umur sync |
| `INVALID_CURSOR` | 400 | Cursor/watermark tidak valid |
| `VALIDATION_FAILED` | 422 | Input dashboard/admin tidak valid |

### 4.6 Export multi-resource

**Ditunda fase 2.** Fase 1 cukup tarik per-resource (§4.3).

---

## 5. UI dashboard (wireframe konsep)

Stack UI fase 1: **Blade + Livewire**. Bahasa: **Indonesia**.

### 5.0 Layout umum

- Sidebar kiri: menu sesuai role.
- Header: nama user, lembaga (Admin Lembaga), logout.
- Area konten: breadcrumb + judul halaman + aksi utama (Tambah).
- Pola list: tabel + search + pagination + badge status (aktif/nonaktif).
- Pola form: validasi inline; field wajib ditandai `*`.
- Empty state: ilustrasi/teks + CTA "Tambah …".
- Loading: skeleton/spinner pada list & submit form.
- Aksi destruktif (hapus, nonaktifkan, rotate key): **modal konfirmasi** + penjelasan dampak.

### 5.1 Super Admin

- Login
- Dashboard ringkas: jumlah lembaga aktif/nonaktif
- Daftar lembaga → buat/edit/aktif-nonaktif (nonaktif → konfirmasi dampak API & admin)
- Detail lembaga → buat Admin Lembaga, buat/rotate/revoke API client/key
  - **Buat client:** isi nama aplikasi, scope resource, dan profil field.
  - **Rotate key:** modal peringatan "app konsumen harus update key segera"; key lama dicabut.
  - **Generate key:** layar copy-once; teks "key tidak bisa dilihat lagi".
- (Opsional) pantau jumlah master per lembaga

### 5.2 Admin Lembaga

- Login (ditolak jika lembaga nonaktif)
- Menu (urutan wajib): Tahun ajaran → Guru → Kelas → Siswa → Karyawan
- List siswa/guru: search by nama, NIS/NISN
- Form create/edit + soft delete (konfirmasi)
- Siswa tanpa kelas: badge **"Belum ada kelas"**
- Lihat daftar **API client + prefix + scope** saja (read-only); tidak bisa rotate/revoke

### 5.3 Integrasi di app lain (bukan UI Pusat Data)

- Tombol **Tarik data dari Data Center** → panggil tarik penuh semua resource yang dibutuhkan
- Tombol **Sinkron** → panggil sync delta per resource dengan `since` terakhir
- Tampilkan status: "Terakhir sinkron: …" per resource

---

## 6. Deploy VPS (spesifikasi operasional)

| Komponen | Spesifikasi target |
|----------|--------------------|
| OS | Ubuntu LTS |
| Web | **Apache** (mod_php atau php-fpm + proxy sesuai setup VPS) |
| App | Laravel (PHP 8.3+) |
| DB | PostgreSQL 16 (hanya jaringan privat / localhost) |
| TLS | HTTPS (Let's Encrypt); HTTP → HTTPS |
| Proteksi edge | **Cloudflare** orange cloud — **wajib sebelum produksi publik** |
| Proteksi abuse | Rate limit Laravel + limit Apache + firewall/fail2ban VPS + Cloudflare |
| Backup | Dump DB harian terenkripsi + offsite; tidak publik |

ENV rahasia: `APP_KEY`, DB password, tidak commit ke git.

**Catatan:** Nginx **tidak** digunakan. Cloudflare belum aktif saat ini; direncanakan ditambahkan sebelum go-live publik.

---

## 6.1 Keamanan (ringkas — detail di RULES B4)

Wajib sebelum produksi:

1. HTTPS + header keamanan dasar
2. Isolasi multi-tenant di server
3. API key prefix + HMAC digest + read-only
4. Throttle login & API
5. DB tidak terbuka ke publik
6. Proteksi DDoS/abuse berlapis: **Cloudflare** (wajib produksi) + rate limit app + Apache + firewall/fail2ban VPS
7. Backup terenkripsi + offsite + uji restore; RPO fase 1 max 24 jam, RTO target max 4 jam
8. Origin VPS di-harden agar tidak mudah di-bypass dari Cloudflare
9. CORS default deny; integrasi fase 1 server-to-server
10. Password admin min 12 karakter; login throttle 5/menit/email+IP dan limit tambahan per IP
11. MFA/TOTP Super Admin aktif sebelum produksi publik

---

## 7. Hal yang sengaja belum di-spec (fase berikutnya)

> Histori perpindahan kelas siswa antar tahun (tabel enrollment) **sudah di-spec** di §3.8.1 (`siswa_penempatan`) dan diimplementasikan di Milestone 6c — lihat [design M6c](./superpowers/specs/2026-07-22-milestone-6c-siswa-lifecycle-design.md).

- Foto/dokumen upload
- Webhook push perubahan
- Import Excel massal — **fase 2** (D9; fase 1 input manual)
- Export multi-resource API (§4.6)
- Whitelist CORS origin per lembaga

---

## 8. Status dokumen

**DISETUJUI — 18 Jul 2026.** Semua keputusan (D1–D18) terkunci di [RULES.md §D](./RULES.md).

Perubahan requirement setelah tanggal ini: update SPEC/RULES → review → baru ubah kode.
