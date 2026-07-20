<?php

namespace App\Support\Authorization;

use App\Models\User;
use App\Services\AuditLogger;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Gate;

class TenantAuthorizer
{
    public function authorizeView(User $user, Model $model): void
    {
        if (! Gate::forUser($user)->allows('view', $model)) {
            app(AuditLogger::class)->record('authz.cross_tenant', 'blocked', [
                'subject_type' => $model::class,
                'subject_id' => $model->getKey(),
            ], user: $user, lembagaId: $user->lembaga_id);
            abort(403);
        }
    }
}
