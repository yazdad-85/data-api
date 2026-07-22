# Milestone 6b Kelas + Siswa Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Ship Admin Lembaga Blade CRUD for Kelas (with Excel import) and Siswa (NIS required; import only from kelas detail), with tenant-safe relations and hard-delete empty kelas.

**Architecture:** Controllers under `/admin` with `EnsuresAdminLembaga`; Form Requests validate composite relations; PhpSpreadsheet importers mirror Guru/Karyawan; siswa import binds `kelas_id` + `tahun_ajaran_id` from route model. Follow RULES B1: write code → review → test (not classic TDD).

**Tech Stack:** Laravel 13, Blade, Form Request, PhpSpreadsheet, PHPUnit, PHP 8.5 (`/usr/local/Cellar/php/8.5.8/bin/php`), PostgreSQL.

**Spec:** `docs/superpowers/specs/2026-07-22-milestone-6b-kelas-siswa-design.md`

---

## File structure

| Path | Responsibility |
|------|----------------|
| `database/factories/KelasFactory.php`, `SiswaFactory.php` | Fixtures |
| `app/Models/Kelas.php`, `Siswa.php` | `HasFactory` if missing |
| `app/Http/Controllers/Admin/KelasController.php` | CRUD + show + kelas import/template + destroy |
| `app/Http/Controllers/Admin/SiswaController.php` | CRUD + search + activate/deactivate + soft delete |
| `app/Http/Requests/Admin/StoreKelasRequest.php`, `UpdateKelasRequest.php`, `ImportKelasRequest.php` | Validasi kelas |
| `app/Http/Requests/Admin/StoreSiswaRequest.php`, `UpdateSiswaRequest.php` | NIS wajib; relasi kelas/TA |
| `app/Services/Kelas/KelasTemplateExporter.php`, `KelasImporter.php` | Excel kelas |
| `app/Services/Siswa/SiswaTemplateExporter.php`, `SiswaImporter.php` | Excel siswa (konteks kelas) |
| `resources/views/admin/kelas/*`, `admin/siswa/*` | UI |
| `routes/web.php` | Register routes; coming-soon only if still needed |
| `app/Support/Navigation/AdminMenu.php` | Enable Kelas & Siswa |
| `tests/Feature/MasterKelasTest.php`, `MasterSiswaTest.php`, `KelasImportTest.php`, `KelasSiswaImportTest.php` | Coverage |
| `docs/SPEC.md`, `docs/IMPLEMENTATION_TODO.md` | NIS wajib UI; checklist M6b |

---

### Task 1: Factories + HasFactory

**Files:**
- Create: `database/factories/KelasFactory.php`, `database/factories/SiswaFactory.php`
- Modify: `app/Models/Kelas.php`, `app/Models/Siswa.php`

- [ ] **Step 1: Add HasFactory + factories**

`KelasFactory`: `lembaga_id`, `tahun_ajaran_id` via `TahunAjaran::factory()`, `nama` unique-ish (`VII-` + letter), optional `tingkat`.

`SiswaFactory`: `lembaga_id`, `nama`, `nis` unique per run, `is_active` true; state `withoutKelas()`, `inKelas(Kelas $kelas)`.

- [ ] **Step 2: Smoke create in tinker/test**

```bash
/usr/local/Cellar/php/8.5.8/bin/php artisan test --filter=does_not_exist 2>/dev/null; true
```

- [ ] **Step 3: Commit**

```bash
git commit -m "feat(m6b): add Kelas and Siswa factories"
```

---

### Task 2: Kelas CRUD + show + hard delete

**Files:**
- Create controller, Store/Update requests, views `index/create/edit/show`
- Modify: `routes/web.php`
- Test: `tests/Feature/MasterKelasTest.php`

- [ ] **Step 1: Routes** (before `{kelas}` wildcards that catch `create`/`template`):

```php
Route::get('/kelas', [KelasController::class, 'index'])->name('admin.kelas.index');
Route::get('/kelas/create', [KelasController::class, 'create'])->name('admin.kelas.create');
Route::post('/kelas', [KelasController::class, 'store'])->name('admin.kelas.store');
Route::get('/kelas/{kelas}', [KelasController::class, 'show'])->name('admin.kelas.show');
Route::get('/kelas/{kelas}/edit', [KelasController::class, 'edit'])->name('admin.kelas.edit');
Route::put('/kelas/{kelas}', [KelasController::class, 'update'])->name('admin.kelas.update');
Route::delete('/kelas/{kelas}', [KelasController::class, 'destroy'])->name('admin.kelas.destroy');
```

- [ ] **Step 2: StoreKelasRequest**

Rules: `tahun_ajaran_id` required + exists for current lembaga; `nama` required max 50; `tingkat` nullable; `wali_kelas_guru_id` nullable + exists guru lembaga. After-hook: unique nama per TA including `Kelas::withTrashed()`.

- [ ] **Step 3: Controller patterns**

Mirror `GuruController` + `EnsuresAdminLembaga`. Index: filter `tahun_ajaran_id`, search nama, paginate 15, `withCount('siswa')`, eager `tahunAjaran`, `waliKelas`. Destroy: if `$kelas->siswa()->exists()` error; else `forceDelete()`.

Show: list siswa of kelas (paginate or limit).

- [ ] **Step 4: Views** — form selects tahun ajaran + wali from lembaga; show lists siswa + placeholder for import actions (wired in Task 5).

- [ ] **Step 5: Tests** — create/update; SA 403; other lembaga 404; destroy blocked with siswa; destroy forceDeletes empty; duplicate nama validation.

```bash
/usr/local/Cellar/php/8.5.8/bin/php artisan test --filter=MasterKelasTest
```

