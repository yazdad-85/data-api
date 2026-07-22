# Milestone 6c Siswa Lifecycle Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Ship Admin Lembaga lifecycle siswa (status, enrollment histori, aksi per siswa, kenaikan batch) sebelum M7 API, tanpa REST endpoint.

**Architecture:** Hybrid — kolom `status_siswa` + metadata di `siswa`; tabel `siswa_penempatan` untuk histori; `kelas_id`/`tahun_ajaran_id` tetap snapshot. Semua mutasi lewat `SiswaLifecycleService` / `KenaikanKelasService` dalam transaksi. UI Blade + Form Request; SA 403. Ikuti RULES B1: implement → review → test (bukan classic TDD).

**Tech Stack:** Laravel 13, Blade, Form Request, PHPUnit, PHP 8.5 (`/usr/local/Cellar/php/8.5.8/bin/php`), PostgreSQL.

**Spec:** `docs/superpowers/specs/2026-07-22-milestone-6c-siswa-lifecycle-design.md`

---

## File structure

| Path | Responsibility |
|------|----------------|
| `database/migrations/2026_07_22_000001_add_siswa_lifecycle_and_penempatan.php` | Kolom status + tabel `siswa_penempatan` + backfill |
| `app/Support/Master/SiswaStatus.php` | Konstanta status + transisi + mapping `is_active` |
| `app/Models/SiswaPenempatan.php` | Model enrollment |
| `app/Models/Siswa.php` | Fillable/casts/relasi `penempatans`, `penempatanAktif` |
| `database/factories/SiswaFactory.php`, `SiswaPenempatanFactory.php` | Fixtures |
| `app/Services/Siswa/SiswaLifecycleService.php` | Transisi status + buka/tutup enrollment + sync snapshot |
| `app/Services/Siswa/KenaikanKelasService.php` | Batch naik/tinggal/lulus/keluar atomik |
| `app/Services/Siswa/SiswaImporter.php` | Setelah create: status `aktif` + penempatan `awal` |
| `app/Http/Controllers/Admin/SiswaController.php` | Filter status; show riwayat; aksi lifecycle |
| `app/Http/Controllers/Admin/KenaikanKelasController.php` | Form + commit batch |
| `app/Http/Requests/Admin/SiswaLifecycleRequest.php`, `KenaikanKelasRequest.php` | Validasi |
| `resources/views/admin/siswa/*`, `admin/kelas/show.blade.php`, `admin/kenaikan/*` | UI |
| `routes/web.php` | Routes lifecycle + kenaikan |
| `tests/Feature/SiswaLifecycleTest.php`, `KenaikanKelasTest.php`, `SiswaPenempatanBackfillTest.php` | Coverage |
| `docs/SPEC.md`, `docs/IMPLEMENTATION_TODO.md` | Status/enrollment; sisip M6c sebelum M7 |

---

### Task 1: Konstanta status + migration + models + backfill

**Files:**
- Create: `app/Support/Master/SiswaStatus.php`
- Create: `database/migrations/2026_07_22_000001_add_siswa_lifecycle_and_penempatan.php`
- Create: `app/Models/SiswaPenempatan.php`
- Create: `database/factories/SiswaPenempatanFactory.php`
- Modify: `app/Models/Siswa.php`, `database/factories/SiswaFactory.php`

- [ ] **Step 1: `SiswaStatus` helper**

```php
<?php

namespace App\Support\Master;

final class SiswaStatus
{
    public const CALON = 'calon';
    public const MUTASI_MASUK = 'mutasi_masuk';
    public const AKTIF = 'aktif';
    public const MUTASI_KELUAR = 'mutasi_keluar';
    public const LULUS = 'lulus';

    public const ALL = [
        self::CALON,
        self::MUTASI_MASUK,
        self::AKTIF,
        self::MUTASI_KELUAR,
        self::LULUS,
    ];

    /** @return list<string> */
    public static function allowedTransitions(string $from): array
    {
        return match ($from) {
            self::CALON => [self::MUTASI_MASUK, self::AKTIF],
            self::MUTASI_MASUK => [self::AKTIF, self::MUTASI_KELUAR],
            self::AKTIF => [self::AKTIF, self::MUTASI_KELUAR, self::LULUS],
            default => [],
        };
    }

    public static function isActiveFlag(string $status): bool
    {
        return match ($status) {
            self::AKTIF => true,
            self::MUTASI_MASUK => true, // setelah penempatan; calon false; service set false jika belum kelas
            self::CALON, self::MUTASI_KELUAR, self::LULUS => false,
            default => false,
        };
    }
}
```

