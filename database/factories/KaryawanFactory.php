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
            'is_active' => true,
        ];
    }
}
