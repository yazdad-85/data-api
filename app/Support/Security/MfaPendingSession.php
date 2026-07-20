<?php

namespace App\Support\Security;

use App\Models\User;
use Illuminate\Support\Facades\Session;

class MfaPendingSession
{
    public const USER_ID = 'auth.mfa_pending_user_id';

    public const EXPIRES_AT = 'auth.mfa_pending_expires_at';

    public static function put(User $user): void
    {
        Session::put(self::USER_ID, $user->id);
        Session::put(
            self::EXPIRES_AT,
            now()->addMinutes((int) config('security.mfa.pending_ttl_minutes', 10))->timestamp
        );
    }

    public static function clear(): void
    {
        Session::forget([self::USER_ID, self::EXPIRES_AT]);
    }

    public static function user(): ?User
    {
        $userId = Session::get(self::USER_ID);
        $expires = Session::get(self::EXPIRES_AT);

        if (! is_string($userId) || $userId === '') {
            self::clear();

            return null;
        }

        if (is_numeric($expires)) {
            $expires = (int) $expires;
        }

        if (! is_int($expires) || $expires < now()->timestamp) {
            self::clear();

            return null;
        }

        return User::query()->find($userId);
    }
}
