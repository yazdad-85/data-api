# Design — Milestone 8: API Tarik Penuh

Status: **Approved**  
Tanggal: 2026-07-24  
Basis: [SPEC.md](../../SPEC.md) §4.3, §4.5; [RULES.md](../../RULES.md) A8, B4; [IMPLEMENTATION_TODO.md](../../IMPLEMENTATION_TODO.md) Milestone 8; M7 [2026-07-23-milestone-7-api-client-auth-design.md](./2026-07-23-milestone-7-api-client-auth-design.md); M6c [2026-07-22-milestone-6c-siswa-lifecycle-design.md](./2026-07-22-milestone-6c-siswa-lifecycle-design.md)

## 1. Tujuan

Menyediakan **tarik penuh (snapshot)** resource master via API konsumen: enforce scope per resource, field profile bertingkat, query filter/pagination, dan embed lifecycle siswa — di atas auth/rate limit M7. Tanpa sync delta (M9).

## 2. Keputusan yang disepakati

| Topik | Keputusan |
|-------|-----------|
| Pendekatan | **Resource registry + shared list pipeline** (bukan controller duplikat per resource) |
| Endpoint baru | `GET /api/v1/{guru\|siswa\|karyawan\|kelas\|tahun-ajaran}` |
| Sudah ada (M7) | `GET /api/v1/health`, `GET /api/v1/me` — tidak dikerjakan ulang |
| Scope | Wajib `{resource}:read` pada client; tanpa scope → **403** `FORBIDDEN` |
| Field profile | `minimal` ⊂ `academic` ⊂ `contact` |
| Query `fields` | Default = `client.field_profile`; boleh sama/lebih sempit; **lebih luas → 403** `FORBIDDEN` |
| Siswa lifecycle | `status_siswa` (+ snapshot kelas/TA) di `minimal`; **`penempatan_aktif`** di `academic`; **`riwayat_penempatan`** di `contact` |
| `per_page` | Default 100, **clamp** ke max 200 (bukan 422) |
| Sync `/sync` | **Di luar** → M9 |
| Tenant query | `withoutGlobalScopes()` + `where lembaga_id = client.lembaga_id` (scope web Auth tidak berlaku di API) |

## 3. Di luar scope M8

- `GET /api/v1/{resource}/sync` dan cursor/watermark (M9)
- Write/update/delete via API key
- CORS whitelist per lembaga
- Endpoint terpisah khusus penempatan
- Perubahan UI admin

## 4. Routes & otorisasi

Prefix `/api/v1`, middleware: `api.client` + `api.throttle` (M7).

| Method | Path | Scope |
|--------|------|-------|
| GET | `/tahun-ajaran` | `tahun_ajaran:read` |
| GET | `/guru` | `guru:read` |
| GET | `/kelas` | `kelas:read` |
| GET | `/siswa` | `siswa:read` |
| GET | `/karyawan` | `karyawan:read` |

Slug URL `tahun-ajaran` ↔ scope token `tahun_ajaran:read`.  
Non-GET → **405**. Missing/insufficient scope → **403** `FORBIDDEN`.

## 5. Query parameters

| Param | Default | Aturan |
|-------|---------|--------|
| `include_deleted` | `false` | `true` → `withTrashed()`, sertakan `deleted_at` di payload |
| `active_only` | `false` | `true` → filter kolom aktif (`is_active` atau `is_aktif` untuk tahun ajaran) |
| `fields` | profil client | `minimal` \| `academic` \| `contact`; tidak boleh melebihi profil client |
| `page` | `1` | integer ≥ 1 |
| `per_page` | `100` | integer; nilai > 200 di-**clamp** ke 200 |

## 6. Field profiles

### 6.1 Umum

- `minimal`: identitas operasional + status aktif + timestamps (+ relasi id yang dibutuhkan operasional).
- `academic`: `minimal` + field akademik/kepegawaian.
- `contact`: `academic` + kontak/alamat/wali (PII).

### 6.2 Per resource

