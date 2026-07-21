# Milestone 6a Tahun Ajaran + Guru + Karyawan Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Ship Admin Lembaga Blade CRUD for Tahun Ajaran (standardized `YYYY/YYYY+1` names + one-active activate), Guru (with `niy`), and Karyawan — scoped to own lembaga; Super Admin gets 403 on these routes.

**Architecture:** Separate controllers per resource under `/admin`; Form Requests; `TahunAjaranNamer` for names; DB transaction on activate; SoftDeletes + is_active for personel; migration rename `nip`→`niy`. Follow RULES B1: write code → review → test (not classic TDD).

**Tech Stack:** Laravel 13, Blade, Form Request, PHPUnit, PHP 8.5, PostgreSQL.

**Spec:** `docs/superpowers/specs/2026-07-21-milestone-6a-master-ta-guru-karyawan-design.md`

**PHP CLI:** `/usr/local/Cellar/php/8.5.8/bin/php` when default `php` is 8.1.

---

## File structure

| Path | Responsibility |
|------|----------------|
| `database/migrations/2026_07_21_000001_rename_guru_nip_to_niy.php` | Rename column |
| `docs/SPEC.md`, `docs/RULES.md` | Document `niy` |
| `app/Models/Guru.php`, `GuruFactory`, `MetadataRedactor` | `niy` field |
| `app/Support/Master/TahunAjaranNamer.php` | Format `Y/(Y+1)` |
| `database/factories/TahunAjaranFactory.php`, `KaryawanFactory.php` | Test fixtures |
| `app/Http/Controllers/Concerns/EnsuresAdminLembaga.php` | `abort_unless` AL |
| Controllers + Form Requests under `Admin/` | CRUD + activate/deactivate/destroy |
| `resources/views/admin/{tahun-ajaran,guru,karyawan}/*` | Blade UI |
| `app/Support/Navigation/AdminMenu.php` | Enable 3 menu items |
| `routes/web.php` | Register routes; trim coming-soon |
| `tests/Unit/TahunAjaranNamerTest.php` | Namer |
| `tests/Feature/MasterTahunAjaranTest.php` | TA flows |
| `tests/Feature/MasterGuruKaryawanTest.php` | Guru/Karyawan + SA 403 |
| `docs/IMPLEMENTATION_TODO.md` | Partial M6 checkboxes |

---

### Task 1: Rename `nip` → `niy` + docs

**Files:**
- Create migration
- Modify: `app/Models/Guru.php`, `database/factories/GuruFactory.php` (if nip present), `app/Support/Security/MetadataRedactor.php`, `docs/SPEC.md`, `docs/RULES.md` (if nip mentioned)

- [ ] **Step 1: Migration**

```php
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('guru', function (Blueprint $table) {
            $table->renameColumn('nip', 'niy');
        });
    }

    public function down(): void
    {
        Schema::table('guru', function (Blueprint $table) {
            $table->renameColumn('niy', 'nip');
        });
    }
};
```

Require `doctrine/dbal` only if Laravel version needs it for rename; on Laravel 13 + PostgreSQL, `renameColumn` usually works without DBAL. If migrate fails, use raw:

```php
DB::statement('ALTER TABLE guru RENAME COLUMN nip TO niy');
```

- [ ] **Step 2: Model + redactor**

In `Guru::$fillable`, replace `'nip'` with `'niy'`.

In `MetadataRedactor` sensitive keys list, replace `'nip'` with `'niy'` (keep both briefly if desired; prefer replace).

- [ ] **Step 3: SPEC**

Replace guru field table `nip` → `niy` with note “Nomor Induk Yayasan”. Update ringkasan field map §2 if it lists `nip`.

Add under Tahun ajaran notes (or RULES A6): aplikasi membentuk `nama` sebagai `YYYY/YYYY+1`.

- [ ] **Step 4: Run migrate in test env**

```bash
/usr/local/Cellar/php/8.5.8/bin/php artisan migrate --force
/usr/local/Cellar/php/8.5.8/bin/php vendor/bin/phpunit --filter=TenantAuthorizationTest --colors=never
```

