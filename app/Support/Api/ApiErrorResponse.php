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
 * - FORBIDDEN           → "Profil field tidak diizinkan." / "Scope tidak mencukupi."
 * - INVALID_SINCE       → "Parameter since tidak valid."
 * - SINCE_TOO_OLD       → "Parameter since terlalu lama; gunakan tarik penuh."
 * - INVALID_CURSOR      → "Cursor atau watermark tidak valid."
 */
final class ApiErrorResponse
{
    public const UNAUTHENTICATED = 'UNAUTHENTICATED';

    public const API_CLIENT_INACTIVE = 'API_CLIENT_INACTIVE';

    public const LEMBAGA_INACTIVE = 'LEMBAGA_INACTIVE';

    public const RATE_LIMITED = 'RATE_LIMITED';

    public const FORBIDDEN = 'FORBIDDEN';

    public const INVALID_SINCE = 'INVALID_SINCE';

    public const SINCE_TOO_OLD = 'SINCE_TOO_OLD';

    public const INVALID_CURSOR = 'INVALID_CURSOR';

    public static function make(string $code, string $message, int $status): JsonResponse
    {
        return response()->json([
            'message' => $message,
            'code' => $code,
            'request_id' => function_exists('request_id') ? request_id() : null,
        ], $status);
    }
}
