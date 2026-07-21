<?php

namespace App\Models;

use App\Models\Concerns\BelongsToLembaga;
use Database\Factories\TahunAjaranFactory;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class TahunAjaran extends Model
{
    /** @use HasFactory<TahunAjaranFactory> */
    use BelongsToLembaga, HasFactory, HasUuids, SoftDeletes;

    protected $table = 'tahun_ajaran';

    protected $fillable = [
        'lembaga_id',
        'nama',
        'tanggal_mulai',
        'tanggal_selesai',
        'is_aktif',
    ];

    protected function casts(): array
    {
        return [
            'tanggal_mulai' => 'date',
            'tanggal_selesai' => 'date',
            'is_aktif' => 'boolean',
        ];
    }

    public function lembaga(): BelongsTo
    {
        return $this->belongsTo(Lembaga::class);
    }

    public function kelas(): HasMany
    {
        return $this->hasMany(Kelas::class);
    }

    public function siswa(): HasMany
    {
        return $this->hasMany(Siswa::class);
    }
}
