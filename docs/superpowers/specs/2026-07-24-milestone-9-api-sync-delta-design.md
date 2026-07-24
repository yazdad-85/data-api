# Design — Milestone 9: API Sync Delta

Status: **DISETUJUI — 24 Jul 2026**  
Tanggal: 2026-07-24  
Basis: [SPEC.md](../../SPEC.md) §4.4, §4.5; [RULES.md](../../RULES.md) A8, B4 (sync watermark/cursor); [IMPLEMENTATION_TODO.md](../../IMPLEMENTATION_TODO.md) Milestone 9; M8 [2026-07-24-milestone-8-api-full-pull-design.md](./2026-07-24-milestone-8-api-full-pull-design.md); M7 [2026-07-23-milestone-7-api-client-auth-design.md](./2026-07-23-milestone-7-api-client-auth-design.md)

## 1. Tujuan

Menyediakan **sinkronisasi delta** per resource master agar app konsumen tidak melewatkan create/update/soft-delete saat perubahan banyak: validasi `since`, watermark sesi, cursor `(changed_at, id)`, tombstone minim PII — di atas auth/rate limit M7 dan catalog/field profile M8. Tanpa write API dan tanpa endpoint penempatan terpisah.

## 2. Keputusan yang disepakati

| Topik | Keputusan |
|-------|-----------|
| Pendekatan | **Sync service di atas M8** (catalog + field profiler + transformer); bukan controller duplikat per resource |
| Endpoint | `GET /api/v1/{tahun-ajaran\|guru\|kelas\|siswa\|karyawan}/sync` |
| Live row fields | **Sama field profiles M8** (`minimal` ⊂ `academic` ⊂ `contact`); `?fields=` ceiling client → 403 `FORBIDDEN` |
| Tombstone | Hanya `id`, `deleted_at`, `changed_at` (tanpa PII / tanpa field profile penuh) |
| Penempatan | **Opsi C** — tidak ada sync `siswa-penempatan` di M9; perubahan enrollment mengandalkan **touch `siswa.updated_at`** (lifecycle M6c sudah `save()` siswa). Endpoint penempatan ditunda |
| Cursor | Opaque **base64url** JSON `{ "c": "<changed_at ISO>", "i": "<uuid>" }` |
| Watermark | `now(UTC)` pada request **tanpa** cursor; request dengan cursor wajib `watermark` yang sama |
| `changed_at` | `greatest(updated_at, coalesce(deleted_at, updated_at))` di query/app; **tanpa** kolom generated DB di M9 |
| `per_page` | Default 100, **clamp** max 200 (sama M8) |
| Max umur `since` | **90 hari** (`config/security.php` + env `API_SYNC_MAX_SINCE_DAYS`) |
| Tenant query | `withoutGlobalScopes()` + `where lembaga_id = client.lembaga_id` + soft-deleted ikut (tombstone) |

## 3. Di luar scope M9

- Write/update/delete via API key
- CORS whitelist per lembaga
- Endpoint terpisah `siswa-penempatan` / sync enrollment
- Kolom/index generated `changed_at` (ditunda sampai bukti performa butuh)
- Perubahan UI admin
- Export multi-resource (SPEC §4.6 fase 2)

## 4. Routes & otorisasi

Prefix `/api/v1`, middleware: `api.client` + `api.throttle` (M7).

| Method | Path | Scope |
|--------|------|-------|
| GET | `/{resource}/sync` | `{resource}:read` (slug `tahun-ajaran` ↔ `tahun_ajaran:read`) |

Resource slug sama M8. Non-GET → **405**. Missing/insufficient scope → **403** `FORBIDDEN`.

## 5. Protokol sync

### 5.1 Query parameters

| Param | Wajib | Aturan |
|-------|:-----:|--------|
| `since` | ya | ISO-8601 UTC; invalid / non-parseable / future → **400** `INVALID_SINCE`; umur > max days → **400** `SINCE_TOO_OLD` |
| `watermark` | jika ada `cursor` | harus sama dengan watermark sesi; hilang/mismatch → **400** `INVALID_CURSOR` |
| `cursor` | tidak | opaque; invalid decode / shape → **400** `INVALID_CURSOR` |
| `fields` | tidak | sama M8; default = profil client; lebih luas → **403** `FORBIDDEN` |
| `per_page` | tidak | default 100; nilai > 200 di-clamp ke 200 |

Tidak memakai `page`, `include_deleted`, atau `active_only` pada sync: sync **selalu** mencakup soft-deleted sebagai tombstone; filter aktif/full-pull tetap di M8 list.

### 5.2 Sesi watermark + cursor

1. Request **tanpa** `cursor` → server set `watermark = now(UTC)` (detik/mikro sesuai Carbon UTC ISO `Z`).
2. Request **dengan** `cursor` → wajib `watermark` dari response sebelumnya; **jangan** buat watermark baru.
3. Ambil baris: `changed_at > since` AND `changed_at <= watermark`, urut `(changed_at ASC, id ASC)`.
4. Setelah cursor: `(changed_at > c) OR (changed_at = c AND id > i)`.
5. Fetch `per_page + 1`; jika ada sisa → set `next_cursor` dari baris ke-`per_page`; else `next_cursor = null`.
6. **Integrator:** simpan `watermark` / `synced_at` sebagai sync terakhir **hanya** bila `next_cursor = null`.

### 5.3 `changed_at`

```text
changed_at = greatest(updated_at, coalesce(deleted_at, updated_at))
```

Index foundation `(lembaga_id, updated_at, id)` dan `(lembaga_id, deleted_at, id)` dipakai; evaluasi ulang generated column hanya jika profiling menunjukkan bottleneck.

