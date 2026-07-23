<?php

namespace App\Support\Api;

use Illuminate\Http\JsonResponse;

/**
 * Consistent JSON error envelope for API v1 responses (SPEC §4.5).
 *
 * Pesan Bahasa Indonesia per kode:
 * - UNAUTHENTICATED     → "Autentikasi gagal."
 * - API_CLIENT_INACTIVE → "API client tidak aktif."
 * - LEMBAGA_INACTIVE    → "Lembaga tidak aktif."
 * - RATE_LIMITED        → "Terlalu banyak permintaan."
 */
final class ApiErrorResponse
{
    public const UNAUTHENTICATED = 'UNAUTHENTICATED';

    public const API_CLIENT_INACTIVE = 'API_CLIENT_INACTIVE';

    public const LEMBAGA_INACTIVE = 'LEMBAGA_INACTIVE';

    public const RATE_LIMITED = 'RATE_LIMITED';

    public static function make(string $code, string $message, int $status): JsonResponse
    {
        return response()->json([
            'message' => $message,
            'code' => $code,
            'request_id' => function_exists('request_id') ? request_id() : null,
        ], $status);
    }
}
