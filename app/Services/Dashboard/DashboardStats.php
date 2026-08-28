<?php

namespace App\Services\Dashboard;

use App\Models\ApiClient;
use App\Models\Guru;
use App\Models\Karyawan;
use App\Models\Kelas;
use App\Models\Lembaga;
use App\Models\Siswa;
use App\Models\TahunAjaran;
use App\Models\User;
use App\Support\Master\PenempatanJenis;
use App\Support\Master\SiswaStatus;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;
use Illuminate\Support\Carbon;

final class DashboardStats
{
    private const FAMILY_STATUSES = [
        'Yatim',
        'Piatu',
        'Yatim Piatu',
        'Anak Guru, Staff, dan Karyawan',
    ];

    private const FAMILY_STATUS_EMPTY = 'Belum diisi';

    /** @return array<string, mixed> */
    public function for(User $user, string $tahunAjaranId = '', string $lembagaId = ''): array
    {
        if ($user->isSuperAdmin()) {
            $lembagaOptions = Lembaga::query()->orderBy('nama')->get();
            $selectedLembagaId = $lembagaOptions->contains('id', $lembagaId) ? $lembagaId : '';
            $academicYears = $this->academicYears($selectedLembagaId);
            $selectedTahunAjaranId = $academicYears->contains('id', $tahunAjaranId) ? $tahunAjaranId : '';
            $lembagas = Lembaga::query()
                ->withCount([
                    'guru',
                    'siswa',
                    'karyawan',
                    'kelas',
                    'tahunAjaran',
                    'guru as guru_aktif_count' => fn ($query) => $query->where('is_active', true),
                    'siswa as siswa_aktif_count' => fn ($query) => $query->where('status_siswa', SiswaStatus::AKTIF),
                    'siswa as siswa_mutasi_masuk_count' => fn ($query) => $query->where('status_siswa', SiswaStatus::MUTASI_MASUK),
                    'siswa as siswa_mutasi_keluar_count' => fn ($query) => $query->where('status_siswa', SiswaStatus::MUTASI_KELUAR),
                    'siswa as siswa_lulus_count' => fn ($query) => $query->where('status_siswa', SiswaStatus::LULUS),
                    'karyawan as karyawan_aktif_count' => fn ($query) => $query->where('is_active', true),
                    'tahunAjaran as tahun_ajaran_aktif_count' => fn ($query) => $query->where('is_aktif', true),
                ])
                ->when($selectedLembagaId !== '', fn (Builder $query) => $query->where('id', $selectedLembagaId))
                ->orderBy('nama')
                ->get();
            $lembagaAktifQuery = Lembaga::query()->where('is_active', true);
            $lembagaNonaktifQuery = Lembaga::query()->where('is_active', false);
            $apiClientQuery = ApiClient::query()->where('is_active', true)->whereNull('revoked_at');
            $guruQuery = Guru::query();
            $guruAktifQuery = Guru::query()->where('is_active', true);
            $siswaQuery = Siswa::query();
            $siswaAktifQuery = Siswa::query()->where('status_siswa', SiswaStatus::AKTIF);
            $siswaMutasiKeluarQuery = Siswa::query()->where('status_siswa', SiswaStatus::MUTASI_KELUAR);
            $siswaLulusQuery = Siswa::query()->where('status_siswa', SiswaStatus::LULUS);
            $karyawanQuery = Karyawan::query();
            $karyawanAktifQuery = Karyawan::query()->where('is_active', true);

            if ($selectedLembagaId !== '') {
                foreach ([
                    $apiClientQuery,
                    $guruQuery,
                    $guruAktifQuery,
                    $siswaQuery,
                    $siswaAktifQuery,
                    $siswaMutasiKeluarQuery,
                    $siswaLulusQuery,
                    $karyawanQuery,
                    $karyawanAktifQuery,
                ] as $query) {
                    $query->where('lembaga_id', $selectedLembagaId);
                }
                $lembagaAktifQuery->where('id', $selectedLembagaId);
                $lembagaNonaktifQuery->where('id', $selectedLembagaId);
            }

            return [
                'role' => 'super_admin',
                'selected_lembaga_id' => $selectedLembagaId,
                'lembaga_options' => $lembagaOptions,
                'lembaga_aktif' => $lembagaAktifQuery->count(),
                'lembaga_nonaktif' => $lembagaNonaktifQuery->count(),
                'api_client_aktif' => $apiClientQuery->count(),
                'guru' => $guruQuery->count(),
                'guru_aktif' => $guruAktifQuery->count(),
                'siswa' => $siswaAktifQuery->count(),
                'total_siswa' => $siswaQuery->count(),
                'siswa_aktif' => $siswaAktifQuery->count(),
                'siswa_mutasi_masuk' => $this->siswaMutasiMasukCount($selectedLembagaId),
                'siswa_mutasi_keluar' => $siswaMutasiKeluarQuery->count(),
                'siswa_lulus' => $siswaLulusQuery->count(),
                'karyawan' => $karyawanQuery->count(),
                'karyawan_aktif' => $karyawanAktifQuery->count(),
                'trend_master' => $this->masterTrend($selectedLembagaId),
                'siswa_status' => $this->siswaStatusSummary($selectedLembagaId),
                'status_keluarga_labels' => [...self::FAMILY_STATUSES, self::FAMILY_STATUS_EMPTY],
                'status_keluarga_summary' => $this->familyStatusSummary($selectedLembagaId),
                'status_keluarga_per_kelas' => $this->familyStatusByClass($selectedLembagaId),
                'tahun_ajaran_options' => $academicYears,
                'tahun_ajaran_analysis' => $this->tahunAjaranAnalysis($academicYears, $selectedTahunAjaranId, true),
                'lembaga_rows' => $lembagas,
                'lembaga_belum_lengkap' => $lembagas
                    ->filter(fn ($lembaga) => $lembaga->guru_count === 0 || $lembaga->siswa_count === 0 || $lembaga->karyawan_count === 0)
                    ->count(),
            ];
        }

        $academicYears = $this->academicYears();
        $selectedTahunAjaranId = $academicYears->contains('id', $tahunAjaranId) ? $tahunAjaranId : '';

        return [
            'role' => 'admin_lembaga',
            'lembaga_nama' => $user->lembaga?->nama,
            'selected_lembaga_id' => '',
            'lembaga_options' => collect(),
            'tahun_ajaran' => TahunAjaran::query()->count(),
            'guru' => Guru::query()->count(),
            'kelas' => Kelas::query()->count(),
            'siswa' => Siswa::query()->where('status_siswa', SiswaStatus::AKTIF)->count(),
            'total_siswa' => Siswa::query()->count(),
            'siswa_mutasi_masuk' => $this->siswaMutasiMasukCount(),
            'siswa_mutasi_keluar' => Siswa::query()->where('status_siswa', SiswaStatus::MUTASI_KELUAR)->count(),
            'siswa_lulus' => Siswa::query()->where('status_siswa', SiswaStatus::LULUS)->count(),
            'karyawan' => Karyawan::query()->count(),
            'karyawan_aktif' => Karyawan::query()->where('is_active', true)->count(),
            'trend_master' => $this->masterTrend(),
            'siswa_status' => $this->siswaStatusSummary(),
            'status_keluarga_labels' => [...self::FAMILY_STATUSES, self::FAMILY_STATUS_EMPTY],
            'status_keluarga_summary' => $this->familyStatusSummary(),
            'status_keluarga_per_kelas' => $this->familyStatusByClass(),
            'tahun_ajaran_options' => $academicYears,
            'tahun_ajaran_analysis' => $this->tahunAjaranAnalysis($academicYears, $selectedTahunAjaranId),
        ];
    }

