<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Concerns\EnsuresAdminLembaga;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\StoreSiswaRequest;
use App\Http\Requests\Admin\UpdateSiswaRequest;
use App\Models\Kelas;
use App\Models\Siswa;
use App\Models\TahunAjaran;
use App\Services\AuditLogger;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class SiswaController extends Controller
{
    use EnsuresAdminLembaga;

    public function __construct(
        private readonly AuditLogger $auditLogger,
    ) {}

    public function index(Request $request): View
    {
        $this->adminLembaga();

        $q = trim((string) $request->query('q', ''));
        $kelasId = $request->query('kelas_id');
        $tahunAjaranId = $request->query('tahun_ajaran_id');

        $siswas = Siswa::query()
            ->with(['kelas', 'tahunAjaran'])
            ->when(is_string($kelasId) && $kelasId !== '', fn ($query) => $query->where('kelas_id', $kelasId))
            ->when(is_string($tahunAjaranId) && $tahunAjaranId !== '', fn ($query) => $query->where('tahun_ajaran_id', $tahunAjaranId))
            ->when($q !== '', function ($query) use ($q) {
                $like = '%'.$q.'%';
                $query->where(function ($inner) use ($like) {
                    if ($inner->getConnection()->getDriverName() === 'pgsql') {
                        $inner->where('nama', 'ilike', $like)
                            ->orWhere('nis', 'ilike', $like)
                            ->orWhere('nisn', 'ilike', $like);
                    } else {
                        $inner->whereRaw('lower(nama) like lower(?)', [$like])
                            ->orWhereRaw('lower(nis) like lower(?)', [$like])
                            ->orWhereRaw('lower(nisn) like lower(?)', [$like]);
                    }
                });
            })
            ->orderBy('nama')
            ->paginate(15)
            ->withQueryString();

        $kelasList = Kelas::query()
            ->with('tahunAjaran')
            ->orderBy('nama')
            ->get();

        $tahunAjarans = TahunAjaran::query()
            ->orderByDesc('nama')
            ->get();

        return view('admin.siswa.index', compact('siswas', 'q', 'kelasId', 'tahunAjaranId', 'kelasList', 'tahunAjarans'));
    }

    public function create(): View
    {
        $this->adminLembaga();

        $kelasList = Kelas::query()
            ->with('tahunAjaran')
            ->orderBy('nama')
            ->get();

        $tahunAjarans = TahunAjaran::query()
            ->orderByDesc('nama')
            ->get();

        return view('admin.siswa.create', compact('kelasList', 'tahunAjarans'));
    }

    public function store(StoreSiswaRequest $request): RedirectResponse
    {
        $user = $this->adminLembaga();

        $siswa = Siswa::query()->create([
            ...$request->validated(),
            'lembaga_id' => $user->lembaga_id,
            'is_active' => true,
        ]);

        $this->auditLogger->record('siswa.create', 'success', [
            'nama' => $siswa->nama,
        ], subject: $siswa, lembagaId: $user->lembaga_id, request: $request);

        return redirect()
            ->route('admin.siswa.index')
            ->with('status', "Siswa {$siswa->nama} berhasil dibuat.");
    }

    public function show(Request $request, Siswa $siswa): View
    {
        $user = $this->adminLembaga();
        abort_unless(hash_equals((string) $siswa->lembaga_id, (string) $user->lembaga_id), 404);

        $siswa->load(['kelas', 'tahunAjaran']);

        $this->auditLogger->record('master.view', 'success', [
            'resource' => 'siswa',
        ], subject: $siswa, lembagaId: $user->lembaga_id, request: $request);

        return view('admin.siswa.show', compact('siswa'));
    }

    public function edit(Siswa $siswa): View
    {
        $user = $this->adminLembaga();
        abort_unless(hash_equals((string) $siswa->lembaga_id, (string) $user->lembaga_id), 404);

        $kelasList = Kelas::query()
            ->with('tahunAjaran')
            ->orderBy('nama')
            ->get();

        $tahunAjarans = TahunAjaran::query()
            ->orderByDesc('nama')
            ->get();

        return view('admin.siswa.edit', compact('siswa', 'kelasList', 'tahunAjarans'));
    }

    public function update(UpdateSiswaRequest $request, Siswa $siswa): RedirectResponse
    {
        $user = $this->adminLembaga();
        abort_unless(hash_equals((string) $siswa->lembaga_id, (string) $user->lembaga_id), 404);

        $siswa->update($request->validated());

        $this->auditLogger->record('siswa.update', 'success', [
            'nama' => $siswa->nama,
        ], subject: $siswa, lembagaId: $user->lembaga_id, request: $request);

        return redirect()
            ->route('admin.siswa.index')
            ->with('status', "Siswa {$siswa->nama} berhasil diperbarui.");
    }

    public function activate(Request $request, Siswa $siswa): RedirectResponse
    {
        $user = $this->adminLembaga();
        abort_unless(hash_equals((string) $siswa->lembaga_id, (string) $user->lembaga_id), 404);

        $siswa->update(['is_active' => true]);

        $this->auditLogger->record('siswa.activate', 'success', [
            'nama' => $siswa->nama,
        ], subject: $siswa, lembagaId: $user->lembaga_id, request: $request);

        return redirect()
            ->route('admin.siswa.index')
            ->with('status', "Siswa {$siswa->nama} diaktifkan.");
    }

    public function deactivate(Request $request, Siswa $siswa): RedirectResponse
    {
        $user = $this->adminLembaga();
        abort_unless(hash_equals((string) $siswa->lembaga_id, (string) $user->lembaga_id), 404);

        $siswa->update(['is_active' => false]);

        $this->auditLogger->record('siswa.deactivate', 'success', [
            'nama' => $siswa->nama,
        ], subject: $siswa, lembagaId: $user->lembaga_id, request: $request);

        return redirect()
            ->route('admin.siswa.index')
            ->with('status', "Siswa {$siswa->nama} dinonaktifkan.");
    }

    public function destroy(Request $request, Siswa $siswa): RedirectResponse
    {
        $user = $this->adminLembaga();
        abort_unless(hash_equals((string) $siswa->lembaga_id, (string) $user->lembaga_id), 404);

        $siswa->delete();

        $this->auditLogger->record('siswa.delete', 'success', [
            'nama' => $siswa->nama,
        ], subject: $siswa, lembagaId: $user->lembaga_id, request: $request);

        return redirect()
            ->route('admin.siswa.index')
            ->with('status', "Siswa {$siswa->nama} dihapus.");
    }
}
