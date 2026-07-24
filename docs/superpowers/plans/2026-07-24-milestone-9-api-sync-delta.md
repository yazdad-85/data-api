# Milestone 9 API Sync Delta Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Ship authenticated `GET /api/v1/{resource}/sync` delta sync with required `since`, server watermark, cursor pagination on `(changed_at, id)`, live rows via M8 field profiles, and minimal soft-delete tombstones.

**Architecture:** Sync pipeline on top of M8 — `ApiSyncCursor` + `ApiSyncQueryValidator` + `ApiResourceSyncer` + `ResourceSyncController`. Reuse `ApiResourceCatalog`, `ApiFieldProfiler`, `ApiResourceTransformer`, M7 auth/throttle/`ApiErrorResponse`. Always `withoutGlobalScopes()` + tenant `lembaga_id`; soft-deleted rows included as tombstones. Follow RULES B1: implement → review → test (not classic TDD).

**Tech Stack:** Laravel 13, PHP 8.5 (`/usr/local/Cellar/php/8.5.8/bin/php`), PostgreSQL, PHPUnit, existing `ApiKeyIssuer` / factories.

**Spec:** `docs/superpowers/specs/2026-07-24-milestone-9-api-sync-delta-design.md`

---

## File structure

| Path | Responsibility |
|------|----------------|
| `config/security.php` | `api_sync_max_since_days` (env `API_SYNC_MAX_SINCE_DAYS`, default 90) |
| `.env.example` | Document `API_SYNC_MAX_SINCE_DAYS=90` |
| `app/Support/Api/ApiErrorResponse.php` | Add `INVALID_SINCE`, `SINCE_TOO_OLD`, `INVALID_CURSOR` |
| `app/Support/Api/ApiSyncCursor.php` | Encode/decode opaque cursor `{c,i}` |
| `app/Services/Api/ApiSyncQueryValidator.php` | Validate `since`, cursor, watermark, `per_page` |
| `app/Services/Api/ApiResourceSyncer.php` | Delta query + live/tombstone transform + envelope |
| `app/Http/Requests/Api/V1/SyncResourceRequest.php` | Query param shape (fields enum, per_page) |
| `app/Http/Controllers/Api/V1/ResourceSyncController.php` | `GET /{resource}/sync` |
| `routes/api.php` | Register sync route (before or beside list) |
| `tests/Unit/ApiSyncCursorTest.php` | Encode/decode round-trip + reject garbage |
| `tests/Unit/ApiSyncQueryValidatorTest.php` | since/cursor/watermark cases |
| `tests/Feature/ApiResourceSyncTest.php` | HTTP delta, multi-page, tombstone, errors, tenant |
| `docs/IMPLEMENTATION_TODO.md` | Centang M9 |
| `docs/SPEC.md` | Catatan singkat penempatan via touch `siswa` (opsional kecil) |

Reuse unchanged: `ApiResourceCatalog`, `ApiFieldProfiler`, `ApiResourceTransformer`, `ApiClientContext`, `ApiKeyIssuer`, M8 list tests.

**PHP binary:** always `/usr/local/Cellar/php/8.5.8/bin/php` (system PHP 8.1 will break artisan).

---

### Task 1: Config + error codes + ApiSyncCursor

**Files:**
- Modify: `config/security.php`
- Modify: `.env.example`
- Modify: `app/Support/Api/ApiErrorResponse.php`
- Create: `app/Support/Api/ApiSyncCursor.php`
- Create: `tests/Unit/ApiSyncCursorTest.php`

- [ ] **Step 1: Config + env**

In `config/security.php` add after rate limits:

```php
'api_sync_max_since_days' => (int) env('API_SYNC_MAX_SINCE_DAYS', 90),
```

In `.env.example` add:

```
API_SYNC_MAX_SINCE_DAYS=90
```

- [ ] **Step 2: Error constants**

Add to `ApiErrorResponse`:

```php
public const INVALID_SINCE = 'INVALID_SINCE';
public const SINCE_TOO_OLD = 'SINCE_TOO_OLD';
public const INVALID_CURSOR = 'INVALID_CURSOR';
```