    /**
     * @return Collection<int, TahunAjaran>
     */
    private function academicYears(string $lembagaId = ''): Collection
    {
        return TahunAjaran::query()
            ->with('lembaga')
            ->when($lembagaId !== '', fn (Builder $query) => $query->where('lembaga_id', $lembagaId))
            ->orderBy('tanggal_mulai')
            ->orderBy('nama')
            ->get();
    }

    /**
     * @return array{labels: list<string>, series: array<string, list<int>>, max: int}
     */
    private function masterTrend(string $lembagaId = ''): array
    {
        $months = collect(range(5, 0))
            ->map(fn (int $offset) => Carbon::now()->startOfMonth()->subMonths($offset));

        $series = [
            'Siswa' => $this->monthlyCounts(Siswa::class, $months, $lembagaId),
            'Guru' => $this->monthlyCounts(Guru::class, $months, $lembagaId),
            'Karyawan' => $this->monthlyCounts(Karyawan::class, $months, $lembagaId),
        ];

        return [
            'labels' => $months->map(fn (Carbon $month) => $month->translatedFormat('M y'))->values()->all(),
            'series' => $series,
            'max' => max([1, ...collect($series)->flatten()->all()]),
        ];
    }

    /**
     * @param  class-string<Model>  $model
     * @param  \Illuminate\Support\Collection<int, Carbon>  $months
     * @return list<int>
     */
    private function monthlyCounts(string $model, $months, string $lembagaId = ''): array
    {
        return $months
            ->map(fn (Carbon $month) => $model::query()
                ->when($lembagaId !== '', fn (Builder $query) => $query->where('lembaga_id', $lembagaId))
                ->whereBetween('created_at', [$month->copy()->startOfMonth(), $month->copy()->endOfMonth()])
                ->count())
            ->values()
            ->all();
    }

