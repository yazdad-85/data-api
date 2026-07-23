<?php

use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;
use App\Http\Middleware\AssignRequestId;
use App\Http\Middleware\AuthenticateApiClient;
use App\Http\Middleware\EnsureMfaSatisfied;
use App\Http\Middleware\EnsureUserIsActive;
use App\Http\Middleware\ThrottleApiClient;
use App\Support\Security\MfaPendingSession;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
        apiPrefix: 'api',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->append(AssignRequestId::class);

        $middleware->alias([
            'active' => EnsureUserIsActive::class,
            'mfa' => EnsureMfaSatisfied::class,
            'api.client' => AuthenticateApiClient::class,
            'api.throttle' => ThrottleApiClient::class,
        ]);

        $middleware->redirectGuestsTo(function () {
            return MfaPendingSession::user() !== null
                ? route('login.mfa')
                : route('login');
        });
        $middleware->redirectUsersTo(fn () => route('admin.dashboard'));
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->shouldRenderJsonWhen(
            fn (Request $request) => $request->is('api/*'),
        );
    })->create();
