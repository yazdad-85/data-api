# Design — Milestone 6b: Master Kelas & Siswa

Status: **Approved (draft for review)**  
Tanggal: 2026-07-22  
Basis: [SPEC.md](../../SPEC.md) §2.3–2.4, §3.5, §3.8, §5.2; [RULES.md](../../RULES.md) A5–A7, A12–A13, B4; [IMPLEMENTATION_TODO.md](../../IMPLEMENTATION_TODO.md) Milestone 6; M6a [2026-07-21-milestone-6a-master-ta-guru-karyawan-design.md](./2026-07-21-milestone-6a-master-ta-guru-karyawan-design.md)

## 1. Tujuan

Mengimplementasikan CRUD master **Kelas** dan **Siswa** untuk **Admin Lembaga** (scoped lembaga sendiri): validasi relasi tahun ajaran / wali kelas / penempatan siswa, import kelas dari index, **import siswa dari detail kelas**, daftar siswa lintas kelas di menu Siswa — tanpa histori pindah kelas, Livewire, atau REST API.

## 2. Keputusan yang disepakati

| Topik | Keputusan |
|-------|-----------|
| Pecahan M6 | **M6b** = Kelas + Siswa (M6a sudah selesai) |
| Pengguna UI | **Admin Lembaga saja**; Super Admin → **403** pada route master M6b |
| UI stack | Blade + controller + Form Request (bukan Livewire) |
| Import Kelas | Ya — template `.xlsx` 2 sheet (Petunjuk + Data) dari index Kelas |
| Import Siswa | Ya — **hanya dari halaman detail Kelas** (bukan dari menu Siswa) |
| NIS | **Wajib** di form create/update dan di template import |
| NISN | **Opsional** di form dan import |
| Siswa tanpa kelas | Diizinkan (form menu Siswa); badge **"Belum ada kelas"** |
| Hapus Kelas | **Hard delete** jika belum ada siswa; **blok** jika masih ada siswa |
| Hapus Siswa | **Soft delete** + aktif/nonaktif; validasi NIS/NISN termasuk soft-deleted (`withTrashed`) agar tidak 500 / “hantu” diam-diam |
| Urutan nomor unik | Partial unique NIS/NISN per lembaga sudah ada di migration foundation |

## 3. Di luar scope M6b

- Histori perpindahan kelas / enrollments multi-tahun
- Import siswa dari menu Siswa
- Auto-generate NIS
- Livewire hybrid
- Endpoint REST `/api/*` (M7+)
- UI Super Admin untuk master Kelas/Siswa

## 4. Perubahan skema & dokumen

### 4.1 Migration

- Tabel `kelas` / `siswa` sudah ada (composite FK, soft deletes, unique nama kelas per TA).
- Pastikan / dokumentasikan index partial unik:
  - `(lembaga_id, nis) WHERE nis IS NOT NULL`
  - `(lembaga_id, nisn) WHERE nisn IS NOT NULL`
- Tidak mengubah unique index untuk mengecualikan `deleted_at` di M6b: baris soft-deleted tetap memegang NIS/NISN → aplikasi **menolak** create ulang dengan pesan jelas (bukan error DB mentah).
- Factories: `KelasFactory`, `SiswaFactory` (+ `HasFactory` pada model jika belum).

### 4.2 SPEC / RULES / TODO

- SPEC §3.8: catat **NIS wajib** pada UI create/import Admin Lembaga (kolom DB tetap nullable untuk kompatibilitas data lama / sync, tetapi Form Request mewajibkan).
- Update `IMPLEMENTATION_TODO` checklist M6b setelah implementasi.
- Aktifkan item menu Kelas & Siswa di `AdminMenu`.

## 5. Arsitektur

### 5.1 Otorisasi

- Middleware: `auth`, `active`, `mfa`.
- Trait `EnsuresAdminLembaga` → SA 403.
- `lembaga_id` selalu dari auth, bukan input form.
- Global scope `BelongsToLembaga` tetap aktif.
- Validasi relasi: `tahun_ajaran_id`, `wali_kelas_guru_id`, `kelas_id` harus milik lembaga yang sama (composite FK + Form Request).

### 5.2 Routes

Prefix `/admin`:

| Resource | Methods |
|----------|---------|
| Kelas | index, create, store, show, edit, update, destroy (hard jika aman), template (GET), import (POST) |
| Kelas → siswa import | `GET/POST admin/kelas/{kelas}/siswa/template`, `admin/kelas/{kelas}/siswa/import` |
| Siswa | index, create, store, show, edit, update, activate, deactivate, destroy (soft) |

Tidak ada route import siswa di `admin.siswa.*`.

### 5.3 Kelas utama

| Komponen | Peran |
|----------|--------|
| `KelasController` | CRUD + show (daftar siswa) + import kelas |
| `KelasSiswaImportController` (atau method di controller kelas) | Template + import siswa scoped ke kelas |
| `SiswaController` | CRUD + search/filter + aktif/nonaktif + soft delete |
| Form Requests | Validasi field & relasi tenant |
| `KelasImporter` / `KelasTemplateExporter` | Excel kelas |
| `SiswaImporter` / `SiswaTemplateExporter` | Excel siswa (konteks kelas) |
| `AdminMenu` | Kelas & Siswa `available=true` |
| `AuditLogger` | create/update/delete/import; `master.view` pada show siswa (tanpa dump PII) |

