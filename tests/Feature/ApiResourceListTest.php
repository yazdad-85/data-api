<?php

namespace Tests\Feature;

use App\Models\ApiClient;
use App\Models\Guru;
use App\Models\Lembaga;
use App\Models\Siswa;
use App\Models\SiswaPenempatan;
use App\Services\Api\ApiKeyIssuer;
use App\Support\Api\ApiErrorResponse;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Tests\TestCase;

class ApiResourceListTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        config(['security.api_key_pepper' => 'test-pepper-not-for-production']);
        Cache::flush();
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

    public function test_health_and_me_still_ok(): void
    {
        ['plain' => $plain, 'client' => $client] = $this->makeClient();

        $this->getJson('/api/v1/health')->assertOk()->assertExactJson(['status' => 'ok']);
        $this->getJson('/api/v1/me', ['X-API-Key' => $plain])
            ->assertOk()
            ->assertJson(['client_id' => $client->id]);
    }

    public function test_missing_scope_returns_403(): void
    {
        ['plain' => $plain] = $this->makeClient(['scopes' => ['guru:read']]);

        $this->getJson('/api/v1/siswa', ['X-API-Key' => $plain])
            ->assertStatus(403)
            ->assertJson(['code' => ApiErrorResponse::FORBIDDEN]);
    }

    public function test_fields_upgrade_returns_403(): void
    {
        ['plain' => $plain, 'lembaga' => $lembaga] = $this->makeClient(['field_profile' => 'minimal']);
        Guru::factory()->create(['lembaga_id' => $lembaga->id]);

        $this->getJson('/api/v1/guru?fields=contact', ['X-API-Key' => $plain])
            ->assertStatus(403)
            ->assertJson(['code' => ApiErrorResponse::FORBIDDEN]);
    }

    public function test_unknown_resource_returns_404(): void
    {
        ['plain' => $plain] = $this->makeClient();

        $this->getJson('/api/v1/dosen', ['X-API-Key' => $plain])
            ->assertStatus(404);
    }

    public function test_non_get_returns_405(): void
    {
        ['plain' => $plain] = $this->makeClient();

        $this->postJson('/api/v1/guru', [], ['X-API-Key' => $plain])
            ->assertStatus(405);
    }

    public function test_guru_minimal_omits_contact_fields(): void
    {
        ['plain' => $plain, 'lembaga' => $lembaga] = $this->makeClient(['field_profile' => 'minimal']);
        Guru::factory()->create([
            'lembaga_id' => $lembaga->id,
            'email' => 'guru@example.test',
            'peg_id' => 'PEG-123',
        ]);

        $response = $this->getJson('/api/v1/guru', ['X-API-Key' => $plain])->assertOk();

        $row = $response->json('data.0');
        $this->assertArrayHasKey('niy', $row);
        $this->assertArrayNotHasKey('email', $row);
        $this->assertArrayNotHasKey('peg_id', $row);
    }

    public function test_siswa_minimal_omits_penempatan_aktif(): void
    {
        ['plain' => $plain, 'lembaga' => $lembaga] = $this->makeClient([
            'field_profile' => 'minimal',
            'scopes' => ['siswa:read'],
        ]);
        $this->makeSiswaWithPenempatan($lembaga);

        $row = $this->getJson('/api/v1/siswa', ['X-API-Key' => $plain])->assertOk()->json('data.0');

        $this->assertArrayNotHasKey('penempatan_aktif', $row);
        $this->assertArrayNotHasKey('riwayat_penempatan', $row);
        $this->assertArrayNotHasKey('email', $row);
        $this->assertArrayNotHasKey('status_keluarga', $row);
    }

    public function test_siswa_academic_includes_penempatan_aktif_not_riwayat(): void
    {
        ['plain' => $plain, 'lembaga' => $lembaga] = $this->makeClient([
            'field_profile' => 'academic',
            'scopes' => ['siswa:read'],
        ]);
        $this->makeSiswaWithPenempatan($lembaga);

        $row = $this->getJson('/api/v1/siswa', ['X-API-Key' => $plain])->assertOk()->json('data.0');

        $this->assertArrayHasKey('penempatan_aktif', $row);
        $this->assertArrayNotHasKey('riwayat_penempatan', $row);
        $this->assertNotNull($row['penempatan_aktif']);
        $this->assertSame('Yatim', $row['status_keluarga']);
        $this->assertArrayNotHasKey('nama_ayah', $row);
    }

    public function test_siswa_contact_includes_riwayat(): void
    {
        ['plain' => $plain, 'lembaga' => $lembaga] = $this->makeClient([
            'field_profile' => 'contact',
            'scopes' => ['siswa:read'],
        ]);
        $this->makeSiswaWithPenempatan($lembaga);

        $row = $this->getJson('/api/v1/siswa', ['X-API-Key' => $plain])->assertOk()->json('data.0');

        $this->assertArrayHasKey('penempatan_aktif', $row);
        $this->assertArrayHasKey('riwayat_penempatan', $row);
        $this->assertCount(2, $row['riwayat_penempatan']);
        $this->assertSame('Ayah API', $row['nama_ayah']);
        $this->assertSame('Ibu API', $row['nama_ibu']);
        $this->assertNull($row['nama_wali']);
        $this->assertSame('Ayah API', $row['nama_kontak_wali']);
    }

    public function test_siswa_contact_nama_kontak_wali_falls_back_to_ibu(): void
    {
        ['plain' => $plain, 'lembaga' => $lembaga] = $this->makeClient([
            'field_profile' => 'contact',
            'scopes' => ['siswa:read'],
        ]);
        Siswa::factory()->create([
            'lembaga_id' => $lembaga->id,
            'nama' => 'Siswa Kontak Ibu',
            'nama_wali' => null,
            'nama_ayah' => null,
            'nama_ibu' => 'Ibu API Cadangan',
        ]);

        $row = $this->getJson('/api/v1/siswa', ['X-API-Key' => $plain])->assertOk()->json('data.0');

        $this->assertNull($row['nama_wali']);
        $this->assertNull($row['nama_ayah']);
        $this->assertSame('Ibu API Cadangan', $row['nama_kontak_wali']);
    }

    public function test_per_page_clamped_to_200(): void
    {
        ['plain' => $plain, 'lembaga' => $lembaga] = $this->makeClient();
        Guru::factory()->create(['lembaga_id' => $lembaga->id]);

        $this->getJson('/api/v1/guru?per_page=500', ['X-API-Key' => $plain])
            ->assertOk()
            ->assertJsonPath('meta.per_page', 200);
    }

    public function test_include_deleted_returns_trashed(): void
    {
        ['plain' => $plain, 'lembaga' => $lembaga] = $this->makeClient();
        Guru::factory()->create(['lembaga_id' => $lembaga->id, 'nama' => 'Aktif']);
        $trashed = Guru::factory()->create(['lembaga_id' => $lembaga->id, 'nama' => 'Terhapus']);
        $trashed->delete();

        $this->getJson('/api/v1/guru', ['X-API-Key' => $plain])
            ->assertOk()
            ->assertJsonPath('meta.total', 1);

        $response = $this->getJson('/api/v1/guru?include_deleted=1', ['X-API-Key' => $plain])
            ->assertOk()
            ->assertJsonPath('meta.total', 2);

        $this->assertArrayHasKey('deleted_at', $response->json('data.0'));
    }

    public function test_active_only_filters(): void
    {
        ['plain' => $plain, 'lembaga' => $lembaga] = $this->makeClient();
        Guru::factory()->create(['lembaga_id' => $lembaga->id, 'is_active' => true]);
        Guru::factory()->create(['lembaga_id' => $lembaga->id, 'is_active' => false]);

        $this->getJson('/api/v1/guru?active_only=true', ['X-API-Key' => $plain])
            ->assertOk()
            ->assertJsonPath('meta.total', 1);
    }

    public function test_client_cannot_read_other_lembaga_data(): void
    {
        $lembagaA = Lembaga::factory()->create();
        $lembagaB = Lembaga::factory()->create();
        Guru::factory()->create(['lembaga_id' => $lembagaA->id, 'nama' => 'Milik A']);
        Guru::factory()->create(['lembaga_id' => $lembagaB->id, 'nama' => 'Milik B']);

        ['plain' => $plain] = $this->makeClient([], $lembagaA);

        $response = $this->getJson('/api/v1/guru', ['X-API-Key' => $plain])
            ->assertOk()
            ->assertJsonPath('meta.total', 1);

        $this->assertSame('Milik A', $response->json('data.0.nama'));
        $this->assertSame($lembagaA->id, $response->json('lembaga_id'));
    }

    public function test_response_envelope_shape(): void
    {
        ['plain' => $plain, 'lembaga' => $lembaga] = $this->makeClient();
        Guru::factory()->create(['lembaga_id' => $lembaga->id]);

        $response = $this->getJson('/api/v1/guru', ['X-API-Key' => $plain])->assertOk();

        $response->assertJsonStructure([
            'resource',
            'lembaga_id',
            'synced_at',
            'data',
            'meta' => ['page', 'per_page', 'total'],
        ]);
        $response->assertJsonPath('resource', 'guru');
        $response->assertJsonPath('lembaga_id', $lembaga->id);
        $this->assertMatchesRegularExpression(
            '/^\d{4}-\d{2}-\d{2}T\d{2}:\d{2}:\d{2}Z$/',
            $response->json('synced_at')
        );
    }

    private function makeSiswaWithPenempatan(Lembaga $lembaga): Siswa
    {
        $siswa = Siswa::factory()->create([
            'lembaga_id' => $lembaga->id,
            'status_keluarga' => 'Yatim',
            'nama_ayah' => 'Ayah API',
            'nama_ibu' => 'Ibu API',
        ]);

        SiswaPenempatan::factory()->create([
            'lembaga_id' => $lembaga->id,
            'siswa_id' => $siswa->id,
            'mulai_at' => now()->subDays(120)->toDateString(),
            'selesai_at' => now()->subDays(90)->toDateString(),
        ]);
        SiswaPenempatan::factory()->open()->create([
            'lembaga_id' => $lembaga->id,
            'siswa_id' => $siswa->id,
            'mulai_at' => now()->subDays(30)->toDateString(),
        ]);

        return $siswa;
    }
}
