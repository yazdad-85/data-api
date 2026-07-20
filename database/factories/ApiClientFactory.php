<?php

namespace Database\Factories;

use App\Models\ApiClient;
use App\Models\Lembaga;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/** @extends Factory<ApiClient> */
class ApiClientFactory extends Factory
{
    protected $model = ApiClient::class;

    public function definition(): array
    {
        return [
            'lembaga_id' => Lembaga::factory(),
            'nama' => fake()->company().' Client',
            'api_key_prefix' => Str::lower(Str::random(12)),
            'api_key_digest' => hash('sha256', 'test-key'),
            'scopes' => ['guru:read'],
            'field_profile' => 'minimal',
            'is_active' => true,
            'revoked_at' => null,
        ];
    }

    public function inactive(): static
    {
        return $this->state(fn (array $attributes) => [
            'is_active' => false,
        ]);
    }

    public function revoked(): static
    {
        return $this->state(fn (array $attributes) => [
            'is_active' => false,
            'revoked_at' => now(),
        ]);
    }
}
