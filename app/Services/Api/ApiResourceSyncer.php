<?php

namespace App\Services\Api;

use App\Models\ApiClient;
use App\Support\Api\ApiFieldProfiles;
use App\Support\Api\ApiResourceCatalog;
use App\Support\Api\ApiSyncCursor;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

final class ApiResourceSyncer
{
    public function __construct(
        private readonly ApiResourceCatalog $catalog,
        private readonly ApiResourceTransformer $transformer,
    ) {}

    /**
     * @param  array{
     *     since: Carbon,
     *     watermark: Carbon,
     *     cursor: ?array{changed_at: Carbon, id: string},
     *     per_page: int,
     *     fields: string,
     * }  $params
     * @return array{
     *     resource: string,
     *     lembaga_id: string,
     *     since: string,
     *     watermark: string,
     *     synced_at: string,
     *     changes: list<array<string, mixed>>,
     *     change_count: int,
     *     next_cursor: ?string,
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
        $changedAtFunction = DB::connection((new $modelClass)->getConnectionName())->getDriverName() === 'sqlite'
            ? 'MAX'
            : 'GREATEST';
        $changedAtSql = "{$changedAtFunction}({$table}.updated_at, COALESCE({$table}.deleted_at, {$table}.updated_at))";

        $builder = $modelClass::query()->withoutGlobalScopes()
            ->where("{$table}.lembaga_id", $client->lembaga_id)
            ->select("{$table}.*")
            ->selectRaw("{$changedAtSql} as sync_changed_at")
            ->whereRaw("{$changedAtSql} > ?", [$since])
            ->whereRaw("{$changedAtSql} <= ?", [$watermark])
            ->orderByRaw("{$changedAtSql} asc")
            ->orderBy("{$table}.id");

        if ($cursor !== null) {
            $changedAt = $cursor['changed_at'];
            $id = $cursor['id'];

            $builder->where(function ($query) use ($changedAtSql, $changedAt, $id, $table): void {
                $query->whereRaw("{$changedAtSql} > ?", [$changedAt])
                    ->orWhere(function ($cursorQuery) use ($changedAtSql, $changedAt, $id, $table): void {
                        $cursorQuery->whereRaw("{$changedAtSql} = ?", [$changedAt])
                            ->where("{$table}.id", '>', $id);
                    });
            });
        }

        $embeds = $entry['embeds'][$profile] ?? [];
        if (in_array('penempatan_aktif', $embeds, true)) {
            $builder->with(['penempatanAktif' => fn ($query) => $query->withoutGlobalScopes()]);
        }
        if (in_array('riwayat_penempatan', $embeds, true)) {
            $builder->with([
                'penempatans' => fn ($query) => $query->withoutGlobalScopes()
                    ->orderBy('mulai_at')
                    ->orderBy('id'),
            ]);
        }

        $rows = $builder->limit($perPage + 1)->get();
        $hasMore = $rows->count() > $perPage;
        if ($hasMore) {
            $rows = $rows->take($perPage);
        }

        $allowedFields = $entry['fields'][$profile] ?? $entry['fields'][ApiFieldProfiles::MINIMAL];
        $changes = [];

        foreach ($rows as $model) {
            $changedAt = Carbon::parse($model->getAttribute('sync_changed_at'))->utc();

            if ($model->getAttribute('deleted_at') !== null) {
                $changes[] = [
                    'id' => (string) $model->id,
                    'deleted_at' => Carbon::parse($model->deleted_at)->utc()->format('Y-m-d\TH:i:s\Z'),
                    'changed_at' => $changedAt->format('Y-m-d\TH:i:s\Z'),
                ];

                continue;
            }

            $row = $this->transformer->transform($model, $allowedFields, $embeds);
            $row['changed_at'] = $changedAt->format('Y-m-d\TH:i:s\Z');
            $row['deleted_at'] = null;
            $changes[] = $row;
        }

        $nextCursor = null;
        if ($hasMore) {
            $last = $rows->last();
            $lastChangedAt = Carbon::parse($last->getAttribute('sync_changed_at'))->utc();
            $nextCursor = ApiSyncCursor::encode($lastChangedAt, (string) $last->id);
        }

        return [
            'resource' => $slug,
            'lembaga_id' => (string) $client->lembaga_id,
            'since' => $since->utc()->format('Y-m-d\TH:i:s\Z'),
            'watermark' => $watermark->utc()->format('Y-m-d\TH:i:s\Z'),
            'synced_at' => Carbon::now('UTC')->format('Y-m-d\TH:i:s\Z'),
            'changes' => $changes,
            'change_count' => count($changes),
            'next_cursor' => $nextCursor,
        ];
    }
}
