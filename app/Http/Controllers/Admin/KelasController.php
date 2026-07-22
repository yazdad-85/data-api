<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Concerns\EnsuresAdminLembaga;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\ImportKelasRequest;
use App\Http\Requests\Admin\ImportSiswaRequest;
use App\Http\Requests\Admin\StoreKelasRequest;
use App\Http\Requests\Admin\UpdateKelasRequest;
use App\Models\Guru;
use App\Models\Kelas;
use App\Models\Lembaga;
use App\Models\TahunAjaran;
use App\Services\AuditLogger;
use App\Services\Kelas\KelasImporter;
use App\Services\Kelas\KelasTemplateExporter;
use App\Services\Siswa\SiswaImporter;
use App\Services\Siswa\SiswaTemplateExporter;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;

class KelasController extends Controller
{
    use EnsuresAdminLembaga;

    public function __construct(
        private readonly AuditLogger $auditLogger,
        private readonly KelasTemplateExporter $templateExporter,
        private readonly KelasImporter $importer,
        private readonly SiswaTemplateExporter $siswaTemplateExporter,
        private readonly SiswaImporter $siswaImporter,
    ) {}

    public function index(Request $request): View
    {
        $this->adminLembaga();

        $q = trim((string) $request->query('q', ''));
        $tahunAjaranId = $request->query('tahun_ajaran_id');

        $kelasList = Kelas::query()
            ->with(['tahunAjaran', 'waliKelas'])
            ->withCount('siswa')
            ->when(is_string($tahunAjaranId) && $tahunAjaranId !== '', fn ($query) => $query->where('tahun_ajaran_id', $tahunAjaranId))
            ->when($q !== '', function ($query) use ($q) {
                $like = '%'.$q.'%';

                if ($query->getConnection()->getDriverName() === 'pgsql') {
                    $query->where('nama', 'ilike', $like);
                } else {
                    $query->whereRaw('lower(nama) like lower(?)', [$like]);
                }
            })
            ->orderBy('nama')
            ->paginate(15)
            ->withQueryString();

        $tahunAjarans = TahunAjaran::query()
            ->orderByDesc('nama')
            ->get();

        return view('admin.kelas.index', compact('kelasList', 'q', 'tahunAjaranId', 'tahunAjarans'));
    }

    public function create(): View
    {
        $this->adminLembaga();

        $tahunAjarans = TahunAjaran::query()
            ->orderByDesc('nama')
            ->get();

        $gurus = Guru::query()
            ->orderBy('nama')
            ->get();

        return view('admin.kelas.create', compact('tahunAjarans', 'gurus'));
    }

    public function store(StoreKelasRequest $request): RedirectResponse
    {
        $user = $this->adminLembaga();

        $kelas = Kelas::query()->create([
            ...$request->validated(),
            'lembaga_id' => $user->lembaga_id,
        ]);

        $this->auditLogger->record('kelas.create', 'success', [
            'nama' => $kelas->nama,
        ], subject: $kelas, lembagaId: $user->lembaga_id, request: $request);

        return redirect()
            ->route('admin.kelas.index')
            ->with('status', "Kelas {$kelas->nama} berhasil dibuat.");
    }

    public function downloadTemplate(): StreamedResponse
    {
        $this->adminLembaga();

        return $this->templateExporter->downloadResponse();
    }

    public function import(ImportKelasRequest $request): RedirectResponse
    {
        $user = $this->adminLembaga();
        $lembaga = Lembaga::query()->findOrFail($user->lembaga_id);

        $result = $this->importer->import(
            $request->file('file'),
            $lembaga,
            (string) $user->lembaga_id,
        );

        $auditResult = $result['success'] > 0 && $result['failed'] === 0
            ? 'success'
            : ($result['success'] > 0 ? 'success' : 'failed');

        $this->auditLogger->record('kelas.import', $auditResult, [
            'success' => $result['success'],
            'failed' => $result['failed'],
        ], lembagaId: $user->lembaga_id, request: $request);

        $status = "Import selesai: {$result['success']} berhasil";
        if ($result['failed'] > 0) {
            $status .= ", {$result['failed']} gagal";
        }
        $status .= '.';

        return redirect()
            ->route('admin.kelas.index')
            ->with('status', $status)
            ->with('import_errors', $result['errors']);
    }

