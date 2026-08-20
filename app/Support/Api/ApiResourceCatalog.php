<?php

namespace App\Support\Api;

use App\Models\Guru;
use App\Models\Karyawan;
use App\Models\Kelas;
use App\Models\Siswa;
use App\Models\TahunAjaran;

/**
 * Registry mapping API v1 resource slugs to their model, required scope,
 * active-flag column, and cumulative field lists per profile (design §6.2, §8).
 */
final class ApiResourceCatalog
{
    /**
     * @return array<string, array{
     *     model: class-string,
     *     scope: string,
     *     active_column: ?string,
     *     fields: array{minimal: list<string>, academic: list<string>, contact: list<string>},
     *     embeds: array<string, list<string>>,
     * }>
     */
    private function entries(): array
    {
        return [
            'tahun-ajaran' => [
                'model' => TahunAjaran::class,
                'scope' => 'tahun_ajaran:read',
                'active_column' => 'is_aktif',
                'fields' => [
                    'minimal' => [
                        'id', 'lembaga_id', 'nama', 'tanggal_mulai', 'tanggal_selesai',
                        'is_aktif', 'created_at', 'updated_at',
                    ],
                    'academic' => [
                        'id', 'lembaga_id', 'nama', 'tanggal_mulai', 'tanggal_selesai',
                        'is_aktif', 'created_at', 'updated_at',
                    ],
                    'contact' => [
                        'id', 'lembaga_id', 'nama', 'tanggal_mulai', 'tanggal_selesai',
                        'is_aktif', 'created_at', 'updated_at',
                    ],
                ],
                'embeds' => [],
            ],
            'guru' => [
                'model' => Guru::class,
                'scope' => 'guru:read',
                'active_column' => 'is_active',
                'fields' => [
                    'minimal' => [
                        'id', 'lembaga_id', 'niy', 'nama', 'is_active', 'created_at', 'updated_at',
                    ],
                    'academic' => [
                        'id', 'lembaga_id', 'niy', 'nama', 'is_active', 'created_at', 'updated_at',
                        'peg_id', 'tahun_masuk', 'jenis_kelamin', 'tempat_lahir', 'tanggal_lahir',
                        'status_kepegawaian', 'pendidikan_terakhir', 'instansi_pendidikan', 'jurusan',
                        'status_sertifikasi', 'status_inpasing', 'mapel_sertifikasi',
                    ],
                    'contact' => [
                        'id', 'lembaga_id', 'niy', 'nama', 'is_active', 'created_at', 'updated_at',
                        'peg_id', 'tahun_masuk', 'jenis_kelamin', 'tempat_lahir', 'tanggal_lahir',
                        'status_kepegawaian', 'nik', 'pendidikan_terakhir', 'instansi_pendidikan',
                        'jurusan', 'status_sertifikasi', 'status_inpasing', 'mapel_sertifikasi',
                        'status_menikah',
                        'email', 'telepon', 'alamat',
                    ],
                ],
                'embeds' => [],
            ],
            'kelas' => [
                'model' => Kelas::class,
                'scope' => 'kelas:read',
                'active_column' => null,
                'fields' => [
                    'minimal' => [
                        'id', 'lembaga_id', 'tahun_ajaran_id', 'nama', 'created_at', 'updated_at',
                    ],
                    'academic' => [
                        'id', 'lembaga_id', 'tahun_ajaran_id', 'nama', 'created_at', 'updated_at',
                        'tingkat', 'wali_kelas_guru_id',
                    ],
                    'contact' => [
                        'id', 'lembaga_id', 'tahun_ajaran_id', 'nama', 'created_at', 'updated_at',
                        'tingkat', 'wali_kelas_guru_id',
                    ],
                ],
                'embeds' => [],
            ],
            'siswa' => [
                'model' => Siswa::class,
                'scope' => 'siswa:read',
                'active_column' => 'is_active',
                'fields' => [
                    'minimal' => [
                        'id', 'lembaga_id', 'nis', 'nama', 'status_siswa', 'is_active',
                        'kelas_id', 'tahun_ajaran_id', 'created_at', 'updated_at',
                    ],
                    'academic' => [
                        'id', 'lembaga_id', 'nis', 'nama', 'status_siswa', 'is_active',
                        'kelas_id', 'tahun_ajaran_id', 'created_at', 'updated_at',
                        'nisn', 'jenis_kelamin', 'tempat_lahir', 'tanggal_lahir',
                        'status_at', 'status_alasan', 'status_asal', 'status_tujuan',
                    ],
                    'contact' => [
                        'id', 'lembaga_id', 'nis', 'nama', 'status_siswa', 'is_active',
                        'kelas_id', 'tahun_ajaran_id', 'created_at', 'updated_at',
                        'nisn', 'jenis_kelamin', 'tempat_lahir', 'tanggal_lahir',
                        'status_at', 'status_alasan', 'status_asal', 'status_tujuan',
                        'email', 'telepon', 'alamat', 'nama_wali', 'telepon_wali',
                    ],
                ],
                'embeds' => [
                    'academic' => ['penempatan_aktif'],
                    'contact' => ['penempatan_aktif', 'riwayat_penempatan'],
                ],
            ],
            'karyawan' => [
                'model' => Karyawan::class,
                'scope' => 'karyawan:read',
                'active_column' => 'is_active',
                'fields' => [
                    'minimal' => [
                        'id', 'lembaga_id', 'nik_pegawai', 'nama', 'is_active', 'created_at', 'updated_at',
                    ],
                    'academic' => [
                        'id', 'lembaga_id', 'nik_pegawai', 'nama', 'is_active', 'created_at', 'updated_at',
                        'tahun_masuk', 'jenis_kelamin', 'jabatan',
                    ],
                    'contact' => [
                        'id', 'lembaga_id', 'nik_pegawai', 'nama', 'is_active', 'created_at', 'updated_at',
                        'tahun_masuk', 'jenis_kelamin', 'jabatan',
                        'email', 'telepon', 'alamat',
                    ],
                ],
                'embeds' => [],
            ],
        ];
    }

    /**
     * @return array{
     *     model: class-string,
     *     scope: string,
     *     active_column: ?string,
     *     fields: array{minimal: list<string>, academic: list<string>, contact: list<string>},
     *     embeds: array<string, list<string>>,
     * }|null
     */
    public function get(string $slug): ?array
    {
        return $this->entries()[$slug] ?? null;
    }

    /**
     * @return list<string>
     */
    public function slugs(): array
    {
        return array_keys($this->entries());
    }
}
