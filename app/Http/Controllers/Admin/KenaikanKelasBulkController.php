<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Concerns\EnsuresAdminLembaga;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\KenaikanKelasBulkRequest;
use App\Models\Kelas;
use App\Models\TahunAjaran;
use App\Services\AuditLogger;
use App\Services\Siswa\KenaikanKelasService;
use App\Support\Master\SiswaStatus;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\View\View;

class KenaikanKelasBulkController extends Controller
{
    use EnsuresAdminLembaga;

    public function __construct(
        private readonly AuditLogger $auditLogger,
        private readonly KenaikanKelasService $kenaikan,
    ) {}

    public function create(Request $request): View
    {
        $this->adminLembaga();

        $tahunAsalId = $request->query('tahun_asal_id');
        $tahunAsalId = is_string($tahunAsalId) && $tahunAsalId !== '' ? $tahunAsalId : '';

        $tahunTujuanId = $request->query('tahun_tujuan_id');
        $tahunTujuanId = is_string($tahunTujuanId) && $tahunTujuanId !== '' ? $tahunTujuanId : '';

        $tahunAjarans = TahunAjaran::query()
            ->orderByDesc('nama')
            ->get();

        $kelasAsalList = collect();
        $kelasTujuanList = collect();
        $tahunTujuanBelumPunyaKelas = false;

        if ($tahunAsalId !== '') {
            $kelasAsalList = Kelas::query()
                ->where('tahun_ajaran_id', $tahunAsalId)
                ->withCount(['siswa as siswa_aktif_count' => fn ($query) => $query->where('status_siswa', SiswaStatus::AKTIF)])
                ->orderBy('nama')
                ->get();
        }

        if ($tahunTujuanId !== '') {
            $kelasTujuanList = Kelas::query()
                ->where('tahun_ajaran_id', $tahunTujuanId)
                ->orderBy('nama')
                ->get();

            $tahunTujuanBelumPunyaKelas = $kelasTujuanList->isEmpty();
        }

        return view('admin.kenaikan.massal', compact(
            'tahunAjarans',
            'tahunAsalId',
            'tahunTujuanId',
            'kelasAsalList',
            'kelasTujuanList',
            'tahunTujuanBelumPunyaKelas',
        ));
    }

    public function store(KenaikanKelasBulkRequest $request): RedirectResponse
    {
        $user = $this->adminLembaga();
        $validated = $request->validated();

        $tahunTujuan = TahunAjaran::query()
            ->where('lembaga_id', $user->lembaga_id)
            ->findOrFail($validated['tahun_tujuan_id']);

        $efektifAt = ! empty($validated['efektif_at']) ? Carbon::parse((string) $validated['efektif_at']) : Carbon::now();

        $kelasIds = collect($validated['mappings'])
            ->flatMap(fn (array $mapping) => [$mapping['kelas_asal_id'], $mapping['kelas_tujuan_id']])
            ->unique();

        $kelasById = Kelas::query()
            ->where('lembaga_id', $user->lembaga_id)
            ->whereIn('id', $kelasIds)
            ->get()
            ->keyBy('id');

        $mappings = collect($validated['mappings'])
            ->map(fn (array $mapping) => [
                'kelas_asal' => $kelasById->get($mapping['kelas_asal_id']),
                'kelas_tujuan' => $kelasById->get($mapping['kelas_tujuan_id']),
            ])
            ->filter(fn (array $mapping) => $mapping['kelas_asal'] !== null && $mapping['kelas_tujuan'] !== null)
            ->values()
            ->all();

        $hasil = $this->kenaikan->commitBulk($mappings, $tahunTujuan, $efektifAt);

        $totalSuccess = array_sum(array_column($hasil, 'success'));
        $totalFailed = array_sum(array_column($hasil, 'failed'));

        $this->auditLogger->record('siswa.kenaikan_massal', $totalFailed === 0 ? 'success' : 'failed', [
            'tahun_asal_id' => $validated['tahun_asal_id'],
            'tahun_tujuan_id' => $tahunTujuan->id,
            'jumlah_kelas' => count($hasil),
            'success' => $totalSuccess,
            'failed' => $totalFailed,
        ], subject: $tahunTujuan, lembagaId: $user->lembaga_id, request: $request);

        return redirect()
            ->route('admin.kenaikan-massal.create', [
                'tahun_asal_id' => $validated['tahun_asal_id'],
                'tahun_tujuan_id' => $tahunTujuan->id,
            ])
            ->with('kenaikan_massal_hasil', $hasil)
            ->with('status', "Kenaikan kelas massal selesai: {$totalSuccess} siswa berhasil diproses" . ($totalFailed > 0 ? ", {$totalFailed} gagal." : '.'));
    }
}
