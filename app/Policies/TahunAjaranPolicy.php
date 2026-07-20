<?php

namespace App\Policies;

use App\Models\TahunAjaran;
use App\Models\User;

class TahunAjaranPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->isSuperAdmin() || $user->isAdminLembaga();
    }

    public function view(User $user, TahunAjaran $tahunAjaran): bool
    {
        return $user->canAccessLembaga((string) $tahunAjaran->lembaga_id);
    }

    public function create(User $user): bool
    {
        return $user->isSuperAdmin() || $user->isAdminLembaga();
    }

    public function update(User $user, TahunAjaran $tahunAjaran): bool
    {
        return $user->canAccessLembaga((string) $tahunAjaran->lembaga_id);
    }

    public function delete(User $user, TahunAjaran $tahunAjaran): bool
    {
        return $user->canAccessLembaga((string) $tahunAjaran->lembaga_id);
    }
}
