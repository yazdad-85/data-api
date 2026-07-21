# Design — Milestone 6a: Master Tahun Ajaran, Guru & Karyawan

Status: **DRAFT — menunggu review pemilik**  
Tanggal: 2026-07-21  
Basis: [SPEC.md](../../SPEC.md) §2.3–2.4, §3.4, §3.6–3.7, §5.2; [RULES.md](../../RULES.md) A5–A6, A12–A13, B4; [IMPLEMENTATION_TODO.md](../../IMPLEMENTATION_TODO.md) Milestone 6; auth M3; shell M4; M5 selesai

## 1. Tujuan

Mengimplementasikan CRUD master **Tahun ajaran**, **Guru**, dan **Karyawan** untuk **Admin Lembaga** (scoped lembaga sendiri): nama tahun ajaran baku otomatis, aktivasi satu TA per lembaga dalam transaksi, nonaktif + soft delete untuk personel, rename kolom `nip` → `niy` — tanpa Kelas/Siswa (**M6b**).

## 2. Keputusan yang disepakati

| Topik | Keputusan |
|-------|-----------|
| Pecahan M6 | **M6a** = TA + Guru + Karyawan; **M6b** = Kelas + Siswa |
| Pengguna UI | **Admin Lembaga saja**; Super Admin akses route master → **403** di M6a |
| Nama Tahun ajaran | Input **tahun mulai** saja; server set `nama = "{Y}/{Y+1}"` (pemisah `/`); tidak ada ketik bebas |
| Status TA saat create | Selalu `is_aktif=false`; **Aktifkan** via aksi + modal (transaksi nonaktifkan TA aktif lain) |
| Guru & Karyawan | **Nonaktif** (`is_active`) **dan** **soft delete** (modal) |
| Kelengkapan form | Field sesuai SPEC (opsional boleh kosong); bisa ditambah nanti jika kurang |
| NIP → NIY | Rename kolom DB `nip` → `niy` + update SPEC/RULES/model/factory |
| UI stack | Blade + controller + Form Request (bukan Livewire) |

## 3. Di luar scope M6a

- CRUD Kelas & Siswa (M6b)
- UI Super Admin untuk master data
- Import Excel massal
- Livewire hybrid
- Endpoint REST konsumen (M7+)

## 4. Perubahan skema & dokumen

### 4.1 Migration

- Rename `guru.nip` → `guru.niy` (`string(40)` nullable), update model `$fillable`, `GuruFactory`, referensi tes/docs.
- Tidak mengubah constraint unik lain di M6a.

### 4.2 SPEC / RULES

- Ganti semua penyebutan field guru `nip` menjadi `niy` (Nomor Induk Yayasan).
- Catat format nama tahun ajaran baku `YYYY/YYYY+1` sebagai aturan UI/aplikasi (kolom `nama` tetap string unik per lembaga).

## 5. Arsitektur

### 5.1 Otorisasi

- Middleware: `auth`, `active`, `mfa`.
- Policies yang ada (`TahunAjaranPolicy`, `GuruPolicy`, `KaryawanPolicy`) + **pengetatan M6a**: mutasi/view CRUD UI hanya `isAdminLembaga()`; Super Admin → 403 pada controller master M6a (meski policy generik sebelumnya mengizinkan SA — spek M6a mengunci UI ke AL; implementasikan `abort_unless($user->isAdminLembaga(), 403)` di controller atau sesuaikan policy dengan Gate khusus `manage-own-master`).
- `lembaga_id` selalu dari `auth()->user()->lembaga_id`, bukan input form.
- Global scope `BelongsToLembaga` tetap aktif.

### 5.2 Routes (usulan)

Prefix `/admin`, name `admin.tahun-ajaran.*`, `admin.guru.*`, `admin.karyawan.*`:

| Resource | Methods |
|----------|---------|
| Tahun ajaran | index, create, store, edit, update, activate (POST), destroy (soft) |
| Guru | index, create, store, show, edit, update, activate, deactivate, destroy |
| Karyawan | index, create, store, show, edit, update, activate, deactivate, destroy |

Tidak ada route SA khusus.

### 5.3 Kelas utama