Update class docblock pesan Indonesia:
- `INVALID_SINCE` → "Parameter since tidak valid."
- `SINCE_TOO_OLD` → "Parameter since terlalu lama; gunakan tarik penuh."
- `INVALID_CURSOR` → "Cursor atau watermark tidak valid."

- [ ] **Step 3: `ApiSyncCursor`**

```php
<?php

namespace App\Support\Api;

use Carbon\Carbon;
use InvalidArgumentException;

/**
 * Opaque sync cursor: base64url JSON {"c":"<ISO8601 Z>","i":"<uuid>"}.
 */
final class ApiSyncCursor
{
    /**
     * @return array{c: string, i: string}
     */
    public static function encode(Carbon $changedAt, string $id): string
    {
        $payload = json_encode([
            'c' => $changedAt->clone()->utc()->format('Y-m-d\TH:i:s\Z'),
            'i' => $id,
        ], JSON_THROW_ON_ERROR);

        return rtrim(strtr(base64_encode($payload), '+/', '-_'), '=');
    }

    /**
     * @return array{changed_at: Carbon, id: string}
     */
    public static function decode(string $cursor): array
    {
        $padded = strtr($cursor, '-_', '+/');
        $pad = strlen($padded) % 4;
        if ($pad > 0) {
            $padded .= str_repeat('=', 4 - $pad);
        }

        $json = base64_decode($padded, true);
        if ($json === false) {
            throw new InvalidArgumentException('Invalid cursor encoding.');
        }

        /** @var mixed $data */
        $data = json_decode($json, true);
        if (! is_array($data) || ! isset($data['c'], $data['i']) || ! is_string($data['c']) || ! is_string($data['i']) || $data['i'] === '') {
            throw new InvalidArgumentException('Invalid cursor payload.');
        }

        try {
            $changedAt = Carbon::parse($data['c'])->utc();
        } catch (\Throwable) {
            throw new InvalidArgumentException('Invalid cursor timestamp.');
        }

        return ['changed_at' => $changedAt, 'id' => $data['i']];
    }
}
```

- [ ] **Step 4: Unit test**

```php
public function test_round_trip(): void
{
    $at = Carbon::parse('2026-07-15T12:01:00Z');
    $id = '11111111-1111-1111-1111-111111111111';
    $decoded = ApiSyncCursor::decode(ApiSyncCursor::encode($at, $id));
    $this->assertTrue($decoded['changed_at']->eq($at));
    $this->assertSame($id, $decoded['id']);
}

public function test_rejects_garbage(): void
{
    $this->expectException(InvalidArgumentException::class);
    ApiSyncCursor::decode('not-a-cursor!!!');
}
```

Run:

```bash
/usr/local/Cellar/php/8.5.8/bin/php artisan test --filter=ApiSyncCursorTest
```

Expected: PASS.

- [ ] **Step 5: Commit**

```bash
git add config/security.php .env.example app/Support/Api/ApiErrorResponse.php app/Support/Api/ApiSyncCursor.php tests/Unit/ApiSyncCursorTest.php
git commit -m "feat(m9): add sync cursor codec and sync error codes"
```

---

### Task 2: ApiSyncQueryValidator

**Files:**
- Create: `app/Services/Api/ApiSyncQueryValidator.php`
- Create: `tests/Unit/ApiSyncQueryValidatorTest.php`

- [ ] **Step 1: Implement validator**

