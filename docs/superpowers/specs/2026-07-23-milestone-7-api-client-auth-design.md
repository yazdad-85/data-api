# Design — Milestone 7: API Client Authentication

Status: **Approved**  
Tanggal: 2026-07-23  
Basis: [SPEC.md](../../SPEC.md) §2, §3.2, §4.1–4.2, §4.5; [RULES.md](../../RULES.md) A8, B4, D2/D8/D11/D13; [IMPLEMENTATION_TODO.md](../../IMPLEMENTATION_TODO.md) Milestone 7; M5b [2026-07-21-milestone-5b-api-client-design.md](./2026-07-21-milestone-5b-api-client-design.md)

## 1. Tujuan

Mengaktifkan **autentikasi API konsumen** untuk Pusat Data: middleware verifikasi API key (prefix + HMAC), status client/lembaga, rate limit, update `last_used_*`, plus smoke endpoints `GET /api/v1/health` dan `GET /api/v1/me` — tanpa tarik resource master (tetap Milestone 8).

## 2. Keputusan yang disepakati

| Topik | Keputusan |
|-------|-----------|
| Pendekatan | Pipeline tipis: `AuthenticateApiClient` + `ApiClientAuthenticator`; rate limit middleware terpisah |
| Header | **`X-API-Key` dan `Authorization: Bearer`**; jika keduanya ada, `X-API-Key` menang |
| Endpoint M7 | `GET /api/v1/health` (tanpa key); `GET /api/v1/me` (wajib key) |
| Resource list / sync | **Di luar scope** → M8 |
| Error envelope | SPEC §4.5: `{ message, code, request_id }` |
| Verifier | Reuse `App\Services\Api\ApiKeyVerifier` (M5b) |
| Rate limit | 120/mnt per API key + 240/mnt per IP |
| UI admin | Tidak diubah (CRUD client tetap M5b) |

## 3. Di luar scope M7

- `GET /api/v1/{resource}` dan sync cursor/watermark (M8)
- Write master via API (selamanya dilarang)
- CORS whitelist per lembaga (fase berikutnya / M11)
- Ganti model penyimpanan key (tetap prefix + HMAC pepper)
- Livewire / halaman admin baru

## 4. Alur autentikasi

### 4.1 Ekstraksi key

1. Jika header `X-API-Key` non-kosong → pakai nilai itu.  
2. Else jika `Authorization` cocok `Bearer <token>` → pakai token.  
3. Jika keduanya ada → **hanya** `X-API-Key`.  
4. Tidak ada key → **401** `UNAUTHENTICATED`.

### 4.2 Validasi

1. Parse format `dc_live_<prefix>_<secret>` (prefix & secret non-kosong). Format invalid → **401** generik `UNAUTHENTICATED` (jangan bocorkan “format vs digest”).  
2. Lookup `api_clients` by `api_key_prefix` (unik).  
3. Verifikasi digest: `ApiKeyVerifier::matches($plain, $storedDigest)` (`hash_hmac` + `hash_equals`).  
4. Lookup/verify gagal → **401** `UNAUTHENTICATED`.  
5. Client `is_active = false` atau `revoked_at` tidak null → **403** `API_CLIENT_INACTIVE`.  
6. Lembaga terkait `is_active = false` → **403** `LEMBAGA_INACTIVE`.  
7. Sukses: bind `ApiClient` (+ `Lembaga`) ke request attributes / helper request; update `last_used_at` dan `last_used_ip` (best-effort: kegagalan update tidak mengubah outcome auth).

### 4.3 Error JSON

```json
{
  "message": "Autentikasi gagal.",
  "code": "UNAUTHENTICATED",
  "request_id": "..."
}
```

Kode M7: `UNAUTHENTICATED` (401), `API_CLIENT_INACTIVE` (403), `LEMBAGA_INACTIVE` (403), `RATE_LIMITED` (429).  
Pesan singkat Bahasa Indonesia; `request_id` dari middleware/helper yang sudah ada bila tersedia.

### 4.4 Logging

Jangan pernah menulis nilai header `X-API-Key` / `Authorization` ke log aplikasi atau metadata audit. Redact bila pipeline log menyentuh headers.

## 5. Rate limit

Setelah auth sukses, middleware `ThrottleApiClient`:

