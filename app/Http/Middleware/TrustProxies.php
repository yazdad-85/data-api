<?php

namespace App\Http\Middleware;

use Illuminate\Http\Middleware\TrustProxies as Middleware;
use Illuminate\Http\Request;

/**
 * Reads trusted proxies from config at request time so `TRUSTED_PROXIES`
 * still works when `php artisan config:cache` has disabled runtime `env()`.
 */
class TrustProxies extends Middleware
{
    /**
     * @var int
     */
    protected $headers = Request::HEADER_X_FORWARDED_FOR |
        Request::HEADER_X_FORWARDED_HOST |
        Request::HEADER_X_FORWARDED_PORT |
        Request::HEADER_X_FORWARDED_PROTO;

    /**
     * @return array<int, string>|string|null
     */
    protected function proxies()
    {
        if (static::$alwaysTrustProxies !== null) {
            return static::$alwaysTrustProxies;
        }

        $configured = trim((string) config('security.trusted_proxies', ''));

        if ($configured === '') {
            return null;
        }

        if ($configured === '*' || $configured === '**') {
            return $configured;
        }

        return array_values(array_filter(
            array_map(trim(...), explode(',', $configured)),
            fn (string $proxy): bool => $proxy !== '',
        ));
    }
}
