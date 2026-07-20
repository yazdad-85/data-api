<?php

namespace App\Policies;

use App\Models\Karyawan;
use App\Models\User;

class KaryawanPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->isSuperAdmin() || $user->isAdminLembaga();
    }

    public function view(User $user, Karyawan $karyawan): bool
    {
        return $user->canAccessLembaga((string) $karyawan->lembaga_id);
    }

    public function create(User $user): bool
    {
        return $user->isSuperAdmin() || $user->isAdminLembaga();
    }

    public function update(User $user, Karyawan $karyawan): bool
    {
        return $user->canAccessLembaga((string) $karyawan->lembaga_id);
    }

    public function delete(User $user, Karyawan $karyawan): bool
    {
        return $user->canAccessLembaga((string) $karyawan->lembaga_id);
    }
}
