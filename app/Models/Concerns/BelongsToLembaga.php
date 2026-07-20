<?php

namespace App\Models\Concerns;

use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Auth;

trait BelongsToLembaga
{
    public static function bootBelongsToLembaga(): void
    {
        static::addGlobalScope('lembaga', function (Builder $builder): void {
            $user = Auth::user();
            if (! $user instanceof User) {
                $builder->whereRaw('1 = 0');

                return;
            }

            if ($user->isSuperAdmin()) {
                return;
            }

            if ($user->isAdminLembaga() && $user->lembaga_id) {
                $builder->where(
                    $builder->getModel()->getTable().'.lembaga_id',
                    $user->lembaga_id
                );

                return;
            }

            $builder->whereRaw('1 = 0');
        });
    }
}
