# Design — Milestone 5b: API Client & API Key

Status: **DRAFT — menunggu review pemilik**  
Tanggal: 2026-07-21  
Basis: [SPEC.md](../../SPEC.md) §2.2, §3.2, §5.1; [RULES.md](../../RULES.md) A8, B4, D2/D11/D13; [IMPLEMENTATION_TODO.md](../../IMPLEMENTATION_TODO.md) Milestone 5; shell M4; auth M3; lembaga/admin M5a

## 1. Tujuan

Mengimplementasikan pengelolaan **API client** dan **API key** untuk aplikasi konsumen: Super Admin membuat/mengubah metadata/rotate/revoke di hub Detail lembaga; Admin Lembaga melihat daftar read-only; plain key copy-once; penyimpanan prefix + HMAC digest — tanpa endpoint REST tarik data (itu **Milestone 7**).

## 2. Keputusan yang disepakati

| Topik | Keputusan |
|-------|-----------|
| Penempatan UI SA | Nested di **Detail lembaga** (sama pola Admin Lembaga M5a) |
| Menu SA “API client” | **Dihapus** dari sidebar (satu pintu: Lembaga → Detail) |
| Scope saat buat/ubah | Checklist resource fase 1: `tahun_ajaran:read`, `guru:read`, `kelas:read`, `siswa:read`, `karyawan:read` |
| Rotate | **In-place**: `id` client tetap; ganti prefix+digest; key lama langsung tidak valid; `revoked_at` hanya untuk **revoke** penuh |
| Edit tanpa rotate | Boleh ubah `nama`, `scopes`, `field_profile` tanpa mengganti key |
| Admin Lembaga | Menu **API client** aktif → list read-only lembaga sendiri (nama, prefix, scopes, field_profile, status, last used); **tidak** bisa buat/rotate/revoke |
| UI stack | Blade + controller + Form Request (bukan Livewire) |

## 3. Di luar scope M5b

- Endpoint REST `/api/*` autentikasi & tarik data (M7)
- Rate limit API konsumen (M7)
- Tabel riwayat key / multi-key history
- Soft delete / hard delete baris API client di UI
- Write master via API key (tetap dilarang selamanya per RULES)

## 4. Arsitektur

### 4.1 Otorisasi

- Mutasi (store/update/rotate/revoke): middleware `auth` + `active` + `mfa` + **Super Admin only** (`ApiClientPolicy` / Gate).
- Binding: `{apiClient}` harus `lembaga_id` = `{lembaga}` → **404** jika tidak cocok (IDOR).
- Admin Lembaga: `viewAny` / `view` hanya untuk `lembaga_id` sendiri; aksi mutasi → **403**.
- `lembaga_id` pada create selalu dari route parent, bukan input klien.

### 4.2 Routes (usulan)

Prefix `/admin`, middleware `auth`, `active`, `mfa`:

| Method | Path | Name |
|--------|------|------|
| POST | `/lembaga/{lembaga}/api-clients` | `admin.lembaga.api-clients.store` |
| PUT | `/lembaga/{lembaga}/api-clients/{apiClient}` | `admin.lembaga.api-clients.update` |
| POST | `/lembaga/{lembaga}/api-clients/{apiClient}/rotate` | `admin.lembaga.api-clients.rotate` |
| POST | `/lembaga/{lembaga}/api-clients/{apiClient}/revoke` | `admin.lembaga.api-clients.revoke` |
| GET | `/lembaga/{lembaga}/api-clients/{apiClient}/key-once` | `admin.lembaga.api-clients.key-once` |
| GET | `/api-clients` | `admin.api-clients.index` (Admin Lembaga read-only; SA boleh redirect ke lembaga atau 403—**prefer AL only**, SA pakai Detail) |

### 4.3 Kelas utama

| Komponen | Peran |
|----------|--------|
| `LembagaApiClientController` | Nested CRUD metadata + rotate/revoke + key-once (SA) |
| `ApiClientController` (atau `AdminLembagaApiClientController`) | Index read-only untuk Admin Lembaga |
| `StoreApiClientRequest` / `UpdateApiClientRequest` | Validasi nama, scopes, field_profile |
| `ApiClientPolicy` | SA manage; AL view own lembaga |
| `ApiKeyIssuer` | Generate plain `dc_live_{prefix}_{secret}`, prefix unik, HMAC-SHA256 digest |
| `ApiKeyVerifier` | `hash_equals` digest (dipakai test M5b; M7 auth middleware memakai ulang) |
| `AuditLogger` | `api_client.create` / `update` / `api_key.rotate` / `api_client.revoke` tanpa plain |
| `AdminMenu` | Hapus “API client” SA; AL → `admin.api-clients.index` available |