| Komponen | Peran |
|----------|--------|
| `TahunAjaranController` | CRUD + activate transaksi |
| `GuruController` / `KaryawanController` | CRUD + aktif/nonaktif + soft delete |
| Form Requests per aksi | Validasi field; larang `lembaga_id` / `nama` TA dari client sembarang |
| `TahunAjaranNamer` (atau helper) | `format(int $tahunMulai): string` → `"{$y}/".($y+1)` |
| `AdminMenu` | Aktifkan Tahun ajaran, Guru, Karyawan; Kelas/Siswa tetap soon |
| Factories | `TahunAjaranFactory`, `KaryawanFactory`; update `GuruFactory` (`niy`) |
| `AuditLogger` | create/update/activate/deactivate/delete; `master.view` pada show guru/karyawan (metadata id/jenis, tanpa dump PII) |

Views: `admin/tahun-ajaran/*`, `admin/guru/*`, `admin/karyawan/*` memakai `x-ui.*`.

## 6. Perilaku fungsional

### 6.1 Tahun ajaran

**Create:** `tahun_mulai` (int, rentang wajar mis. `now()->year - 2` … `now()->year + 3`), `tanggal_mulai`, `tanggal_selesai` (`>` mulai). Server: `nama`, `lembaga_id`, `is_aktif=false`.

**Update:** hanya `tanggal_mulai` / `tanggal_selesai` (nama tidak diubah agar referensi stabil).

**Activate:** modal Bahasa Indonesia menjelaskan TA aktif sebelumnya akan dinonaktifkan. Transaksi DB: `UPDATE ... SET is_aktif=false WHERE lembaga_id=? AND is_aktif=true`; set target `is_aktif=true`. Audit `tahun_ajaran.activate`.

**Destroy (soft):** modal; jika `kelas()->exists()` → **blok** dengan pesan jelas (siap untuk M6b; di M6a biasanya 0 kelas).

**List:** badge Aktif/Nonaktif; aksi Ubah, Aktifkan (jika nonaktif), Hapus.

### 6.2 Guru

Field: `niy`, `nuptk`, `nama` (wajib), `jenis_kelamin` (`L`/`P`), `tempat_lahir`, `tanggal_lahir`, `email`, `telepon`, `alamat`, `status_kepegawaian`, `is_active` (default true).

List: search `nama` / `niy`, pagination, badge status. Soft delete + activate/deactivate dengan modal untuk hapus/nonaktif.

### 6.3 Karyawan

Field: `nik_pegawai`, `nama` (wajib), `jenis_kelamin`, `jabatan`, `email`, `telepon`, `alamat`, `is_active`.

Pola list/aksi sama Guru.

## 7. Integrasi shell

- Layout `admin`; breadcrumb Indonesia.
- Empty state + flash status.
- Menu AL: tiga item master pertama `available=true`.

## 8. Testing

1. AL create TA → `nama` format baku; reject jika coba kirim `nama` berbeda via request (diabaikan/tidak ada di rules).
2. Activate: hanya satu `is_aktif` per lembaga.
3. Duplikat `nama` TA di lembaga yang sama → validation error.
4. Guru CRUD + soft delete + nonaktif; scope lintas lembaga ditolak.
5. Karyawan CRUD serupa.
6. SA → 403 pada index TA/Guru/Karyawan.
7. Setelah migration, tidak ada kolom `nip`; create guru memakai `niy`.

## 9. Acceptance criteria

- Checklist TODO terkait Tahun ajaran / Guru / Karyawan (search guru, soft delete, satu TA aktif) dapat dicentang; Kelas/Siswa tetap terbuka.
- SPEC/RULES mencerminkan `niy`.
- Tidak ada implementasi Kelas/Siswa di spek ini.

## 10. Urutan implementasi disarankan

1. Migration `nip`→`niy` + update SPEC/RULES/model/factory  
2. Tahun ajaran CRUD + activate + tests  
3. Guru CRUD + search + soft delete/aktif  
4. Karyawan CRUD  
5. Menu + otorisasi SA 403 + feature tests penuh  
6. Update `IMPLEMENTATION_TODO` (parsial M6)  
7. Commit/push; spek M6b menyusul
