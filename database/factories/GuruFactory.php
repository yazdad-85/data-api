<?php

namespace Database\Factories;

use App\Models\Guru;
use App\Models\Lembaga;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Guru>
 */
class GuruFactory extends Factory
{
    protected $model = Guru::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'lembaga_id' => Lembaga::factory(),
            'nama' => fake()->name(),
            'jenis_kelamin' => fake()->randomElement(['L', 'P']),
            'tahun_masuk' => fake()->numberBetween(1990, (int) date('Y')),
            'pendidikan_terakhir' => fake()->randomElement(['SMA', 'S1', 'S2']),
            'status_sertifikasi' => fake()->randomElement(['Sudah', 'Belum']),
            'status_inpasing' => fake()->randomElement(['Sudah', 'Belum']),
            'status_menikah' => fake()->randomElement(['Sudah Menikah', 'Belum Menikah']),
            'is_active' => true,
        ];
    }
}
