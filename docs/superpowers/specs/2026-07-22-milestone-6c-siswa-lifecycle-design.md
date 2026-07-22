# Design — Milestone 6c: Lifecycle Siswa

Status: **Approved**  
Tanggal: 2026-07-22  
Basis: [SPEC.md](../../SPEC.md) §3.8, §7; [RULES.md](../../RULES.md) A5–A7; [IMPLEMENTATION_TODO.md](../../IMPLEMENTATION_TODO.md) (sisipan sebelum M7); M6b [2026-07-22-milestone-6b-kelas-siswa-design.md](./2026-07-22-milestone-6b-kelas-siswa-design.md)

## 1. Tujuan

Menambahkan **lifecycle siswa** untuk Admin Lembaga: status operasional, histori penempatan (enrollment), kenaikan kelas batch, serta aksi per siswa (mutasi masuk/keluar, lulus/alumni) — tanpa mengimplementasikan endpoint REST (tetap M7). Data disiapkan agar API konsumen nanti tidak perlu mengubah kontrak berkali-kali.

## 2. Keputusan yang disepakati

| Topik | Keputusan |
|-------|-----------|
| Urutan roadmap | **M6c sebelum M7** (lifecycle dulu, API client kemudian) |
| Pendekatan data | **Hybrid**: `status_siswa` + metadata singkat di `siswa`; tabel enrollment penuh; `kelas_id` / `tahun_ajaran_id` tetap snapshot terkini |
| Status fase 1 | `calon` · `mutasi_masuk` · `aktif` · `mutasi_keluar` · `lulus` |
| UX kenaikan | **Batch + per siswa** |
| Pengguna UI | **Admin Lembaga saja**; Super Admin → **403** |
| UI stack | Blade + controller + Form Request (bukan Livewire) |
| Soft delete | Tetap untuk hapus master; **bukan** pengganti status lifecycle |
| REST `/api/*` | **Tidak** di M6c (hanya kontrak data dipersiapkan) |

## 3. Di luar scope M6c

- Endpoint REST, API key middleware, field_profile sync (M7+)
- Restore otomatis dari `mutasi_keluar` / `lulus` ke `aktif` (fase berikutnya bila dibutuhkan)
- Auto-generate NIS
- Livewire hybrid
- UI Super Admin untuk lifecycle
- Mutasi antar-lembaga dalam satu yayasan (fase 1: metadata teks asal/tujuan saja)
- Upload surat mutasi / ijazah

## 4. Status & aturan operasional

### 4.1 Semantik status

| Status | Kelas | `is_active` | Tampil operasional (daftar kelas / default kenaikan) |
|--------|-------|-------------|------------------------------------------------------|
| `calon` | boleh kosong | `false` | tidak (filter “Calon”) |
| `mutasi_masuk` | boleh kosong, lalu diisi saat diterima | `true` setelah penempatan kelas | ya, setelah punya kelas |
| `aktif` | biasanya terisi | `true` | ya |
| `mutasi_keluar` | dikosongkan (jejak di enrollment) | `false` | tidak |
| `lulus` | dikosongkan | `false` | tidak (filter “Alumni”) |

### 4.2 Transisi diizinkan (fase 1)

```
calon → mutasi_masuk | aktif
mutasi_masuk → aktif | mutasi_keluar
aktif → aktif (pindah/naik kelas) | mutasi_keluar | lulus
mutasi_keluar → (tidak dibuka di fase 1)
lulus → (tidak dibuka di fase 1)
```

### 4.3 Metadata singkat di `siswa`

- `status_siswa` (string/enum, wajib; default data lama: `aktif` jika `is_active`, else perlu backfill konsisten — lihat §5.3)
- `status_at` (date/datetime efektif, nullable)
- `status_alasan` (string singkat, nullable)
- `status_asal` / `status_tujuan` (string, nullable — mis. nama sekolah untuk mutasi)

## 5. Skema enrollment

### 5.1 Tabel `siswa_penempatan`

| Field | Tipe | Catatan |
|-------|------|---------|
| `id` | UUID | PK |
| `lembaga_id` | UUID | tenant |
| `siswa_id` | UUID | composite FK ke siswa |
| `tahun_ajaran_id` | UUID | composite FK; wajib jika ada penempatan TA |
| `kelas_id` | UUID null | boleh null (calon / keluar tanpa kelas) |
| `mulai_at` | date | mulai penempatan |
| `selesai_at` | date null | null = masih berjalan |
| `jenis` | string | `awal` · `kenaikan` · `pindah_kelas` · `mutasi_masuk` · `mutasi_keluar` · `lulus` |
| `keterangan` | text null | opsional |
| `created_at` / `updated_at` | timestamps | |

Composite FK `(lembaga_id, …)` mengikuti pola foundation. Index: `(lembaga_id, siswa_id)`, partial unique satu baris terbuka per siswa bila memungkinkan di PostgreSQL (`WHERE selesai_at IS NULL`), atau ditegakkan di service layer + tes.

### 5.2 Aturan penempatan

1. Paling banyak **satu** baris terbuka (`selesai_at IS NULL`) per siswa saat masih punya penempatan berjalan.
2. Naik / pindah / keluar / lulus: tutup baris terbuka (`selesai_at` = tanggal efektif) → buat baris baru jika masih ditempatkan.
3. `siswa.kelas_id` / `tahun_ajaran_id` = **mirror** penempatan terbuka; `null` untuk `mutasi_keluar`, `lulus`, dan `calon` tanpa kelas.
4. Import siswa dari detail kelas (M6b): status **`aktif`**, enrollment `jenis=awal` (atau `mutasi_masuk` hanya jika admin memilih konteks itu di fase berikutnya; **default M6c = `awal` + `aktif`**).

