<?php

namespace App\Models;

use Database\Factories\ApiClientFactory;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ApiClient extends Model
{
    /** @use HasFactory<ApiClientFactory> */
    use HasFactory, HasUuids;

    protected $fillable = [
        'lembaga_id',
        'nama',
        'api_key_prefix',
        'api_key_digest',
        'scopes',
        'field_profile',
        'is_active',
        'last_used_at',
        'last_used_ip',
        'revoked_at',
    ];

    protected $hidden = [
        'api_key_digest',
    ];

    protected function casts(): array
    {
        return [
            'scopes' => 'array',
            'is_active' => 'boolean',
            'last_used_at' => 'datetime',
            'revoked_at' => 'datetime',
        ];
    }

    public function lembaga(): BelongsTo
    {
        return $this->belongsTo(Lembaga::class);
    }
}
