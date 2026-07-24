<?php

namespace App\Services\Api;

use App\Models\ApiClient;
use App\Support\Api\ApiFieldProfiles;
use App\Support\Api\ApiResourceCatalog;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Model;
use InvalidArgumentException;

/**
 * Builds the tenant-scoped snapshot query for an API v1 resource, paginates it,
 * and transforms rows into the response envelope (design §5, §7, §8).
 *
 * The web `BelongsToLembaga` global scope is Auth-driven and blocks every row
 * in the API context (no session user); therefore both the base query and the
 * siswa embed relations must run with `withoutGlobalScopes()`. That also drops
 * the SoftDeletingScope, so `deleted_at IS NULL` is re-applied unless the caller
 * asks for `include_deleted`.
 */
final class ApiResourceLister
{
    public function __construct(
        private readonly ApiResourceCatalog $catalog,
        private readonly ApiResourceTransformer $transformer,
    ) {}

    /**
     * @param  array<string, mixed>  $query
     * @return array{
     *     resource: string,
     *     lembaga_id: string,
     *     synced_at: string,
     *     data: list<array<string, mixed>>,
     *     meta: array{page: int, per_page: int, total: int},
     * }
     */
    public function list(ApiClient $client, string $slug, array $query): array
    {
        $entry = $this->catalog->get($slug);
        if ($entry === null) {
            throw new InvalidArgumentException("Unknown resource slug: {$slug}");
        }

        $profile = (string) ($query['fields'] ?? $client->field_profile ?? ApiFieldProfiles::MINIMAL);
        $perPage = min(max(1, (int) ($query['per_page'] ?? 100)), 200);
        $page = max(1, (int) ($query['page'] ?? 1));
        $includeDeleted = $this->boolParam($query['include_deleted'] ?? false);
        $activeOnly = $this->boolParam($query['active_only'] ?? false);

        /** @var class-string<Model> $modelClass */
        $modelClass = $entry['model'];
        $table = (new $modelClass)->getTable();

        $builder = $modelClass::query()->withoutGlobalScopes()
            ->where($table.'.lembaga_id', $client->lembaga_id);

        if (! $includeDeleted) {
            $builder->whereNull($table.'.deleted_at');
        }

        if ($activeOnly && $entry['active_column'] !== null) {
            $builder->where($table.'.'.$entry['active_column'], true);
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

        if ($slug === 'tahun-ajaran') {
            $builder->orderByDesc($table.'.nama')->orderBy($table.'.id');
        } else {
            $builder->orderBy($table.'.nama')->orderBy($table.'.id');
        }

        $paginator = $builder->paginate($perPage, ['*'], 'page', $page);

        $fields = $entry['fields'][$profile];
        if ($includeDeleted) {
            $fields[] = 'deleted_at';
        }

        $data = [];
        foreach ($paginator->items() as $model) {
            $data[] = $this->transformer->transform($model, $fields, $embeds);
        }

        return [
            'resource' => $slug,
            'lembaga_id' => $client->lembaga_id,
            'synced_at' => Carbon::now('UTC')->format('Y-m-d\TH:i:s\Z'),
            'data' => $data,
            'meta' => [
                'page' => $paginator->currentPage(),
                'per_page' => $perPage,
                'total' => $paginator->total(),
            ],
        ];
    }

    private function boolParam(mixed $value): bool
    {
        return filter_var($value, FILTER_VALIDATE_BOOLEAN);
    }
}
