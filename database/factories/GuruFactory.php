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
            'jenis_kelamin' => fake()->optional()->randomElement(['L', 'P']),
            'is_active' => true,
        ];
    }
}