```php
<?php

namespace App\Services\Api;

use App\Support\Api\ApiErrorResponse;
use App\Support\Api\ApiSyncCursor;
use Carbon\Carbon;
use InvalidArgumentException;

final class ApiSyncQueryValidator
{
    /**
     * @param  array{since?: mixed, cursor?: mixed, watermark?: mixed, per_page?: mixed}  $input
     * @return array{
     *   ok: true,
     *   since: Carbon,
     *   watermark: ?Carbon,
     *   cursor: ?array{changed_at: Carbon, id: string},
     *   per_page: int,
     *   is_first_page: bool
     * }|array{ok: false, code: string, status: int, message: string}
     */
    public function validate(array $input, ?Carbon $now = null): array
    {
        $now = ($now ?? Carbon::now('UTC'))->utc();

        $sinceRaw = $input['since'] ?? null;
        if (! is_string($sinceRaw) || trim($sinceRaw) === '') {
            return $this->fail(ApiErrorResponse::INVALID_SINCE, 'Parameter since tidak valid.', 400);
        }

        try {
            $since = Carbon::parse($sinceRaw)->utc();
        } catch (\Throwable) {
            return $this->fail(ApiErrorResponse::INVALID_SINCE, 'Parameter since tidak valid.', 400);
        }

        // Reject clearly non-UTC if original string has explicit non-Z offset? Spec: ISO-8601 UTC.
        // Accept any parseable instant, normalize to UTC; reject future.
        if ($since->greaterThan($now)) {
            return $this->fail(ApiErrorResponse::INVALID_SINCE, 'Parameter since tidak valid.', 400);
        }

        $maxDays = max(1, (int) config('security.api_sync_max_since_days', 90));
        if ($since->lt($now->copy()->subDays($maxDays))) {
            return $this->fail(
                ApiErrorResponse::SINCE_TOO_OLD,
                'Parameter since terlalu lama; gunakan tarik penuh.',
                400
            );
        }

        $cursorRaw = $input['cursor'] ?? null;
        $watermarkRaw = $input['watermark'] ?? null;
        $hasCursor = is_string($cursorRaw) && $cursorRaw !== '';

        if (! $hasCursor) {
            if (is_string($watermarkRaw) && $watermarkRaw !== '') {
                // Watermark without cursor is ignored or invalid — treat as invalid to avoid silent misuse.
                return $this->fail(ApiErrorResponse::INVALID_CURSOR, 'Cursor atau watermark tidak valid.', 400);
            }

            $perPage = $this->clampPerPage($input['per_page'] ?? 100);

            return [
                'ok' => true,
                'since' => $since,
                'watermark' => null, // controller/syncer sets now
                'cursor' => null,
                'per_page' => $perPage,
                'is_first_page' => true,
            ];
        }

        if (! is_string($watermarkRaw) || $watermarkRaw === '') {
            return $this->fail(ApiErrorResponse::INVALID_CURSOR, 'Cursor atau watermark tidak valid.', 400);
        }

        try {
            $watermark = Carbon::parse($watermarkRaw)->utc();
            $cursor = ApiSyncCursor::decode($cursorRaw);
        } catch (\Throwable) {
            return $this->fail(ApiErrorResponse::INVALID_CURSOR, 'Cursor atau watermark tidak valid.', 400);
        }

        if ($watermark->greaterThan($now)) {
            return $this->fail(ApiErrorResponse::INVALID_CURSOR, 'Cursor atau watermark tidak valid.', 400);
        }

        // Cursor timestamp must fall within (since, watermark]
        if ($cursor['changed_at']->lte($since) || $cursor['changed_at']->greaterThan($watermark)) {
            return $this->fail(ApiErrorResponse::INVALID_CURSOR, 'Cursor atau watermark tidak valid.', 400);
        }

        return [
            'ok' => true,
            'since' => $since,
            'watermark' => $watermark,
            'cursor' => $cursor,
            'per_page' => $this->clampPerPage($input['per_page'] ?? 100),
            'is_first_page' => false,
        ];
    }

    private function clampPerPage(mixed $value): int
    {
        $n = is_numeric($value) ? (int) $value : 100;

        return min(max(1, $n), 200);
    }

    /**
     * @return array{ok: false, code: string, status: int, message: string}
     */
    private function fail(string $code, string $message, int $status): array
    {
        return ['ok' => false, 'code' => $code, 'status' => $status, 'message' => $message];
    }
}
```

**Note:** “watermark without cursor → invalid” keeps clients honest; if product prefers ignore, change later — default for M9 is reject.

- [ ] **Step 2: Unit tests** (use frozen `$now`)

Cover: missing since; future since; since older than 90 days; first page OK; cursor without watermark; bad cursor; cursor outside window; `per_page=500` → 200.

Run:

```bash
/usr/local/Cellar/php/8.5.8/bin/php artisan test --filter=ApiSyncQueryValidatorTest
```

- [ ] **Step 3: Commit**

```bash
git commit -m "feat(m9): validate sync since cursor and watermark"
```

---

### Task 3: ApiResourceSyncer

