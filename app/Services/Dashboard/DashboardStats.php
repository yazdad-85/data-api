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
use App\Support\Master\SiswaStatus;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

final class DashboardStats
{
    /** @return array<string, mixed> */
    public function for(User $user): array
    {
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
                'siswa_mutasi_masuk' => Siswa::query()->where('status_siswa', SiswaStatus::MUTASI_MASUK)->count(),
                'siswa_mutasi_keluar' => Siswa::query()->where('status_siswa', SiswaStatus::MUTASI_KELUAR)->count(),
                'siswa_lulus' => Siswa::query()->where('status_siswa', SiswaStatus::LULUS)->count(),
                'karyawan' => Karyawan::query()->count(),
                'karyawan_aktif' => Karyawan::query()->where('is_active', true)->count(),
                'trend_master' => $this->masterTrend(),
                'siswa_status' => $this->siswaStatusSummary(),
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
            'siswa_mutasi_masuk' => Siswa::query()->where('status_siswa', SiswaStatus::MUTASI_MASUK)->count(),
            'siswa_mutasi_keluar' => Siswa::query()->where('status_siswa', SiswaStatus::MUTASI_KELUAR)->count(),
            'siswa_lulus' => Siswa::query()->where('status_siswa', SiswaStatus::LULUS)->count(),
            'karyawan' => Karyawan::query()->count(),
            'karyawan_aktif' => Karyawan::query()->where('is_active', true)->count(),
            'trend_master' => $this->masterTrend(),
            'siswa_status' => $this->siswaStatusSummary(),
            'urutan' => [
                ['step' => 1, 'label' => 'Tahun ajaran', 'count_key' => 'tahun_ajaran'],
                ['step' => 2, 'label' => 'Guru', 'count_key' => 'guru'],
                ['step' => 3, 'label' => 'Kelas', 'count_key' => 'kelas'],
                ['step' => 4, 'label' => 'Siswa', 'count_key' => 'siswa'],
                ['step' => 5, 'label' => 'Karyawan', 'count_key' => 'karyawan'],
            ],
        ];
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
            SiswaStatus::MUTASI_MASUK => Siswa::query()->where('status_siswa', SiswaStatus::MUTASI_MASUK)->count(),
            SiswaStatus::MUTASI_KELUAR => Siswa::query()->where('status_siswa', SiswaStatus::MUTASI_KELUAR)->count(),
            SiswaStatus::LULUS => Siswa::query()->where('status_siswa', SiswaStatus::LULUS)->count(),
            SiswaStatus::CALON => Siswa::query()->where('status_siswa', SiswaStatus::CALON)->count(),
        ];
    }
}