Expected: PASS (Guru factory still works)

- [ ] **Step 5: Commit**

```bash
git commit -m "refactor(m6a): rename guru nip column to niy"
```

---

### Task 2: TahunAjaranNamer + factories

**Files:**
- Create: `app/Support/Master/TahunAjaranNamer.php`
- Create: `tests/Unit/TahunAjaranNamerTest.php`
- Create: `database/factories/TahunAjaranFactory.php`, `KaryawanFactory.php`
- Modify: `TahunAjaran` / `Karyawan` models to use `HasFactory` if missing

- [ ] **Step 1: Namer**

```php
<?php

namespace App\Support\Master;

final class TahunAjaranNamer
{
    public static function fromTahunMulai(int $tahunMulai): string
    {
        return sprintf('%d/%d', $tahunMulai, $tahunMulai + 1);
    }
}
```

- [ ] **Step 2: Unit test**

```php
public function test_formats_year_slash_next_year(): void
{
    $this->assertSame('2026/2027', TahunAjaranNamer::fromTahunMulai(2026));
}
```

- [ ] **Step 3: Factories**

`TahunAjaranFactory`: `lembaga_id`, `nama` via namer from fake year, dates, `is_aktif` false by default; state `aktif()`.

`KaryawanFactory`: `lembaga_id`, `nama`, `is_active` true.

Add `HasFactory` + factory class reference on models.

- [ ] **Step 4: Commit**

```bash
git commit -m "feat(m6a): add TahunAjaranNamer and master factories"
```

---

### Task 3: EnsuresAdminLembaga + Tahun Ajaran CRUD

**Files:**
- Create concern, controller, requests, views, routes
- Modify: `AdminMenu` (enable tahun-ajaran only for now, or all three at Task 5 — prefer enable per task when route exists)
- Test: `MasterTahunAjaranTest.php`

- [ ] **Step 1: Concern**

```php
<?php

namespace App\Http\Controllers\Concerns;

use App\Models\User;

trait EnsuresAdminLembaga
{
    protected function adminLembaga(): User
    {
        $user = request()->user();
        abort_unless($user?->isAdminLembaga() && $user->lembaga_id, 403);

        return $user;
    }
}
```

Use at the start of every M6a controller action.

- [ ] **Step 2: Form requests**

`StoreTahunAjaranRequest`:

```php
public function authorize(): bool
{
    return $this->user()?->isAdminLembaga() === true;
}

public function rules(): array
{
    $y = (int) now()->year;

    return [
        'tahun_mulai' => ['required', 'integer', 'min:'.($y - 2), 'max:'.($y + 3)],
        'tanggal_mulai' => ['required', 'date'],
        'tanggal_selesai' => ['required', 'date', 'after:tanggal_mulai'],
    ];
}
```

`UpdateTahunAjaranRequest`: only `tanggal_mulai`, `tanggal_selesai` (after).

- [ ] **Step 3: Controller essentials**

