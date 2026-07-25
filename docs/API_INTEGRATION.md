# Panduan integrasi API (fase 1)

Untuk developer aplikasi konsumen lembaga. Kontrak normatif: [SPEC.md](./SPEC.md) §2.2 dan §4. Ringkasan akses vs proxy: [PRODUCTION_NOTES.md](./PRODUCTION_NOTES.md).

## 1. Ringkasan

API fase 1 ditujukan untuk integrasi server-to-server: backend aplikasi lembaga mengirim request ke API memakai API key. Jangan memanggil API langsung dari browser; CORS menolak origin browser secara default.

Seluruh endpoint API ini bersifat read-only dan hanya menerima `GET`. Gunakan base URL placeholder berikut pada contoh, lalu ganti dengan host yang diberikan operator:

```text
https://data.example.id
```

## 2. Quick start

### Prasyarat

- Base URL production diberikan oleh operator Pusat Data; `https://data.example.id` dalam dokumen ini hanya placeholder.
- Super Admin membuat atau me-rotate API client untuk lembaga melalui admin UI, lalu menyerahkan plain key kepada integrator satu kali. Simpan key itu di secret store; Admin Lembaga hanya dapat melihat daftar client milik lembaganya dan tidak dapat membuat atau me-rotate key.
- Ganti API key dan UUID dummy dalam seluruh contoh dengan nilai milik Anda.

Contoh berikut memakai satu API key dan UUID dummy yang konsisten. Key `dc_live_demo12345678_00000000000000000000000000000000` adalah data contoh, bukan secret asli.

### Langkah 1 — Periksa layanan

```bash
curl --request GET \
  https://data.example.id/api/v1/health
```

```json
{"status":"ok"}
```

### Langkah 2 — Periksa identitas client

```bash
curl --request GET \
  --header 'X-API-Key: dc_live_demo12345678_00000000000000000000000000000000' \
  https://data.example.id/api/v1/me
```

```json
{
  "lembaga_id": "11111111-1111-4111-8111-111111111111",
  "kode": "DEMO",
  "nama": "Lembaga Demo",
  "is_active": true,
  "client_id": "22222222-2222-4222-8222-222222222222",
  "client_name": "Integrasi Demo",
  "scopes": ["guru:read"],
  "field_profile": "minimal"
}
```

### Langkah 3 — Ambil satu data guru

Client pada contoh `/me` memiliki scope `guru:read` dan profil field `minimal`, sehingga respons berikut berisi field profil minimal. Parameter `per_page` dan parameter query lain dijelaskan di §6.

```bash
curl --request GET \
  --header 'X-API-Key: dc_live_demo12345678_00000000000000000000000000000000' \
  'https://data.example.id/api/v1/guru?per_page=1'
```

```json
{
  "resource": "guru",
  "lembaga_id": "11111111-1111-4111-8111-111111111111",
  "synced_at": "2026-07-25T02:00:00Z",
  "data": [
    {
      "id": "33333333-3333-4333-8333-333333333333",
      "lembaga_id": "11111111-1111-4111-8111-111111111111",
      "niy": "G-001",
      "nama": "Siti Aminah",
      "is_active": true,
      "created_at": "2026-07-25T02:00:00Z",
      "updated_at": "2026-07-25T02:00:00Z"
    }
  ],
  "meta": {
    "page": 1,
    "per_page": 1,
    "total": 1
  }
}
```

## 3. Autentikasi

Kirim API key dengan header utama `X-API-Key`:

```http
X-API-Key: dc_live_<prefix>_<secret>
```

Sebagai alternatif, gunakan `Authorization: Bearer`:

```http
Authorization: Bearer dc_live_<prefix>_<secret>
```

Apabila kedua header ada, nilai `X-API-Key` yang tidak kosong diprioritaskan. Format key adalah `dc_live_<prefix>_<secret>`; `prefix` dan `secret` hanya berisi huruf atau angka. Simpan plain key dengan aman: plain key hanya ditampilkan sekali ketika dibuat atau di-rotate.

| HTTP | `code` | Pesan |
|---:|---|---|
| 401 | `UNAUTHENTICATED` | `Autentikasi gagal.` |
| 403 | `API_CLIENT_INACTIVE` | `API client tidak aktif.` |
| 403 | `LEMBAGA_INACTIVE` | `Lembaga tidak aktif.` |

Contoh key hilang, salah format, tidak dikenal, atau tidak cocok akan menghasilkan 401:

```json
{
  "message": "Autentikasi gagal.",
  "code": "UNAUTHENTICATED",
  "request_id": "44444444-4444-4444-8444-444444444444"
}
```

## 4. Endpoint dan envelope

| Method | Path | Autentikasi | Keterangan |
|---|---|---|---|
| `GET` | `/api/v1/health` | Tidak | Pemeriksaan layanan |
| `GET` | `/api/v1/me` | Ya | Identitas lembaga dan API client pemanggil |
| `GET` | `/api/v1/{resource}` | Ya | Daftar snapshot resource |
| `GET` | `/api/v1/{resource}/sync` | Ya | Perubahan resource sejak waktu tertentu |

Nilai `{resource}` hanya dapat berupa `tahun-ajaran`, `guru`, `kelas`, `siswa`, atau `karyawan`. Method selain `GET` menghasilkan `405 Method Not Allowed`.

Envelope daftar memiliki bentuk berikut. `synced_at` adalah waktu server saat response dibuat, dalam UTC:

```json
{
  "resource": "guru",
  "lembaga_id": "11111111-1111-4111-8111-111111111111",
  "synced_at": "2026-07-25T02:00:00Z",
  "data": [],
  "meta": {
    "page": 1,
    "per_page": 100,
    "total": 0
  }
}
```

