<?php

namespace Tests\Unit;

use App\Models\ApiClient;
use App\Models\Lembaga;
use App\Services\Api\ApiClientAuthenticator;
use App\Services\Api\ApiKeyIssuer;
use App\Support\Api\ApiErrorResponse;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Tests\TestCase;

class ApiClientAuthenticatorTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        config(['security.api_key_pepper' => 'test-pepper-not-for-production']);
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

    private function requestWithKey(?string $plain): Request
    {
        $request = Request::create('/api/v1/me', 'GET');
        if ($plain !== null) {
            $request->headers->set('X-API-Key', $plain);
        }

        return $request;
    }

    public function test_authenticate_succeeds_and_updates_last_used(): void
    {
        ['client' => $client, 'plain' => $plain] = $this->makeClient();

        $request = $this->requestWithKey($plain);
        $request->server->set('REMOTE_ADDR', '203.0.113.9');

        $result = app(ApiClientAuthenticator::class)->authenticate($request);

        $this->assertTrue($result['ok']);
        $this->assertInstanceOf(ApiClient::class, $result['client']);
        $this->assertTrue($result['client']->is($client));

        $client->refresh();
        $this->assertNotNull($client->last_used_at);
        $this->assertSame('203.0.113.9', $client->last_used_ip);
    }

    public function test_no_key_returns_unauthenticated(): void
    {
        $result = app(ApiClientAuthenticator::class)->authenticate($this->requestWithKey(null));

        $this->assertFalse($result['ok']);
        $this->assertSame(ApiErrorResponse::UNAUTHENTICATED, $result['code']);
        $this->assertSame(401, $result['status']);
    }

    public function test_malformed_key_returns_unauthenticated(): void
    {
        $result = app(ApiClientAuthenticator::class)->authenticate($this->requestWithKey('not-a-valid-key'));

        $this->assertFalse($result['ok']);
        $this->assertSame(ApiErrorResponse::UNAUTHENTICATED, $result['code']);
        $this->assertSame(401, $result['status']);
    }

    public function test_unknown_prefix_returns_unauthenticated(): void
    {
        $result = app(ApiClientAuthenticator::class)
            ->authenticate($this->requestWithKey('dc_live_unknownprefix_somesecretvalue'));

        $this->assertFalse($result['ok']);
        $this->assertSame(ApiErrorResponse::UNAUTHENTICATED, $result['code']);
        $this->assertSame(401, $result['status']);
    }

    public function test_wrong_secret_returns_unauthenticated(): void
    {
        ['client' => $client] = $this->makeClient();

        $tampered = 'dc_live_'.$client->api_key_prefix.'_wrongsecretvalue';
        $result = app(ApiClientAuthenticator::class)->authenticate($this->requestWithKey($tampered));

        $this->assertFalse($result['ok']);
        $this->assertSame(ApiErrorResponse::UNAUTHENTICATED, $result['code']);
        $this->assertSame(401, $result['status']);
    }

    public function test_inactive_client_returns_api_client_inactive(): void
    {
        ['plain' => $plain] = $this->makeClient(['is_active' => false]);

        $result = app(ApiClientAuthenticator::class)->authenticate($this->requestWithKey($plain));

        $this->assertFalse($result['ok']);
        $this->assertSame(ApiErrorResponse::API_CLIENT_INACTIVE, $result['code']);
        $this->assertSame(403, $result['status']);
    }

    public function test_revoked_client_returns_api_client_inactive(): void
    {
        ['plain' => $plain] = $this->makeClient(['is_active' => true, 'revoked_at' => now()]);

        $result = app(ApiClientAuthenticator::class)->authenticate($this->requestWithKey($plain));

        $this->assertFalse($result['ok']);
        $this->assertSame(ApiErrorResponse::API_CLIENT_INACTIVE, $result['code']);
        $this->assertSame(403, $result['status']);
    }

    public function test_inactive_lembaga_returns_lembaga_inactive(): void
    {
        $lembaga = Lembaga::factory()->inactive()->create();
        ['plain' => $plain] = $this->makeClient([], $lembaga);

        $result = app(ApiClientAuthenticator::class)->authenticate($this->requestWithKey($plain));

        $this->assertFalse($result['ok']);
        $this->assertSame(ApiErrorResponse::LEMBAGA_INACTIVE, $result['code']);
        $this->assertSame(403, $result['status']);
    }
}
