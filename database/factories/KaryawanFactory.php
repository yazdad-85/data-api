<?php

namespace Database\Factories;

use App\Models\Karyawan;
use App\Models\Lembaga;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Karyawan>
 */
class KaryawanFactory extends Factory
{
    protected $model = Karyawan::class;

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
            'is_active' => true,
        ];
    }
}