```php
public function store(StoreTahunAjaranRequest $request): RedirectResponse
{
    $user = $this->adminLembaga();
    $tahunMulai = (int) $request->validated('tahun_mulai');
    $nama = TahunAjaranNamer::fromTahunMulai($tahunMulai);

    // Unique check: Rule::unique('tahun_ajaran','nama')->where(fn ($q) => $q->where('lembaga_id', $user->lembaga_id)->whereNull('deleted_at'))
    // Prefer add unique rule in Form Request with lembaga_id closure.

    $ta = TahunAjaran::query()->create([
        'lembaga_id' => $user->lembaga_id,
        'nama' => $nama,
        'tanggal_mulai' => $request->validated('tanggal_mulai'),
        'tanggal_selesai' => $request->validated('tanggal_selesai'),
        'is_aktif' => false,
    ]);

    $this->auditLogger->record('tahun_ajaran.create', 'success', [
        'nama' => $ta->nama,
    ], subject: $ta, lembagaId: $user->lembaga_id, request: $request);

    return redirect()->route('admin.tahun-ajaran.index')->with('status', 'Tahun ajaran dibuat.');
}

public function activate(Request $request, TahunAjaran $tahunAjaran): RedirectResponse
{
    $user = $this->adminLembaga();
    abort_unless(hash_equals((string) $tahunAjaran->lembaga_id, (string) $user->lembaga_id), 404);

    DB::transaction(function () use ($user, $tahunAjaran) {
        TahunAjaran::query()
            ->where('lembaga_id', $user->lembaga_id)
            ->where('is_aktif', true)
            ->update(['is_aktif' => false]);

        $tahunAjaran->update(['is_aktif' => true]);
    });

    $this->auditLogger->record('tahun_ajaran.activate', 'success', [
        'nama' => $tahunAjaran->nama,
    ], subject: $tahunAjaran, lembagaId: $user->lembaga_id, request: $request);

    return back()->with('status', 'Tahun ajaran diaktifkan.');
}

public function destroy(Request $request, TahunAjaran $tahunAjaran): RedirectResponse
{
    $user = $this->adminLembaga();
    abort_unless(hash_equals((string) $tahunAjaran->lembaga_id, (string) $user->lembaga_id), 404);

    if ($tahunAjaran->kelas()->exists()) {
        return back()->withErrors([
            'tahun_ajaran' => 'Tahun ajaran tidak dapat dihapus karena masih dipakai kelas.',
        ]);
    }

    $tahunAjaran->delete();
    $this->auditLogger->record('tahun_ajaran.delete', 'success', [
        'nama' => $tahunAjaran->nama,
    ], subject: $tahunAjaran, lembagaId: $user->lembaga_id, request: $request);

    return redirect()->route('admin.tahun-ajaran.index')->with('status', 'Tahun ajaran dihapus.');
}
```

`index`/`create`/`edit`/`update` follow M5 lembaga patterns. Route model binding: ensure soft-deleted not found by default.

- [ ] **Step 4: Routes**

```php
Route::get('/tahun-ajaran', ...)->name('admin.tahun-ajaran.index');
Route::get('/tahun-ajaran/create', ...)->name('admin.tahun-ajaran.create');
Route::post('/tahun-ajaran', ...)->name('admin.tahun-ajaran.store');
Route::get('/tahun-ajaran/{tahun_ajaran}/edit', ...)->name('admin.tahun-ajaran.edit');
Route::put('/tahun-ajaran/{tahun_ajaran}', ...)->name('admin.tahun-ajaran.update');
Route::post('/tahun-ajaran/{tahun_ajaran}/activate', ...)->name('admin.tahun-ajaran.activate');
Route::delete('/tahun-ajaran/{tahun_ajaran}', ...)->name('admin.tahun-ajaran.destroy');
```

Use parameter `{tahunAjaran}` consistent with Laravel camel binding or explicit `tahun_ajaran`.

Coming-soon `where`: remove `tahun-ajaran` when live.

- [ ] **Step 5: Blade**

`index` with badges + Aktifkan modal + Hapus modal; `create` select tahun_mulai + dates; `edit` dates only (show nama read-only).

- [ ] **Step 6: Feature tests**

```php
// AL creates → nama 2026/2027
// Activate B while A active → only B is_aktif
// Duplicate nama → 422
// SA get index → 403
// Soft delete blocked when kelas exists (create kelas via model if needed, or skip until M6b with empty kelas)
```

```bash
/usr/local/Cellar/php/8.5.8/bin/php vendor/bin/phpunit --filter=MasterTahunAjaranTest --colors=never
```

- [ ] **Step 7: Commit**

```bash
git commit -m "feat(m6a): Admin Lembaga tahun ajaran CRUD and activate-one"
```

---

### Task 4: Guru CRUD

**Files:**
- `GuruController`, Store/Update requests, views, routes
- Extend `MasterGuruKaryawanTest` or `MasterGuruTest`

- [ ] **Step 1: Validation rules**

