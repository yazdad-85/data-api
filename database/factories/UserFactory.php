<?php

namespace Database\Factories;

use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

/**
 * @extends Factory<User>
 */
class UserFactory extends Factory
{
    /**
     * The current password being used by the factory.
     */
    protected static ?string $password;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'name' => fake()->name(),
            'email' => fake()->unique()->safeEmail(),
            'email_verified_at' => now(),
            'password' => static::$password ??= Hash::make('password'),
            'role' => 'super_admin',
            'lembaga_id' => null,
            'is_active' => true,
            'remember_token' => Str::random(10),
        ];
    }

    /**
     * Indicate that the model's email address should be unverified.
     */
    public function unverified(): static
    {
        return $this->state(fn (array $attributes) => [
            'email_verified_at' => null,
        ]);
    }

    public function adminLembaga(?string $lembagaId = null): static
    {
        return $this->state(fn (array $attributes) => [
            'role' => 'admin_lembaga',
            'lembaga_id' => $lembagaId,
        ]);
    }

    public function inactive(): static
    {
        return $this->state(fn (array $attributes) => [
            'is_active' => false,
        ]);
    }

    public function withMfa(string $secret = 'JBSWY3DPEHPK3PXP', array $recoveryCodes = ['AAAA-BBBB', 'CCCC-DDDD']): static
    {
        return $this->state(fn (array $attributes) => [
            'mfa_enabled_at' => now(),
            'mfa_secret' => $secret,
            'recovery_codes_hash' => array_map(
                static fn (string $code): string => Hash::make($code),
                $recoveryCodes,
            ),
        ]);
    }
}
