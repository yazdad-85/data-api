<?php

namespace Database\Factories;

use App\Models\Kelas;
use App\Models\Lembaga;
use App\Models\Siswa;
use App\Support\Master\SiswaStatus;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Siswa>
 */
class SiswaFactory extends Factory
{
    protected $model = Siswa::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'lembaga_id' => Lembaga::factory(),
            'nama' => fake()->name(),
            'nis' => fake()->unique()->numerify('NIS#####'),
            'status_keluarga' => fake()->optional()->randomElement(['Yatim', 'Piatu', 'Yatim Piatu', 'Anak Guru, Staff, dan Karyawan']),
            'is_active' => true,
            'status_siswa' => SiswaStatus::AKTIF,
        ];
    }

    public function withoutKelas(): static
    {
        return $this->state(fn (array $attributes) => [
            'kelas_id' => null,
            'tahun_ajaran_id' => null,
        ]);
    }

    public function inKelas(Kelas $kelas): static
    {
        return $this->state(fn (array $attributes) => [
            'kelas_id' => $kelas->id,
            'tahun_ajaran_id' => $kelas->tahun_ajaran_id,
            'lembaga_id' => $kelas->lembaga_id,
        ]);
    }

    public function calon(): static
    {
        return $this->state(fn (array $attributes) => [
            'status_siswa' => SiswaStatus::CALON,
            'is_active' => false,
            'kelas_id' => null,
            'tahun_ajaran_id' => null,
        ]);
    }

    public function mutasiMasuk(): static
    {
        return $this->state(fn (array $attributes) => [
            'status_siswa' => SiswaStatus::MUTASI_MASUK,
            'is_active' => false,
            'kelas_id' => null,
            'tahun_ajaran_id' => null,
        ]);
    }

    public function mutasiKeluar(): static
    {
        return $this->state(fn (array $attributes) => [
            'status_siswa' => SiswaStatus::MUTASI_KELUAR,
            'is_active' => false,
            'kelas_id' => null,
            'tahun_ajaran_id' => null,
        ]);
    }

    public function lulus(): static
    {
        return $this->state(fn (array $attributes) => [
            'status_siswa' => SiswaStatus::LULUS,
            'is_active' => false,
            'kelas_id' => null,
            'tahun_ajaran_id' => null,
        ]);
    }
}
