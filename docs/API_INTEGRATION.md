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

Gunakan endpoint daftar untuk membuat snapshot awal atau menarik ulang seluruh resource:

```bash
curl --request GET \
  --header 'X-API-Key: dc_live_demo12345678_00000000000000000000000000000000' \
  'https://data.example.id/api/v1/guru?include_deleted=true&active_only=true&fields=minimal&per_page=200'
```

| Parameter | Default | Keterangan |
|---|---:|---|
| `include_deleted` | `false` | Jika `true`, sertakan record soft-deleted dan field `deleted_at` pada setiap baris. |
| `active_only` | `false` | Jika `true`, hanya record dengan kolom aktif bernilai `true`. Berlaku untuk `tahun-ajaran` (`is_aktif`), `guru`, `siswa`, dan `karyawan` (`is_active`); tidak berpengaruh pada `kelas`. |
| `fields` | profil client | Profil field yang diminta (`minimal`, `academic`, atau `contact`), selama tidak melampaui profil client seperti dijelaskan di §5. |
| `page` | `1` | Nomor halaman, mulai dari 1. |
| `per_page` | `100` | Jumlah baris per halaman. Nilai dibatasi (clamp) ke 1–200; nilai di atas 200 menjadi 200, bukan error. |

Urutan stabil membantu pemrosesan halaman: `nama ASC, id ASC`. Resource `tahun-ajaran` merupakan pengecualian: `nama DESC, id ASC`.

Contoh response daftar lengkap berikut menunjukkan hasil dengan `include_deleted=true` dan profil `minimal`:

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
      "updated_at": "2026-07-25T02:00:00Z",
      "deleted_at": null
    },
    {
      "id": "44444444-4444-4444-8444-444444444444",
      "lembaga_id": "11111111-1111-4111-8111-111111111111",
      "niy": "G-002",
      "nama": "Tono Pratama",
      "is_active": true,
      "created_at": "2026-07-24T02:00:00Z",
      "updated_at": "2026-07-24T02:00:00Z",
      "deleted_at": "2026-07-25T01:30:00Z"
    }
  ],
  "meta": {
    "page": 1,
    "per_page": 200,
    "total": 2
  }
}
```

Untuk melanjutkan ke halaman kedua, ubah hanya parameter `page`:

```bash
curl --request GET \
  --header 'X-API-Key: dc_live_demo12345678_00000000000000000000000000000000' \
  'https://data.example.id/api/v1/guru?page=2&per_page=200'
```

## 7. Sync delta

Sync delta mengambil perubahan setelah `since` tanpa kehilangan perubahan yang datang ketika halaman sedang diproses:

1. Halaman pertama: kirim `since` saja. Server menetapkan `watermark` UTC untuk rangkaian sync itu.
2. Jika `next_cursor` tidak `null`, kirim ulang `since` yang sama, `watermark` yang sama, dan `cursor` dari response sebelumnya.
3. Terapkan setiap halaman secara idempoten di penyimpanan lokal. Simpan `watermark` (atau `synced_at` lokal) hanya setelah halaman dengan `next_cursor === null` berhasil diterapkan.

`cursor` bersifat opaque: jangan di-decode, dimodifikasi, atau dibuat sendiri. Mengirim `watermark` tanpa `cursor` menghasilkan `INVALID_CURSOR`. Parameter `fields` didukung dan mengikuti ceiling profil client yang sama seperti endpoint daftar (§5). `per_page` juga menggunakan default 100 dan clamp 1–200.

Tidak ada endpoint sync tersendiri untuk penempatan siswa. Perubahan penempatan memperbarui `siswa.updated_at`, sehingga akan muncul pada sync resource `siswa`.

Contoh halaman pertama:

```bash
curl --request GET \
  --header 'X-API-Key: dc_live_demo12345678_00000000000000000000000000000000' \
  'https://data.example.id/api/v1/guru/sync?since=2026-07-24T02%3A00%3A00Z&per_page=2'
```

```json
{
  "resource": "guru",
  "lembaga_id": "11111111-1111-4111-8111-111111111111",
  "since": "2026-07-24T02:00:00Z",
  "watermark": "2026-07-25T02:00:00Z",
  "synced_at": "2026-07-25T02:00:00Z",
  "changes": [
    {
      "id": "33333333-3333-4333-8333-333333333333",
      "lembaga_id": "11111111-1111-4111-8111-111111111111",
      "niy": "G-001",
      "nama": "Siti Aminah",
      "is_active": true,
      "created_at": "2026-07-25T01:00:00Z",
      "updated_at": "2026-07-25T01:00:00Z",
      "changed_at": "2026-07-25T01:00:00Z",
      "deleted_at": null
    },
    {
      "id": "44444444-4444-4444-8444-444444444444",
      "deleted_at": "2026-07-25T01:30:00Z",
      "changed_at": "2026-07-25T01:30:00Z"
    }
  ],
  "change_count": 2,
  "next_cursor": "eyJjIjoiMjAyNi0wNy0yNVQwMTozMDowMFoiLCJpIjoiNDQ0NDQ0NDQtNDQ0NC00NDQ0LTg0NDQtNDQ0NDQ0NDQ0NDQ0In0"
}
```

Tombstone selalu tepat berbentuk `{id, deleted_at, changed_at}`; jangan mengharapkan field resource lain pada record yang telah dihapus.

Gunakan cursor apa adanya untuk halaman berikut:

```bash
curl --request GET \
  --header 'X-API-Key: dc_live_demo12345678_00000000000000000000000000000000' \
  'https://data.example.id/api/v1/guru/sync?since=2026-07-24T02%3A00%3A00Z&watermark=2026-07-25T02%3A00%3A00Z&cursor=eyJjIjoiMjAyNi0wNy0yNVQwMTozMDowMFoiLCJpIjoiNDQ0NDQ0NDQtNDQ0NC00NDQ0LTg0NDQtNDQ0NDQ0NDQ0NDQ0In0&per_page=2'