- [ ] **Step 6: Commit**

```bash
git commit -m "feat(m6b): Admin Lembaga kelas CRUD with hard delete"
```

---

### Task 3: Import kelas + template

**Files:**
- Create: `app/Services/Kelas/KelasTemplateExporter.php`, `KelasImporter.php`, `ImportKelasRequest.php`
- Modify: `KelasController`, routes, `admin/kelas/index.blade.php`
- Test: `tests/Feature/KelasImportTest.php`

- [ ] **Step 1: Template sheets** `Petunjuk` + `Data Kelas`; headers: `nama`, `tahun_ajaran`, `tingkat`, `wali_kelas_niy`.

- [ ] **Step 2: Importer** — resolve `tahun_ajaran` by `nama` + lembaga; resolve wali by `niy`; create rows; collect per-row errors.

- [ ] **Step 3: Routes**

```php
Route::get('/kelas/template', ...)->name('admin.kelas.template');
Route::post('/kelas/import', ...)->name('admin.kelas.import');
```

Place **before** `/kelas/{kelas}`.

- [ ] **Step 4: Tests** — download ok; import 2 rows; bad tahun_ajaran fails row.

```bash
/usr/local/Cellar/php/8.5.8/bin/php artisan test --filter=KelasImportTest
```

- [ ] **Step 5: Commit**

```bash
git commit -m "feat(m6b): Excel import for kelas"
```

---

### Task 4: Siswa CRUD + search + soft delete

**Files:**
- Create `SiswaController`, Store/Update requests, views
- Modify routes, later menu in Task 6
- Test: `tests/Feature/MasterSiswaTest.php`

- [ ] **Step 1: StoreSiswaRequest / UpdateSiswaRequest**

- `nama`, `nis` required; `nisn` nullable; uniqueness `withTrashed()` on nis/nisn per lembaga.
- If `kelas_id` filled: load kelas, require matching `tahun_ajaran_id`, same lembaga.
- If no kelas: force `kelas_id`/`tahun_ajaran_id` null (ignore client mismatch).

- [ ] **Step 2: Controller** — search nama/nis/nisn; optional filters; activate/deactivate/destroy soft; show audits `master.view` without PII dump.

- [ ] **Step 3: Views** — badge “Belum ada kelas”; NIS required on forms.

- [ ] **Step 4: Tests** — create with/without kelas; mismatch TA rejected; soft-deleted NIS blocks recreate; SA 403; cross-lembaga 404; search.

```bash
/usr/local/Cellar/php/8.5.8/bin/php artisan test --filter=MasterSiswaTest
```

- [ ] **Step 5: Commit**

```bash
git commit -m "feat(m6b): Admin Lembaga siswa CRUD with required NIS"
```

---

### Task 5: Import siswa from kelas detail

**Files:**
- Create `SiswaTemplateExporter`, `SiswaImporter`
- Modify `KelasController@show` or dedicated methods `siswaTemplate` / `siswaImport`
- Modify `admin/kelas/show.blade.php`
- Test: `tests/Feature/KelasSiswaImportTest.php`

- [ ] **Step 1: Routes**

```php
Route::get('/kelas/{kelas}/siswa/template', ...)->name('admin.kelas.siswa.template');
Route::post('/kelas/{kelas}/siswa/import', ...)->name('admin.kelas.siswa.import');
```

- [ ] **Step 2: Template** — sheets Petunjuk + Data Siswa; headers: `nis`, `nama`, `nisn`, `jenis_kelamin`, `tempat_lahir`, `tanggal_lahir`, `email`, `telepon`, `alamat`, `nama_wali`, `telepon_wali`.

- [ ] **Step 3: Importer** — require nis+nama; set `lembaga_id`, `kelas_id`, `tahun_ajaran_id` from `$kelas`; skip empty; duplicate nis → row error.

- [ ] **Step 4: UI on show** — Unduh template + Import modal; flash import_errors.

- [ ] **Step 5: Tests** — import attaches kelas; appears in `admin.siswa.index`; duplicate nis fails.

```bash
/usr/local/Cellar/php/8.5.8/bin/php artisan test --filter=KelasSiswaImportTest
```

- [ ] **Step 6: Commit**

```bash
git commit -m "feat(m6b): import siswa from kelas detail"
```

---

### Task 6: Menu + coming-soon + docs

**Files:**
- Modify: `AdminMenu.php`, `routes/web.php` (coming-soon `where` if only unused features remain), `docs/SPEC.md` §3.8, `docs/IMPLEMENTATION_TODO.md`, `tests/Feature/AdminShellTest.php` if asserts coming-soon

- [ ] **Step 1: Menu** — Kelas → `admin.kelas.index` available true; Siswa → `admin.siswa.index` available true.

- [ ] **Step 2: SPEC** — note NIS required on Admin Lembaga UI create/import.

- [ ] **Step 3: TODO** — check all M6b boxes; status M6 complete or M6b done.

- [ ] **Step 4: Full suite**

```bash
/usr/local/Cellar/php/8.5.8/bin/php artisan test
```

- [ ] **Step 5: Commit**

```bash
git commit -m "docs(m6b): enable kelas/siswa menus and mark milestone complete"
```

---

## Spec coverage checklist

| Spec item | Task |
|-----------|------|
| Kelas CRUD + hard delete | 2 |
| Import kelas | 3 |
| Siswa CRUD, NIS wajib, badge | 4 |
| Import siswa from detail | 5 |
| Menu + TODO + SPEC | 6 |
| SA 403 / tenant tests | 2, 4 |
| Partial unique / withTrashed NIS | 4, 5 |

## Out of scope (do not implement)

Enrollments history, siswa import on siswa index, auto NIS, Livewire, REST API.