| Resource | `minimal` | `academic` (+minimal) | `contact` (+academic) |
|----------|-----------|----------------------|------------------------|
| **tahun-ajaran** | id, lembaga_id, nama, tanggal_mulai, tanggal_selesai, is_aktif, created_at, updated_at | (=) | (=) |
| **guru** | id, lembaga_id, niy, nama, is_active, created_at, updated_at | nuptk, tahun_masuk, jenis_kelamin, tempat_lahir, tanggal_lahir, status_kepegawaian | email, telepon, alamat |
| **karyawan** | id, lembaga_id, nik_pegawai, nama, is_active, created_at, updated_at | tahun_masuk, jenis_kelamin, jabatan | email, telepon, alamat |
| **kelas** | id, lembaga_id, tahun_ajaran_id, nama, created_at, updated_at | tingkat, wali_kelas_guru_id | (=) |
| **siswa** | id, lembaga_id, nis, nama, status_siswa, is_active, kelas_id, tahun_ajaran_id, created_at, updated_at | nisn, jenis_kelamin, tempat_lahir, tanggal_lahir, status_at, status_alasan, status_asal, status_tujuan, **penempatan_aktif** | email, telepon, alamat, nama_wali, telepon_wali, **riwayat_penempatan** |

Jika `include_deleted=true`, tambahkan `deleted_at` (semua resource soft-delete).

### 6.3 Embed siswa

**`penempatan_aktif`** (satu objek atau `null`):

- `id`, `kelas_id`, `tahun_ajaran_id`, `mulai_at`, `jenis`

**`riwayat_penempatan`** (array, urutan `mulai_at` asc / `created_at`):

- `id`, `kelas_id`, `tahun_ajaran_id`, `mulai_at`, `selesai_at`, `jenis`

Tanpa dump `keterangan` panjang kecuali dibutuhkan nanti (YAGNI: tidak di M8).

## 7. Response envelope

```json
{
  "resource": "siswa",
  "lembaga_id": "...",
  "synced_at": "2026-07-24T01:00:00Z",
  "data": [ { "...": "..." } ],
  "meta": { "page": 1, "per_page": 100, "total": 250 }
}
```

- `synced_at`: waktu UTC server saat response digenerate (ISO-8601 dengan `Z`).
- Timestamps record juga ISO-8601 UTC.
- Error: reuse `ApiErrorResponse` M7 (`message`, `code`, `request_id`).

## 8. Arsitektur

| Komponen | Peran |
|----------|--------|
| `App\Support\Api\ApiResourceCatalog` | Slug → model class, scope name, active column, field sets, eager loads per profile |
| `App\Services\Api\ApiFieldProfiler` | Resolve effective profile; 403 jika `fields` naik |
| `App\Services\Api\ApiResourceLister` | Build query (tenant, deleted, active), paginate, transform rows |
| `App\Http\Middleware\EnsureApiScope` (atau check di controller) | Pastikan scope ada di `api_client.scopes` |
| `App\Http\Controllers\Api\V1\ResourceListController` | Entry `GET /{resource}` |
| Transform helpers | Map model → array sesuai profile; siswa embeds |

**Query tenant (wajib):**

```php
Model::withoutGlobalScopes()
    ->where('lembaga_id', $client->lembaga_id)
    ...
```

Eager load untuk siswa: `penempatanAktif` bila profile ≥ academic; `penempatans` bila ≥ contact (atau satu query penempatans lalu filter aktif di transformer).

## 9. Testing (wajib)

- Tanpa scope resource → 403 `FORBIDDEN`
- `fields=contact` saat client `minimal` → 403
- Profile `minimal` siswa/guru **tidak** mengirim email/alamat/wali / `penempatan_aktif`
- Profile `academic` siswa mengirim `penempatan_aktif`, bukan `riwayat_penempatan`
- Profile `contact` mengirim keduanya
- `per_page=500` → efektif 200 di `meta.per_page`
- `include_deleted=true` mengembalikan soft-deleted (+ `deleted_at`)
- `active_only=true` memfilter benar
- Client lembaga A tidak melihat data lembaga B
- Envelope berisi `resource`, `lembaga_id`, `synced_at`, `data`, `meta`
- Health/me regresi tetap hijau

## 10. Urutan implementasi (ringkas)

1. Catalog + field profiler + unit tests.
2. Lister + transformer + scope gate.
3. Routes + controller untuk 5 resource.
4. Feature tests per resource (minimal set + siswa embeds + tenant).
5. Update `IMPLEMENTATION_TODO` M8 (centang health/me sebagai sudah M7).
6. Update SPEC §4.3 bila perlu mencatat embed siswa / clamp `per_page`.

## 11. Risiko

- N+1 pada embed siswa → wajib eager load.
- Global scope Auth menipu developer → selalu `withoutGlobalScopes` di path API.
- Riwayat penempatan besar → paginate resource tetap page-based; riwayat per siswa dibatasi “semua baris siswa itu” (biasanya kecil); jika perlu limit nanti di M9+.
