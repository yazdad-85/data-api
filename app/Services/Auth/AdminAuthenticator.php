<?php

namespace App\Services\Auth;

use App\Models\User;
use App\Services\AuditLogger;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;

final class AdminAuthenticator
{
    public const FAILURE_MESSAGE = 'Email atau password salah';

    public function __construct(private readonly AuditLogger $auditLogger)
    {
    }

    /**
     * @return array{ok: true, user: User}|array{ok: false, message: string, reason: string}
     */
    public function attempt(string $email, string $password, Request $request): array
    {
        $email = strtolower(trim($email));
        $user = User::query()->where('email', $email)->first();

        if ($user === null || ! Hash::check($password, $user->password)) {
            $this->auditLogger->record('auth.login', 'failed', [
                'reason' => 'invalid_credentials',
                'email' => $email,
            ], request: $request);

            return ['ok' => false, 'message' => self::FAILURE_MESSAGE, 'reason' => 'invalid_credentials'];
        }

        if (! $user->is_active) {
            $this->auditLogger->record('auth.login', 'failed', [
                'reason' => 'user_inactive',
                'email' => $email,
            ], user: $user, lembagaId: $user->lembaga_id, request: $request);

            return ['ok' => false, 'message' => self::FAILURE_MESSAGE, 'reason' => 'user_inactive'];
        }

        if ($user->isAdminLembaga()) {
            $user->loadMissing('lembaga');
            if ($user->lembaga === null || ! $user->lembaga->is_active) {
                $this->auditLogger->record('auth.login', 'failed', [
                    'reason' => 'lembaga_inactive',
                    'email' => $email,
                ], user: $user, lembagaId: $user->lembaga_id, request: $request);

                return ['ok' => false, 'message' => self::FAILURE_MESSAGE, 'reason' => 'lembaga_inactive'];
            }
        }

        return ['ok' => true, 'user' => $user];
    }
}
