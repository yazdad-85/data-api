<?php

namespace App\Services\Api;

use App\Support\Api\ApiErrorResponse;
use App\Support\Api\ApiFieldProfiles;

/**
 * Resolves the effective field profile for an API v1 resource list request
 * (design §6, §8): the requested `fields` query must not exceed the
 * client's assigned field profile.
 */
final class ApiFieldProfiler
{
    /**
     * @return array{ok: true, profile: string}|array{ok: false, code: string, status: int, message: string}
     */
    public function resolve(string $clientProfile, ?string $requested): array
    {
        if ($requested === null || $requested === '') {
            return ['ok' => true, 'profile' => $clientProfile];
        }

        if (! ApiFieldProfiles::allows($clientProfile, $requested)) {
            return [
                'ok' => false,
                'code' => ApiErrorResponse::FORBIDDEN,
                'status' => 403,
                'message' => 'Profil field tidak diizinkan.',
            ];
        }

        return ['ok' => true, 'profile' => $requested];
    }
}
