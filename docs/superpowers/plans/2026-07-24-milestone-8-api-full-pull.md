# Milestone 8 API Full Pull Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Ship authenticated `GET /api/v1/{resource}` snapshot list for guru, siswa, karyawan, kelas, tahun-ajaran with scope enforcement, field profiles, query filters, and siswa penempatan embeds.

**Architecture:** Shared pipeline — `ApiResourceCatalog` + `ApiFieldProfiler` + `ApiResourceLister` + single `ResourceListController`. Reuse M7 `api.client` / `api.throttle` / `ApiErrorResponse` / `ApiClientContext`. Always query with `withoutGlobalScopes()` + `lembaga_id` from API client. Follow RULES B1: implement → review → test (not classic TDD).

**Tech Stack:** Laravel 13, PHP 8.5 (`/usr/local/Cellar/php/8.5.8/bin/php`), PHPUnit, existing ApiKeyIssuer for tests.

**Spec:** `docs/superpowers/specs/2026-07-24-milestone-8-api-full-pull-design.md`

---

## File structure

| Path | Responsibility |
|------|----------------|
| `app/Support/Api/ApiResourceCatalog.php` | Slug → model, scope, fields per profile, active column, eager loads |
| `app/Support/Api/ApiFieldProfiles.php` | Profile rank helpers (`minimal` < `academic` < `contact`) |
| `app/Services/Api/ApiFieldProfiler.php` | Resolve effective profile; throw/return FORBIDDEN if upgrade |
| `app/Services/Api/ApiResourceLister.php` | Query + paginate + transform |
| `app/Services/Api/ApiResourceTransformer.php` | Model → array by allowed fields + siswa embeds |
| `app/Http/Middleware/EnsureApiScope.php` | Optional; or check inside controller via catalog scope |
| `app/Http/Controllers/Api/V1/ResourceListController.php` | `GET /api/v1/{resource}` |
| `app/Http/Requests/Api/V1/ListResourceRequest.php` | Validate query params |
| `routes/api.php` | Register 5 GET routes under auth middleware |
| `tests/Unit/ApiFieldProfilerTest.php` | Profile ceiling |
| `tests/Unit/ApiResourceCatalogTest.php` | Slugs/scopes exist |
| `tests/Feature/ApiResourceListTest.php` | HTTP coverage |
| `docs/IMPLEMENTATION_TODO.md` | Centang M8; health/me already M7 |
| `docs/SPEC.md` | Catat embed siswa + clamp per_page bila perlu |

Reuse: `ApiClientScopes` (if exists), `ApiKeyIssuer`, factories, M7 auth tests patterns.

---

### Task 1: Catalog + field profiler

**Files:**
- Create: `app/Support/Api/ApiFieldProfiles.php`
- Create: `app/Support/Api/ApiResourceCatalog.php`
- Create: `app/Services/Api/ApiFieldProfiler.php`
- Create: `tests/Unit/ApiFieldProfilerTest.php`, `tests/Unit/ApiResourceCatalogTest.php`

- [ ] **Step 1: Profile ranks**

```php
final class ApiFieldProfiles
{
    public const MINIMAL = 'minimal';
    public const ACADEMIC = 'academic';
    public const CONTACT = 'contact';
    public const ALL = [self::MINIMAL, self::ACADEMIC, self::CONTACT];

    public static function rank(string $profile): int
    {
        return match ($profile) {
            self::MINIMAL => 0,
            self::ACADEMIC => 1,
            self::CONTACT => 2,
            default => -1,
        };
    }

    public static function allows(string $clientProfile, string $requested): bool
    {
        return self::rank($requested) >= 0
            && self::rank($requested) <= self::rank($clientProfile);
    }
}
```

- [ ] **Step 2: Catalog** — define for each slug `tahun-ajaran`, `guru`, `kelas`, `siswa`, `karyawan`:

```php
[
  'model' => Siswa::class,
  'scope' => 'siswa:read',
  'active_column' => 'is_active', // tahun-ajaran => 'is_aktif'; null if N/A
  'fields' => [
    'minimal' => [...],
    'academic' => [...], // full list including minimal keys OR only deltas — prefer **cumulative lists** in code
    'contact' => [...],
  ],
  'embeds' => [ // siswa only
    'academic' => ['penempatan_aktif'],
    'contact' => ['penempatan_aktif', 'riwayat_penempatan'],
  ],
]
```

Field lists must match approved design §6.2 exactly. Method `get(string $slug): ?array`, `slugs(): array`.

- [ ] **Step 3: `ApiFieldProfiler::resolve(string $clientProfile, ?string $fieldsQuery): string`**

