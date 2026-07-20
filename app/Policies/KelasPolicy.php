<?php

namespace App\Policies;

use App\Models\Kelas;
use App\Models\User;

class KelasPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->isSuperAdmin() || $user->isAdminLembaga();
    }

    public function view(User $user, Kelas $kelas): bool
    {
        return $user->canAccessLembaga((string) $kelas->lembaga_id);
    }

    public function create(User $user): bool
    {
        return $user->isSuperAdmin() || $user->isAdminLembaga();
    }

    public function update(User $user, Kelas $kelas): bool
    {
        return $user->canAccessLembaga((string) $kelas->lembaga_id);
    }

    public function delete(User $user, Kelas $kelas): bool
    {
        return $user->canAccessLembaga((string) $kelas->lembaga_id);
    }
}
