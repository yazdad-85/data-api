<?php

namespace App\Models;

use App\Models\Concerns\BelongsToLembaga;
use Database\Factories\GuruFactory;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Guru extends Model
{
    /** @use HasFactory<GuruFactory> */
    use BelongsToLembaga, HasFactory, HasUuids, SoftDeletes;

    protected $table = 'guru';

    protected $fillable = [
        'lembaga_id',
        'nip',
        'nuptk',
        'nama',
        'jenis_kelamin',
        'tempat_lahir',
        'tanggal_lahir',
        'email',
        'telepon',
        'alamat',
        'status_kepegawaian',
        'is_active',
    ];

    protected function casts(): array
    {
        return [
            'tanggal_lahir' => 'date',
            'is_active' => 'boolean',
        ];
    }

    public function lembaga(): BelongsTo
    {
        return $this->belongsTo(Lembaga::class);
    }

    public function kelasWali(): HasMany
    {
        return $this->hasMany(Kelas::class, 'wali_kelas_guru_id');
    }
}
