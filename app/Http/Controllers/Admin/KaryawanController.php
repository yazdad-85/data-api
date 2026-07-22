<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Concerns\EnsuresAdminLembaga;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\ImportKaryawanRequest;
use App\Http\Requests\Admin\StoreKaryawanRequest;
use App\Http\Requests\Admin\UpdateKaryawanRequest;
use App\Models\Karyawan;
use App\Models\Lembaga;
use App\Services\AuditLogger;
use App\Services\Karyawan\KaryawanImporter;
use App\Services\Karyawan\KaryawanTemplateExporter;
use App\Support\Master\GuruNiyGenerator;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;
use InvalidArgumentException;
use Symfony\Component\HttpFoundation\StreamedResponse;

class KaryawanController extends Controller
{
    use EnsuresAdminLembaga;

    public function __construct(
        private readonly AuditLogger $auditLogger,
        private readonly GuruNiyGenerator $niyGenerator,
        private readonly KaryawanTemplateExporter $templateExporter,
        private readonly KaryawanImporter $importer,
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
        $user = $this->adminLembaga();
        $lembaga = Lembaga::query()->findOrFail($user->lembaga_id);

        return view('admin.karyawan.create', compact('lembaga'));
    }

    public function store(StoreKaryawanRequest $request): RedirectResponse
    {
        $user = $this->adminLembaga();
        $lembaga = Lembaga::query()->findOrFail($user->lembaga_id);

        try {
            $karyawan = DB::transaction(function () use ($request, $lembaga, $user) {
                $nik = $this->niyGenerator->generate(
                    $lembaga,
                    $request->validated('jenis_kelamin'),
                    (int) $request->validated('tahun_masuk'),
                );

                return Karyawan::query()->create([
                    ...collect($request->validated())->except(['nik_pegawai'])->all(),
                    'lembaga_id' => $user->lembaga_id,
                    'nik_pegawai' => $nik,
                    'is_active' => true,
                ]);
            });
        } catch (InvalidArgumentException $exception) {
            return redirect()
                ->back()
                ->withInput()
                ->withErrors(['tahun_masuk' => $exception->getMessage()]);
        }

        $this->auditLogger->record('karyawan.create', 'success', [
            'nama' => $karyawan->nama,
        ], subject: $karyawan, lembagaId: $user->lembaga_id, request: $request);

        return redirect()
            ->route('admin.karyawan.index')
            ->with('status', "Karyawan {$karyawan->nama} berhasil dibuat dengan NIK {$karyawan->nik_pegawai}.");
    }

    public function downloadTemplate(): StreamedResponse
    {
        $this->adminLembaga();

        return $this->templateExporter->downloadResponse();
    }

    public function import(ImportKaryawanRequest $request): RedirectResponse
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

        $this->auditLogger->record('karyawan.import', $auditResult, [
            'success' => $result['success'],
            'failed' => $result['failed'],
        ], lembagaId: $user->lembaga_id, request: $request);

        $status = "Import selesai: {$result['success']} berhasil";
        if ($result['failed'] > 0) {
            $status .= ", {$result['failed']} gagal";
        }
        $status .= '.';

        return redirect()
            ->route('admin.karyawan.index')
            ->with('status', $status)
            ->with('import_errors', $result['errors']);
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
