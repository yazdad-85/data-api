<?php

namespace App\Support\Api;

use App\Models\ApiClient;
use Illuminate\Http\Request;

/**
 * Binds/reads the authenticated ApiClient on the request (SPEC §7.2).
 * Avoids web session auth for API consumers.
 */
final class ApiClientContext
{
    public const ATTR = 'api_client';

    public static function set(Request $request, ApiClient $client): void
    {
        $request->attributes->set(self::ATTR, $client);
    }

    public static function get(Request $request): ?ApiClient
    {
        $client = $request->attributes->get(self::ATTR);

        return $client instanceof ApiClient ? $client : null;
    }
}