    /**
     * @return array<string, int>
     */
    private function siswaStatusSummary(string $lembagaId = ''): array
    {
        return [
            SiswaStatus::AKTIF => Siswa::query()
                ->when($lembagaId !== '', fn (Builder $query) => $query->where('lembaga_id', $lembagaId))
                ->where('status_siswa', SiswaStatus::AKTIF)
                ->count(),
            SiswaStatus::MUTASI_MASUK => $this->siswaMutasiMasukCount($lembagaId),
            SiswaStatus::MUTASI_KELUAR => Siswa::query()
                ->when($lembagaId !== '', fn (Builder $query) => $query->where('lembaga_id', $lembagaId))
                ->where('status_siswa', SiswaStatus::MUTASI_KELUAR)
                ->count(),
            SiswaStatus::LULUS => Siswa::query()
                ->when($lembagaId !== '', fn (Builder $query) => $query->where('lembaga_id', $lembagaId))
                ->where('status_siswa', SiswaStatus::LULUS)
                ->count(),
            SiswaStatus::CALON => Siswa::query()
                ->when($lembagaId !== '', fn (Builder $query) => $query->where('lembaga_id', $lembagaId))
                ->where('status_siswa', SiswaStatus::CALON)
                ->count(),
        ];
    }

    /**
     * @return array<string, int>
     */
    private function familyStatusSummary(string $lembagaId = ''): array
    {
        $summary = [];

        foreach (self::FAMILY_STATUSES as $status) {
            $summary[$status] = $this->activeStudentFamilyQuery($lembagaId)
                ->where('status_keluarga', $status)
                ->count();
        }

        $summary[self::FAMILY_STATUS_EMPTY] = $this->activeStudentFamilyQuery($lembagaId)
            ->where(function (Builder $query): void {
                $query->whereNull('status_keluarga')
                    ->orWhere('status_keluarga', '');
            })
            ->count();

        return $summary;
    }