**Files:**
- Create: `app/Services/Api/ApiResourceSyncer.php`
- Create: `app/Services/Api/ApiResourceSyncer.php` only (HTTP coverage in Task 5).

- [ ] **Step 1: Implement `ApiResourceSyncer`**

```php
<?php

namespace App\Services\Api;

use App\Models\ApiClient;
use App\Support\Api\ApiFieldProfiles;
use App\Support\Api\ApiResourceCatalog;
use App\Support\Api\ApiSyncCursor;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Model;
use InvalidArgumentException;

final class ApiResourceSyncer
{
    public function __construct(
        private readonly ApiResourceCatalog $catalog,
        private readonly ApiResourceTransformer $transformer,
    ) {}

    /**
     * @param  array{
     *   since: Carbon,
     *   watermark: Carbon,
     *   cursor: ?array{changed_at: Carbon, id: string},
     *   per_page: int,
     *   fields: string
     * }  $params
     * @return array{
     *   resource: string,
     *   lembaga_id: string,
     *   since: string,
     *   watermark: string,
     *   synced_at: string,
     *   changes: list<array<string, mixed>>,
     *   change_count: int,
     *   next_cursor: ?string
     * }
     */
    public function sync(ApiClient $client, string $slug, array $params): array
    {
        $entry = $this->catalog->get($slug);
        if ($entry === null) {
            throw new InvalidArgumentException("Unknown resource slug: {$slug}");
        }

        $profile = $params['fields'];
        $since = $params['since'];
        $watermark = $params['watermark'];
        $cursor = $params['cursor'];
        $perPage = $params['per_page'];

        /** @var class-string<Model> $modelClass */
        $modelClass = $entry['model'];
        $table = (new $modelClass)->getTable();

        $changedAtSql = "GREATEST({$table}.updated_at, COALESCE({$table}.deleted_at, {$table}.updated_at))";

        $builder = $modelClass::query()->withoutGlobalScopes()
            ->where("{$table}.lembaga_id", $client->lembaga_id)
            ->select("{$table}.*")
            ->selectRaw("{$changedAtSql} as sync_changed_at")
            ->whereRaw("{$changedAtSql} > ?", [$since])
            ->whereRaw("{$changedAtSql} <= ?", [$watermark])
            ->orderByRaw("{$changedAtSql} asc")
            ->orderBy("{$table}.id");

        if ($cursor !== null) {
            $c = $cursor['changed_at'];
            $i = $cursor['id'];
            $builder->where(function ($q) use ($changedAtSql, $c, $i, $table) {
                $q->whereRaw("{$changedAtSql} > ?", [$c])
                    ->orWhere(function ($q2) use ($changedAtSql, $c, $i, $table) {
                        $q2->whereRaw("{$changedAtSql} = ?", [$c])
                            ->where("{$table}.id", '>', $i);
                    });
            });
        }

        $embeds = $entry['embeds'][$profile] ?? [];
        if (in_array('penempatan_aktif', $embeds, true)) {
            $builder->with(['penempatanAktif' => fn ($q) => $q->withoutGlobalScopes()]);
        }
        if (in_array('riwayat_penempatan', $embeds, true)) {
            $builder->with([
                'penempatans' => fn ($q) => $q->withoutGlobalScopes()
                    ->orderBy('mulai_at')
                    ->orderBy('id'),
            ]);
        }

        $rows = $builder->limit($perPage + 1)->get();
        $hasMore = $rows->count() > $perPage;
        if ($hasMore) {
            $rows = $rows->take($perPage);
        }

        $allowed = $entry['fields'][$profile] ?? $entry['fields'][ApiFieldProfiles::MINIMAL];
        $changes = [];
        foreach ($rows as $model) {
            /** @var Carbon $changedAt */
            $changedAt = Carbon::parse($model->getAttribute('sync_changed_at'))->utc();

            if ($model->getAttribute('deleted_at') !== null) {
                $changes[] = [
                    'id' => (string) $model->id,
                    'deleted_at' => Carbon::parse($model->deleted_at)->utc()->format('Y-m-d\TH:i:s\Z'),
                    'changed_at' => $changedAt->format('Y-m-d\TH:i:s\Z'),
                ];
                continue;
            }

            $row = $this->transformer->transform($model, $allowed, $embeds);
            $row['changed_at'] = $changedAt->format('Y-m-d\TH:i:s\Z');
            $row['deleted_at'] = null;
            $changes[] = $row;
        }

        $nextCursor = null;
        if ($hasMore) {
            $last = $rows->last();
            $lastChanged = Carbon::parse($last->getAttribute('sync_changed_at'))->utc();
            $nextCursor = ApiSyncCursor::encode($lastChanged, (string) $last->id);
        }

        $syncedAt = Carbon::now('UTC')->format('Y-m-d\TH:i:s\Z');

        return [
            'resource' => $slug,
            'lembaga_id' => (string) $client->lembaga_id,
            'since' => $since->format('Y-m-d\TH:i:s\Z'),
            'watermark' => $watermark->format('Y-m-d\TH:i:s\Z'),
            'synced_at' => $syncedAt,
            'changes' => $changes,
            'change_count' => count($changes),
            'next_cursor' => $nextCursor,
        ];
    }
}
```

