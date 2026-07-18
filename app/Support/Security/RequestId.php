<?php

namespace App\Support\Security;

use Illuminate\Http\Request;
use Illuminate\Support\Str;

class RequestId
{
    private const HEADER = 'X-Request-ID';

    public static function headerName(): string
    {
        return self::HEADER;
    }

    public static function fromRequest(Request $request): string
    {
        $requestId = $request->headers->get(self::HEADER);

        if (is_string($requestId) && self::isValid($requestId)) {
            return $requestId;
        }

        return (string) Str::uuid();
    }

    public static function bind(string $requestId, ?Request $request = null): void
    {
        if ($request !== null) {
            $request->attributes->set('request_id', $requestId);
        }

        app()->instance('request_id', $requestId);
    }

    public static function current(?Request $request = null): ?string
    {
        $request ??= app()->bound('request') ? request() : null;

        if ($request instanceof Request) {
            $requestId = $request->attributes->get('request_id');

            if (is_string($requestId) && $requestId !== '') {
                return $requestId;
            }
        }

        if (app()->bound('request_id')) {
            $requestId = app('request_id');

            return is_string($requestId) ? $requestId : null;
        }

        return null;
    }

    private static function isValid(string $requestId): bool
    {
        return preg_match('/\A[A-Za-z0-9._-]{8,64}\z/', $requestId) === 1;
    }
}