Views: `admin/kelas/*`, `admin/siswa/*` memakai `x-ui.*`.

## 6. Perilaku fungsional

### 6.1 Kelas

**Create / Update**

- Wajib: `tahun_ajaran_id`, `nama`.
- Opsional: `tingkat`, `wali_kelas_guru_id` (hanya guru lembaga; disarankan aktif).
- Unik `(lembaga_id, tahun_ajaran_id, nama)` — validasi aplikasi; cek `withTrashed()` jika masih ada soft-deleted historis, pesan jelas.

**Destroy**

- Jika `siswa()->exists()` → redirect + error: tidak dapat dihapus.
- Else → `forceDelete()` (permanen); nama kelas bisa dipakai lagi.

**List**

- Filter opsional tahun ajaran; search nama; pagination; tampilkan tingkat, wali, jumlah siswa (opsional count).

**Show (detail)**

- Metadata kelas + tabel siswa di kelas ini.
- Aksi: Ubah, Hapus (modal), **Unduh template siswa**, **Import siswa**.

**Import kelas (index)**

- Sheet `Petunjuk` + `Data Kelas`.
- Kolom: `nama*` , `tahun_ajaran*` (teks `YYYY/YYYY+1` yang sudah ada di lembaga), `tingkat`, `wali_kelas_niy` (opsional).
- Resolve TA & guru dalam lembaga; gagal per baris dilaporkan.

### 6.2 Siswa

**Create / Update (menu Siswa)**

- Wajib: `nama`, `nis`.
- Opsional: `nisn`, identitas, kontak, wali, `kelas_id`, `tahun_ajaran_id`.
- Jika `kelas_id` terisi: `tahun_ajaran_id` wajib dan harus sama dengan `kelas.tahun_ajaran_id`; kelas milik lembaga.
- Jika tanpa kelas: `kelas_id` & `tahun_ajaran_id` null; badge di list.
- NIS/NISN unik per lembaga (partial); validasi `withTrashed()`.

**Activate / Deactivate / Soft delete**

- Pola sama Guru/Karyawan (modal hapus).

**List**

- Search `nama` / `nis` / `nisn`; pagination; badge kelas atau “Belum ada kelas”; filter kelas/TA opsional.

**Import siswa (hanya dari detail kelas)**

- Sheet `Petunjuk` + `Data Siswa`.
- Kolom: `nis*`, `nama*`, `nisn`, `jenis_kelamin`, `tempat_lahir`, `tanggal_lahir`, `email`, `telepon`, `alamat`, `nama_wali`, `telepon_wali`.
- Server set `kelas_id` + `tahun_ajaran_id` dari kelas konteks; `lembaga_id` dari auth.
- Hasil import otomatis terlihat di menu Siswa dan di detail kelas.
- Duplikat NIS / nama policy: NIS duplikat → gagal baris; (nama boleh sama jika NIS beda).

## 7. Integrasi shell

- Layout `admin`; breadcrumb Indonesia.
- Empty state + flash + ringkasan error import per baris.
- Hapus route coming-soon untuk `kelas` | `siswa` setelah menu live (atau biarkan route unused).

## 8. Testing

1. AL CRUD kelas; SA 403.
2. Tahun ajaran / wali lintas lembaga ditolak.
3. Hapus kelas berisi siswa diblok; kelas kosong hard-deleted.
4. AL CRUD siswa; NIS wajib; tanpa kelas → badge.
5. `kelas_id` + `tahun_ajaran_id` mismatch ditolak.
6. Import kelas sukses; import siswa dari detail kelas → record di `siswa` dengan `kelas_id` benar.
7. NIS duplikat (termasuk soft-deleted) → session/validation error.
8. AL A tidak melihat/mutasi kelas/siswa lembaga B.
9. Search/pagination siswa (nama/NIS/NISN).

## 9. Acceptance criteria

- Checklist M6b di `IMPLEMENTATION_TODO` dapat dicentang.
- Menu Kelas & Siswa live untuk Admin Lembaga.
- Import siswa hanya dari detail kelas; menu Siswa menampilkan semua siswa lembaga.
- Tidak ada soft-delete “hantu” untuk kelas (hard delete); siswa soft-delete dengan validasi unik eksplisit.
- Tidak mengimplementasikan enrollments histori / API konsumen.

## 10. Urutan implementasi disarankan

1. Factories + (jika perlu) penyesuaian model `HasFactory`  
2. Kelas CRUD + show + hard delete + tests  
3. Import kelas + template  
4. Siswa CRUD + search + aktif/soft delete + NIS wajib + tests  
5. Import siswa dari detail kelas + template  
6. Menu + coming-soon cleanup + feature tests penuh  
7. Update SPEC catatan NIS wajib UI + `IMPLEMENTATION_TODO`  
8. Commit / push  