Catatan implementasi `mutasi_masuk` tanpa kelas: service set `is_active=false` sampai ditempatkan; setelah punya kelas → `true` (selaras spek §4.1).

Jenis penempatan (string constants di model atau class `PenempatanJenis`): `awal`, `kenaikan`, `pindah_kelas`, `mutasi_masuk`, `mutasi_keluar`, `lulus`.

- [ ] **Step 2: Migration**

```php
Schema::table('siswa', function (Blueprint $table) {
    $table->string('status_siswa', 30)->default('aktif')->after('is_active');
    $table->date('status_at')->nullable()->after('status_siswa');
    $table->string('status_alasan', 255)->nullable()->after('status_at');
    $table->string('status_asal', 150)->nullable()->after('status_alasan');
    $table->string('status_tujuan', 150)->nullable()->after('status_asal');
    $table->index(['lembaga_id', 'status_siswa']);
});

Schema::create('siswa_penempatan', function (Blueprint $table) {
    $table->uuid('id')->primary();
    $table->uuid('lembaga_id');
    $table->uuid('siswa_id');
    $table->uuid('tahun_ajaran_id')->nullable();
    $table->uuid('kelas_id')->nullable();
    $table->date('mulai_at');
    $table->date('selesai_at')->nullable();
    $table->string('jenis', 30);
    $table->text('keterangan')->nullable();
    $table->timestamps();

    $table->foreign('lembaga_id')->references('id')->on('lembaga')->restrictOnDelete();
    $table->foreign(['lembaga_id', 'siswa_id'])->references(['lembaga_id', 'id'])->on('siswa')->restrictOnDelete();
    $table->foreign(['lembaga_id', 'tahun_ajaran_id'])->references(['lembaga_id', 'id'])->on('tahun_ajaran')->restrictOnDelete();
    // kelas FK: hanya jika tahun_ajaran_id + kelas_id terisi — gunakan composite seperti siswa bila keduanya NOT NULL;
    // jika kelas_id null diizinkan, FK kelas opsional: foreign(['lembaga_id','kelas_id']) → kelas(lembaga_id,id) HANYA jika skema kelas punya unique (lembaga_id,id) — sudah ada di foundation.

    $table->unique(['lembaga_id', 'id']);
    $table->index(['lembaga_id', 'siswa_id']);
});

// Partial unique satu penempatan terbuka (PostgreSQL):
DB::statement('CREATE UNIQUE INDEX siswa_penempatan_satu_terbuka_per_siswa ON siswa_penempatan (lembaga_id, siswa_id) WHERE selesai_at IS NULL');

// Backfill status:
DB::table('siswa')->whereNull('status_siswa')->orWhere('status_siswa', '')->update(['status_siswa' => 'aktif']);
// (kolom baru sudah default aktif)

// Backfill penempatan untuk siswa yang punya kelas_id:
// INSERT ... SELECT id, lembaga_id, siswa_id=id, tahun_ajaran_id, kelas_id, mulai_at=DATE(created_at), selesai_at=null, jenis='awal'
```

Sesuaikan FK `kelas` dengan unique yang ada di tabel `kelas` (`unique(['lembaga_id','id'])` atau composite dengan TA — cek migration foundation; mirror pola di `siswa`).

- [ ] **Step 3: Models + factories**

`Siswa`: tambah fillable status fields; casts `status_at` date; relasi:

```php
public function penempatans(): HasMany
{
    return $this->hasMany(SiswaPenempatan::class);
}

public function penempatanAktif(): HasOne
{
    return $this->hasOne(SiswaPenempatan::class)->whereNull('selesai_at');
}
```

`SiswaPenempatan`: `BelongsToLembaga`, `HasUuids`, fillable, relasi siswa/kelas/tahunAjaran.

Factory: `SiswaFactory` default `status_siswa=aktif`; state `calon()`, `lulus()`, dll. `SiswaPenempatanFactory` + state `open()`.

- [ ] **Step 4: Migrate + test backfill**

```bash
/usr/local/Cellar/php/8.5.8/bin/php artisan migrate
/usr/local/Cellar/php/8.5.8/bin/php artisan test --filter=SiswaPenempatanBackfill
```