- null/empty query → return `$clientProfile`
- invalid profile string → treat as validation error later (Form Request); profiler assumes valid enum
- if `! ApiFieldProfiles::allows($clientProfile, $fieldsQuery)` → throw domain exception or return failure used by controller for 403 `FORBIDDEN` message `Profil field tidak diizinkan.`

Prefer:

```php
/**
 * @return array{ok: true, profile: string}|array{ok: false, code: string, status: int, message: string}
 */
public function resolve(string $clientProfile, ?string $requested): array
```

- [ ] **Step 4: Unit tests** — allows/denies upgrade; catalog has 5 slugs and scopes; siswa academic fields include status metadata keys.

Run:

```bash
/usr/local/Cellar/php/8.5.8/bin/php artisan test --filter='ApiFieldProfiler|ApiResourceCatalog'
```

- [ ] **Step 5: Commit**

```bash
git commit -m "feat(m8): add API resource catalog and field profiler"
```

---

### Task 2: Transformer + Lister

**Files:**
- Create: `app/Services/Api/ApiResourceTransformer.php`
- Create: `app/Services/Api/ApiResourceLister.php`
- Create: `tests/Unit/ApiResourceListerTest.php` (or Feature-only in Task 3 — prefer unit with RefreshDatabase)

- [ ] **Step 1: Transformer**

```php
public function transform(Model $model, array $allowedFields, array $embeds = []): array
```

- Only include keys in `$allowedFields` (except embeds added separately).
- Dates/`created_at`/`updated_at`/`deleted_at` → ISO-8601 UTC `Y-m-d\TH:i:s\Z` (Carbon `utc()->format(...)`).
- Date-only fields (`tanggal_lahir`, `mulai_at`) → `Y-m-d` or full UTC midnight — **terkunci: date columns as `Y-m-d`; datetimes as UTC Z**.
- Siswa embeds:
  - `penempatan_aktif`: from `$model->penempatanAktif` → `{id, kelas_id, tahun_ajaran_id, mulai_at, jenis}` or null
  - `riwayat_penempatan`: from `$model->penempatans` ordered by `mulai_at`, map same fields + `selesai_at`

- [ ] **Step 2: Lister**

```php
/**
 * @return array{resource: string, lembaga_id: string, synced_at: string, data: list<array>, meta: array{page: int, per_page: int, total: int}}
 */
public function list(ApiClient $client, string $slug, array $query): array
```

Steps:
1. Load catalog entry; abort if unknown slug (controller 404).
2. Resolve profile via profiler (caller may pass already-resolved profile).
3. `$perPage = min(max(1, (int)($query['per_page'] ?? 100)), 200);`
4. `$page = max(1, (int)($query['page'] ?? 1));`
5. Query:

```php
$q = $modelClass::withoutGlobalScopes()
    ->where('lembaga_id', $client->lembaga_id);
if (!($query['include_deleted'] ?? false)) {
    // SoftDeletes global applies only with scopes; withoutGlobalScopes removes soft delete too!
    // MUST re-apply: $q->whereNull($table.'.deleted_at') unless include_deleted
}
if (($query['active_only'] ?? false) && $activeColumn) {
    $q->where($activeColumn, true);
}
```

**Critical:** `withoutGlobalScopes()` also removes SoftDeletingScope — default must filter `deleted_at IS NULL` unless `include_deleted`.

6. Eager load: if siswa and profile academic+ → `penempatanAktif`; contact → also `penempatans`.
7. `orderBy('nama')` or `orderBy('id')` for stable pages — **tahun-ajaran** orderByDesc nama; others `orderBy('nama')->orderBy('id')`.
8. Paginate with `paginate($perPage, ['*'], 'page', $page)`.
9. Transform each row; `synced_at` = now UTC.

- [ ] **Step 3: Tests** — tenant filter; soft-delete default hidden; include_deleted; active_only; per_page clamp; siswa embed presence by profile.

Run:

```bash
/usr/local/Cellar/php/8.5.8/bin/php artisan test --filter=ApiResourceLister
```

- [ ] **Step 4: Commit**

```bash
git commit -m "feat(m8): add API resource lister and transformer"
```

---

### Task 3: HTTP layer — request, controller, routes, scope check

**Files:**
- Create: `app/Http/Requests/Api/V1/ListResourceRequest.php`
- Create: `app/Http/Controllers/Api/V1/ResourceListController.php`
- Modify: `routes/api.php`

- [ ] **Step 1: Form Request**

Validate:
- `include_deleted` → boolean (accept `true`/`false`/`1`/`0`)
- `active_only` → boolean
- `fields` → nullable `Rule::in(ApiFieldProfiles::ALL)`
- `page` → integer min 1
- `per_page` → integer min 1 (clamp later in lister; validation max optional 1000)