```

Response terakhir tidak memiliki cursor lanjutan:

```json
{
  "resource": "guru",
  "lembaga_id": "11111111-1111-4111-8111-111111111111",
  "since": "2026-07-24T02:00:00Z",
  "watermark": "2026-07-25T02:00:00Z",
  "synced_at": "2026-07-25T02:01:00Z",
  "changes": [
    {
      "id": "22222222-2222-4222-8222-222222222222",
      "lembaga_id": "11111111-1111-4111-8111-111111111111",
      "niy": "G-003",
      "nama": "Wati Lestari",
      "is_active": true,
      "created_at": "2026-07-25T01:45:00Z",
      "updated_at": "2026-07-25T01:45:00Z",
      "changed_at": "2026-07-25T01:45:00Z",
      "deleted_at": null
    }
  ],
  "change_count": 1,
  "next_cursor": null
}
```

Nilai `since` maksimum berumur 90 hari secara default. Operator dapat mengubahnya melalui `API_SYNC_MAX_SINCE_DAYS`. Jika server mengembalikan `SINCE_TOO_OLD`, lakukan fallback ke tarik penuh, lalu mulai rangkaian sync delta berikutnya dari waktu snapshot lokal selesai diterapkan:

```json
{
  "message": "Parameter since terlalu lama; gunakan tarik penuh.",
  "code": "SINCE_TOO_OLD",
  "request_id": "44444444-4444-4444-8444-444444444444"
}
```

## 8. Error dan rate limit

Error bisnis menggunakan envelope berikut:

```json
{
  "message": "Pesan kesalahan.",
  "code": "ERROR_CODE",
  "request_id": "44444444-4444-4444-8444-444444444444"
}
```

| HTTP | `code` | Pesan persis |
|---:|---|---|
| 401 | `UNAUTHENTICATED` | `Autentikasi gagal.` |
| 403 | `API_CLIENT_INACTIVE` | `API client tidak aktif.` |
| 403 | `LEMBAGA_INACTIVE` | `Lembaga tidak aktif.` |
| 403 | `FORBIDDEN` | `Scope tidak mencukupi.` atau `Profil field tidak diizinkan.` |
| 429 | `RATE_LIMITED` | `Terlalu banyak permintaan.` |
| 400 | `INVALID_SINCE` | `Parameter since tidak valid.` |
| 400 | `SINCE_TOO_OLD` | `Parameter since terlalu lama; gunakan tarik penuh.` |
| 400 | `INVALID_CURSOR` | `Cursor atau watermark tidak valid.` |

Klien dapat mengirim `X-Request-ID` sepanjang 8–64 karakter yang hanya berisi `[A-Za-z0-9._-]`. Nilai valid tersebut di-echo pada header response dan field `request_id`; bila tidak dikirim atau tidak valid, server membuat ID baru.

Error validasi parameter yang ditangani Laravel menggunakan HTTP 422 dengan bentuk `{message, errors}`, bukan envelope bisnis. Slug resource yang tidak dikenal menghasilkan 404 JSON framework.

Batas default adalah 120 request per menit per API key dan 240 request per menit per IP. Keduanya dapat diubah operator melalui `API_RATE_PER_MINUTE` dan `API_IP_RATE_PER_MINUTE`. Jika salah satu batas terlampaui, response 429 membawa header `Retry-After` dalam detik. API tidak mengirim header `X-RateLimit-*`.

```http
HTTP/1.1 429 Too Many Requests
Content-Type: application/json
Retry-After: 37
X-Request-ID: 44444444-4444-4444-8444-444444444444
```

```json
{
  "message": "Terlalu banyak permintaan.",
  "code": "RATE_LIMITED",
  "request_id": "44444444-4444-4444-8444-444444444444"
}
```

## 9. Retry

- Untuk HTTP 429, tunggu selama nilai header `Retry-After`, lalu ulangi request yang identik.
- Untuk HTTP 5xx, timeout, atau gangguan jaringan, gunakan backoff eksponensial dengan jitter. Jumlah percobaan maksimum adalah pilihan integrator sesuai kebutuhan operasionalnya.
- Request sync aman diulang: gunakan triplet `since`, `watermark`, dan `cursor` yang sama persis untuk halaman yang gagal.
- Jangan menaikkan watermark lokal sebelum menerima dan berhasil menerapkan halaman dengan `next_cursor === null`.
- Jangan retry otomatis untuk 400, 401, atau 403. Perbaiki parameter, API key, status client/lembaga, scope, atau profil field terlebih dahulu.

## 10. Checklist sebelum production

- [ ] Simpan API key di secret store dan jangan pernah menulis plain key ke log.
- [ ] Verifikasi scope dan profil field client sesuai data yang benar-benar dibutuhkan.
- [ ] Uji sync multi-halaman, termasuk penerapan tombstone.
- [ ] Tangani 429 dan 5xx dengan aturan retry di §9.
- [ ] Siapkan fallback tarik penuh untuk `SINCE_TOO_OLD`.
- [ ] Jadwalkan sync berkala sesuai kebutuhan aplikasi.
- [ ] Simpan watermark sync secara persisten dan perbarui hanya setelah rangkaian sync selesai.
