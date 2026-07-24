<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Requests\Api\V1\ListResourceRequest;
use App\Services\Api\ApiFieldProfiler;
use App\Services\Api\ApiResourceLister;
use App\Support\Api\ApiClientContext;
use App\Support\Api\ApiErrorResponse;
use App\Support\Api\ApiResourceCatalog;
use Illuminate\Http\JsonResponse;

/**
 * Entry point for authenticated snapshot lists `GET /api/v1/{resource}`
 * (design §4, §8). Runs behind api.client + api.throttle so a client is bound.
 *
 * Order of checks: unknown resource → 404; missing scope → 403; requested
 * `fields` profile above client ceiling → 403; otherwise list + transform.
 */
class ResourceListController
{
    public function __construct(
        private readonly ApiResourceCatalog $catalog,
        private readonly ApiFieldProfiler $profiler,
        private readonly ApiResourceLister $lister,
    ) {}

    public function __invoke(ListResourceRequest $request, string $resource): JsonResponse
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

        $profile = $this->profiler->resolve(
            (string) $client->field_profile,
            $request->validated('fields')
        );

        if (! ($profile['ok'] ?? false)) {
            return ApiErrorResponse::make($profile['code'], $profile['message'], $profile['status']);
        }

        $payload = $this->lister->list($client, $resource, [
            ...$request->validated(),
            'fields' => $profile['profile'],
        ]);

        return response()->json($payload);
    }
}