    /**
     * @return Collection<int, array{
     *     lembaga_nama: string,
     *     tahun_ajaran_nama: string,
     *     kelas_nama: string,
     *     total: int,
     *     statuses: array<string, int>
     * }>
     */
    private function familyStatusByClass(string $lembagaId = ''): Collection
    {
        return Kelas::query()
            ->with(['lembaga', 'tahunAjaran'])
            ->withCount([
                'siswa as active_students_count' => fn (Builder $query) => $query
                    ->where('status_siswa', SiswaStatus::AKTIF),
                'siswa as yatim_count' => fn (Builder $query) => $this->familyStatusCountConstraint($query, 'Yatim'),
                'siswa as piatu_count' => fn (Builder $query) => $this->familyStatusCountConstraint($query, 'Piatu'),
                'siswa as yatim_piatu_count' => fn (Builder $query) => $this->familyStatusCountConstraint($query, 'Yatim Piatu'),
                'siswa as anak_guru_count' => fn (Builder $query) => $this->familyStatusCountConstraint($query, 'Anak Guru, Staff, dan Karyawan'),
                'siswa as family_empty_count' => fn (Builder $query) => $query
                    ->where('status_siswa', SiswaStatus::AKTIF)
                    ->where(function (Builder $inner): void {
                        $inner->whereNull('status_keluarga')
                            ->orWhere('status_keluarga', '');
                    }),
            ])
            ->when($lembagaId !== '', fn (Builder $query) => $query->where('lembaga_id', $lembagaId))
            ->orderBy(
                Lembaga::query()
                    ->select('nama')
                    ->whereColumn('lembaga.id', 'kelas.lembaga_id')
                    ->limit(1)
            )
            ->orderBy(
                TahunAjaran::query()
                    ->select('tanggal_mulai')
                    ->whereColumn('tahun_ajaran.id', 'kelas.tahun_ajaran_id')
                    ->limit(1)
            )
            ->orderBy('nama')
            ->get()
            ->map(fn (Kelas $kelas): array => [
                'lembaga_nama' => $kelas->lembaga?->nama ?? '-',
                'tahun_ajaran_nama' => $kelas->tahunAjaran?->nama ?? '-',
                'kelas_nama' => $kelas->nama,
                'total' => (int) $kelas->active_students_count,
                'statuses' => [
                    'Yatim' => (int) $kelas->yatim_count,
                    'Piatu' => (int) $kelas->piatu_count,
                    'Yatim Piatu' => (int) $kelas->yatim_piatu_count,
                    'Anak Guru, Staff, dan Karyawan' => (int) $kelas->anak_guru_count,
                    self::FAMILY_STATUS_EMPTY => (int) $kelas->family_empty_count,
                ],
            ]);
    }

    private function activeStudentFamilyQuery(string $lembagaId = ''): Builder
    {
        return Siswa::query()
            ->when($lembagaId !== '', fn (Builder $query) => $query->where('lembaga_id', $lembagaId))
            ->where('status_siswa', SiswaStatus::AKTIF);
    }

    private function familyStatusCountConstraint(Builder $query, string $status): Builder
    {
        return $query
            ->where('status_siswa', SiswaStatus::AKTIF)
            ->where('status_keluarga', $status);
    }

    /**
     * @param  Collection<int, TahunAjaran>  $years
     * @return array{
     *     selected_id: string,
     *     mode: 'comparison'|'single',
     *     labels: list<string>,
     *     series: array<string, list<int>>,
     *     max: int,
     *     selected_summary: array{label: string, total: int, aktif: int, mutasi_masuk: int, mutasi_keluar: int, lulus: int}|null
     * }
     */
    private function tahunAjaranAnalysis(Collection $years, string $selectedId, bool $includeLembaga = false): array
    {
        $selected = $selectedId !== '' ? $years->firstWhere('id', $selectedId) : null;
        $targetYears = $selected instanceof TahunAjaran ? collect([$selected]) : $years;

        $summaries = $targetYears
            ->map(fn (TahunAjaran $year) => $this->studentYearSummary($year, $includeLembaga))
            ->values();

        $series = [
            'Total' => $summaries->pluck('total')->all(),
            'Aktif' => $summaries->pluck('aktif')->all(),
            'Mutasi masuk' => $summaries->pluck('mutasi_masuk')->all(),
            'Mutasi keluar' => $summaries->pluck('mutasi_keluar')->all(),
            'Lulus' => $summaries->pluck('lulus')->all(),
        ];

        return [
            'selected_id' => $selectedId,
            'mode' => $selected instanceof TahunAjaran ? 'single' : 'comparison',
            'labels' => $summaries->pluck('label')->all(),
            'series' => $series,
            'max' => max([1, ...collect($series)->flatten()->all()]),
            'selected_summary' => $selected instanceof TahunAjaran ? $summaries->first() : null,
        ];
    }

