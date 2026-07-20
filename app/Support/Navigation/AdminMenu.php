<?php

namespace App\Support\Navigation;

use App\Models\User;
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
                ['label' => 'Lembaga', 'route' => 'admin.lembaga.index', 'available' => true],
                ['label' => 'API client', 'route' => 'admin.coming-soon', 'params' => ['feature' => 'api-client'], 'available' => false],
            ]);
        }

        if ($user->isAdminLembaga()) {
            return collect([
                ['label' => 'Dashboard', 'route' => 'admin.dashboard', 'available' => true],
                ['label' => 'Tahun ajaran', 'route' => 'admin.coming-soon', 'params' => ['feature' => 'tahun-ajaran'], 'available' => false],
                ['label' => 'Guru', 'route' => 'admin.coming-soon', 'params' => ['feature' => 'guru'], 'available' => false],
                ['label' => 'Kelas', 'route' => 'admin.coming-soon', 'params' => ['feature' => 'kelas'], 'available' => false],
                ['label' => 'Siswa', 'route' => 'admin.coming-soon', 'params' => ['feature' => 'siswa'], 'available' => false],
                ['label' => 'Karyawan', 'route' => 'admin.coming-soon', 'params' => ['feature' => 'karyawan'], 'available' => false],
                ['label' => 'API client', 'route' => 'admin.coming-soon', 'params' => ['feature' => 'api-client-ro'], 'available' => false],
            ]);
        }

        return collect();
    }
}
