<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Concerns\EnsuresAdminLembaga;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\StoreGuruRequest;
use App\Http\Requests\Admin\UpdateGuruRequest;
use App\Models\Guru;
use App\Services\AuditLogger;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class GuruController extends Controller
{
    use EnsuresAdminLembaga;

    public function __construct(
        private readonly AuditLogger $auditLogger,
    ) {}

    public function index(Request $request): View
    {
        $this->adminLembaga();

        $q = trim((string) $request->query('q', ''));

        $gurus = Guru::query()
            ->when($q !== '', function ($query) use ($q) {
                $like = '%'.$q.'%';
                $query->where(function ($inner) use ($like) {
                    if ($inner->getConnection()->getDriverName() === 'pgsql') {
                        $inner->where('nama', 'ilike', $like)->orWhere('niy', 'ilike', $like);
                    } else {
                        $inner->whereRaw('lower(nama) like lower(?)', [$like])
                            ->orWhereRaw('lower(niy) like lower(?)', [$like]);
                    }
                });
            })
            ->orderBy('nama')
            ->paginate(15)
            ->withQueryString();

        return view('admin.guru.index', compact('gurus', 'q'));
    }

    public function create(): View
    {
        $this->adminLembaga();

        return view('admin.guru.create');
    }

    public function store(StoreGuruRequest $request): RedirectResponse
    {
        $user = $this->adminLembaga();

        $guru = Guru::query()->create([
            ...$request->validated(),
            'lembaga_id' => $user->lembaga_id,
            'is_active' => true,
        ]);

        $this->auditLogger->record('guru.create', 'success', [
            'nama' => $guru->nama,
        ], subject: $guru, lembagaId: $user->lembaga_id, request: $request);

        return redirect()
            ->route('admin.guru.index')
            ->with('status', "Guru {$guru->nama} berhasil dibuat.");
    }

    public function show(Request $request, Guru $guru): View
    {
        $user = $this->adminLembaga();
        abort_unless(hash_equals((string) $guru->lembaga_id, (string) $user->lembaga_id), 404);

        $this->auditLogger->record('master.view', 'success', [
            'resource' => 'guru',
        ], subject: $guru, lembagaId: $user->lembaga_id, request: $request);

        return view('admin.guru.show', compact('guru'));
    }

    public function edit(Guru $guru): View
    {
        $user = $this->adminLembaga();
        abort_unless(hash_equals((string) $guru->lembaga_id, (string) $user->lembaga_id), 404);

        return view('admin.guru.edit', compact('guru'));
    }

    public function update(UpdateGuruRequest $request, Guru $guru): RedirectResponse
    {
        $user = $this->adminLembaga();
        abort_unless(hash_equals((string) $guru->lembaga_id, (string) $user->lembaga_id), 404);

        $guru->update($request->validated());

        $this->auditLogger->record('guru.update', 'success', [
            'nama' => $guru->nama,
        ], subject: $guru, lembagaId: $user->lembaga_id, request: $request);

        return redirect()
            ->route('admin.guru.index')
            ->with('status', "Guru {$guru->nama} berhasil diperbarui.");
    }

    public function activate(Request $request, Guru $guru): RedirectResponse
    {
        $user = $this->adminLembaga();
        abort_unless(hash_equals((string) $guru->lembaga_id, (string) $user->lembaga_id), 404);

        $guru->update(['is_active' => true]);

        $this->auditLogger->record('guru.activate', 'success', [
            'nama' => $guru->nama,
        ], subject: $guru, lembagaId: $user->lembaga_id, request: $request);

        return redirect()
            ->route('admin.guru.index')
            ->with('status', "Guru {$guru->nama} diaktifkan.");
    }

    public function deactivate(Request $request, Guru $guru): RedirectResponse
    {
        $user = $this->adminLembaga();
        abort_unless(hash_equals((string) $guru->lembaga_id, (string) $user->lembaga_id), 404);

        $guru->update(['is_active' => false]);

        $this->auditLogger->record('guru.deactivate', 'success', [
            'nama' => $guru->nama,
        ], subject: $guru, lembagaId: $user->lembaga_id, request: $request);

        return redirect()
            ->route('admin.guru.index')
            ->with('status', "Guru {$guru->nama} dinonaktifkan.");
    }

    public function destroy(Request $request, Guru $guru): RedirectResponse
    {
        $user = $this->adminLembaga();
        abort_unless(hash_equals((string) $guru->lembaga_id, (string) $user->lembaga_id), 404);

        $guru->delete();

        $this->auditLogger->record('guru.delete', 'success', [
            'nama' => $guru->nama,
        ], subject: $guru, lembagaId: $user->lembaga_id, request: $request);

        return redirect()
            ->route('admin.guru.index')
            ->with('status', "Guru {$guru->nama} dihapus.");
    }
}
