<?php

namespace App\Models;

use App\Models\Concerns\BelongsToLembaga;
use Database\Factories\SiswaFactory;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\SoftDeletes;

class Siswa extends Model
{
    /** @use HasFactory<SiswaFactory> */
    use BelongsToLembaga, HasFactory, HasUuids, SoftDeletes;

    protected $table = 'siswa';

    protected $fillable = [
        'lembaga_id',
        'nis',
        'nisn',
        'nama',
        'jenis_kelamin',
        'tempat_lahir',
        'tanggal_lahir',
        'kelas_id',
        'tahun_ajaran_id',
        'email',
        'telepon',
        'alamat',
        'nama_wali',
        'telepon_wali',
        'is_active',
        'status_siswa',
        'status_at',
        'status_alasan',
        'status_asal',
        'status_tujuan',
    ];

    protected function casts(): array
    {
        return [
            'tanggal_lahir' => 'date',
            'is_active' => 'boolean',
            'status_at' => 'date',
        ];
    }

    public function lembaga(): BelongsTo
    {
        return $this->belongsTo(Lembaga::class);
    }

    public function kelas(): BelongsTo
    {
        return $this->belongsTo(Kelas::class);
    }

    public function tahunAjaran(): BelongsTo
    {
        return $this->belongsTo(TahunAjaran::class);
    }

    public function penempatans(): HasMany
    {
        return $this->hasMany(SiswaPenempatan::class);
    }

    public function penempatanAktif(): HasOne
    {
        return $this->hasOne(SiswaPenempatan::class)->whereNull('selesai_at');
    }
}
