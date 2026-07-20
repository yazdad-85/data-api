<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\MfaChallengeRequest;
use App\Services\AuditLogger;
use App\Support\Security\MfaPendingSession;
use App\Support\Security\TotpVerifier;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;

class MfaChallengeController extends Controller
{
    public function __construct(
        private readonly TotpVerifier $totpVerifier,
        private readonly AuditLogger $auditLogger,
    ) {
    }

    public function create(): View|RedirectResponse
    {
        if (MfaPendingSession::user() === null) {
            return redirect()->route('login');
        }

        return view('auth.mfa');
    }

    public function store(MfaChallengeRequest $request): RedirectResponse
    {
        $user = MfaPendingSession::user();

        if ($user === null) {
            return redirect()->route('login');
        }

        $code = $request->validated('code');

        if (! $this->totpVerifier->verify($user, $code)) {
            $this->auditLogger->record('auth.mfa', 'failed', [
                'reason' => 'invalid_code',
            ], user: $user, lembagaId: $user->lembaga_id, request: $request);

            return back()->withErrors([
                'code' => 'Kode autentikasi tidak valid',
            ]);
        }

        Auth::login($user, false);
        $request->session()->regenerate();
        MfaPendingSession::clear();

        $this->auditLogger->record('auth.mfa', 'success', [], user: $user, lembagaId: $user->lembaga_id, request: $request);

        return redirect()->route('admin.dashboard');
    }
}
