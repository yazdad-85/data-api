<?php

namespace Tests\Feature;

use App\Models\ApiClient;
use App\Models\AuditLog;
use App\Models\Lembaga;
use App\Models\User;
use App\Services\Api\ApiKeyVerifier;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ApiClientAdminTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        config(['security.api_key_pepper' => 'test-pepper-not-for-production']);
        config(['security.mfa.super_admin_required' => false]);
    }

    private function superAdmin(): User
    {
        return User::factory()->create(['role' => 'super_admin', 'lembaga_id' => null]);
    }

    public function test_super_admin_creates_api_client_and_sees_plain_key_once(): void
    {
        $sa = $this->superAdmin();
        $lembaga = Lembaga::factory()->create();

        $response = $this->actingAs($sa)->followingRedirects()->post(
            route('admin.lembaga.api-clients.store', $lembaga),
            [
                'nama' => 'Aplikasi Rapor',
                'scopes' => ['siswa:read', 'kelas:read'],
                'field_profile' => 'academic',
            ]
        );

        $response->assertOk();

        $client = ApiClient::query()->where('nama', 'Aplikasi Rapor')->firstOrFail();
        $this->assertSame($lembaga->id, $client->lembaga_id);
        $this->assertSame(['siswa:read', 'kelas:read'], $client->scopes);
        $this->assertSame('academic', $client->field_profile);
        $this->assertTrue($client->is_active);
        $this->assertNull($client->revoked_at);

        $plain = $this->extractApiKeyFromHtml($response->getContent());
        $this->assertNotNull($plain);
        $this->assertStringStartsWith('dc_live_'.$client->api_key_prefix.'_', $plain);
        $this->assertTrue(app(ApiKeyVerifier::class)->matches($plain, $client->api_key_digest));

        $log = AuditLog::query()->where('event', 'api_client.create')->first();
        $this->assertNotNull($log);
        $this->assertSame('success', $log->result);
        $this->assertSame($client->id, $log->subject_id);

        $payload = json_encode($log->toArray());
        $this->assertIsString($payload);
        $this->assertStringNotContainsString($plain, $payload);

        $second = $this->get(route('admin.lembaga.api-clients.key-once', [$lembaga, $client]));
        $second->assertRedirect(route('admin.lembaga.show', $lembaga));
        $this->assertStringContainsString(
            'tidak tersedia',
            (string) $second->getSession()->get('status')
        );
    }

    public function test_update_metadata_does_not_change_digest_and_audits(): void
    {
        $sa = $this->superAdmin();
        $lembaga = Lembaga::factory()->create();
        $client = ApiClient::factory()->for($lembaga)->create([
            'nama' => 'Nama Lama',
            'scopes' => ['guru:read'],
            'field_profile' => 'minimal',
        ]);
        $originalDigest = $client->api_key_digest;

        $this->actingAs($sa)->put(route('admin.lembaga.api-clients.update', [$lembaga, $client]), [
            'nama' => 'Nama Baru',
            'scopes' => ['guru:read', 'kelas:read'],
            'field_profile' => 'contact',
        ])->assertRedirect(route('admin.lembaga.show', $lembaga));

        $client->refresh();
        $this->assertSame('Nama Baru', $client->nama);
        $this->assertSame(['guru:read', 'kelas:read'], $client->scopes);
        $this->assertSame('contact', $client->field_profile);
        $this->assertSame($originalDigest, $client->api_key_digest);

        $this->assertSame('success', AuditLog::query()->where('event', 'api_client.update')->value('result'));
    }

    public function test_update_revoked_api_client_is_forbidden(): void
    {
        $sa = $this->superAdmin();
        $lembaga = Lembaga::factory()->create();
        $client = ApiClient::factory()->for($lembaga)->revoked()->create();

        $this->actingAs($sa)->put(route('admin.lembaga.api-clients.update', [$lembaga, $client]), [
            'nama' => 'Coba Ubah',
            'scopes' => ['guru:read'],
            'field_profile' => 'minimal',
        ])->assertForbidden();
    }

    public function test_api_client_of_lembaga_a_cannot_be_updated_via_lembaga_b_url(): void
    {
        $sa = $this->superAdmin();
        $lembagaA = Lembaga::factory()->create();
        $lembagaB = Lembaga::factory()->create();
        $client = ApiClient::factory()->for($lembagaA)->create(['nama' => 'Milik A']);

        $this->actingAs($sa)->put(route('admin.lembaga.api-clients.update', [$lembagaB, $client]), [
            'nama' => 'Diretas',
            'scopes' => ['guru:read'],
            'field_profile' => 'minimal',
        ])->assertNotFound();

        $this->assertSame('Milik A', $client->refresh()->nama);
    }

    public function test_admin_lembaga_cannot_create_api_client(): void
    {
        $lembaga = Lembaga::factory()->create();
        $admin = User::factory()->adminLembaga($lembaga->id)->create();

        $this->actingAs($admin)->post(route('admin.lembaga.api-clients.store', $lembaga), [
            'nama' => 'Coba Buat',
            'scopes' => ['guru:read'],
            'field_profile' => 'minimal',
        ])->assertForbidden();
    }

    private function extractApiKeyFromHtml(string $html): ?string
    {
        if (preg_match('/id="api-client-key"[^>]*value="([^"]*)"/', $html, $matches) !== 1) {
            return null;
        }

        return html_entity_decode($matches[1]);
    }
}
