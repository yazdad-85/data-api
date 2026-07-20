<?php

namespace App\Policies;

use App\Models\Lembaga;
use App\Models\User;

class LembagaPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->isSuperAdmin();
    }

    public function view(User $user, Lembaga $lembaga): bool
    {
        return $user->isSuperAdmin();
    }

    public function create(User $user): bool
    {
        return $user->isSuperAdmin();
    }

    public function update(User $user, Lembaga $lembaga): bool
    {
        return $user->isSuperAdmin();
    }

    public function activate(User $user, Lembaga $lembaga): bool
    {
        return $user->isSuperAdmin();
    }

    public function deactivate(User $user, Lembaga $lembaga): bool
    {
        return $user->isSuperAdmin();
    }
}