- [ ] **Step 2: Controller**

```php
public function __invoke(ListResourceRequest $request, string $resource)
{
    $client = ApiClientContext::get($request);
    $entry = app(ApiResourceCatalog::class)->get($resource);
    if ($entry === null) {
        return ApiErrorResponse::make('FORBIDDEN', 'Resource tidak ditemukan.', 404);
        // or abort 404 JSON — prefer 404 with code if we add NOT_FOUND; else FORBIDDEN/404 plain
    }
    // Spec: unknown resource → 404
    $scopes = $client->scopes ?? [];
    if (! in_array($entry['scope'], $scopes, true)) {
        return ApiErrorResponse::make(ApiErrorResponse::FORBIDDEN, 'Scope tidak mencukupi.', 403);
    }
    $profileResult = app(ApiFieldProfiler::class)->resolve(
        (string) $client->field_profile,
        $request->validated('fields')
    );
    if (! ($profileResult['ok'] ?? false)) {
        return ApiErrorResponse::make($profileResult['code'], $profileResult['message'], $profileResult['status']);
    }
    $payload = app(ApiResourceLister::class)->list($client, $resource, [
        ...$request->validated(),
        'fields' => $profileResult['profile'],
    ]);

    return response()->json($payload);
}
```

Use `ApiErrorResponse::FORBIDDEN` constant if added in M7 file — add `FORBIDDEN = 'FORBIDDEN'` if missing.

- [ ] **Step 3: Routes**

```php
Route::middleware(['api.client', 'api.throttle'])->group(function () {
    Route::get('/me', MeController::class)->name('api.v1.me');
    Route::get('/{resource}', ResourceListController::class)
        ->where('resource', 'tahun-ajaran|guru|kelas|siswa|karyawan')
        ->name('api.v1.resource.index');
});
```

- [ ] **Step 4: Commit**

```bash
git commit -m "feat(m8): expose GET /api/v1 resource list endpoints"
```

---

### Task 4: Feature tests ApiResourceListTest

**Files:**
- Create: `tests/Feature/ApiResourceListTest.php`

Helper: issue key via `ApiKeyIssuer`, create client with chosen scopes + field_profile + lembaga.

- [ ] **Step 1: Cases**

```php
test_health_and_me_still_ok() // regression light
test_missing_scope_returns_403()
test_fields_upgrade_returns_403()
test_guru_minimal_omits_contact_fields()
test_siswa_minimal_omits_penempatan_aktif()
test_siswa_academic_includes_penempatan_aktif_not_riwayat()
test_siswa_contact_includes_riwayat()
test_per_page_clamped_to_200()
test_include_deleted_returns_trashed()
test_active_only_filters()
test_client_cannot_read_other_lembaga_data()
test_response_envelope_shape()
```

Run:

```bash
/usr/local/Cellar/php/8.5.8/bin/php artisan test --filter='ApiResourceList|ApiClientAuth'
```

- [ ] **Step 2: Commit**

```bash
git commit -m "test(m8): cover API full-pull resource lists"
```

---

### Task 5: Docs + SPEC note + full suite

**Files:**
- Modify: `docs/IMPLEMENTATION_TODO.md` — M8 status selesai; health/me centang sebagai sudah M7; link spek
- Modify: `docs/SPEC.md` §4.3 — catat clamp `per_page`; siswa embeds per profile (singkat)

- [ ] **Step 1: Update docs**

- [ ] **Step 2: Full suite**

```bash
/usr/local/Cellar/php/8.5.8/bin/php artisan test
```

Expected: all green.

- [ ] **Step 3: Commit**

```bash
git commit -m "docs(m8): mark API full-pull milestone complete"
```

---

## Spec coverage check

| Spek | Task |
|------|------|
| 5 resources + scopes | 1, 3, 4 |
| Field profiles + fields ceiling 403 | 1, 3, 4 |
| Siswa embeds tiered | 2, 4 |
| include_deleted / active_only / pagination clamp | 2, 4 |
| Tenant withoutGlobalScopes | 2, 4 |
| Envelope + UTC | 2, 4 |
| No /sync | (none) |
| TODO + SPEC | 5 |

## Self-review notes

- SoftDeletes + `withoutGlobalScopes()` = must manually filter `deleted_at`.
- Route `{resource}` must not capture `health`/`me` — place health outside auth; me before `{resource}` or use where constraint.
- Commit only when allowed; do not push unless asked.
- PHP: `/usr/local/Cellar/php/8.5.8/bin/php`
