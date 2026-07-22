<?php

namespace Database\Factories;

use App\Models\Kelas;
use App\Models\Lembaga;
use App\Models\Siswa;
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
            'is_active' => true,
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
}
