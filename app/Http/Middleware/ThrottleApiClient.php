<?php

namespace App\Http\Middleware;

use App\Support\Api\ApiErrorResponse;
use Closure;
use Illuminate\Cache\RateLimiter;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Cache\RateLimiting\Unlimited;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Rate limits authenticated API requests per API key (120/min) and per IP
 * (240/min) via named limiters registered in AppServiceProvider (SPEC §5).
 *
 * On exceed returns the SPEC §4.5 envelope with code RATE_LIMITED (429) and a
 * Retry-After header when available.
 */
class ThrottleApiClient
{
    /** @var array<int, string> */
    private const LIMITERS = ['api-client-key', 'api-client-ip'];

    public function __construct(
        private readonly RateLimiter $limiter,
    ) {}

    public function handle(Request $request, Closure $next): Response
    {
        $resolved = [];

        foreach (self::LIMITERS as $name) {
            foreach ($this->limitsFor($request, $name) as $limit) {
                if ($limit instanceof Unlimited) {
                    continue;
                }

                $resolved[] = [
                    'cacheKey' => md5($name.$limit->key),
                    'maxAttempts' => $limit->maxAttempts,
                    'decaySeconds' => $limit->decaySeconds,
                ];
            }
        }

        foreach ($resolved as $limit) {
            if ($this->limiter->tooManyAttempts($limit['cacheKey'], $limit['maxAttempts'])) {
                return ApiErrorResponse::make(
                    ApiErrorResponse::RATE_LIMITED,
                    'Terlalu banyak permintaan.',
                    429,
                )->header('Retry-After', (string) $this->limiter->availableIn($limit['cacheKey']));
            }
        }

        foreach ($resolved as $limit) {
            $this->limiter->hit($limit['cacheKey'], $limit['decaySeconds']);
        }

        return $next($request);
    }

    /**
     * @return array<int, Limit|Unlimited>
     */
    private function limitsFor(Request $request, string $name): array
    {
        $limiter = $this->limiter->limiter($name);

        if ($limiter === null) {
            return [];
        }

        $result = $limiter($request);

        if ($result === null) {
            return [];
        }

        return is_array($result) ? $result : [$result];
    }
}
