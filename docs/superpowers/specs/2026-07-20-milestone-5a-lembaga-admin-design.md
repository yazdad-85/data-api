# Design — Milestone 5a: CRUD Lembaga & Admin Lembaga

Status: **DRAFT — menunggu review pemilik**  
Tanggal: 2026-07-20  
Basis: [SPEC.md](../../SPEC.md) §2.3, §3.1, §3.3, §5.1; [RULES.md](../../RULES.md) A11, B4; [IMPLEMENTATION_TODO.md](../../IMPLEMENTATION_TODO.md) Milestone 5; shell M4; auth M3

## 1. Tujuan

Mengimplementasikan pengelolaan **Lembaga** dan **Admin Lembaga** untuk Super Admin: list/detail/create/edit, aktif/nonaktif dengan modal dampak berjumlah, password admin generate + copy-once, invalidasi session saat admin dinonaktifkan — tanpa API client/key (spek **M5b** terpisah).

## 2. Keputusan yang disepakati

| Topik | Keputusan |
|-------|-----------|
| Scope spek | **M5a** = Lembaga + Admin Lembaga saja; API client/key = **M5b** |
| UI | Blade + controller + Form Request (bukan Livewire) |
| Navigasi | List lembaga → **detail hub**; form create/edit admin **di halaman show** (tanpa index admin terpisah) |
| Menu sidebar | Setelah M5a: item **Lembaga** `available: true` → `admin.lembaga.index`. Item sidebar **“Admin lembaga” dihapus** (bukan coming-soon) agar satu pintu masuk |
| Nonaktif lembaga | Modal: teks dampak + **jumlah** Admin Lembaga aktif + API client aktif |
| Hapus lembaga | **Tidak** di UI fase 1 (hanya `is_active`) |
| Password Admin Lembaga | Generate server-side (≥12) + tampil **copy-once** (create & reset) |
| Hybrid Livewire | Follow-up tercatat di M3/M4 TODO; tidak mengulang milestone selesai |

## 3. Di luar scope M5a

- API client create/rotate/revoke/copy-once key (M5b)
- Soft delete / hard delete lembaga di UI
- MFA untuk Admin Lembaga
- Forgot-password email
- CRUD master tahun ajaran/guru/… (M6)
- Livewire hybrid forms/modals

## 4. Arsitektur

### 4.1 Otorisasi

- Semua route M5a: middleware `auth`, `active`, `mfa` + Gate/Policy **Super Admin only** (`manage-all-lembaga` / `LembagaPolicy` / `UserPolicy` untuk subjek `admin_lembaga`).
- Admin Lembaga yang mengakses URL ini → **403** (bukan hide-only di UI).
- `lembaga_id` admin selalu diambil dari lembaga di URL/detail, **bukan** dari input klien yang bisa diubah ke lembaga lain.

### 4.2 Routes (usulan)

Prefix `/admin`, name `admin.lembaga.*` / `admin.lembaga.admins.*`:

| Method | Path | Name (contoh) |
|--------|------|----------------|
| GET | `/lembaga` | `admin.lembaga.index` |
| GET | `/lembaga/create` | `admin.lembaga.create` |
| POST | `/lembaga` | `admin.lembaga.store` |
| GET | `/lembaga/{lembaga}` | `admin.lembaga.show` |
| GET | `/lembaga/{lembaga}/edit` | `admin.lembaga.edit` |
| PUT/PATCH | `/lembaga/{lembaga}` | `admin.lembaga.update` |
| POST | `/lembaga/{lembaga}/activate` | `admin.lembaga.activate` |
| POST | `/lembaga/{lembaga}/deactivate` | `admin.lembaga.deactivate` |
| POST | `/lembaga/{lembaga}/admins` | `admin.lembaga.admins.store` |
| PUT | `/lembaga/{lembaga}/admins/{user}` | `admin.lembaga.admins.update` |
| POST | `/lembaga/{lembaga}/admins/{user}/activate` | `admin.lembaga.admins.activate` |
| POST | `/lembaga/{lembaga}/admins/{user}/deactivate` | `admin.lembaga.admins.deactivate` |
| POST | `/lembaga/{lembaga}/admins/{user}/reset-password` | `admin.lembaga.admins.reset-password` |

Binding: `{user}` harus `admin_lembaga` dengan `lembaga_id` = `{lembaga}` (abort 404 jika tidak cocok — hindari IDOR).

### 4.3 Kelas utama

| Komponen | Peran |
|----------|--------|
| `LembagaController` | CRUD UI + activate/deactivate |
| `LembagaAdminController` (atau nested actions) | Admin di bawah lembaga |
| `StoreLembagaRequest` / `UpdateLembagaRequest` | Validasi field lembaga |
| `StoreLembagaAdminRequest` / `UpdateLembagaAdminRequest` | Validasi admin |
| `LembagaPolicy` | viewAny/view/create/update + activate/deactivate (SA only) |
| `AdminPasswordGenerator` | Random password memenuhi policy min 12 |
| `SessionInvalidator` | Dipanggil saat deactivate / reset-password admin |
| `AuditLogger` | Event kritis tanpa secret |
| `AdminMenu` | `Lembaga` → index; **hapus** entri “Admin lembaga” dari menu Super Admin |

