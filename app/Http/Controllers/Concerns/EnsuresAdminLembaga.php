<?php

namespace App\Http\Controllers\Concerns;

use App\Models\User;

trait EnsuresAdminLembaga
{
    /**
     * Guard M6a master data controllers to Admin Lembaga only.
     *
     * Super Admin gets a 403 here even though the underlying policies may
     * allow generic access, because M6a master UI is scoped to Admin Lembaga.
     */
    protected function adminLembaga(): User
    {
        $user = request()->user();

        abort_unless($user?->isAdminLembaga() && $user->lembaga_id, 403);

        return $user;
    }

    /**
     * Read-only master data pages can be opened by Super Admin or Admin Lembaga.
     * Mutating actions should keep using adminLembaga().
     */
    protected function masterDataReader(): User
    {
        $user = request()->user();

        abort_unless(
            $user?->isSuperAdmin() || ($user?->isAdminLembaga() && $user->lembaga_id),
            403
        );

        return $user;
    }
}
