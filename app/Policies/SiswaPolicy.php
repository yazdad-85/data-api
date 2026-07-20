<?php

namespace App\Policies;

use App\Models\Siswa;
use App\Models\User;

class SiswaPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->isSuperAdmin() || $user->isAdminLembaga();
    }

    public function view(User $user, Siswa $siswa): bool
    {
        return $user->canAccessLembaga((string) $siswa->lembaga_id);
    }

    public function create(User $user): bool
    {
        return $user->isSuperAdmin() || $user->isAdminLembaga();
    }

    public function update(User $user, Siswa $siswa): bool
    {
        return $user->canAccessLembaga((string) $siswa->lembaga_id);
    }

    public function delete(User $user, Siswa $siswa): bool
    {
        return $user->canAccessLembaga((string) $siswa->lembaga_id);
    }
}
