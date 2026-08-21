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
    /** @return array<string, mixed> */
    public function for(User $user, string $tahunAjaranId = ''): array
    {
        $academicYears = $this->academicYears();
        $selectedTahunAjaranId = $academicYears->contains('id', $tahunAjaranId) ? $tahunAjaranId : '';

        if ($user->isSuperAdmin()) {
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
                ->orderBy('nama')
                ->get();

            return [
                'role' => 'super_admin',
                'lembaga_aktif' => Lembaga::query()->where('is_active', true)->count(),
                'lembaga_nonaktif' => Lembaga::query()->where('is_active', false)->count(),
                'api_client_aktif' => ApiClient::query()->where('is_active', true)->whereNull('revoked_at')->count(),
                'guru' => Guru::query()->count(),
                'guru_aktif' => Guru::query()->where('is_active', true)->count(),
                'siswa' => Siswa::query()->where('status_siswa', SiswaStatus::AKTIF)->count(),
                'total_siswa' => Siswa::query()->count(),
                'siswa_aktif' => Siswa::query()->where('status_siswa', SiswaStatus::AKTIF)->count(),
                'siswa_mutasi_masuk' => $this->siswaMutasiMasukCount(),
                'siswa_mutasi_keluar' => Siswa::query()->where('status_siswa', SiswaStatus::MUTASI_KELUAR)->count(),
                'siswa_lulus' => Siswa::query()->where('status_siswa', SiswaStatus::LULUS)->count(),
                'karyawan' => Karyawan::query()->count(),
                'karyawan_aktif' => Karyawan::query()->where('is_active', true)->count(),
                'trend_master' => $this->masterTrend(),
                'siswa_status' => $this->siswaStatusSummary(),
                'tahun_ajaran_options' => $academicYears,
                'tahun_ajaran_analysis' => $this->tahunAjaranAnalysis($academicYears, $selectedTahunAjaranId, true),
                'lembaga_rows' => $lembagas,
                'lembaga_belum_lengkap' => $lembagas
                    ->filter(fn ($lembaga) => $lembaga->guru_count === 0 || $lembaga->siswa_count === 0 || $lembaga->karyawan_count === 0)
                    ->count(),
            ];
        }

        return [
            'role' => 'admin_lembaga',
            'lembaga_nama' => $user->lembaga?->nama,
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
            'tahun_ajaran_options' => $academicYears,
            'tahun_ajaran_analysis' => $this->tahunAjaranAnalysis($academicYears, $selectedTahunAjaranId),
        ];
    }

    /**
     * @return Collection<int, TahunAjaran>
     */
    private function academicYears(): Collection
    {
        return TahunAjaran::query()
            ->with('lembaga')
            ->orderBy('tanggal_mulai')
            ->orderBy('nama')
            ->get();
    }

    /**
     * @return array{labels: list<string>, series: array<string, list<int>>, max: int}
     */
    private function masterTrend(): array
    {
        $months = collect(range(5, 0))
            ->map(fn (int $offset) => Carbon::now()->startOfMonth()->subMonths($offset));

        $series = [
            'Siswa' => $this->monthlyCounts(Siswa::class, $months),
            'Guru' => $this->monthlyCounts(Guru::class, $months),
            'Karyawan' => $this->monthlyCounts(Karyawan::class, $months),
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
    private function monthlyCounts(string $model, $months): array
    {
        return $months
            ->map(fn (Carbon $month) => $model::query()
                ->whereBetween('created_at', [$month->copy()->startOfMonth(), $month->copy()->endOfMonth()])
                ->count())
            ->values()
            ->all();
    }

    /**
     * @return array<string, int>
     */
    private function siswaStatusSummary(): array
    {
        return [
            SiswaStatus::AKTIF => Siswa::query()->where('status_siswa', SiswaStatus::AKTIF)->count(),
            SiswaStatus::MUTASI_MASUK => $this->siswaMutasiMasukCount(),
            SiswaStatus::MUTASI_KELUAR => Siswa::query()->where('status_siswa', SiswaStatus::MUTASI_KELUAR)->count(),
            SiswaStatus::LULUS => Siswa::query()->where('status_siswa', SiswaStatus::LULUS)->count(),
            SiswaStatus::CALON => Siswa::query()->where('status_siswa', SiswaStatus::CALON)->count(),
        ];
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
                $query->whereBetween('status_at', [$year->tanggal_mulai, $year->tanggal_selesai])
                    ->orWhereHas('penempatans', fn (Builder $placement) => $placement->where('tahun_ajaran_id', $year->id));
            })
            ->count();
    }

    private function siswaMutasiMasukCount(): int
    {
        return Siswa::query()
            ->where(function (Builder $query): void {
                $query->where('status_siswa', SiswaStatus::MUTASI_MASUK)
                    ->orWhereHas('penempatans', fn (Builder $placement) => $placement->where('jenis', PenempatanJenis::MUTASI_MASUK));
            })
            ->count();
    }
}
