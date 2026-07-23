<?php

namespace Tests\Feature;

use App\Models\ApiClient;
use App\Models\Lembaga;
use App\Services\Api\ApiKeyIssuer;
use App\Support\Api\ApiErrorResponse;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Tests\TestCase;

class ApiClientAuthTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        config(['security.api_key_pepper' => 'test-pepper-not-for-production']);

        // Array cache persists rate-limit hits across tests in the same process.
        Cache::flush();
    }

    /**
     * @param  array<string, mixed>  $clientState
     * @return array{client: ApiClient, plain: string}
     */
    private function makeClient(array $clientState = [], ?Lembaga $lembaga = null): array
    {
        $lembaga ??= Lembaga::factory()->create();
        $issued = app(ApiKeyIssuer::class)->issue();

        $client = ApiClient::factory()->create(array_merge([
            'lembaga_id' => $lembaga->id,
            'api_key_prefix' => $issued['prefix'],
            'api_key_digest' => $issued['digest'],
        ], $clientState));

        return ['client' => $client, 'plain' => $issued['plain']];
    }

    public function test_health_without_key_returns_ok(): void
    {
        $this->getJson('/api/v1/health')
            ->assertOk()
            ->assertExactJson(['status' => 'ok']);
    }

    public function test_me_without_key_returns_401_unauthenticated(): void
    {
        $this->getJson('/api/v1/me')
            ->assertStatus(401)
            ->assertJson(['code' => ApiErrorResponse::UNAUTHENTICATED]);
    }

    public function test_me_with_invalid_key_returns_401(): void
    {
        $this->getJson('/api/v1/me', ['X-API-Key' => 'dc_live_bogusprefix_bogussecret'])
            ->assertStatus(401)
            ->assertJson(['code' => ApiErrorResponse::UNAUTHENTICATED]);
    }

    public function test_me_with_x_api_key_succeeds(): void
    {
        ['client' => $client, 'plain' => $plain] = $this->makeClient();

        $this->getJson('/api/v1/me', ['X-API-Key' => $plain])
            ->assertOk()
            ->assertJson([
                'lembaga_id' => $client->lembaga_id,
                'client_id' => $client->id,
                'client_name' => $client->nama,
            ]);
    }

    public function test_me_with_bearer_succeeds(): void
    {
        ['client' => $client, 'plain' => $plain] = $this->makeClient();

        $this->getJson('/api/v1/me', ['Authorization' => 'Bearer '.$plain])
            ->assertOk()
            ->assertJson(['client_id' => $client->id]);
    }

    public function test_me_prefers_x_api_key_when_both_present(): void
    {
        ['client' => $client, 'plain' => $plain] = $this->makeClient();

        // Valid X-API-Key must win even though the Bearer token is garbage.
        $this->getJson('/api/v1/me', [
            'X-API-Key' => $plain,
            'Authorization' => 'Bearer dc_live_bogusprefix_bogussecret',
        ])
            ->assertOk()
            ->assertJson(['client_id' => $client->id]);
    }

    public function test_revoked_client_returns_403_api_client_inactive(): void
    {
        ['plain' => $plain] = $this->makeClient(['is_active' => true, 'revoked_at' => now()]);

        $this->getJson('/api/v1/me', ['X-API-Key' => $plain])
            ->assertStatus(403)
            ->assertJson(['code' => ApiErrorResponse::API_CLIENT_INACTIVE]);
    }

    public function test_inactive_lembaga_returns_403_lembaga_inactive(): void
    {
        $lembaga = Lembaga::factory()->inactive()->create();
        ['plain' => $plain] = $this->makeClient([], $lembaga);

        $this->getJson('/api/v1/me', ['X-API-Key' => $plain])
            ->assertStatus(403)
            ->assertJson(['code' => ApiErrorResponse::LEMBAGA_INACTIVE]);
    }

    public function test_me_scoped_to_own_lembaga_only(): void
    {
        $lembagaA = Lembaga::factory()->create();
        $lembagaB = Lembaga::factory()->create();
        ['plain' => $plain] = $this->makeClient([], $lembagaA);

        $this->getJson('/api/v1/me', ['X-API-Key' => $plain])
            ->assertOk()
            ->assertJson(['lembaga_id' => $lembagaA->id])
            ->assertJsonMissing(['lembaga_id' => $lembagaB->id]);
    }

    public function test_rate_limit_returns_429(): void
    {
        config(['security.api_rate_per_minute' => 3]);
        ['plain' => $plain] = $this->makeClient();
        $headers = ['X-API-Key' => $plain];

        for ($i = 0; $i < 3; $i++) {
            $this->getJson('/api/v1/me', $headers)->assertOk();
        }

        $this->getJson('/api/v1/me', $headers)
            ->assertStatus(429)
            ->assertJson(['code' => ApiErrorResponse::RATE_LIMITED])
            ->assertHeader('Retry-After');
    }
}
