<?php

namespace App\Models;

use Database\Factories\LembagaFactory;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Lembaga extends Model
{
    /** @use HasFactory<LembagaFactory> */
    use HasFactory, HasUuids, SoftDeletes;

    protected $table = 'lembaga';

    protected $fillable = [
        'kode',
        'niy_kode',
        'nama',
        'nama_kepala',
        'jenis',
        'alamat',
        'kota',
        'provinsi',
        'telepon',
        'email',
        'kop_surat_path',
        'is_active',
    ];

    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
        ];
    }

    public function users(): HasMany
    {
        return $this->hasMany(User::class);
    }

    public function apiClients(): HasMany
    {
        return $this->hasMany(ApiClient::class);
    }

    public function tahunAjaran(): HasMany
    {
        return $this->hasMany(TahunAjaran::class);
    }

    public function guru(): HasMany
    {
        return $this->hasMany(Guru::class);
    }

    public function kelas(): HasMany
    {
        return $this->hasMany(Kelas::class);
    }

    public function siswa(): HasMany
    {
        return $this->hasMany(Siswa::class);
    }

    public function karyawan(): HasMany
    {
        return $this->hasMany(Karyawan::class);
    }
}
