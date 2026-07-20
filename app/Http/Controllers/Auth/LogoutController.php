<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Services\AuditLogger;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class LogoutController extends Controller
{
    public function __construct(private readonly AuditLogger $auditLogger)
    {
    }

    public function __invoke(Request $request): RedirectResponse
    {
        $user = $request->user();

        Auth::logout();

        $request->session()->invalidate();
        $request->session()->regenerateToken();

        if ($user !== null) {
            $this->auditLogger->record('auth.logout', 'success', [], user: $user, lembagaId: $user->lembaga_id, request: $request);
        }

        return redirect()->route('login');
    }
}
