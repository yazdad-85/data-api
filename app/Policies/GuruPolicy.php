<?php

namespace App\Policies;

use App\Models\Guru;
use App\Models\User;

class GuruPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->isSuperAdmin() || $user->isAdminLembaga();
    }

    public function view(User $user, Guru $guru): bool
    {
        return $user->canAccessLembaga((string) $guru->lembaga_id);
    }

    public function create(User $user): bool
    {
        return $user->isSuperAdmin() || $user->isAdminLembaga();
    }

    public function update(User $user, Guru $guru): bool
    {
        return $user->canAccessLembaga((string) $guru->lembaga_id);
    }

    public function delete(User $user, Guru $guru): bool
    {
        return $user->canAccessLembaga((string) $guru->lembaga_id);
    }
}
