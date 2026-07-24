<?php

namespace Tests\Feature;

use App\Models\ApiClient;
use App\Models\Guru;
use App\Models\Lembaga;
use App\Services\Api\ApiKeyIssuer;
use App\Support\Api\ApiErrorResponse;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Testing\TestResponse;
use Tests\TestCase;

class ApiResourceSyncTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        config(['security.api_key_pepper' => 'test-pepper-not-for-production']);
        Cache::flush();
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();

        parent::tearDown();
    }

    public function test_create_update_delete_appear_in_sync(): void
    {
        ['plain' => $plain, 'lembaga' => $lembaga] = $this->makeClient();
        $since = now()->subMinute()->utc()->toIso8601String();

        $guru = Guru::factory()->create([
            'lembaga_id' => $lembaga->id,
            'nama' => 'Nama Awal',
            'email' => 'guru@example.test',
            'telepon' => '081234567890',
            'alamat' => 'Jl. Contoh 1',
        ]);
        $created = $this->sync($plain, ['since' => $since])->assertOk();
        $createdChange = $this->changeFor($created, $guru->id);
        $this->assertSame('Nama Awal', $createdChange['nama']);
        $this->assertSame('guru@example.test', $createdChange['email']);
        $this->assertSame('081234567890', $createdChange['telepon']);
        $this->assertNull($createdChange['deleted_at']);
        $this->assertArrayHasKey('changed_at', $createdChange);

        $guru->update(['nama' => 'Nama Diperbarui']);
        $updated = $this->sync($plain, ['since' => $since])->assertOk();
        $this->assertSame('Nama Diperbarui', $this->changeFor($updated, $guru->id)['nama']);

        $guru->delete();
        $deleted = $this->sync($plain, ['since' => $since])->assertOk();
        $tombstone = $this->changeFor($deleted, $guru->id);
        $this->assertSame(['id', 'deleted_at', 'changed_at'], array_keys($tombstone));
        $this->assertNotNull($tombstone['deleted_at']);
        $this->assertArrayNotHasKey('nama', $tombstone);
        $this->assertArrayNotHasKey('email', $tombstone);
        $this->assertArrayNotHasKey('telepon', $tombstone);
        $this->assertArrayNotHasKey('alamat', $tombstone);
    }

    public function test_multi_page_cursor_no_duplicates(): void
    {
        ['plain' => $plain, 'lembaga' => $lembaga] = $this->makeClient();
        $since = now()->subMinute()->utc()->toIso8601String();
        Guru::factory()->count(3)->create(['lembaga_id' => $lembaga->id]);

        $pageOne = $this->sync($plain, ['since' => $since, 'per_page' => 2])->assertOk();
        $cursor = $pageOne->json('next_cursor');
        $this->assertNotNull($cursor);

        $pageTwo = $this->sync($plain, [
            'since' => $pageOne->json('since'),
            'watermark' => $pageOne->json('watermark'),
            'cursor' => $cursor,
            'per_page' => 2,
        ])->assertOk();

        $ids = array_merge(
            array_column($pageOne->json('changes'), 'id'),
            array_column($pageTwo->json('changes'), 'id'),
        );
        $this->assertCount(3, $ids);
        $this->assertCount(3, array_unique($ids));
        $this->assertNull($pageTwo->json('next_cursor'));
    }

    public function test_update_after_watermark_excluded(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-07-24 09:00:00', 'UTC'));
        ['plain' => $plain, 'lembaga' => $lembaga] = $this->makeClient();
        $first = Guru::factory()->create(['lembaga_id' => $lembaga->id]);
        $late = Guru::factory()->create(['lembaga_id' => $lembaga->id, 'nama' => 'Sebelum Watermark']);
        $since = now()->subMinute()->toIso8601String();

        $pageOne = $this->sync($plain, ['since' => $since, 'per_page' => 1])->assertOk();
        $this->assertSame((string) $first->id, $pageOne->json('changes.0.id'));
        $this->assertNotNull($pageOne->json('next_cursor'));

        $continuationQuery = [
            'since' => $pageOne->json('since'),
            'watermark' => $pageOne->json('watermark'),
            'cursor' => $pageOne->json('next_cursor'),
            'per_page' => 1,
        ];

        $pageTwoBeforeUpdate = $this->sync($plain, $continuationQuery)->assertOk();
        $this->assertSame('Sebelum Watermark', $this->changeFor($pageTwoBeforeUpdate, $late->id)['nama']);

        Carbon::setTestNow(now()->addSecond());
        $late->update(['nama' => 'Sesudah Watermark']);

        $pageTwoAfterUpdate = $this->sync($plain, $continuationQuery)->assertOk();
        $this->assertSame([], $pageTwoAfterUpdate->json('changes'));
    }

    public function test_missing_since_returns_invalid_since(): void
    {
        ['plain' => $plain] = $this->makeClient();

        $this->sync($plain)->assertStatus(400)
            ->assertJson(['code' => ApiErrorResponse::INVALID_SINCE]);
    }

    public function test_since_in_future_returns_invalid_since(): void
    {
        ['plain' => $plain] = $this->makeClient();

        $this->sync($plain, ['since' => now()->addMinute()->utc()->toIso8601String()])
            ->assertStatus(400)
            ->assertJson(['code' => ApiErrorResponse::INVALID_SINCE]);
    }

    public function test_since_too_old_returns_since_too_old(): void
    {
        ['plain' => $plain] = $this->makeClient();

        $this->sync($plain, ['since' => now()->subDays(91)->utc()->toIso8601String()])
            ->assertStatus(400)
            ->assertJson(['code' => ApiErrorResponse::SINCE_TOO_OLD]);
    }

    public function test_cursor_without_watermark_returns_invalid_cursor(): void
    {
        ['plain' => $plain] = $this->makeClient();

        $this->sync($plain, [
            'since' => now()->subMinute()->utc()->toIso8601String(),
            'cursor' => 'garbage',
        ])->assertStatus(400)
            ->assertJson(['code' => ApiErrorResponse::INVALID_CURSOR]);
    }

    public function test_garbage_cursor_returns_invalid_cursor(): void
    {
        ['plain' => $plain] = $this->makeClient();

        $this->sync($plain, [
            'since' => now()->subMinute()->utc()->toIso8601String(),
            'watermark' => now()->utc()->toIso8601String(),
            'cursor' => 'garbage',
        ])->assertStatus(400)
            ->assertJson(['code' => ApiErrorResponse::INVALID_CURSOR]);
    }

    public function test_fields_upgrade_returns_403(): void
    {
        ['plain' => $plain] = $this->makeClient(['field_profile' => 'minimal']);

        $this->sync($plain, [
            'since' => now()->subMinute()->utc()->toIso8601String(),
            'fields' => 'contact',
        ])->assertStatus(403)
            ->assertJson(['code' => ApiErrorResponse::FORBIDDEN]);
    }

    public function test_missing_scope_returns_403(): void
    {
        ['plain' => $plain] = $this->makeClient(['scopes' => ['siswa:read']]);

        $this->sync($plain, ['since' => now()->subMinute()->utc()->toIso8601String()])
            ->assertStatus(403)
            ->assertJson(['code' => ApiErrorResponse::FORBIDDEN]);
    }

    public function test_tenant_isolation(): void
    {
        $lembagaA = Lembaga::factory()->create();
        $lembagaB = Lembaga::factory()->create();
        ['plain' => $plain] = $this->makeClient([], $lembagaA);
        $since = now()->subMinute()->utc()->toIso8601String();

        $guruA = Guru::factory()->create(['lembaga_id' => $lembagaA->id, 'nama' => 'Milik A']);
        Guru::factory()->create(['lembaga_id' => $lembagaB->id, 'nama' => 'Milik B']);

        $response = $this->sync($plain, ['since' => $since])->assertOk();
        $this->assertSame([(string) $guruA->id], array_column($response->json('changes'), 'id'));
    }

    public function test_envelope_shape(): void
    {
        ['plain' => $plain, 'lembaga' => $lembaga] = $this->makeClient();
        Guru::factory()->create(['lembaga_id' => $lembaga->id]);

        $response = $this->sync($plain, ['since' => now()->subMinute()->utc()->toIso8601String()])
            ->assertOk();

        $response->assertJsonStructure([
            'resource',
            'lembaga_id',
            'since',
            'watermark',
            'synced_at',
            'changes',
            'change_count',
            'next_cursor',
        ]);
        $response->assertJsonPath('resource', 'guru');
        $response->assertJsonPath('lembaga_id', $lembaga->id);
    }

    public function test_health_me_and_list_still_ok(): void
    {
        ['plain' => $plain, 'client' => $client, 'lembaga' => $lembaga] = $this->makeClient();
        Guru::factory()->create(['lembaga_id' => $lembaga->id]);

        $this->getJson('/api/v1/health')->assertOk()->assertExactJson(['status' => 'ok']);
        $this->getJson('/api/v1/me', ['X-API-Key' => $plain])
            ->assertOk()
            ->assertJson(['client_id' => $client->id]);
        $this->getJson('/api/v1/guru', ['X-API-Key' => $plain])
            ->assertOk()
            ->assertJsonPath('meta.total', 1);
    }

    /**
     * @param  array<string, mixed>  $clientState
     * @return array{client: ApiClient, plain: string, lembaga: Lembaga}
     */
    private function makeClient(array $clientState = [], ?Lembaga $lembaga = null): array
    {
        $lembaga ??= Lembaga::factory()->create();
        $issued = app(ApiKeyIssuer::class)->issue();

        $client = ApiClient::factory()->create(array_merge([
            'lembaga_id' => $lembaga->id,
            'api_key_prefix' => $issued['prefix'],
            'api_key_digest' => $issued['digest'],
            'scopes' => ['guru:read', 'siswa:read'],
            'field_profile' => 'contact',
        ], $clientState));

        return ['client' => $client, 'plain' => $issued['plain'], 'lembaga' => $lembaga];
    }

    /**
     * @param  array<string, mixed>  $query
     */
    private function sync(string $plain, array $query = []): TestResponse
    {
        $url = '/api/v1/guru/sync';
        if ($query !== []) {
            $url .= '?'.http_build_query($query);
        }

        return $this->getJson($url, ['X-API-Key' => $plain]);
    }

    /**
     * @return array<string, mixed>
     */
    private function changeFor(TestResponse $response, string $id): array
    {
        foreach ($response->json('changes') as $change) {
            if ($change['id'] === (string) $id) {
                return $change;
            }
        }

        $this->fail("Expected change for resource {$id} was not found.");
    }
}
