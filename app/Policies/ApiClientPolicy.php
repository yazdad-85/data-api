<?php

namespace App\Policies;

use App\Models\ApiClient;
use App\Models\User;

class ApiClientPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->isSuperAdmin() || $user->isAdminLembaga();
    }

    public function view(User $user, ApiClient $apiClient): bool
    {
        if ($user->isSuperAdmin()) {
            return true;
        }

        return $user->isAdminLembaga()
            && $user->lembaga_id !== null
            && hash_equals((string) $user->lembaga_id, (string) $apiClient->lembaga_id);
    }

    public function create(User $user): bool
    {
        return $user->isSuperAdmin() || $user->isAdminLembaga();
    }

    public function update(User $user, ApiClient $apiClient): bool
    {
        return $user->isSuperAdmin();
    }

    public function rotate(User $user, ApiClient $apiClient): bool
    {
        return $user->isSuperAdmin() && $apiClient->revoked_at === null && $apiClient->is_active;
    }

    public function revoke(User $user, ApiClient $apiClient): bool
    {
        return $user->isSuperAdmin() && $apiClient->revoked_at === null;
    }
}
