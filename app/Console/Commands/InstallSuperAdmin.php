<?php

namespace App\Console\Commands;

use App\Models\User;
use App\Services\AuditLogger;
use App\Support\Security\MfaCredentialFactory;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rules\Password;

class InstallSuperAdmin extends Command
{
    protected $signature = 'install:super-admin
        {--name= : Nama Super Admin pertama}
        {--email= : Email Super Admin pertama}
        {--password= : Password Super Admin pertama}';

    protected $description = 'Membuat Super Admin pertama jika belum ada.';

    public function handle(AuditLogger $auditLogger, MfaCredentialFactory $mfaFactory): int
    {
        if ($this->superAdminExists()) {
            $auditLogger->record('super_admin.bootstrap', 'blocked', [
                'reason' => 'super_admin_exists',
            ]);

            $this->error('Super Admin sudah ada. Command ini tidak boleh overwrite akun yang sudah dibuat.');

            return self::FAILURE;
        }

        $data = [
            'name' => trim((string) ($this->option('name') ?: $this->ask('Nama Super Admin'))),
            'email' => strtolower(trim((string) ($this->option('email') ?: $this->ask('Email Super Admin')))),
            'password' => (string) ($this->option('password') ?: $this->secret('Password Super Admin')),
        ];

        $validator = Validator::make($data, [
            'name' => ['required', 'string', 'max:150'],
            'email' => ['required', 'email', 'max:150', 'unique:users,email'],
            'password' => ['required', 'string', Password::min(12)],
        ]);

        if ($validator->fails()) {
            $auditLogger->record('super_admin.bootstrap', 'failed', [
                'reason' => 'validation_failed',
                'fields' => array_keys($validator->errors()->messages()),
            ]);

            foreach ($validator->errors()->all() as $message) {
                $this->error($message);
            }

            return self::FAILURE;
        }

        $mfaCredentials = $mfaFactory->generate();

        try {
            $user = DB::transaction(function () use ($data, $mfaCredentials, $auditLogger): User {
                $this->lockUsersForBootstrap();

                if ($this->superAdminExists()) {
                    $auditLogger->record('super_admin.bootstrap', 'blocked', [
                        'reason' => 'super_admin_exists_after_lock',
                    ]);

                    throw new SuperAdminAlreadyExists();
                }

                $user = User::query()->create([
                    'name' => $data['name'],
                    'email' => $data['email'],
                    'password' => $data['password'],
                    'role' => 'super_admin',
                    'lembaga_id' => null,
                    'is_active' => true,
                    'mfa_enabled_at' => now(),
                    'mfa_secret' => $mfaCredentials['secret'],
                    'recovery_codes_hash' => array_map(
                        static fn (string $code): string => Hash::make($code),
                        $mfaCredentials['recovery_codes'],
                    ),
                ]);

                $auditLogger->record('super_admin.bootstrap', 'success', [
                    'email' => $data['email'],
                    'mfa_recovery_code_count' => count($mfaCredentials['recovery_codes']),
                ], subject: $user);

                return $user;
            });
        } catch (SuperAdminAlreadyExists) {
            $this->error('Super Admin sudah ada. Command ini tidak boleh overwrite akun yang sudah dibuat.');

            return self::FAILURE;
        }

        $this->info('Super Admin pertama berhasil dibuat.');
        $this->line('ID: '.$user->id);
        $this->line('Email: '.$user->email);
        $this->warn('MFA secret dan recovery code hanya tampil sekali. Simpan di tempat aman.');
        $this->line('MFA secret: '.$mfaCredentials['secret']);
        $this->line('Recovery codes:');

        foreach ($mfaCredentials['recovery_codes'] as $code) {
            $this->line('- '.$code);
        }

        return self::SUCCESS;
    }

    private function superAdminExists(): bool
    {
        return User::query()->where('role', 'super_admin')->exists();
    }

    private function lockUsersForBootstrap(): void
    {
        if (DB::getDriverName() === 'pgsql') {
            DB::statement('LOCK TABLE users IN EXCLUSIVE MODE');
        }
    }
}

class SuperAdminAlreadyExists extends \RuntimeException
{
}
