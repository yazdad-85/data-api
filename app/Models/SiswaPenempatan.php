<?php

namespace App\Models;

use App\Models\Concerns\BelongsToLembaga;
use Database\Factories\SiswaPenempatanFactory;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SiswaPenempatan extends Model
{
    /** @use HasFactory<SiswaPenempatanFactory> */
    use BelongsToLembaga, HasFactory, HasUuids;

    protected $table = 'siswa_penempatan';

    protected $fillable = [
        'lembaga_id',
        'siswa_id',
        'tahun_ajaran_id',
        'kelas_id',
        'mulai_at',
        'selesai_at',
        'jenis',
        'keterangan',
    ];

    protected function casts(): array
    {
        return [
            'mulai_at' => 'date',
            'selesai_at' => 'date',
        ];
    }

    public function lembaga(): BelongsTo
    {
        return $this->belongsTo(Lembaga::class);
    }

    public function siswa(): BelongsTo
    {
        return $this->belongsTo(Siswa::class);
    }

    public function kelas(): BelongsTo
    {
        return $this->belongsTo(Kelas::class);
    }

    public function tahunAjaran(): BelongsTo
    {
        return $this->belongsTo(TahunAjaran::class);
    }
}