**Important cleanup when coding:** remove the messy ternary in the tombstone block; use:

```php
if ($model->getAttribute('deleted_at') !== null) {
    $changes[] = [
        'id' => (string) $model->id,
        'deleted_at' => Carbon::parse($model->deleted_at)->utc()->format('Y-m-d\TH:i:s\Z'),
        'changed_at' => $changedAt->format('Y-m-d\TH:i:s\Z'),
    ];
    continue;
}
```

- [ ] **Step 2: Commit**

```bash
git commit -m "feat(m9): implement ApiResourceSyncer delta query"
```

---

### Task 4: Controller + Form Request + routes

**Files:**
- Create: `app/Http/Requests/Api/V1/SyncResourceRequest.php`
- Create: `app/Http/Controllers/Api/V1/ResourceSyncController.php`
- Modify: `routes/api.php`

- [ ] **Step 1: Form request**

Validate optional `fields` in `minimal|academic|contact`; `per_page` integer; leave `since`/`cursor`/`watermark` to `ApiSyncQueryValidator` (string passthrough). Mirror `ListResourceRequest` style.

```php
public function rules(): array
{
    return [
        'since' => ['nullable', 'string'], // required enforced by validator service
        'cursor' => ['nullable', 'string'],
        'watermark' => ['nullable', 'string'],
        'fields' => ['nullable', 'string', 'in:minimal,academic,contact'],
        'per_page' => ['nullable', 'integer', 'min:1'],
    ];
}
```

- [ ] **Step 2: Controller**

Mirror `ResourceListController`:

1. Resolve client from `ApiClientContext`.
2. Catalog lookup → 404 if missing.
3. Scope check → 403 `FORBIDDEN`.
4. `ApiFieldProfiler::resolve` → 403 if upgrade.
5. `ApiSyncQueryValidator::validate($request->validated())` → 400 codes.
6. If `is_first_page`, set `watermark = now(UTC)`.
7. `ApiResourceSyncer::sync(...)` with resolved profile.
8. `return response()->json($payload)`.

- [ ] **Step 3: Route** — register **before** `/{resource}` list:

```php
use App\Http\Controllers\Api\V1\ResourceSyncController;

Route::get('/{resource}/sync', ResourceSyncController::class)
    ->where('resource', 'tahun-ajaran|guru|kelas|siswa|karyawan')
    ->name('api.v1.resource.sync');

Route::get('/{resource}', ResourceListController::class)
    ->where('resource', 'tahun-ajaran|guru|kelas|siswa|karyawan')
    ->name('api.v1.resource.index');
```

- [ ] **Step 4: Smoke** — hit one sync with artisan test after Task 5, or quick:

```bash
/usr/local/Cellar/php/8.5.8/bin/php artisan route:list --path=api/v1
```

Expect `api/v1/{resource}/sync`.

- [ ] **Step 5: Commit**

```bash
git commit -m "feat(m9): expose GET /api/v1/{resource}/sync"
```

---

### Task 5: Feature tests `ApiResourceSyncTest`

**Files:**
- Create: `tests/Feature/ApiResourceSyncTest.php`

Helper: same pattern as `ApiResourceListTest::makeClient` (pepper config + Cache flush + `ApiKeyIssuer`).

