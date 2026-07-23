<?php

namespace App\Services\Api;

use Illuminate\Http\Request;

final class ApiKeyParser
{
    private const FORMAT = '/^dc_live_([a-z0-9]+)_([a-z0-9]+)$/i';

    /**
     * Prefer non-empty `X-API-Key` header; fallback to `Authorization: Bearer <token>`.
     * If both are present, `X-API-Key` wins.
     */
    public function extractFromRequest(Request $request): ?string
    {
        $apiKeyHeader = trim((string) $request->headers->get('X-API-Key', ''));

        if ($apiKeyHeader !== '') {
            return $apiKeyHeader;
        }

        $authorization = trim((string) $request->headers->get('Authorization', ''));

        if (preg_match('/^Bearer\s+(\S+)/i', $authorization, $matches) === 1) {
            return $matches[1];
        }

        return null;
    }

    /**
     * @return array{prefix: string, secret: string}|null null jika format tidak valid.
     */
    public function parse(string $plain): ?array
    {
        if (preg_match(self::FORMAT, $plain, $matches) !== 1) {
            return null;
        }

        return [
            'prefix' => strtolower($matches[1]),
            'secret' => strtolower($matches[2]),
        ];
    }
}
