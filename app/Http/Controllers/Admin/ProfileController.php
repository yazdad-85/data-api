<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\UpdatePasswordRequest;
use App\Http\Requests\Admin\UpdateProfileRequest;
use App\Services\AuditLogger;
use App\Services\Auth\SessionInvalidator;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class ProfileController extends Controller
{
    public function __construct(
        private readonly AuditLogger $auditLogger,
        private readonly SessionInvalidator $sessionInvalidator,
    ) {
    }

    public function show(Request $request): View
    {
        return view('admin.profile.show', [
            'user' => $request->user(),
        ]);
    }

    public function update(UpdateProfileRequest $request): RedirectResponse
    {
        $user = $request->user();
        $name = $request->validated('name');

        $user->forceFill(['name' => $name])->save();

        $this->auditLogger->record('profile.update', 'success', [
            'fields' => ['name'],
        ], user: $user, lembagaId: $user->lembaga_id, request: $request);

        return redirect()
            ->route('admin.profile.show')
            ->with('status', 'Nama profil berhasil diperbarui.');
    }

    public function updatePassword(UpdatePasswordRequest $request): RedirectResponse
    {
        $user = $request->user();
        $currentSessionId = $request->session()->getId();

        $user->forceFill([
            'password' => $request->validated('password'),
        ])->save();

        $this->sessionInvalidator->invalidateOtherSessions(
            (string) $user->getAuthIdentifier(),
            $currentSessionId,
        );

        $request->session()->regenerate();

        $this->auditLogger->record(
            'profile.password_change',
            'success',
            [],
            user: $user,
            lembagaId: $user->lembaga_id,
            request: $request,
        );

        return redirect()
            ->route('admin.profile.show')
            ->with('status', 'Password berhasil diganti. Sesi di perangkat lain telah diakhiri.');
    }
}
