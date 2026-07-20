<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\LoginRequest;
use App\Services\AuditLogger;
use App\Services\Auth\AdminAuthenticator;
use App\Support\Security\MfaPendingSession;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;

class LoginController extends Controller
{
    public function __construct(
        private readonly AdminAuthenticator $authenticator,
        private readonly AuditLogger $auditLogger,
    ) {
    }

    public function create(): View
    {
        return view('auth.login');
    }

    public function store(LoginRequest $request): RedirectResponse
    {
        $validated = $request->validated();

        $result = $this->authenticator->attempt(
            $validated['email'],
            $validated['password'],
            $request,
        );

        if (! $result['ok']) {
            return back()->withErrors([
                'email' => AdminAuthenticator::FAILURE_MESSAGE,
            ])->onlyInput('email');
        }

        $user = $result['user'];
        $request->session()->regenerate();

        if ($user->isSuperAdmin() && (bool) config('security.mfa.super_admin_required')) {
            if (! $user->hasMfaEnabled()) {
                $this->auditLogger->record('auth.login', 'failed', [
                    'reason' => 'mfa_not_enabled',
                    'email' => $user->email,
                ], user: $user, lembagaId: $user->lembaga_id, request: $request);

                return back()->withErrors([
                    'email' => AdminAuthenticator::FAILURE_MESSAGE,
                ])->onlyInput('email');
            }

            MfaPendingSession::put($user);

            return redirect()->route('login.mfa');
        }

        Auth::login($user, false);

        $this->auditLogger->record('auth.login', 'success', [
            'email' => $user->email,
        ], user: $user, lembagaId: $user->lembaga_id, request: $request);

        return redirect()->route('admin.dashboard');
    }
}