### 5.3 Migration & backfill

- Alter `siswa`: kolom status + metadata (§4.3).
- Create `siswa_penempatan`.
- Backfill: setiap siswa dengan `kelas_id` terisi → 1 baris `jenis=awal`, `mulai_at` ≈ `created_at` (atau `status_at` bila ada), `selesai_at` null.
- Backfill status (terkunci): semua siswa existing → `status_siswa=aktif`; nilai `is_active` **tidak diubah** saat migrasi. Jangan menebak `lulus` / `mutasi_keluar`. Setelah M6c live, aksi lifecycle-lah yang menyelaraskan `is_active` menurut tabel §4.1. UI menjelaskan bahwa siswa nonaktif lama bisa di-set status lifecycle secara eksplisit.

## 6. Arsitektur UI

### 6.1 Otorisasi

- Middleware: `auth`, `active`, `mfa`.
- `EnsuresAdminLembaga` → SA 403.
- `lembaga_id` dari auth.
- Service lifecycle dalam transaksi DB.

### 6.2 Routes (usulan)

Prefix `/admin`:

| Area | Methods |
|------|---------|
| Siswa lifecycle (per siswa) | POST/PUT aksi: tempatkan, pindah kelas, mutasi keluar, luluskan, set calon/mutasi_masuk (nama route final di plan) |
| Kenaikan batch | GET form + POST commit (dari detail kelas dan/atau entry “Kenaikan kelas”) |
| Siswa index | filter `status_siswa` |
| Siswa show | panel riwayat `siswa_penempatan` (read-only) |

CRUD M6b tetap; edit biasa tidak boleh melewati aturan transisi status tanpa aksi lifecycle.

### 6.3 Komponen utama

| Komponen | Peran |
|----------|--------|
| `SiswaLifecycleService` (nama final di plan) | Transisi status + tutup/buka enrollment + sync snapshot |
| `KenaikanKelasService` | Batch commit atomik |
| Controllers + Form Requests | Validasi tenant & input |
| Views `admin/siswa/*`, `admin/kelas/*` (+ wizard kenaikan) | Aksi & filter |
| `AuditLogger` | Event lifecycle (tanpa dump PII berlebih) |

## 7. Perilaku fungsional

### 7.1 Aksi per siswa

| Aksi | Efek |
|------|------|
| Set calon / mutasi masuk | update status + metadata; kelas opsional |
| Tempatkan ke kelas | enrollment baru; biasanya status → `aktif` |
| Pindah kelas (sama TA) | tutup enrollment → buka `jenis=pindah_kelas` |
| Mutasi keluar | `mutasi_keluar`, kosongkan kelas, tutup enrollment `mutasi_keluar` |
| Luluskan | `lulus`, kosongkan kelas, tutup enrollment `lulus` |
| Riwayat | daftar penempatan di show |

### 7.2 Kenaikan batch

1. Pilih kelas asal → TA / kelas tujuan.
2. Daftar default: siswa `status_siswa=aktif` di kelas asal.
3. Per baris override: naik | tinggal (kelas lain/sama) | lulus | mutasi keluar.
4. Commit **satu transaksi**; gagal → rollback + pesan per baris (pola mirip import).

### 7.3 List siswa

- Filter status: semua / calon / mutasi_masuk / aktif / mutasi_keluar / lulus.
- Badge status; badge “Belum ada kelas” untuk tanpa `kelas_id`.

## 8. Persiapan kontrak API (bukan implementasi M7)

1. Payload siswa nanti: `status_siswa`, `status_at`, `is_active`, `kelas_id`, `tahun_ajaran_id`.
2. Opsional: embed `penempatan_aktif` / `riwayat_penempatan` atau resource terpisah; bedakan field_profile minimal vs lengkap.
3. Filter list API nanti: by `status_siswa`, by TA/kelas pada penempatan aktif.
4. Sync: soft delete = tombstone; `lulus` / `mutasi_keluar` = record hidup, `is_active=false`.
5. Update SPEC §3.8 (status + enrollment); §7: pindahkan “histori enrollments” ke spek ini (bukan “belum di-spec”).
6. Sisipkan checklist M6c di `IMPLEMENTATION_TODO.md` sebelum Milestone 7.

## 9. Testing (wajib)

- Tenant: lembaga A tidak bisa lifecycle siswa lembaga B.
- Transisi ilegal ditolak.
- Paling banyak satu enrollment terbuka per siswa.
- Snapshot `kelas_id` / `tahun_ajaran_id` konsisten setelah naik/pindah/keluar/lulus.
- Batch kenaikan atomic (semua sukses atau rollback).
- Import siswa ke kelas tetap menghasilkan `aktif` + enrollment awal.
- Backfill migration tidak merusak siswa tanpa kelas.

## 10. Urutan implementasi (ringkas)

1. Migration + model + backfill + factory.
2. Service lifecycle + tes unit/feature inti.
3. Aksi per siswa di UI show.
4. Filter list + badge.
5. Wizard kenaikan batch.
6. Update SPEC / IMPLEMENTATION_TODO; audit events.
7. Review + suite tes penuh sebelum centang M6c.

## 11. Risiko

- Data lama `is_active=false` tanpa status lifecycle → perlu copy UX yang jelas di filter/list.
- Partial unique enrollment terbuka: enforce di DB jika bisa; selalu di service + tes.
- Batch besar: batasi ukuran batch atau chunk dalam transaksi wajar (detail di plan).