- [ ] **Step 1: Cases (implement all)**

```php
test_create_update_delete_appear_in_sync()
// create guru; sync since before create → 1 live change with changed_at
// update nama; sync → live row
// soft delete; sync → tombstone keys only id, deleted_at, changed_at (assertMissing nama/email)

test_multi_page_cursor_no_duplicates()
// create 3 gurus with controlled updated_at if needed; per_page=2
// page1 next_cursor not null; page2 with watermark+cursor; union ids unique; page2 next_cursor null

test_update_after_watermark_excluded()
// first page watermark captured; then update another row with Carbon::setTestNow after watermark
// continue or re-query same watermark session → new update not included
// (use Carbon::setTestNow to freeze watermark, then advance time for late update)

test_missing_since_returns_invalid_since()
test_since_in_future_returns_invalid_since()
test_since_too_old_returns_since_too_old() // config max 90; since = now-91 days
test_cursor_without_watermark_returns_invalid_cursor()
test_garbage_cursor_returns_invalid_cursor()
test_fields_upgrade_returns_403()
test_missing_scope_returns_403()
test_tenant_isolation()
test_envelope_shape()
test_health_me_and_list_still_ok() // light regression
```

Use headers `['X-API-Key' => $plain]`.

For time control:

```php
Carbon::setTestNow(Carbon::parse('2026-07-20T12:00:00Z'));
// ... create + first sync
$watermark = $response->json('watermark');
Carbon::setTestNow(Carbon::parse('2026-07-20T12:05:00Z'));
// late update
// sync with original since + watermark (+ cursor if any) must not include late row
Carbon::setTestNow(); // clear in tearDown
```

- [ ] **Step 2: Run**

```bash
/usr/local/Cellar/php/8.5.8/bin/php artisan test --filter=ApiResourceSyncTest
```

Expected: all PASS.

- [ ] **Step 3: Full suite**

```bash
/usr/local/Cellar/php/8.5.8/bin/php artisan test
```

Expected: all green (baseline was 248; M9 adds tests).

- [ ] **Step 4: Commit**

```bash
git commit -m "test(m9): cover API sync delta watermark and tombstones"
```

---

### Task 6: Docs + SPEC note

**Files:**
- Modify: `docs/IMPLEMENTATION_TODO.md` (Milestone 9 — status selesai, centang item terbukti)
- Modify: `docs/SPEC.md` §4.4 — 1–2 kalimat: penempatan tidak punya sync terpisah; perubahan enrollment via `siswa.updated_at` / lifecycle touch
- Modify: design status → **DISETUJUI** if still DRAFT: `docs/superpowers/specs/2026-07-24-milestone-9-api-sync-delta-design.md`

- [ ] **Step 1: Update TODO checklist** for all M9 items proven by tests.
- [ ] **Step 2: SPEC note + design status.**
- [ ] **Step 3: Full suite once more.**
- [ ] **Step 4: Commit**

```bash
git commit -m "docs(m9): mark API sync delta milestone complete"
```

---

## Self-review (plan vs spec)

| Spec requirement | Task |
|------------------|------|
| `GET /{resource}/sync` | 4 |
| Validate `since` ISO UTC, reject future | 2, 5 |
| `SINCE_TOO_OLD` 90 days | 1 (config), 2, 5 |
| `changed_at = greatest(updated_at, deleted_at)` | 3 |
| Watermark on first page; stable on cursor pages | 2, 3, 4, 5 |
| Cursor `(changed_at, id)` opaque | 1, 3 |
| Live = M8 profiles; tombstone minimal | 3, 5 |
| No separate penempatan sync; touch siswa | 6 (docs) |
| Error codes INVALID_* | 1, 2, 5 |
| Tenant isolation | 5 |
| Review race / PII / index | covered by watermark tests + tombstone assert; index already in foundation |

**Placeholder scan:** clean — no TBD / “implement later”.

**Type consistency:** validator returns `is_first_page`; controller sets watermark when true; syncer always receives concrete `Carbon $watermark`.

---

## Execution notes

- Worktree recommended: `.worktrees/m9-api-sync` branch `feature/m9-api-sync` (use using-git-worktrees at execution).
- Do not push unless asked.
- After merge: remove worktree like M7/M8.