    public function show(Request $request, Kelas $kelas): View
    {
        $user = $this->adminLembaga();
        abort_unless(hash_equals((string) $kelas->lembaga_id, (string) $user->lembaga_id), 404);

        $kelas->load(['tahunAjaran', 'waliKelas']);

        $siswa = $kelas->siswa()
            ->orderBy('nama')
            ->paginate(15);

        $this->auditLogger->record('master.view', 'success', [
            'resource' => 'kelas',
        ], subject: $kelas, lembagaId: $user->lembaga_id, request: $request);

        return view('admin.kelas.show', compact('kelas', 'siswa'));
    }

    public function siswaTemplate(Kelas $kelas): StreamedResponse
    {
        $user = $this->adminLembaga();
        abort_unless(hash_equals((string) $kelas->lembaga_id, (string) $user->lembaga_id), 404);

        return $this->siswaTemplateExporter->downloadResponse();
    }

    public function siswaImport(ImportSiswaRequest $request, Kelas $kelas): RedirectResponse
    {
        $user = $this->adminLembaga();
        abort_unless(hash_equals((string) $kelas->lembaga_id, (string) $user->lembaga_id), 404);

        $result = $this->siswaImporter->import(
            $request->file('file'),
            $kelas,
        );

        $auditResult = $result['success'] > 0 && $result['failed'] === 0
            ? 'success'
            : ($result['success'] > 0 ? 'success' : 'failed');

        $this->auditLogger->record('siswa.import', $auditResult, [
            'success' => $result['success'],
            'failed' => $result['failed'],
            'kelas_id' => $kelas->id,
        ], subject: $kelas, lembagaId: $user->lembaga_id, request: $request);

        $status = "Import selesai: {$result['success']} berhasil";
        if ($result['failed'] > 0) {
            $status .= ", {$result['failed']} gagal";
        }
        $status .= '.';

        return redirect()
            ->route('admin.kelas.show', $kelas)
            ->with('status', $status)
            ->with('import_errors', $result['errors']);
    }

    public function edit(Kelas $kelas): View
    {
        $user = $this->adminLembaga();
        abort_unless(hash_equals((string) $kelas->lembaga_id, (string) $user->lembaga_id), 404);

        $tahunAjarans = TahunAjaran::query()
            ->orderByDesc('nama')
            ->get();

        $gurus = Guru::query()
            ->orderBy('nama')
            ->get();

        return view('admin.kelas.edit', compact('kelas', 'tahunAjarans', 'gurus'));
    }

    public function update(UpdateKelasRequest $request, Kelas $kelas): RedirectResponse
    {
        $user = $this->adminLembaga();
        abort_unless(hash_equals((string) $kelas->lembaga_id, (string) $user->lembaga_id), 404);

        $kelas->update($request->validated());

        $this->auditLogger->record('kelas.update', 'success', [
            'nama' => $kelas->nama,
        ], subject: $kelas, lembagaId: $user->lembaga_id, request: $request);

        return redirect()
            ->route('admin.kelas.index')
            ->with('status', "Kelas {$kelas->nama} berhasil diperbarui.");
    }

    public function destroy(Request $request, Kelas $kelas): RedirectResponse
    {
        $user = $this->adminLembaga();
        abort_unless(hash_equals((string) $kelas->lembaga_id, (string) $user->lembaga_id), 404);

        if ($kelas->siswa()->exists()) {
            return redirect()
                ->route('admin.kelas.index')
                ->withErrors([
                    'kelas' => "Kelas {$kelas->nama} tidak dapat dihapus karena masih memiliki siswa.",
                ]);
        }

        $nama = $kelas->nama;
        $kelas->forceDelete();

        $this->auditLogger->record('kelas.delete', 'success', [
            'nama' => $nama,
        ], subject: $kelas, lembagaId: $user->lembaga_id, request: $request);

        return redirect()
            ->route('admin.kelas.index')
            ->with('status', "Kelas {$nama} dihapus permanen.");
    }
}
