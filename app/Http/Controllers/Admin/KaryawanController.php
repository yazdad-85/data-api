<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Concerns\EnsuresAdminLembaga;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\StoreKaryawanRequest;
use App\Http\Requests\Admin\UpdateKaryawanRequest;
use App\Models\Karyawan;
use App\Services\AuditLogger;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class KaryawanController extends Controller
{
    use EnsuresAdminLembaga;

    public function __construct(
        private readonly AuditLogger $auditLogger,
    ) {}

    public function index(Request $request): View
    {
        $this->adminLembaga();

        $q = trim((string) $request->query('q', ''));

        $karyawans = Karyawan::query()
            ->when($q !== '', function ($query) use ($q) {
                $like = '%'.$q.'%';
                $query->where(function ($inner) use ($like) {
                    if ($inner->getConnection()->getDriverName() === 'pgsql') {
                        $inner->where('nama', 'ilike', $like)->orWhere('nik_pegawai', 'ilike', $like);
                    } else {
                        $inner->whereRaw('lower(nama) like lower(?)', [$like])
                            ->orWhereRaw('lower(nik_pegawai) like lower(?)', [$like]);
                    }
                });
            })
            ->orderBy('nama')
            ->paginate(15)
            ->withQueryString();

        return view('admin.karyawan.index', compact('karyawans', 'q'));
    }

    public function create(): View
    {
        $this->adminLembaga();

        return view('admin.karyawan.create');
    }

    public function store(StoreKaryawanRequest $request): RedirectResponse
    {
        $user = $this->adminLembaga();

        $karyawan = Karyawan::query()->create([
            ...$request->validated(),
            'lembaga_id' => $user->lembaga_id,
            'is_active' => true,
        ]);

        $this->auditLogger->record('karyawan.create', 'success', [
            'nama' => $karyawan->nama,
        ], subject: $karyawan, lembagaId: $user->lembaga_id, request: $request);

        return redirect()
            ->route('admin.karyawan.index')
            ->with('status', "Karyawan {$karyawan->nama} berhasil dibuat.");
    }

    public function show(Request $request, Karyawan $karyawan): View
    {
        $user = $this->adminLembaga();
        abort_unless(hash_equals((string) $karyawan->lembaga_id, (string) $user->lembaga_id), 404);

        $this->auditLogger->record('master.view', 'success', [
            'resource' => 'karyawan',
        ], subject: $karyawan, lembagaId: $user->lembaga_id, request: $request);

        return view('admin.karyawan.show', compact('karyawan'));
    }

    public function edit(Karyawan $karyawan): View
    {
        $user = $this->adminLembaga();
        abort_unless(hash_equals((string) $karyawan->lembaga_id, (string) $user->lembaga_id), 404);

        return view('admin.karyawan.edit', compact('karyawan'));
    }

    public function update(UpdateKaryawanRequest $request, Karyawan $karyawan): RedirectResponse
    {
        $user = $this->adminLembaga();
        abort_unless(hash_equals((string) $karyawan->lembaga_id, (string) $user->lembaga_id), 404);

        $karyawan->update($request->validated());

        $this->auditLogger->record('karyawan.update', 'success', [
            'nama' => $karyawan->nama,
        ], subject: $karyawan, lembagaId: $user->lembaga_id, request: $request);

        return redirect()
            ->route('admin.karyawan.index')
            ->with('status', "Karyawan {$karyawan->nama} berhasil diperbarui.");
    }

    public function activate(Request $request, Karyawan $karyawan): RedirectResponse
    {
        $user = $this->adminLembaga();
        abort_unless(hash_equals((string) $karyawan->lembaga_id, (string) $user->lembaga_id), 404);

        $karyawan->update(['is_active' => true]);

        $this->auditLogger->record('karyawan.activate', 'success', [
            'nama' => $karyawan->nama,
        ], subject: $karyawan, lembagaId: $user->lembaga_id, request: $request);

        return redirect()
            ->route('admin.karyawan.index')
            ->with('status', "Karyawan {$karyawan->nama} diaktifkan.");
    }

    public function deactivate(Request $request, Karyawan $karyawan): RedirectResponse
    {
        $user = $this->adminLembaga();
        abort_unless(hash_equals((string) $karyawan->lembaga_id, (string) $user->lembaga_id), 404);

        $karyawan->update(['is_active' => false]);

        $this->auditLogger->record('karyawan.deactivate', 'success', [
            'nama' => $karyawan->nama,
        ], subject: $karyawan, lembagaId: $user->lembaga_id, request: $request);

        return redirect()
            ->route('admin.karyawan.index')
            ->with('status', "Karyawan {$karyawan->nama} dinonaktifkan.");
    }

    public function destroy(Request $request, Karyawan $karyawan): RedirectResponse
    {
        $user = $this->adminLembaga();
        abort_unless(hash_equals((string) $karyawan->lembaga_id, (string) $user->lembaga_id), 404);

        $karyawan->delete();

        $this->auditLogger->record('karyawan.delete', 'success', [
            'nama' => $karyawan->nama,
        ], subject: $karyawan, lembagaId: $user->lembaga_id, request: $request);

        return redirect()
            ->route('admin.karyawan.index')
            ->with('status', "Karyawan {$karyawan->nama} dihapus.");
    }
}
