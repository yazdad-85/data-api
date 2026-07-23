<?php

namespace App\Http\Middleware;

use App\Services\Api\ApiClientAuthenticator;
use App\Support\Api\ApiClientContext;
use App\Support\Api\ApiErrorResponse;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class AuthenticateApiClient
{
    public function __construct(
        private readonly ApiClientAuthenticator $authenticator,
    ) {}

    public function handle(Request $request, Closure $next): Response
    {
        $result = $this->authenticator->authenticate($request);

        if (($result['ok'] ?? false) !== true) {
            return ApiErrorResponse::make(
                $result['code'],
                $result['message'],
                $result['status'],
            );
        }

        ApiClientContext::set($request, $result['client']);

        return $next($request);
    }
}
