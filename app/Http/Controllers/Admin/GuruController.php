<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Concerns\EnsuresAdminLembaga;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\ImportGuruRequest;
use App\Http\Requests\Admin\StoreGuruRequest;
use App\Http\Requests\Admin\UpdateGuruRequest;
use App\Models\Guru;
use App\Models\Lembaga;
use App\Services\AuditLogger;
use App\Services\Guru\GuruImporter;
use App\Services\Guru\GuruTemplateExporter;
use App\Support\Master\GuruNiyGenerator;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;
use InvalidArgumentException;
use Symfony\Component\HttpFoundation\StreamedResponse;

class GuruController extends Controller
{
    use EnsuresAdminLembaga;

    public function __construct(
        private readonly AuditLogger $auditLogger,
        private readonly GuruNiyGenerator $niyGenerator,
        private readonly GuruTemplateExporter $templateExporter,
        private readonly GuruImporter $importer,
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
                        $inner->where('nama', 'ilike', $like)
                            ->orWhere('niy', 'ilike', $like)
                            ->orWhere('nik', 'ilike', $like);
                    } else {
                        $inner->whereRaw('lower(nama) like lower(?)', [$like])
                            ->orWhereRaw('lower(niy) like lower(?)', [$like])
                            ->orWhereRaw('lower(nik) like lower(?)', [$like]);
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
        $user = $this->adminLembaga();
        $lembaga = Lembaga::query()->findOrFail($user->lembaga_id);

        return view('admin.guru.create', compact('lembaga'));
    }

    public function store(StoreGuruRequest $request): RedirectResponse
    {
        $user = $this->adminLembaga();
        $lembaga = Lembaga::query()->findOrFail($user->lembaga_id);

        try {
            $guru = DB::transaction(function () use ($request, $lembaga, $user) {
                $niy = $this->niyGenerator->generate(
                    $lembaga,
                    $request->validated('jenis_kelamin'),
                    (int) $request->validated('tahun_masuk'),
                );

                return Guru::query()->create([
                    ...collect($request->validated())->except(['niy'])->all(),
                    'lembaga_id' => $user->lembaga_id,
                    'niy' => $niy,
                    'is_active' => true,
                ]);
            });
        } catch (InvalidArgumentException $exception) {
            return redirect()
                ->back()
                ->withInput()
                ->withErrors(['tahun_masuk' => $exception->getMessage()]);
        }

        $this->auditLogger->record('guru.create', 'success', [
            'nama' => $guru->nama,
        ], subject: $guru, lembagaId: $user->lembaga_id, request: $request);

        return redirect()
            ->route('admin.guru.index')
            ->with('status', "Guru {$guru->nama} berhasil dibuat dengan NIY {$guru->niy}.");
    }

    public function downloadTemplate(): StreamedResponse
    {
        $this->adminLembaga();

        return $this->templateExporter->downloadResponse();
    }

    public function import(ImportGuruRequest $request): RedirectResponse
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

        $this->auditLogger->record('guru.import', $auditResult, [
            'success' => $result['success'],
            'failed' => $result['failed'],
        ], lembagaId: $user->lembaga_id, request: $request);

        $status = "Import selesai: {$result['success']} berhasil";
        if ($result['failed'] > 0) {
            $status .= ", {$result['failed']} gagal";
        }
        $status .= '.';

        return redirect()
            ->route('admin.guru.index')
            ->with('status', $status)
            ->with('import_errors', $result['errors']);
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
