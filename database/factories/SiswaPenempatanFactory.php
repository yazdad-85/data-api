<?php

namespace Database\Factories;

use App\Models\Lembaga;
use App\Models\Siswa;
use App\Models\SiswaPenempatan;
use App\Support\Master\PenempatanJenis;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<SiswaPenempatan>
 */
class SiswaPenempatanFactory extends Factory
{
    protected $model = SiswaPenempatan::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'lembaga_id' => Lembaga::factory(),
            'siswa_id' => Siswa::factory(),
            'tahun_ajaran_id' => null,
            'kelas_id' => null,
            'mulai_at' => now()->toDateString(),
            'selesai_at' => now()->subDays(30)->toDateString(),
            'jenis' => PenempatanJenis::AWAL,
            'keterangan' => null,
        ];
    }

    public function open(): static
    {
        return $this->state(fn (array $attributes) => [
            'selesai_at' => null,
        ]);
    }
}
