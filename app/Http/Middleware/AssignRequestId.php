<?php

namespace App\Http\Middleware;

use App\Support\Security\RequestId;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class AssignRequestId
{
    /**
     * @param Closure(Request): Response $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $requestId = RequestId::fromRequest($request);
        RequestId::bind($requestId, $request);

        $response = $next($request);
        $response->headers->set(RequestId::headerName(), $requestId);

        return $response;
    }
}
