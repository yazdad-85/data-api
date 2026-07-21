<?php

namespace Database\Factories;

use App\Models\Lembaga;
use App\Models\TahunAjaran;
use App\Support\Master\TahunAjaranNamer;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<TahunAjaran>
 */
class TahunAjaranFactory extends Factory
{
    protected $model = TahunAjaran::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $tahunMulai = fake()->numberBetween(2020, 2030);

        return [
            'lembaga_id' => Lembaga::factory(),
            'nama' => TahunAjaranNamer::fromTahunMulai($tahunMulai),
            'tanggal_mulai' => sprintf('%d-07-01', $tahunMulai),
            'tanggal_selesai' => sprintf('%d-06-30', $tahunMulai + 1),
            'is_aktif' => false,
        ];
    }

    public function aktif(): static
    {
        return $this->state(fn (array $attributes) => [
            'is_aktif' => true,
        ]);
    }
}
