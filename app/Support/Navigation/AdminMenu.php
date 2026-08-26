<?php

namespace App\Support\Navigation;

use App\Models\User;
use App\Support\Master\SiswaStatus;
use Illuminate\Support\Collection;

final class AdminMenu
{
    /**
     * @return Collection<int, array{label: string, route: string, params?: array<string, string>, available: bool}>
     */
    public function forUser(User $user): Collection
    {
        if ($user->isSuperAdmin()) {
            return collect([
                ['label' => 'Dashboard', 'route' => 'admin.dashboard', 'available' => true],
                ['label' => 'Monitoring', 'route' => 'admin.monitoring.siswa', 'available' => true],
                ['label' => 'Laporan Siswa', 'route' => 'admin.laporan.siswa', 'available' => true],
                ['label' => 'SPMB', 'route' => 'admin.laporan.siswa', 'params' => ['status_siswa' => SiswaStatus::CALON], 'available' => true],
                ['label' => 'Lembaga', 'route' => 'admin.lembaga.index', 'available' => true],
                ['label' => 'Pengaturan', 'route' => 'admin.settings.show', 'available' => true],
            ]);
        }

        if ($user->isAdminLembaga()) {
            return collect([
                ['label' => 'Dashboard', 'route' => 'admin.dashboard', 'available' => true],
                ['label' => 'Tahun ajaran', 'route' => 'admin.tahun-ajaran.index', 'available' => true],
                ['label' => 'Guru', 'route' => 'admin.guru.index', 'available' => true],
                ['label' => 'Kelas', 'route' => 'admin.kelas.index', 'available' => true],
                ['label' => 'Siswa', 'route' => 'admin.siswa.index', 'available' => true],
                ['label' => 'Laporan Siswa', 'route' => 'admin.laporan.siswa', 'available' => true],
                ['label' => 'SPMB', 'route' => 'admin.spmb-distribusi.create', 'available' => true],
                ['label' => 'Karyawan', 'route' => 'admin.karyawan.index', 'available' => true],
                ['label' => 'API client', 'route' => 'admin.api-clients.index', 'available' => true],
                ['label' => 'Profil Lembaga', 'route' => 'admin.lembaga-profile.show', 'available' => true],
            ]);
        }

        return collect();
    }
}
