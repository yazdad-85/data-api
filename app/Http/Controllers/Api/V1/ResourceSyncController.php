<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Requests\Api\V1\SyncResourceRequest;
use App\Services\Api\ApiFieldProfiler;
use App\Services\Api\ApiResourceSyncer;
use App\Services\Api\ApiSyncQueryValidator;
use App\Support\Api\ApiClientContext;
use App\Support\Api\ApiErrorResponse;
use App\Support\Api\ApiResourceCatalog;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;

/**
 * Entry point for authenticated delta syncs `GET /api/v1/{resource}/sync`.
 */
class ResourceSyncController
{
    public function __construct(
        private readonly ApiResourceCatalog $catalog,
        private readonly ApiFieldProfiler $profiler,
        private readonly ApiSyncQueryValidator $validator,
        private readonly ApiResourceSyncer $syncer,
    ) {}

    public function __invoke(SyncResourceRequest $request, string $resource): JsonResponse
    {
        $client = ApiClientContext::get($request);
        abort_if($client === null, 500);

        $entry = $this->catalog->get($resource);
        if ($entry === null) {
            return ApiErrorResponse::make('NOT_FOUND', 'Resource tidak ditemukan.', 404);
        }

        $scopes = $client->scopes ?? [];
        if (! in_array($entry['scope'], $scopes, true)) {
            return ApiErrorResponse::make(ApiErrorResponse::FORBIDDEN, 'Scope tidak mencukupi.', 403);
        }

        $input = $request->validated();
        $profile = $this->profiler->resolve(
            (string) $client->field_profile,
            $input['fields'] ?? null,
        );
        if (! ($profile['ok'] ?? false)) {
            return ApiErrorResponse::make($profile['code'], $profile['message'], $profile['status']);
        }

        $query = $this->validator->validate($input);
        if (! ($query['ok'] ?? false)) {
            return ApiErrorResponse::make($query['code'], $query['message'], $query['status']);
        }

        if ($query['is_first_page']) {
            $query['watermark'] = Carbon::now('UTC');
        }

        $payload = $this->syncer->sync($client, $resource, [
            'since' => $query['since'],
            'watermark' => $query['watermark'],
            'cursor' => $query['cursor'],
            'per_page' => $query['per_page'],
            'fields' => $profile['profile'],
        ]);

        return response()->json($payload);
    }
}
