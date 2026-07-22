<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Concerns\EnsuresAdminLembaga;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\KenaikanKelasRequest;
use App\Models\Kelas;
use App\Models\TahunAjaran;
use App\Services\AuditLogger;
use App\Services\Siswa\KenaikanKelasService;
use App\Support\Master\SiswaStatus;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\View\View;

class KenaikanKelasController extends Controller
{
    use EnsuresAdminLembaga;

    public function __construct(
        private readonly AuditLogger $auditLogger,
        private readonly KenaikanKelasService $kenaikan,
    ) {}

    public function create(Request $request, Kelas $kelas): View
    {
        $user = $this->adminLembaga();
        abort_unless(hash_equals((string) $kelas->lembaga_id, (string) $user->lembaga_id), 404);

        $kelas->load('tahunAjaran');

        $siswa = $kelas->siswa()
            ->where('status_siswa', SiswaStatus::AKTIF)
            ->orderBy('nama')
            ->get();

        $tahunAjarans = TahunAjaran::query()
            ->orderByDesc('nama')
            ->get();

        $kelasTujuan = Kelas::query()
            ->with('tahunAjaran')
            ->where('id', '!=', $kelas->id)
            ->orderBy('nama')
            ->get();

        return view('admin.kenaikan.create', compact('kelas', 'siswa', 'tahunAjarans', 'kelasTujuan'));
    }

    public function store(KenaikanKelasRequest $request, Kelas $kelas): RedirectResponse
    {
        $user = $this->adminLembaga();
        abort_unless(hash_equals((string) $kelas->lembaga_id, (string) $user->lembaga_id), 404);

        $validated = $request->validated();

        $tahunTujuan = null;
        if (! empty($validated['tahun_tujuan_id'])) {
            $tahunTujuan = TahunAjaran::query()
                ->where('lembaga_id', $user->lembaga_id)
                ->find($validated['tahun_tujuan_id']);
        }

        $kelasTujuanDefault = null;
        if (! empty($validated['kelas_tujuan_default_id'])) {
            $kelasTujuanDefault = Kelas::query()
                ->where('lembaga_id', $user->lembaga_id)
                ->find($validated['kelas_tujuan_default_id']);
        }

        $efektifAt = ! empty($validated['efektif_at'])
            ? Carbon::parse($validated['efektif_at'])
            : Carbon::now();

        /** @var list<array<string, mixed>> $rows */
        $rows = array_values($validated['rows']);

        $result = $this->kenaikan->commit($kelas, $tahunTujuan, $kelasTujuanDefault, $rows, $efektifAt);

        $auditResult = $result['failed'] === 0 ? 'success' : 'failed';
        $this->auditLogger->record('siswa.kenaikan', $auditResult, [
            'kelas_asal_id' => $kelas->id,
            'success' => $result['success'],
            'failed' => $result['failed'],
        ], subject: $kelas, lembagaId: $user->lembaga_id, request: $request);

        if ($result['failed'] > 0) {
            return redirect()
                ->route('admin.kelas.kenaikan.create', $kelas)
                ->withInput()
                ->with('kenaikan_errors', $result['errors'])
                ->withErrors(['kenaikan' => 'Batch dibatalkan: tidak ada perubahan yang disimpan. Perbaiki baris berikut lalu coba lagi.']);
        }

        return redirect()
            ->route('admin.kelas.show', $kelas)
            ->with('status', "Kenaikan kelas selesai: {$result['success']} siswa diproses.");
    }
}
