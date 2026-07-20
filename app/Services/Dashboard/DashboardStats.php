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

final class DashboardStats
{
    /** @return array<string, mixed> */
    public function for(User $user): array
    {
        if ($user->isSuperAdmin()) {
            return [
                'role' => 'super_admin',
                'lembaga_aktif' => Lembaga::query()->where('is_active', true)->count(),
                'lembaga_nonaktif' => Lembaga::query()->where('is_active', false)->count(),
                'api_client_aktif' => ApiClient::query()->where('is_active', true)->whereNull('revoked_at')->count(),
                'guru' => Guru::query()->count(),
                'siswa' => Siswa::query()->count(),
                'karyawan' => Karyawan::query()->count(),
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