Tulis `tests/Feature/SiswaPenempatanBackfillTest.php`: create siswa+kelas pre-migration style via factory after migrate; assert penempatan dibuat saat menjalankan seeder helper **atau** test service backfill command jika backfill hanya di migration — cukup assert factory `inKelas` membuat penempatan di Task 2; untuk migration backfill, test dengan `RefreshDatabase` setelah migrate bahwa siswa seeded di test setup punya penempatan jika dibuat lewat path yang memanggil service. Minimal: test bahwa unique index + model create open penempatan works.

- [ ] **Step 5: Commit**

```bash
git add app/Support/Master/SiswaStatus.php database/migrations/2026_07_22_000001_add_siswa_lifecycle_and_penempatan.php app/Models/Siswa.php app/Models/SiswaPenempatan.php database/factories/
git commit -m "feat(m6c): add siswa status columns and siswa_penempatan"
```

---

### Task 2: `SiswaLifecycleService`

**Files:**
- Create: `app/Services/Siswa/SiswaLifecycleService.php`
- Test: `tests/Feature/SiswaLifecycleTest.php` (atau Unit + Feature)

- [ ] **Step 1: Implement service methods** (semua dalam `DB::transaction`)

```php
final class SiswaLifecycleService
{
    public function setStatus(Siswa $siswa, string $to, array $meta = []): Siswa
    public function tempatkan(Siswa $siswa, Kelas $kelas, ?CarbonInterface $mulai = null, string $jenis = 'awal'): Siswa
    public function pindahKelas(Siswa $siswa, Kelas $kelasTujuan, ?CarbonInterface $mulai = null): Siswa
    public function mutasiKeluar(Siswa $siswa, array $meta = []): Siswa
    public function luluskan(Siswa $siswa, array $meta = []): Siswa

    private function assertTransition(string $from, string $to): void
    private function closeOpenPenempatan(Siswa $siswa, CarbonInterface $selesaiAt): void
    private function openPenempatan(Siswa $siswa, ?Kelas $kelas, string $jenis, CarbonInterface $mulaiAt, ?string $keterangan = null): SiswaPenempatan
    private function syncSnapshot(Siswa $siswa, ?Kelas $kelas): void // set/null kelas_id + tahun_ajaran_id
    private function applyStatusFlags(Siswa $siswa, string $status): void // status_siswa, is_active, status_at
}
```

Aturan kunci:
- `tempatkan` dari `calon`/`mutasi_masuk` → status `aktif`, `jenis` default `awal` atau `mutasi_masuk` jika status sebelumnya `mutasi_masuk`.
- `pindahKelas`: hanya dari `aktif`; kelas tujuan sama lembaga; tutup open → buka `pindah_kelas` (sama TA) atau `kenaikan` jika TA berbeda (opsional param `$jenis`).
- `mutasiKeluar` / `luluskan`: tutup open dengan jenis sesuai; `kelas_id`/`tahun_ajaran_id` null; `is_active` false.
- Tolak jika sudah `lulus`/`mutasi_keluar` (fase 1).
- Tolak dua open penempatan (andal index + cek di code).

- [ ] **Step 2: Feature tests**

```php
public function test_tempatkan_calon_menjadi_aktif_dengan_satu_penempatan_terbuka(): void
public function test_transisi_ilegal_ditolak(): void
public function test_mutasi_keluar_mengosongkan_kelas_dan_menutup_penempatan(): void
public function test_pindah_kelas_menutup_lama_membuka_baru(): void
public function test_admin_lembaga_a_tidak_bisa_lifecycle_siswa_b(): void // via HTTP di Task 3; service cukup lembaga match
```

Jalankan:

```bash
/usr/local/Cellar/php/8.5.8/bin/php artisan test --filter=SiswaLifecycle
```

Expected: PASS.

- [ ] **Step 3: Commit**

```bash
git commit -m "feat(m6c): add SiswaLifecycleService with transition rules"
```

---

### Task 3: Hook create/import + aksi per siswa di UI

**Files:**
- Modify: `app/Http/Controllers/Admin/SiswaController.php` (`store`/`update`/`show`)
- Modify: `app/Services/Siswa/SiswaImporter.php`
- Create: `app/Http/Requests/Admin/SiswaLifecycleRequest.php`
- Modify: `routes/web.php`
- Modify: `resources/views/admin/siswa/show.blade.php`, `create.blade.php` (opsional status awal)
- Test: extend `tests/Feature/MasterSiswaTest.php` + `KelasSiswaImportTest.php` + lifecycle HTTP tests