```php
'niy' => ['nullable', 'string', 'max:40'],
'nuptk' => ['nullable', 'string', 'max:40'],
'nama' => ['required', 'string', 'max:150'],
'jenis_kelamin' => ['nullable', Rule::in(['L', 'P'])],
'tempat_lahir' => ['nullable', 'string', 'max:100'],
'tanggal_lahir' => ['nullable', 'date'],
'email' => ['nullable', 'email', 'max:150'],
'telepon' => ['nullable', 'string', 'max:30'],
'alamat' => ['nullable', 'string'],
'status_kepegawaian' => ['nullable', 'string', 'max:40'],
```

Store sets `lembaga_id`, `is_active` true (ignore client is_active on create or allow — prefer server default true).

- [ ] **Step 2: Controller**

`index`: search `q` on `nama`/`niy` (driver-aware like lembaga: `ilike` pgsql else `lower like`).

`show`: audit `master.view` with `['resource' => 'guru']` only.

`deactivate`/`activate`/`destroy` with modals in views; destroy = soft delete.

IDOR: abort 404 if `lembaga_id` mismatch (scope usually hides; still assert).

- [ ] **Step 3: Menu** — enable Guru route when ready.

- [ ] **Step 4: Tests** — create/update/search/soft delete/deactivate; AL B cannot see AL A guru; SA 403.

- [ ] **Step 5: Commit**

```bash
git commit -m "feat(m6a): Admin Lembaga guru CRUD with NIY and soft delete"
```

---

### Task 5: Karyawan CRUD + finish menus

**Files:**
- `KaryawanController` + requests + views + routes
- `AdminMenu`: Tahun ajaran, Guru, Karyawan all `available=true`
- Coming-soon where: drop `tahun-ajaran|guru|karyawan`
- `AdminShellTest`: AL sees live links without Segera for those three; Kelas/Siswa still Segera

- [ ] Implement mirror of Guru (fields: `nik_pegawai`, `nama`, `jenis_kelamin`, `jabatan`, `email`, `telepon`, `alamat`).

- [ ] Search optional for karyawan (nama / nik_pegawai) — recommended for parity.

- [ ] Commit:

```bash
git commit -m "feat(m6a): Admin Lembaga karyawan CRUD and enable master menus"
```

---

### Task 6: Full feature suite polish

**Files:**
- Ensure `MasterTahunAjaranTest` + `MasterGuruKaryawanTest` cover spec §8
- Cross-check `TenantAuthorizationTest` still green after policy/controller changes

```bash
/usr/local/Cellar/php/8.5.8/bin/php vendor/bin/phpunit --colors=never
```

Expected: all PASS

Commit only if new tests/fixes:

```bash
git commit -m "test(m6a): cover master tahun ajaran, guru, and karyawan security paths"
```

---

### Task 7: Docs TODO

**Files:**
- `docs/IMPLEMENTATION_TODO.md`
- Spec status → `Approved`

- [ ] Status Milestone 6: **M6a selesai (TA/Guru/Karyawan); M6b Kelas/Siswa belum**
- [ ] Check: CRUD Tahun ajaran, aktifkan satu TA, CRUD Guru, CRUD Karyawan, search guru, soft delete modal (for these), audit view PII (guru/karyawan show)
- [ ] Leave unchecked: Kelas, Siswa, related validation/tests for kelas/siswa
- [ ] Commit: `docs: mark Milestone 6a master TA/guru/karyawan complete; M6b open`

---

## Spec coverage checklist

| Spec requirement | Task |
|------------------|------|
| Rename nip→niy + SPEC | 1 |
| TahunAjaranNamer `Y/(Y+1)` | 2, 3 |
| Create TA inactive + activate transaction | 3 |
| Update TA dates only | 3 |
| Soft delete TA blocked if kelas | 3 |
| Guru full fields + NIY + soft delete + is_active | 4 |
| Karyawan full fields | 5 |
| AL only / SA 403 | 3–5 |
| Menu enable three items | 5 |
| IMPLEMENTATION_TODO partial | 7 |

## Out of scope

Kelas, Siswa, SA master UI, Livewire, Excel import, REST API.
