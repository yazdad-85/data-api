<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Concerns\EnsuresAdminLembaga;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\SpmbDistribusiRequest;
use App\Models\Kelas;
use App\Models\Siswa;
use App\Models\TahunAjaran;
use App\Services\AuditLogger;
use App\Services\Siswa\SpmbDistribusiService;
use App\Support\Master\SiswaStatus;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\View\View;

class SpmbDistribusiController extends Controller
{
    use EnsuresAdminLembaga;

    public function __construct(
        private readonly AuditLogger $auditLogger,
        private readonly SpmbDistribusiService $spmb,
    ) {}

    public function create(Request $request): View
    {
        $this->adminLembaga();

        $tahunAjaranId = $request->query('tahun_ajaran_id');
        $tahunAjaranId = is_string($tahunAjaranId) && $tahunAjaranId !== '' ? $tahunAjaranId : '';

        $tingkat = $request->query('tingkat');
        $tingkat = is_string($tingkat) && $tingkat !== '' ? $tingkat : '';

        $tahunAjarans = TahunAjaran::query()
            ->orderByDesc('nama')
            ->get();

        $calonSiswa = Siswa::query()
            ->with('tahunAjaran')
            ->where('status_siswa', SiswaStatus::CALON)
            ->when($tahunAjaranId !== '', function ($query) use ($tahunAjaranId) {
                $query->where(function ($inner) use ($tahunAjaranId) {
                    $inner->where('tahun_ajaran_id', $tahunAjaranId)
                        ->orWhereNull('tahun_ajaran_id');
                });
            })
            ->orderBy('nama')
            ->get();

        $kelasTujuanList = Kelas::query()
            ->with('tahunAjaran')
            ->when($tahunAjaranId !== '', fn ($query) => $query->where('tahun_ajaran_id', $tahunAjaranId))
            ->when($tingkat !== '', fn ($query) => $query->where('tingkat', $tingkat))
            ->orderBy('nama')
            ->get();

        $tingkatOptions = Kelas::query()
            ->when($tahunAjaranId !== '', fn ($query) => $query->where('tahun_ajaran_id', $tahunAjaranId))
            ->whereNotNull('tingkat')
            ->distinct()
            ->orderBy('tingkat')
            ->pluck('tingkat');

        return view('admin.spmb.distribusi', compact(
            'tahunAjarans',
            'tahunAjaranId',
            'tingkat',
            'tingkatOptions',
            'calonSiswa',
            'kelasTujuanList',
        ));
    }

    public function store(SpmbDistribusiRequest $request): RedirectResponse
    {
        $user = $this->adminLembaga();

        $validated = $request->validated();

        $kelasTujuan = Kelas::query()
            ->where('lembaga_id', $user->lembaga_id)
            ->findOrFail($validated['kelas_id']);

        $mulai = ! empty($validated['mulai_at']) ? Carbon::parse((string) $validated['mulai_at']) : null;

        /** @var list<string> $siswaIds */
        $siswaIds = array_values($validated['siswa_ids']);

        $result = $this->spmb->commit($user->lembaga_id, $kelasTujuan, $siswaIds, $mulai);

        $auditResult = $result['failed'] === 0 ? 'success' : 'failed';
        $this->auditLogger->record('siswa.spmb_distribusi', $auditResult, [
            'kelas_tujuan_id' => $kelasTujuan->id,
            'success' => $result['success'],
            'failed' => $result['failed'],
        ], subject: $kelasTujuan, lembagaId: $user->lembaga_id, request: $request);

        if ($result['failed'] > 0) {
            return redirect()
                ->route('admin.spmb-distribusi.create')
                ->withInput()
                ->with('spmb_errors', $result['errors'])
                ->withErrors(['spmb' => 'Batch dibatalkan: tidak ada perubahan yang disimpan. Perbaiki lalu coba lagi.']);
        }

        return redirect()
            ->route('admin.siswa.index')
            ->with('status', "Distribusi selesai: {$result['success']} siswa ditempatkan ke kelas {$kelasTujuan->nama}.");
    }
}
