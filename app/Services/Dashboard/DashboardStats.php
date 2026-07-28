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
                'siswa' => Siswa::query()->count(),
                'siswa_aktif' => Siswa::query()->where('status_siswa', SiswaStatus::AKTIF)->count(),
                'karyawan' => Karyawan::query()->count(),
                'karyawan_aktif' => Karyawan::query()->where('is_active', true)->count(),
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
            'siswa' => Siswa::query()->count(),
            'karyawan' => Karyawan::query()->count(),
            'urutan' => [
                ['step' => 1, 'label' => 'Tahun ajaran', 'count_key' => 'tahun_ajaran'],
                ['step' => 2, 'label' => 'Guru', 'count_key' => 'guru'],
                ['step' => 3, 'label' => 'Kelas', 'count_key' => 'kelas'],
                ['step' => 4, 'label' => 'Siswa', 'count_key' => 'siswa'],
                ['step' => 5, 'label' => 'Karyawan', 'count_key' => 'karyawan'],
            ],
        ];
    }
}