Views: section API client di `admin/lembaga/show`, `admin/lembaga/api-clients/key-once`, `admin/api-clients/index` (AL).

### 4.4 Pepper & format key

- Env wajib dokumentasi: `API_KEY_PEPPER` (string panjang acak; **jangan** commit). Ditambah ke `.env.example` sebagai placeholder kosong + komentar.
- Digest: `hash_hmac('sha256', $plainApiKey, config('security.api_key_pepper'))` → hex 64 karakter.
- Format plain: `dc_live_{prefix}_{secret}` per SPEC; `api_key_prefix` unik global (kolom DB max 16).
- Model `ApiClient`: `api_key_digest` tetap `$hidden`.

## 5. Perilaku fungsional

### 5.1 Buat

Field: `nama` (wajib), `scopes` (array non-kosong dari whitelist), `field_profile` (`minimal` \| `academic` \| `contact`, default `minimal`).  
Server set `lembaga_id`, `is_active=true`, `revoked_at=null`, prefix+digest.  
Redirect ke **key-once** dengan flash `{ api_client_id, plain_key }` (terikat id; tidak bocor antar client).  
Audit `api_client.create` (metadata: nama, prefix, scopes—bukan plain).

### 5.2 Ubah metadata

Update `nama`, `scopes`, `field_profile` saja. Tidak mengubah prefix/digest. Audit `api_client.update`.

### 5.3 Rotate

Modal Bahasa Indonesia: key lama langsung tidak berlaku; integrator harus memasang key baru; id client tidak berubah.  
POST → generate material baru → update prefix+digest → clear `last_used_*` opsional → key-once flash.  
**Tidak** set `revoked_at` pada rotate (keputusan A). Audit `api_key.rotate` dengan prefix baru (dan boleh catat prefix lama di metadata, bukan secret).

### 5.4 Revoke

Modal dampak: client tidak bisa autentikasi (403 di M7); Super Admin tidak bisa “un-revoke” key yang sama—harus buat client baru jika perlu akses lagi (atau aktifkan kembali hanya jika produk mengizinkan; **fase 1: revoke final** pada baris itu: `is_active=false`, `revoked_at=now()`; tidak ada tombol “aktifkan lagi” untuk client yang sudah revoked).  
Audit `api_client.revoke`.

### 5.5 Admin Lembaga read-only

List client `lembaga_id = auth.user.lembaga_id`, termasuk yang revoked (tampilkan status jelas). Kolom: nama, prefix, scopes, field_profile, status (aktif/revoked), last_used_at. Tanpa plain key, tanpa tombol mutasi.

## 6. Integrasi shell M4 / M5a

- Layout `admin`; section di show lembaga di bawah Admin lembaga.
- Modal `x-ui.modal` untuk rotate & revoke.
- Empty state jika belum ada client.
- Count `api_clients_aktif` di modal nonaktif lembaga M5a tetap: `is_active` + `revoked_at` null.

## 7. Testing

1. SA create → plain sekali; DB prefix+digest; audit tanpa plain; GET key-once kedua ditolak.
2. Rotate → plain baru; digest lama gagal verifikasi; id sama.
3. Update metadata → digest tidak berubah.
4. Revoke → flags benar; AL melihat status revoked.
5. AL index 200; AL POST mutate → 403.
6. SA mutate client lembaga A via lembaga B URL → 404.
7. Flash key tidak bocor ke client lain (user_id/client_id binding).

## 8. Acceptance criteria

- Item API client/key di Milestone 5 `IMPLEMENTATION_TODO` dapat dicentang; M7 tetap terbuka.
- Review keamanan (secret, HMAC, IDOR, otorisasi AL) bersih + test hijau.
- Tidak ada implementasi endpoint konsumen di spek ini.

## 9. Urutan implementasi disarankan

1. Config pepper + `ApiKeyIssuer` / `ApiKeyVerifier` + unit tests  
2. Policy + nested SA routes/controller + section show + key-once  
3. Rotate/revoke modals + audit  
4. AL read-only index + menu  
5. Feature tests + update `IMPLEMENTATION_TODO`  
6. Commit/push; M7 menyusul
