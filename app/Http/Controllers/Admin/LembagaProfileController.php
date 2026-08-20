<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Concerns\EnsuresAdminLembaga;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\UpdateLembagaProfileRequest;
use App\Services\AuditLogger;
use App\Services\Lembaga\KopSuratProcessor;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Storage;
use Illuminate\View\View;

class LembagaProfileController extends Controller
{
    use EnsuresAdminLembaga;

    public function __construct(
        private readonly AuditLogger $auditLogger,
        private readonly KopSuratProcessor $kopSuratProcessor,
    ) {}

    public function show(): View
    {
        $user = $this->adminLembaga();

        return view('admin.lembaga-profile.show', [
            'lembaga' => $user->lembaga,
            'kopSuratUrl' => $user->lembaga->kop_surat_path
                ? Storage::disk('public')->url($user->lembaga->kop_surat_path)
                : null,
        ]);
    }

    public function update(UpdateLembagaProfileRequest $request): RedirectResponse
    {
        $user = $this->adminLembaga();
        $lembaga = $user->lembaga;

        $validated = $request->validated();
        unset($validated['kop_surat'], $validated['remove_kop_surat']);

        $kopSuratChanged = false;

        if ($request->boolean('remove_kop_surat')) {
            $this->kopSuratProcessor->delete($lembaga->kop_surat_path);
            $validated['kop_surat_path'] = null;
            $kopSuratChanged = true;
        } elseif ($request->hasFile('kop_surat')) {
            $oldPath = $lembaga->kop_surat_path;
            $validated['kop_surat_path'] = $this->kopSuratProcessor->store($request->file('kop_surat'));
            $this->kopSuratProcessor->delete($oldPath);
            $kopSuratChanged = true;
        }

        $lembaga->update($validated);

        $this->auditLogger->record('lembaga.profile_update', 'success', [
            'fields' => array_keys($validated),
            'kop_surat_changed' => $kopSuratChanged,
        ], subject: $lembaga, user: $user, lembagaId: $lembaga->id, request: $request);

        return redirect()
            ->route('admin.lembaga-profile.show')
            ->with('status', 'Profil lembaga berhasil diperbarui.');
    }
}
