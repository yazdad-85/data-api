<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

#[Fillable([
    'name',
    'email',
    'password',
    'role',
    'lembaga_id',
    'is_active',
    'mfa_enabled_at',
    'mfa_secret',
    'recovery_codes_hash',
])]
#[Hidden(['password', 'remember_token', 'mfa_secret', 'recovery_codes_hash'])]
class User extends Authenticatable
{
    /** @use HasFactory<UserFactory> */
    use HasFactory, HasUuids, Notifiable;

    public function lembaga(): BelongsTo
    {
        return $this->belongsTo(Lembaga::class);
    }

    public function auditLogs(): HasMany
    {
        return $this->hasMany(AuditLog::class);
    }

    public function isSuperAdmin(): bool
    {
        return $this->role === 'super_admin';
    }

    public function isAdminLembaga(): bool
    {
        return $this->role === 'admin_lembaga';
    }

    public function canAccessLembaga(string $lembagaId): bool
    {
        if ($this->isSuperAdmin()) {
            return true;
        }

        return $this->isAdminLembaga()
            && $this->lembaga_id !== null
            && hash_equals((string) $this->lembaga_id, $lembagaId);
    }

    public function hasMfaEnabled(): bool
    {
        return $this->mfa_enabled_at !== null && filled($this->mfa_secret);
    }

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'is_active' => 'boolean',
            'mfa_enabled_at' => 'datetime',
            'mfa_secret' => 'encrypted',
            'recovery_codes_hash' => 'array',
            'password' => 'hashed',
        ];
    }
}
