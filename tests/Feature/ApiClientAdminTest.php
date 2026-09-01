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

    public function test_rotate_issues_new_key_invalidates_old_key_and_keeps_same_id(): void
    {
        $sa = $this->superAdmin();
        $lembaga = Lembaga::factory()->create();

        $createResponse = $this->actingAs($sa)->followingRedirects()->post(
            route('admin.lembaga.api-clients.store', $lembaga),
            [
                'nama' => 'Aplikasi Rapor',
                'scopes' => ['siswa:read'],
                'field_profile' => 'academic',
            ]
        );
        $oldPlain = $this->extractApiKeyFromHtml($createResponse->getContent());
        $this->assertNotNull($oldPlain);

        $client = ApiClient::query()->where('nama', 'Aplikasi Rapor')->firstOrFail();
        $oldPrefix = $client->api_key_prefix;
        $oldId = $client->id;

        $rotateResponse = $this->actingAs($sa)->followingRedirects()->post(
            route('admin.lembaga.api-clients.rotate', [$lembaga, $client])
        );
        $rotateResponse->assertOk();

        $client->refresh();
        $this->assertSame($oldId, $client->id);
        $this->assertNotSame($oldPrefix, $client->api_key_prefix);
        $this->assertNull($client->revoked_at);
        $this->assertTrue($client->is_active);
        $this->assertNull($client->last_used_at);
        $this->assertNull($client->last_used_ip);

        $newPlain = $this->extractApiKeyFromHtml($rotateResponse->getContent());
        $this->assertNotNull($newPlain);
        $this->assertNotSame($oldPlain, $newPlain);

        $verifier = app(ApiKeyVerifier::class);
        $this->assertTrue($verifier->matches($newPlain, $client->api_key_digest));
        $this->assertFalse($verifier->matches($oldPlain, $client->api_key_digest));

        $log = AuditLog::query()->where('event', 'api_key.rotate')->first();
        $this->assertNotNull($log);
        $this->assertSame('success', $log->result);
        $this->assertSame($client->id, $log->subject_id);
        $this->assertSame($oldPrefix, $log->metadata['old_prefix'] ?? null);
        $this->assertSame($client->api_key_prefix, $log->metadata['new_prefix'] ?? null);

        $payload = json_encode($log->toArray());
        $this->assertIsString($payload);
        $this->assertStringNotContainsString($oldPlain, $payload);
        $this->assertStringNotContainsString($newPlain, $payload);
    }

    public function test_rotate_of_revoked_api_client_is_forbidden(): void
    {
        $sa = $this->superAdmin();
        $lembaga = Lembaga::factory()->create();
        $client = ApiClient::factory()->for($lembaga)->revoked()->create();

        $this->actingAs($sa)->post(route('admin.lembaga.api-clients.rotate', [$lembaga, $client]))
            ->assertForbidden();
    }

    public function test_rotate_of_api_client_of_lembaga_a_via_lembaga_b_url_is_not_found(): void
    {
        $sa = $this->superAdmin();
        $lembagaA = Lembaga::factory()->create();
        $lembagaB = Lembaga::factory()->create();
        $client = ApiClient::factory()->for($lembagaA)->create();
        $originalPrefix = $client->api_key_prefix;

        $this->actingAs($sa)->post(route('admin.lembaga.api-clients.rotate', [$lembagaB, $client]))
            ->assertNotFound();

        $this->assertSame($originalPrefix, $client->refresh()->api_key_prefix);
    }

    public function test_admin_lembaga_cannot_rotate_api_client(): void
    {
        $lembaga = Lembaga::factory()->create();
        $admin = User::factory()->adminLembaga($lembaga->id)->create();
        $client = ApiClient::factory()->for($lembaga)->create();

        $this->actingAs($admin)->post(route('admin.lembaga.api-clients.rotate', [$lembaga, $client]))
            ->assertForbidden();
    }

    public function test_revoke_sets_flags_and_audits(): void
    {
        $sa = $this->superAdmin();
        $lembaga = Lembaga::factory()->create();
        $client = ApiClient::factory()->for($lembaga)->create();

        $this->actingAs($sa)->post(route('admin.lembaga.api-clients.revoke', [$lembaga, $client]))
            ->assertRedirect(route('admin.lembaga.show', $lembaga));

        $client->refresh();
        $this->assertFalse($client->is_active);
        $this->assertNotNull($client->revoked_at);

        $log = AuditLog::query()->where('event', 'api_client.revoke')->first();
        $this->assertNotNull($log);
        $this->assertSame('success', $log->result);
        $this->assertSame($client->id, $log->subject_id);
    }

    public function test_revoke_of_already_revoked_api_client_is_forbidden(): void
    {
        $sa = $this->superAdmin();
        $lembaga = Lembaga::factory()->create();
        $client = ApiClient::factory()->for($lembaga)->revoked()->create();

        $this->actingAs($sa)->post(route('admin.lembaga.api-clients.revoke', [$lembaga, $client]))
            ->assertForbidden();
    }

    public function test_revoke_of_api_client_of_lembaga_a_via_lembaga_b_url_is_not_found(): void
    {
        $sa = $this->superAdmin();
        $lembagaA = Lembaga::factory()->create();
        $lembagaB = Lembaga::factory()->create();
        $client = ApiClient::factory()->for($lembagaA)->create();

        $this->actingAs($sa)->post(route('admin.lembaga.api-clients.revoke', [$lembagaB, $client]))
            ->assertNotFound();

        $this->assertTrue($client->refresh()->is_active);
        $this->assertNull($client->revoked_at);
    }

    public function test_admin_lembaga_cannot_revoke_api_client(): void
    {
        $lembaga = Lembaga::factory()->create();
        $admin = User::factory()->adminLembaga($lembaga->id)->create();
        $client = ApiClient::factory()->for($lembaga)->create();

        $this->actingAs($admin)->post(route('admin.lembaga.api-clients.revoke', [$lembaga, $client]))
            ->assertForbidden();
    }

    public function test_admin_lembaga_creates_own_api_client_and_sees_plain_key_once(): void
    {
        $lembaga = Lembaga::factory()->create();
        $admin = User::factory()->adminLembaga($lembaga->id)->create();

        $response = $this->actingAs($admin)->followingRedirects()->post(route('admin.api-clients.store'), [
            'nama' => 'Client Lembaga',
            'scopes' => ['guru:read'],
            'field_profile' => 'minimal',
        ]);

        $response->assertOk();

        $client = ApiClient::query()->where('nama', 'Client Lembaga')->firstOrFail();
        $this->assertSame($lembaga->id, $client->lembaga_id);

        $plain = $this->extractApiKeyFromHtml($response->getContent());
        $this->assertNotNull($plain);
        $this->assertStringStartsWith('dc_live_'.$client->api_key_prefix.'_', $plain);
        $this->assertTrue(app(ApiKeyVerifier::class)->matches($plain, $client->api_key_digest));
        $response->assertSee('Kembali ke API client');
        $response->assertDontSee('Kembali ke detail lembaga');
    }

    public function test_admin_lembaga_cannot_create_api_client_for_other_lembaga(): void
    {
        $lembaga = Lembaga::factory()->create();
        $otherLembaga = Lembaga::factory()->create();
        $admin = User::factory()->adminLembaga($lembaga->id)->create();

        $this->actingAs($admin)->post(route('admin.lembaga.api-clients.store', $otherLembaga), [
            'nama' => 'Coba Buat',
            'scopes' => ['guru:read'],
            'field_profile' => 'minimal',
        ])->assertForbidden();

        $this->assertFalse(ApiClient::query()->where('nama', 'Coba Buat')->exists());
    }

    public function test_generated_key_flash_does_not_leak_to_other_client_when_key_once_skipped(): void
    {
        $sa = $this->superAdmin();
        $lembagaA = Lembaga::factory()->create();
        $lembagaB = Lembaga::factory()->create();

        $this->actingAs($sa)->post(route('admin.lembaga.api-clients.store', $lembagaA), [
            'nama' => 'Client A',
            'scopes' => ['guru:read'],
            'field_profile' => 'minimal',
        ])->assertRedirect();

        $clientA = ApiClient::query()->where('nama', 'Client A')->firstOrFail();

        $this->actingAs($sa)->post(route('admin.lembaga.api-clients.store', $lembagaB), [
            'nama' => 'Client B',
            'scopes' => ['guru:read'],
            'field_profile' => 'minimal',
        ])->assertRedirect();

        $keyOnceForA = $this->actingAs($sa)->get(route('admin.lembaga.api-clients.key-once', [$lembagaA, $clientA]));

        $keyOnceForA->assertRedirect(route('admin.lembaga.show', $lembagaA));
        $this->assertStringContainsString(
            'tidak tersedia',
            (string) $keyOnceForA->getSession()->get('status')
        );
    }

    public function test_admin_lembaga_sees_own_api_clients_including_revoked_status(): void
    {
        $lembaga = Lembaga::factory()->create();
        $otherLembaga = Lembaga::factory()->create();
        $admin = User::factory()->adminLembaga($lembaga->id)->create();

        $ownClient = ApiClient::factory()->for($lembaga)->create(['nama' => 'Client Sendiri']);
        $ownRevokedClient = ApiClient::factory()->for($lembaga)->revoked()->create(['nama' => 'Client Dicabut']);
        $otherClient = ApiClient::factory()->for($otherLembaga)->create(['nama' => 'Client Lembaga Lain']);

        $response = $this->actingAs($admin)->get(route('admin.api-clients.index'));

        $response->assertOk();
        $response->assertSee('Client Sendiri');
        $response->assertSee('Client Dicabut');
        $response->assertSee('Dicabut');
        $response->assertDontSee('Client Lembaga Lain');

        $this->assertNotNull($ownClient);
        $this->assertNotNull($otherClient);
    }

    public function test_super_admin_sees_all_api_clients_and_can_filter_by_lembaga(): void
    {
        $sa = $this->superAdmin();
        $lembagaA = Lembaga::factory()->create(['nama' => 'Lembaga API A']);
        $lembagaB = Lembaga::factory()->create(['nama' => 'Lembaga API B']);

        ApiClient::factory()->for($lembagaA)->create(['nama' => 'Client A']);
        ApiClient::factory()->for($lembagaB)->create(['nama' => 'Client B']);

        $this->actingAs($sa)
            ->get(route('admin.api-clients.index'))
            ->assertOk()
            ->assertSee('Tambah API client')
            ->assertSee('Lembaga API A')
            ->assertSee('Lembaga API B')
            ->assertSee('Client A')
            ->assertSee('Client B');

        $this->actingAs($sa)
            ->get(route('admin.api-clients.index', ['lembaga_id' => $lembagaA->id]))
            ->assertOk()
            ->assertSee('value="'.$lembagaA->id.'" selected', false)
            ->assertSee('Client A')
            ->assertDontSee('Client B');
    }

    public function test_super_admin_creates_api_client_from_global_index_for_selected_lembaga(): void
    {
        $sa = $this->superAdmin();
        $lembaga = Lembaga::factory()->create(['nama' => 'Lembaga Terpilih']);

        $response = $this->actingAs($sa)->followingRedirects()->post(route('admin.api-clients.store'), [
            'lembaga_id' => $lembaga->id,
            'nama' => 'Client Pusat',
            'scopes' => ['siswa:read', 'kelas:read'],
            'field_profile' => 'academic',
        ]);

        $response->assertOk();

        $client = ApiClient::query()->where('nama', 'Client Pusat')->firstOrFail();
        $this->assertSame($lembaga->id, $client->lembaga_id);
        $this->assertSame(['siswa:read', 'kelas:read'], $client->scopes);
        $response->assertSee('Kembali ke detail lembaga');
    }

    private function extractApiKeyFromHtml(string $html): ?string
    {
        if (preg_match('/id="api-client-key"[^>]*value="([^"]*)"/', $html, $matches) !== 1) {
            return null;
        }

        return html_entity_decode($matches[1]);
    }
}