- [ ] **Step 1: Routes** (sebelum wildcard yang bentrok)

```php
Route::post('/siswa/{siswa}/lifecycle/tempatkan', ...)->name('admin.siswa.lifecycle.tempatkan');
Route::post('/siswa/{siswa}/lifecycle/pindah-kelas', ...)->name('admin.siswa.lifecycle.pindah');
Route::post('/siswa/{siswa}/lifecycle/mutasi-keluar', ...)->name('admin.siswa.lifecycle.mutasi_keluar');
Route::post('/siswa/{siswa}/lifecycle/luluskan', ...)->name('admin.siswa.lifecycle.lulus');
Route::post('/siswa/{siswa}/lifecycle/set-status', ...)->name('admin.siswa.lifecycle.set_status'); // calon | mutasi_masuk
```

Controller methods panggil service; `abort_unless` admin lembaga; audit `siswa.lifecycle.*`.

- [ ] **Step 2: `store` siswa**

Jika `kelas_id` terisi → setelah create panggil `tempatkan` (atau create + open penempatan `awal` + `status=aktif`). Jika tanpa kelas → `status_siswa=calon` (atau biarkan pilihan form; **default spek create tanpa kelas = `calon`** atau `aktif` tanpa kelas? Spek: siswa tanpa kelas diizinkan M6b; untuk M6c default create tanpa kelas = `calon` jika form tidak memilih; create dengan kelas = `aktif` + penempatan. Dokumentasikan di form.

- [ ] **Step 3: Importer**

Setelah `Siswa::create(...)` sukses, pastikan `status_siswa=aktif`, buka penempatan `awal` untuk kelas import (idempotent jika service dipanggil).

- [ ] **Step 4: Show UI**

- Badge status + metadata.
- Tabel riwayat penempatan.
- Modal/form: Tempatkan, Pindah kelas, Mutasi keluar, Luluskan, Set calon/mutasi masuk (hanya jika transisi valid).

- [ ] **Step 5: Tests HTTP**

```bash
/usr/local/Cellar/php/8.5.8/bin/php artisan test --filter='SiswaLifecycle|MasterSiswa|KelasSiswaImport'
```

- [ ] **Step 6: Commit**

```bash
git commit -m "feat(m6c): per-siswa lifecycle actions and import enrollment"
```

---

### Task 4: Filter list + badge

**Files:**
- Modify: `SiswaController@index`, `resources/views/admin/siswa/index.blade.php`
- Modify: `resources/views/admin/kelas/show.blade.php` (hanya tampilkan operasional / badge status)

- [ ] **Step 1: Query filter `status_siswa`**

```php
$status = $request->query('status_siswa');
->when(is_string($status) && in_array($status, SiswaStatus::ALL, true), fn ($q) => $q->where('status_siswa', $status))
```

Default index: tanpa filter = semua; opsional default `aktif` — **terkunci spek: default semua**, filter eksplisit.

- [ ] **Step 2: Badge di tabel** (calon / mutasi_masuk / aktif / mutasi_keluar / lulus) + “Belum ada kelas”.

- [ ] **Step 3: Test filter**

```php
public function test_index_filter_status_lulus(): void
```

- [ ] **Step 4: Commit**

```bash
git commit -m "feat(m6c): filter and badge status siswa on index"
```

---

### Task 5: Kenaikan kelas batch

**Files:**
- Create: `app/Services/Siswa/KenaikanKelasService.php`
- Create: `app/Http/Controllers/Admin/KenaikanKelasController.php`
- Create: `app/Http/Requests/Admin/KenaikanKelasRequest.php`
- Create: `resources/views/admin/kenaikan/create.blade.php` (atau `admin/kelas/kenaikan.blade.php`)
- Modify: `routes/web.php`, `resources/views/admin/kelas/show.blade.php` (tombol entry)
- Test: `tests/Feature/KenaikanKelasTest.php`

- [ ] **Step 1: Service**

```php
/**
 * @param  list<array{siswa_id: string, aksi: 'naik'|'tinggal'|'lulus'|'mutasi_keluar', kelas_tujuan_id?: string|null, meta?: array}>  $rows
 * @return array{success: int, failed: int, errors: list<array{row: int, message: string}>}
 */
public function commit(Kelas $kelasAsal, TahunAjaran $tahunTujuan, ?Kelas $kelasTujuanDefault, array $rows, CarbonInterface $efektifAt): array
```

- Load semua siswa_id milik lembaga + kelas asal + `status_siswa=aktif`.
- Satu `DB::transaction`; jika ada error validasi sebelum commit, kumpulkan errors dan **jangan** partial commit (spek: atomic — jika satu gagal, rollback seluruh batch). Implementasi: validasi semua dulu, baru mutate; atau try/catch rollback.
- `naik`: pindah ke `kelas_tujuan` (default atau per-row), jenis `kenaikan`.
- `tinggal`: opsional pindah ke kelas lain di TA yang sama/beda sesuai input; jika tidak ada perubahan, skip atau tetap di kelas asal (dokumentasikan: tinggal = tidak ikut naik, biarkan di kelas asal).
- `lulus` / `mutasi_keluar`: panggil lifecycle service.

Batasi max rows (mis. 200) di Form Request.

- [ ] **Step 2: Routes + UI**

```php
Route::get('/kelas/{kelas}/kenaikan', ...)->name('admin.kelas.kenaikan.create');
Route::post('/kelas/{kelas}/kenaikan', ...)->name('admin.kelas.kenaikan.store');
```

Form: pilih TA tujuan + kelas tujuan default; tabel siswa dengan select aksi per baris.

- [ ] **Step 3: Tests**

```php
public function test_batch_naik_atomic_success(): void
public function test_batch_rollback_jika_satu_baris_invalid(): void
public function test_hanya_siswa_aktif_kelas_asal(): void
```

```bash
/usr/local/Cellar/php/8.5.8/bin/php artisan test --filter=KenaikanKelas
```

- [ ] **Step 4: Commit**

```bash
git commit -m "feat(m6c): batch kenaikan kelas wizard"
```

---

### Task 6: Docs + checklist M6c + full suite

**Files:**
- Modify: `docs/SPEC.md` §3.8 (status + metadata; rujuk `siswa_penempatan`); §7 hapus/pindahkan bullet enrollments
- Modify: `docs/IMPLEMENTATION_TODO.md` — sisipkan **Milestone 6c** sebelum M7 dengan checklist dari spek §9–10; link spek
- Modify: M6b spek “di luar scope” tetap; tidak reopen M6 sebagai belum selesai

- [ ] **Step 1: Update SPEC + TODO**

Checklist TODO minimal:

```markdown
## Milestone 6c - Lifecycle siswa

Status: … spek [M6c](./superpowers/specs/2026-07-22-milestone-6c-siswa-lifecycle-design.md)

- [ ] status_siswa + metadata + backfill
- [ ] tabel siswa_penempatan + satu terbuka per siswa
- [ ] SiswaLifecycleService + aksi per siswa UI
- [ ] Import/create sync enrollment
- [ ] Filter/badge status
- [ ] Kenaikan batch atomic
- [ ] Update SPEC kontrak data untuk M7
- [ ] Tes tenant + transisi + batch
```

- [ ] **Step 2: Full related tests**

```bash
/usr/local/Cellar/php/8.5.8/bin/php artisan test --filter='Siswa|Kelas|Kenaikan'
```

Expected: PASS. Lalu centang item TODO yang sudah benar-benar selesai.

- [ ] **Step 3: Commit**

```bash
git commit -m "docs(m6c): SPEC enrollment/status and IMPLEMENTATION_TODO checklist"
```

---

## Spec coverage check

| Spek | Task |
|------|------|
| Status + transisi + is_active | 1, 2 |
| Metadata status_* | 1, 3 |
| Tabel siswa_penempatan + mirror snapshot | 1, 2 |
| Backfill | 1 |
| Aksi per siswa + riwayat UI | 3 |
| Import → aktif + awal | 3 |
| Filter/badge list | 4 |
| Kenaikan batch + atomic | 5 |
| Persiapan API / SPEC / TODO | 6 |
| Tanpa REST | (tidak ada task API) |
| SA 403 / tenant | 3, 5 tests |

## Self-review notes

- Tidak ada endpoint `/api/*` di plan.
- `mutasi_masuk` tanpa kelas: `is_active` false sampai `tempatkan` (klarifikasi di Task 1/2).
- Nama route/controller boleh disesuaikan selama behavior spek terpenuhi.
- Commit steps: hanya saat user meminta commit di sesi eksekusi, atau ikuti instruksi user saat itu; agent jangan force-commit jika user rule melarang tanpa request.
