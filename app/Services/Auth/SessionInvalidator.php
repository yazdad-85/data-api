<?php

namespace App\Services\Auth;

use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Session;

class SessionInvalidator
{
    public function invalidateUser(string $userId): void
    {
        if (config('session.driver') === 'database') {
            DB::table(config('session.table', 'sessions'))
                ->where('user_id', $userId)
                ->delete();
        }

        if ((string) Auth::id() === $userId) {
            Auth::logout();
            Session::invalidate();
            Session::regenerateToken();
        }
    }
}
