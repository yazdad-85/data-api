<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\StoreLembagaAdminRequest;
use App\Http\Requests\Admin\UpdateLembagaAdminRequest;
use App\Models\Lembaga;
use App\Models\User;
use App\Services\AuditLogger;
use App\Services\Auth\AdminPasswordGenerator;
use App\Services\Auth\SessionInvalidator;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class LembagaAdminController extends Controller
{
    public function __construct(
        private readonly AuditLogger $auditLogger,
        private readonly AdminPasswordGenerator $passwordGenerator,
        private readonly SessionInvalidator $sessionInvalidator,
    ) {}

    public function store(StoreLembagaAdminRequest $request, Lembaga $lembaga): RedirectResponse
    {
        $plainPassword = $this->passwordGenerator->generate();

        $admin = User::query()->create([
            'name' => $request->validated('name'),
            'email' => $request->validated('email'),
            'password' => $plainPassword,
            'role' => 'admin_lembaga',
            'lembaga_id' => $lembaga->id,
            'is_active' => true,
        ]);

        $this->auditLogger->record('admin.create', 'success', [], subject: $admin, lembagaId: $lembaga->id, request: $request);

        return redirect()
            ->route('admin.lembaga.admins.password-once', [$lembaga, $admin])
            ->with('generated_password', ['user_id' => (string) $admin->id, 'password' => $plainPassword])
            ->with('status', 'Admin lembaga berhasil dibuat.');
    }

    public function update(UpdateLembagaAdminRequest $request, Lembaga $lembaga, User $user): RedirectResponse
    {
        $this->assertAdminBelongsToLembaga($lembaga, $user);

        $user->update($request->validated());

        $this->auditLogger->record('admin.update', 'success', [], subject: $user, lembagaId: $lembaga->id, request: $request);

        return redirect()
            ->route('admin.lembaga.show', $lembaga)
            ->with('status', 'Admin lembaga berhasil diperbarui.');
    }

    public function activate(Request $request, Lembaga $lembaga, User $user): RedirectResponse
    {
        $this->assertAdminBelongsToLembaga($lembaga, $user);
        $this->authorize('activate', $user);

        $user->update(['is_active' => true]);

        $this->auditLogger->record('admin.activate', 'success', [], subject: $user, lembagaId: $lembaga->id, request: $request);

        return redirect()
            ->route('admin.lembaga.show', $lembaga)
            ->with('status', 'Admin lembaga diaktifkan.');
    }

    public function deactivate(Request $request, Lembaga $lembaga, User $user): RedirectResponse
    {
        $this->assertAdminBelongsToLembaga($lembaga, $user);
        $this->authorize('deactivate', $user);

        $user->update(['is_active' => false]);
        $this->sessionInvalidator->invalidateUser((string) $user->id);

        $this->auditLogger->record('admin.deactivate', 'success', [], subject: $user, lembagaId: $lembaga->id, request: $request);

        return redirect()
            ->route('admin.lembaga.show', $lembaga)
            ->with('status', 'Admin lembaga dinonaktifkan.');
    }

    public function resetPassword(Request $request, Lembaga $lembaga, User $user): RedirectResponse
    {
        $this->assertAdminBelongsToLembaga($lembaga, $user);
        $this->authorize('resetPassword', $user);

        $plainPassword = $this->passwordGenerator->generate();

        $user->update(['password' => $plainPassword]);
        $this->sessionInvalidator->invalidateUser((string) $user->id);

        $this->auditLogger->record('admin.reset_password', 'success', [], subject: $user, lembagaId: $lembaga->id, request: $request);

        return redirect()
            ->route('admin.lembaga.admins.password-once', [$lembaga, $user])
            ->with('generated_password', ['user_id' => (string) $user->id, 'password' => $plainPassword])
            ->with('status', 'Kata sandi admin lembaga berhasil direset.');
    }

    public function passwordOnce(Request $request, Lembaga $lembaga, User $user): View|RedirectResponse
    {
        $this->assertAdminBelongsToLembaga($lembaga, $user);
        $this->authorize('view', $lembaga);

        $generatedPassword = $request->session()->pull('generated_password');

        $plainPassword = is_array($generatedPassword)
            && (string) ($generatedPassword['user_id'] ?? '') === (string) $user->id
            && is_string($generatedPassword['password'] ?? null)
            && $generatedPassword['password'] !== ''
                ? $generatedPassword['password']
                : null;

        if ($plainPassword === null) {
            return redirect()
                ->route('admin.lembaga.show', $lembaga)
                ->with('status', 'Kata sandi hanya dapat dilihat sekali dan sudah tidak tersedia. Gunakan reset kata sandi jika diperlukan.');
        }

        return view('admin.lembaga.password-once', [
            'lembaga' => $lembaga,
            'admin' => $user,
            'plainPassword' => $plainPassword,
        ]);
    }

    private function assertAdminBelongsToLembaga(Lembaga $lembaga, User $user): void
    {
        if (! $user->isAdminLembaga() || (string) $user->lembaga_id !== (string) $lembaga->id) {
            abort(404);
        }
    }
}
