<?php

namespace App\Http\Middleware;

use App\Services\AuditLogger;
use App\Services\Auth\AdminAuthenticator;
use App\Services\Auth\SessionInvalidator;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureUserIsActive
{
    public function __construct(
        private readonly SessionInvalidator $sessionInvalidator,
        private readonly AuditLogger $auditLogger,
    ) {
    }

    /**
     * @param Closure(Request): Response $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if ($user === null) {
            return $next($request);
        }

        $reason = null;

        if (! $user->is_active) {
            $reason = 'user_inactive';
        } elseif ($user->isAdminLembaga()) {
            $user->loadMissing('lembaga');

            if ($user->lembaga === null || ! $user->lembaga->is_active) {
                $reason = 'lembaga_inactive';
            }
        }

        if ($reason === null) {
            return $next($request);
        }

        $this->auditLogger->record('auth.session_revoked', 'success', [
            'reason' => $reason,
        ], user: $user, lembagaId: $user->lembaga_id, request: $request);

        $this->sessionInvalidator->invalidateUser((string) $user->id);

        return redirect()->route('login')->withErrors([
            'email' => AdminAuthenticator::FAILURE_MESSAGE,
        ]);
    }
}
