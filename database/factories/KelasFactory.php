<?php

namespace Database\Factories;

use App\Models\Kelas;
use App\Models\Lembaga;
use App\Models\TahunAjaran;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Kelas>
 */
class KelasFactory extends Factory
{
    protected $model = Kelas::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'lembaga_id' => Lembaga::factory(),
            'tahun_ajaran_id' => fn (array $attributes) => TahunAjaran::factory()->create([
                'lembaga_id' => $attributes['lembaga_id'],
            ])->id,
            'nama' => 'VII-'.fake()->unique()->randomElement(['A', 'B', 'C', 'D', 'E', 'F', 'G', 'H']),
            'tingkat' => fake()->optional()->randomElement(['7', '8', '9', '10', '11', '12']),
        ];
    }
}
