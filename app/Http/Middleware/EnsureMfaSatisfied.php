<?php

namespace App\Http\Middleware;

use App\Services\Auth\AdminAuthenticator;
use App\Support\Security\MfaPendingSession;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

class EnsureMfaSatisfied
{
    /**
     * @param Closure(Request): Response $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if ($user === null) {
            if (MfaPendingSession::user() !== null && ! $request->routeIs('login.mfa')) {
                return redirect()->route('login.mfa');
            }

            return $next($request);
        }

        if (
            $user->isSuperAdmin()
            && (bool) config('security.mfa.super_admin_required')
            && ! $user->hasMfaEnabled()
        ) {
            Auth::logout();
            $request->session()->invalidate();
            $request->session()->regenerateToken();

            return redirect()->route('login')->withErrors([
                'email' => AdminAuthenticator::FAILURE_MESSAGE,
            ]);
        }

        return $next($request);
    }
}