### 5.4 Payload baris

- **Live** (`deleted_at` null): output transform M8 untuk effective profile + field `changed_at` (+ `deleted_at: null` boleh mengikuti contoh SPEC).
- **Tombstone** (`deleted_at` not null): `{ "id", "deleted_at", "changed_at" }` saja.

Embed siswa (`penempatan_aktif` / `riwayat_penempatan`) mengikuti aturan M8 pada baris live saja.

## 6. Response envelope

```json
{
  "resource": "siswa",
  "lembaga_id": "...",
  "since": "2026-07-15T10:00:00Z",
  "watermark": "2026-07-15T13:05:00Z",
  "synced_at": "2026-07-15T13:05:00Z",
  "changes": [ { "...": "..." } ],
  "change_count": 2,
  "next_cursor": null
}
```

- `synced_at`: waktu UTC server saat response digenerate (boleh sama dengan `watermark` pada page pertama; pada page lanjut tetap “now” response, bukan watermark baru untuk filter).
- Error: reuse `ApiErrorResponse` M7 (`message`, `code`, `request_id`).

## 7. Error codes (M9-specific + reuse)

| Code | HTTP | Kondisi |
|------|:----:|---------|
| `INVALID_SINCE` | 400 | `since` hilang, format salah, atau masa depan |
| `SINCE_TOO_OLD` | 400 | `since` lebih tua dari `API_SYNC_MAX_SINCE_DAYS` (default 90) |
| `INVALID_CURSOR` | 400 | cursor rusak; watermark hilang saat ada cursor; watermark ≠ sesi; pasangan cursor tidak konsisten |
| `FORBIDDEN` | 403 | scope kurang atau `fields` melebihi profil client |
| `UNAUTHENTICATED` / `LEMBAGA_INACTIVE` / `API_CLIENT_INACTIVE` / `RATE_LIMITED` | 401/403/429 | sama M7 |

## 8. Arsitektur

| Komponen | Peran |
|----------|--------|
| `App\Http\Controllers\Api\V1\ResourceSyncController` | Entry `GET /{resource}/sync` |
| `App\Services\Api\ApiSyncQueryValidator` | Validasi `since` / cursor / watermark / `per_page`; lempar error code SPEC |
| `App\Support\Api\ApiSyncCursor` | Encode/decode opaque cursor; bandingkan watermark |
| `App\Services\Api\ApiResourceSyncer` | Query delta, paginate cursor, bedakan live vs tombstone |
| Reuse M8 | `ApiResourceCatalog`, `ApiFieldProfiler`, `ApiResourceTransformer` (live rows) |
| Reuse M7 | middleware auth/throttle, `ApiErrorResponse`, scope check |

**Config:** tambah di `config/security.php`:

```php
'api_sync_max_since_days' => (int) env('API_SYNC_MAX_SINCE_DAYS', 90),
```

**Route:** daftarkan di `routes/api.php` di samping list M8 (satu pola `{resource}/sync`).

## 9. Penempatan & dokumentasi integrator

- Perubahan status/enrollment yang sudah menyentuh `siswa` via M6c muncul di sync `siswa` melalui `updated_at`.
- Jika di masa depan ada mutasi penempatan **tanpa** touch siswa, itu bug produk — perbaiki di service lifecycle, bukan menambah resource sync di M9.
- Docs/SPEC catatan: app menyimpan watermark hanya setelah `next_cursor = null`; `SINCE_TOO_OLD` → arahkan tarik penuh M8.

## 10. Testing (wajib)

- Create / update / soft-delete muncul di sync dengan `changed_at` benar.
- Multi-page: perubahan > `per_page` → `next_cursor`; page berikutnya memakai `watermark` yang sama; tanpa duplikat/lompat.
- Update **setelah** watermark tidak masuk batch sesi lama.
- `since` invalid / future → `INVALID_SINCE`; terlalu tua → `SINCE_TOO_OLD`.
- Cursor/watermark invalid → `INVALID_CURSOR`.
- Tombstone hanya `id`, `deleted_at`, `changed_at` (tanpa nama/email/dll).
- Scope kurang / `fields` naik → `FORBIDDEN` (sama M8).
- Client lembaga A tidak melihat perubahan lembaga B.
- Regresi M7 health/me + smoke M8 list tetap hijau.

## 11. Urutan implementasi (ringkas)

1. Config `api_sync_max_since_days` + unit `ApiSyncCursor` / validator `since`.
2. `ApiResourceSyncer` + tombstone vs live transform.
3. Controller + routes `{resource}/sync`.
4. Feature tests (delta, multi-page, watermark race, errors, tenant, tombstone PII).
5. Update `IMPLEMENTATION_TODO` M9; catatan singkat di SPEC §4.4 bila perlu (penempatan via touch siswa).

## 12. Risiko

- Race update setelah watermark: **disengaja** tertutup watermark; client mengambil di sync berikutnya — jangan “lebarkan” watermark di tengah sesi.
- `withoutGlobalScopes()` juga drop SoftDeletingScope → syncer harus **eksplisit** include trashed (bukan mengandalkan default list M8 yang exclude deleted).
- Ekspresi `greatest(...)` di PostgreSQL harus selaras timezone UTC dengan `since`/`watermark` tersimpan sebagai timestamptz.
- Expression filter mungkin kurang optimal vs index murni `updated_at` — pantau; generated `changed_at` ditunda.
- Cursor opaque mudah di-tamper → selalu validasi shape + watermark; jangan percaya client untuk memperluas window.
