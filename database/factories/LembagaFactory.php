<?php

namespace Database\Factories;

use App\Models\Lembaga;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<Lembaga> */
class LembagaFactory extends Factory
{
    protected $model = Lembaga::class;

    public function definition(): array
    {
        return [
            'kode' => strtoupper(fake()->unique()->bothify('LBG-###')),
            'niy_kode' => str_pad((string) fake()->unique()->numberBetween(1, 99), 2, '0', STR_PAD_LEFT),
            'nama' => fake()->company(),
            'jenis' => 'sekolah',
            'is_active' => true,
        ];
    }

    public function inactive(): static
    {
        return $this->state(fn (array $attributes) => [
            'is_active' => false,
        ]);
    }
}
