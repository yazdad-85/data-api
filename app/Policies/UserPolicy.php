<?php

namespace App\Policies;

use App\Models\User;

class UserPolicy
{
    public function createAdminLembaga(User $actor): bool
    {
        return $actor->isSuperAdmin();
    }

    public function update(User $actor, User $subject): bool
    {
        return $actor->isSuperAdmin() && $subject->isAdminLembaga();
    }

    public function activate(User $actor, User $subject): bool
    {
        return $actor->isSuperAdmin() && $subject->isAdminLembaga();
    }

    public function deactivate(User $actor, User $subject): bool
    {
        return $actor->isSuperAdmin() && $subject->isAdminLembaga();
    }

    public function resetPassword(User $actor, User $subject): bool
    {
        return $actor->isSuperAdmin() && $subject->isAdminLembaga();
    }
}