    /**
     * @return array{label: string, total: int, aktif: int, mutasi_masuk: int, mutasi_keluar: int, lulus: int}
     */
    private function studentYearSummary(TahunAjaran $year, bool $includeLembaga = false): array
    {
        return [
            'label' => $includeLembaga && $year->lembaga !== null
                ? "{$year->nama} · {$year->lembaga->nama}"
                : $year->nama,
            'total' => $this->countStudentsForYear($year),
            'aktif' => Siswa::query()
                ->where('status_siswa', SiswaStatus::AKTIF)
                ->where('tahun_ajaran_id', $year->id)
                ->whereDoesntHave('penempatans', fn (Builder $placement) => $placement
                    ->where('tahun_ajaran_id', $year->id)
                    ->where('jenis', PenempatanJenis::MUTASI_MASUK))
                ->count(),
            'mutasi_masuk' => Siswa::query()
                ->where(function (Builder $query) use ($year): void {
                    $query->where(function (Builder $inner) use ($year): void {
                        $inner->where('status_siswa', SiswaStatus::MUTASI_MASUK)
                            ->where('tahun_ajaran_id', $year->id);
                    })->orWhereHas('penempatans', fn (Builder $placement) => $placement
                        ->where('tahun_ajaran_id', $year->id)
                        ->where('jenis', PenempatanJenis::MUTASI_MASUK));
                })
                ->count(),
            'mutasi_keluar' => $this->countStudentsWithStatusInYear($year, SiswaStatus::MUTASI_KELUAR),
            'lulus' => $this->countStudentsWithStatusInYear($year, SiswaStatus::LULUS),
        ];
    }

    private function countStudentsForYear(TahunAjaran $year): int
    {
        return Siswa::query()
            ->where(function (Builder $query) use ($year): void {
                $query->where('tahun_ajaran_id', $year->id)
                    ->orWhereHas('penempatans', fn (Builder $placement) => $placement->where('tahun_ajaran_id', $year->id))
                    ->orWhere(function (Builder $inner) use ($year): void {
                        $inner->whereIn('status_siswa', [SiswaStatus::MUTASI_KELUAR, SiswaStatus::LULUS])
                            ->where('lembaga_id', $year->lembaga_id)
                            ->whereBetween('status_at', [$year->tanggal_mulai, $year->tanggal_selesai]);
                    });
            })
            ->count();
    }

    private function countStudentsWithStatusInYear(TahunAjaran $year, string $status): int
    {
        return Siswa::query()
            ->where('status_siswa', $status)
            ->where(function (Builder $query) use ($year): void {
                $query->where(function (Builder $inner) use ($year): void {
                    $inner->where('lembaga_id', $year->lembaga_id)
                        ->whereBetween('status_at', [$year->tanggal_mulai, $year->tanggal_selesai]);
                })
                    ->orWhereHas('penempatans', fn (Builder $placement) => $placement->where('tahun_ajaran_id', $year->id));
            })
            ->count();
    }

    private function siswaMutasiMasukCount(string $lembagaId = ''): int
    {
        return Siswa::query()
            ->when($lembagaId !== '', fn (Builder $query) => $query->where('lembaga_id', $lembagaId))
            ->where(function (Builder $query): void {
                $query->where('status_siswa', SiswaStatus::MUTASI_MASUK)
                    ->orWhereHas('penempatans', fn (Builder $placement) => $placement->where('jenis', PenempatanJenis::MUTASI_MASUK));
            })
            ->count();
    }
}