Envelope sync memiliki bentuk berikut. Detail alur tarik penuh dan sync delta diisi pada §6–§7.

```json
{
  "resource": "guru",
  "lembaga_id": "11111111-1111-4111-8111-111111111111",
  "since": "2026-07-24T02:00:00Z",
  "watermark": "2026-07-25T02:00:00Z",
  "synced_at": "2026-07-25T02:00:00Z",
  "changes": [],
  "change_count": 0,
  "next_cursor": null
}
```

## 5. Resource, scope, dan profil field

Setiap resource memerlukan scope read berikut:

| Resource | Scope |
|---|---|
| `tahun-ajaran` | `tahun_ajaran:read` |
| `guru` | `guru:read` |
| `kelas` | `kelas:read` |
| `siswa` | `siswa:read` |
| `karyawan` | `karyawan:read` |

Profil field bertingkat: `minimal ⊂ academic ⊂ contact`. Tanpa query parameter `fields`, profil efektif adalah profil yang di-assign ke client, bukan selalu `minimal`. Client dapat meminta profil lebih rendah; permintaan profil di atas ceiling client menghasilkan `403 FORBIDDEN` dengan pesan `Profil field tidak diizinkan.`.

Daftar berikut adalah field kumulatif persis yang tersedia pada setiap profil.

### `tahun-ajaran`

Ketiga profil (`minimal`, `academic`, dan `contact`) identik: `id`, `lembaga_id`, `nama`, `tanggal_mulai`, `tanggal_selesai`, `is_aktif`, `created_at`, `updated_at`.

### `guru`

| Profil | Field |
|---|---|
| `minimal` | `id`, `lembaga_id`, `niy`, `nama`, `is_active`, `created_at`, `updated_at` |
| `academic` | `id`, `lembaga_id`, `niy`, `nama`, `is_active`, `created_at`, `updated_at`, `nuptk`, `tahun_masuk`, `jenis_kelamin`, `tempat_lahir`, `tanggal_lahir`, `status_kepegawaian` |
| `contact` | `id`, `lembaga_id`, `niy`, `nama`, `is_active`, `created_at`, `updated_at`, `nuptk`, `tahun_masuk`, `jenis_kelamin`, `tempat_lahir`, `tanggal_lahir`, `status_kepegawaian`, `email`, `telepon`, `alamat` |

### `kelas`

| Profil | Field |
|---|---|
| `minimal` | `id`, `lembaga_id`, `tahun_ajaran_id`, `nama`, `created_at`, `updated_at` |
| `academic` | `id`, `lembaga_id`, `tahun_ajaran_id`, `nama`, `created_at`, `updated_at`, `tingkat`, `wali_kelas_guru_id` |
| `contact` | `id`, `lembaga_id`, `tahun_ajaran_id`, `nama`, `created_at`, `updated_at`, `tingkat`, `wali_kelas_guru_id` |

### `siswa`

| Profil | Field |
|---|---|
| `minimal` | `id`, `lembaga_id`, `nis`, `nama`, `status_siswa`, `is_active`, `kelas_id`, `tahun_ajaran_id`, `created_at`, `updated_at` |
| `academic` | `id`, `lembaga_id`, `nis`, `nama`, `status_siswa`, `is_active`, `kelas_id`, `tahun_ajaran_id`, `created_at`, `updated_at`, `nisn`, `jenis_kelamin`, `tempat_lahir`, `tanggal_lahir`, `status_at`, `status_alasan`, `status_asal`, `status_tujuan` |
| `contact` | `id`, `lembaga_id`, `nis`, `nama`, `status_siswa`, `is_active`, `kelas_id`, `tahun_ajaran_id`, `created_at`, `updated_at`, `nisn`, `jenis_kelamin`, `tempat_lahir`, `tanggal_lahir`, `status_at`, `status_alasan`, `status_asal`, `status_tujuan`, `email`, `telepon`, `alamat`, `nama_wali`, `telepon_wali` |

Profil `academic` menambahkan embed `penempatan_aktif`: objek atau `null` dengan field `id`, `kelas_id`, `tahun_ajaran_id`, `mulai_at`, dan `jenis`. Profil `contact` juga menambahkan `riwayat_penempatan`: array objek yang masing-masing memiliki `id`, `kelas_id`, `tahun_ajaran_id`, `mulai_at`, `selesai_at`, dan `jenis`.

### `karyawan`

| Profil | Field |
|---|---|
| `minimal` | `id`, `lembaga_id`, `nik_pegawai`, `nama`, `is_active`, `created_at`, `updated_at` |
| `academic` | `id`, `lembaga_id`, `nik_pegawai`, `nama`, `is_active`, `created_at`, `updated_at`, `tahun_masuk`, `jenis_kelamin`, `jabatan` |
| `contact` | `id`, `lembaga_id`, `nik_pegawai`, `nama`, `is_active`, `created_at`, `updated_at`, `tahun_masuk`, `jenis_kelamin`, `jabatan`, `email`, `telepon`, `alamat` |

Nilai kolom bertipe date menggunakan format `Y-m-d`. Timestamp, termasuk `created_at`, `updated_at`, dan `deleted_at`, selalu UTC dalam format `Y-m-d\TH:i:s\Z`, misalnya `2026-07-25T02:00:00Z`.

## 6. Tarik penuh

_(diisi pada bagian berikutnya)_

## 7. Sync delta

_(diisi pada bagian berikutnya)_

## 8. Error dan rate limit

_(diisi pada bagian berikutnya)_

## 9. Retry

_(diisi pada bagian berikutnya)_

## 10. Checklist sebelum production

_(diisi pada bagian berikutnya)_