Views: `admin/lembaga/index|create|edit|show` (show = hub: ringkasan lembaga + tabel admin + form tambah + aksi), partial modal deactivate, halaman/section **password copy-once** setelah store/reset (redirect ke route khusus singkat atau section flash yang tidak hilang sampai user dismiss).

## 5. Perilaku fungsional

### 5.1 Lembaga

**Field:** `kode` (unik, max 30), `nama` (wajib), `jenis`, `alamat`, `kota`, `provinsi`, `telepon`, `email`, `is_active` (default true).

**List:** search `kode`/`nama`, badge status, pagination, aksi ke detail/edit.

**Deactivate:**
1. Hitung `admins_aktif` = users `admin_lembaga` + `lembaga_id` + `is_active`.
2. Hitung `api_clients_aktif` = api_clients lembaga + `is_active` + `revoked_at` null (meski M5b belum CRUD, angka tetap akurat).
3. Modal Bahasa Indonesia menjelaskan: admin tidak bisa login; API key lembaga akan ditolak (403 saat M7); Super Admin bisa aktifkan lagi.
4. Tampilkan kedua jumlah.
5. POST → `is_active = false` + audit `lembaga.deactivate` (metadata: counts, bukan PII penuh).
6. Invalidasi session semua Admin Lembaga lembaga itu (loop `SessionInvalidator` / helper setara) agar efek segera, tidak menunggu request berikutnya. Middleware `EnsureUserIsActive` tetap jadi jaring pengaman.

**Activate:** set `is_active = true` + audit `lembaga.activate` (tidak auto-reactivate admin yang sudah `is_active=false`).

**Tidak ada** tombol hapus di UI. Model boleh tetap `SoftDeletes` dari skema; M5a **tidak** memanggil `delete()`/`forceDelete()`.

### 5.2 Admin Lembaga

**Buat:** `name`, `email` (unik). Server set `role=admin_lembaga`, `lembaga_id` dari parent, `is_active=true`, password hasil generator. Response sukses menampilkan plain password **sekali** (halaman/flash yang jelas: “simpan sekarang, tidak bisa dilihat lagi”). Audit `admin.create` tanpa password.

**Update:** `name`, `email` saja.

**Deactivate:** `is_active=false` → `SessionInvalidator::invalidateUser` → audit `admin.deactivate`.

**Activate:** `is_active=true` + audit.

**Reset password:** generate baru → invalidate session → tampil copy-once → audit `admin.reset_password` tanpa plain.

**Tidak** boleh memindahkan admin ke lembaga lain lewat form.

### 5.3 Password generator

- Panjang minimal 12; campuran huruf/angka (boleh simbol aman URL/copy).
- Hanya hidup di memori request + one-time view; DB = hash (cast `hashed` User).

## 6. Integrasi shell M4

- Layout `admin`; breadcrumb: Dashboard / Lembaga / {Nama} / …
- Empty state list lembaga & list admin kosong.
- Modal pakai `x-ui.modal` (Blade/`<dialog>`).
- Setelah M5a: `AdminMenu` — Lembaga available; hapus item “Admin lembaga”.

## 7. Testing (setelah review kode — RULES B1)

1. Super Admin: buat/ubah lembaga; lihat di list/detail.
2. Deactivate lembaga: modal counts; Admin Lembaga lembaga itu gagal login (pesan generik).
3. Activate lembaga: admin aktif bisa login lagi (jika admin `is_active`).
4. Buat admin: plain password muncul sekali; DB hash; audit tanpa plain.
5. Deactivate admin: session invalid; tidak autentikasi.
6. Reset password: plain baru sekali; session lama hangus.
7. ActingAs Admin Lembaga → route lembaga → 403.
8. User admin lembaga A tidak bisa diubah lewat URL lembaga B (404/403).

## 8. Acceptance criteria

M5a siap lanjut M5b hanya jika:

- Checklist terkait lembaga + admin di `IMPLEMENTATION_TODO` Milestone 5 dapat dicentang (item API key tetap terbuka).
- Review keamanan (IDOR, secret, session, audit) bersih + test hijau.
- UI Bahasa Indonesia; otorisasi server-side.
- Tidak ada implementasi API key di spek ini.

## 9. Urutan implementasi disarankan

1. Policy + menu route Lembaga  
2. CRUD lembaga + activate/deactivate + tests  
3. Nested admin CRUD + password generator + copy-once + SessionInvalidator hooks  
4. Feature tests penuh + update `IMPLEMENTATION_TODO` (parsial M5)  
5. Commit/push; spek M5b menyusul
