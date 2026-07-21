<?php

namespace App\Models;

use App\Models\Concerns\BelongsToLembaga;
use Database\Factories\KaryawanFactory;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class Karyawan extends Model
{
    /** @use HasFactory<KaryawanFactory> */
    use BelongsToLembaga, HasFactory, HasUuids, SoftDeletes;

    protected $table = 'karyawan';

    protected $fillable = [
        'lembaga_id',
        'nik_pegawai',
        'nama',
        'jenis_kelamin',
        'jabatan',
        'email',
        'telepon',
        'alamat',
        'is_active',
    ];

    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
        ];
    }

    public function lembaga(): BelongsTo
    {
        return $this->belongsTo(Lembaga::class);
    }
}