| Bucket | Limit |
|--------|------:|
| Per API key (`api_key_prefix` atau `client_id`) | **120 / menit** |
| Per IP | **240 / menit** |

- Implementasi: `Illuminate\Support\Facades\RateLimiter` + cache app (Redis dianjurkan produksi; tidak wajib setup baru di M7).  
- Melebihi → **429** `RATE_LIMITED`; sertakan `Retry-After` jika mudah.  
- `GET /api/v1/health`: tanpa auth; boleh hanya ikut limit IP ringan atau tanpa throttle ketat (terkunci: **tanpa** limit per key; IP limit opsional sama 240/mnt).

## 6. Endpoints

Base: `/api/v1` (prefix `api` Laravel + versi di route group).

| Method | Path | Auth | Response |
|--------|------|------|----------|
| GET | `/health` | Tidak | `{ "status": "ok" }` — tanpa versi/stack/app info |
| GET | `/me` | Ya + throttle | Ringkas: `lembaga_id`, `kode`, `nama`, `is_active`, `client_id`, `client_name`, `scopes`, `field_profile` |

Method selain GET pada route ini → **405**.  
Tidak ada endpoint resource di M7.

## 7. Arsitektur

### 7.1 Komponen

| Komponen | Peran |
|----------|--------|
| `App\Services\Api\ApiKeyParser` (opsional) | Parse `dc_live_*` → prefix + secret / plain |
| `App\Services\Api\ApiClientAuthenticator` | Orkestrasi lookup + verify + status checks + last_used |
| `App\Services\Api\ApiKeyVerifier` | Sudah ada (M5b) — reuse |
| `App\Http\Middleware\AuthenticateApiClient` | Ekstrak header → authenticator → bind / JSON error |
| `App\Http\Middleware\ThrottleApiClient` | Rate limit key + IP |
| `App\Support\Api\ApiErrorResponse` (atau sejenis) | Envelope error konsisten |
| `App\Http\Controllers\Api\V1\HealthController` | Health |
| `App\Http\Controllers\Api\V1\MeController` | Profil dari client terautentikasi |
| `bootstrap/app.php` / `routes/api.php` | Alias middleware + route group |

### 7.2 Binding request

Setelah auth: simpan `ApiClient` (dan lembaga) di `$request->attributes` (mis. `api_client`) dan/atau helper `apiClient()` untuk controller M7/M8. Jangan memakai `Auth::login` session web.

### 7.3 Keamanan tambahan

- Compare digest hanya lewat `hash_equals`.  
- Route API tidak memakai middleware `web` session/CSRF.  
- Pastikan `api_key_digest` tetap `$hidden` di model.  
- Siapkan kebiasaan read-only: M8 akan menolak non-GET secara global untuk API key.

## 8. Testing (wajib)

- Tanpa key → 401 `UNAUTHENTICATED`  
- Key salah / format rusak → 401 `UNAUTHENTICATED`  
- Key revoked / client inactive → 403 `API_CLIENT_INACTIVE`  
- Lembaga inactive → 403 `LEMBAGA_INACTIVE`  
- Key lembaga A → `/me` hanya data lembaga A (bukan B)  
- `X-API-Key` dan `Bearer` keduanya lolos jika valid  
- Rate limit → 429 `RATE_LIMITED`  
- Health tanpa key → 200 `{status:ok}`  
- `/me` tanpa key → 401  
- Assert redaction / tidak ada plain key di log bila ada hook yang diuji  

## 9. Urutan implementasi (ringkas)

1. Parser + authenticator + error helper (+ unit tests).  
2. Middleware auth + throttle; daftar di bootstrap.  
3. Routes `health` + `me` + controllers.  
4. Feature tests auth/rate limit/tenant.  
5. Review secret handling; update `IMPLEMENTATION_TODO` M7.  
6. Opsional: catatan singkat di SPEC jika rate IP 240 perlu dikunci eksplisit (boleh di spek ini saja).

## 10. Risiko

- Cache file driver: rate limit kurang akurat multi-process — dokumentasikan Redis untuk produksi.  
- Update `last_used_*` di setiap request: beban write; tetap best-effort sinkron di M7 (async job ditunda bila perlu nanti).  
- Dua header berbeda: dokumentasikan prioritas `X-API-Key` untuk integrator.
