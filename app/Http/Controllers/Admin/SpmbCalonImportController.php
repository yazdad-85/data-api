<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Concerns\EnsuresAdminLembaga;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\ImportSiswaCalonRequest;
use App\Models\TahunAjaran;
use App\Services\AuditLogger;
use App\Services\Siswa\SiswaCalonImporter;
use App\Services\Siswa\SiswaCalonTemplateExporter;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;

class SpmbCalonImportController extends Controller
{
    use EnsuresAdminLembaga;

    public function __construct(
        private readonly AuditLogger $auditLogger,
        private readonly SiswaCalonTemplateExporter $templateExporter,
        private readonly SiswaCalonImporter $importer,
    ) {}

    public function create(): View
    {
        $user = $this->adminLembaga();

        $tahunAjarans = TahunAjaran::query()
            ->where('lembaga_id', $user->lembaga_id)
            ->orderByDesc('nama')
            ->get();

        return view('admin.spmb.calon-import', compact('tahunAjarans'));
    }

    public function template(): StreamedResponse
    {
        $this->adminLembaga();

        return $this->templateExporter->downloadResponse();
    }

    public function store(ImportSiswaCalonRequest $request): RedirectResponse
    {
        $user = $this->adminLembaga();

        $validated = $request->validated();
        $tahunAjaranId = $validated['tahun_ajaran_id'] ?? null;

        $result = $this->importer->import(
            $request->file('file'),
            $user->lembaga_id,
            $tahunAjaranId,
        );

        $auditResult = $result['success'] > 0 && $result['failed'] === 0
            ? 'success'
            : ($result['success'] > 0 ? 'success' : 'failed');

        $this->auditLogger->record('siswa.spmb_calon_import', $auditResult, [
            'success' => $result['success'],
            'failed' => $result['failed'],
        ], lembagaId: $user->lembaga_id, request: $request);

        $status = "Import selesai: {$result['success']} calon murid berhasil disimpan";
        if ($result['failed'] > 0) {
            $status .= ", {$result['failed']} gagal";
        }
        $status .= '.';

        return redirect()
            ->route('admin.spmb-distribusi.create')
            ->with('status', $status)
            ->with('import_errors', $result['errors']);
    }
}
