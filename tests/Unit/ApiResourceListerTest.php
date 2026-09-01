<?php

namespace Tests\Unit;

use App\Models\ApiClient;
use App\Models\Guru;
use App\Models\Lembaga;
use App\Models\Siswa;
use App\Models\SiswaPenempatan;
use App\Services\Api\ApiResourceLister;
use App\Services\Api\ApiResourceTransformer;
use App\Support\Api\ApiResourceCatalog;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ApiResourceListerTest extends TestCase
{
    use RefreshDatabase;

    private function lister(): ApiResourceLister
    {
        return new ApiResourceLister(new ApiResourceCatalog, new ApiResourceTransformer);
    }

    private function client(Lembaga $lembaga, string $profile = 'minimal'): ApiClient
    {
        return ApiClient::factory()->create([
            'lembaga_id' => $lembaga->id,
            'field_profile' => $profile,
        ]);
    }

    public function test_filters_to_client_lembaga_only(): void
    {
        $lembagaA = Lembaga::factory()->create();
        $lembagaB = Lembaga::factory()->create();
        Guru::factory()->create(['lembaga_id' => $lembagaA->id, 'nama' => 'Ahmad']);
        Guru::factory()->create(['lembaga_id' => $lembagaB->id, 'nama' => 'Budi']);

        $result = $this->lister()->list($this->client($lembagaA), 'guru', ['fields' => 'minimal']);

        $this->assertSame(1, $result['meta']['total']);
        $this->assertSame('Ahmad', $result['data'][0]['nama']);
        $this->assertSame($lembagaA->id, $result['lembaga_id']);
        $this->assertSame('guru', $result['resource']);
    }

    public function test_soft_deleted_hidden_by_default(): void
    {
        $lembaga = Lembaga::factory()->create();
        Guru::factory()->create(['lembaga_id' => $lembaga->id, 'nama' => 'Aktif']);
        $trashed = Guru::factory()->create(['lembaga_id' => $lembaga->id, 'nama' => 'Terhapus']);
        $trashed->delete();

        $result = $this->lister()->list($this->client($lembaga), 'guru', ['fields' => 'minimal']);

        $this->assertSame(1, $result['meta']['total']);
        $this->assertSame('Aktif', $result['data'][0]['nama']);
        $this->assertArrayNotHasKey('deleted_at', $result['data'][0]);
    }

    public function test_include_deleted_returns_trashed_with_deleted_at(): void
    {
        $lembaga = Lembaga::factory()->create();
        Guru::factory()->create(['lembaga_id' => $lembaga->id, 'nama' => 'Aktif']);
        $trashed = Guru::factory()->create(['lembaga_id' => $lembaga->id, 'nama' => 'Terhapus']);
        $trashed->delete();

        $result = $this->lister()->list($this->client($lembaga), 'guru', [
            'fields' => 'minimal',
            'include_deleted' => true,
        ]);

        $this->assertSame(2, $result['meta']['total']);
        foreach ($result['data'] as $row) {
            $this->assertArrayHasKey('deleted_at', $row);
        }
        $deletedRow = collect($result['data'])->firstWhere('nama', 'Terhapus');
        $this->assertNotNull($deletedRow['deleted_at']);
        $this->assertMatchesRegularExpression('/^\d{4}-\d{2}-\d{2}T\d{2}:\d{2}:\d{2}Z$/', $deletedRow['deleted_at']);
    }

    public function test_active_only_filters_inactive(): void
    {
        $lembaga = Lembaga::factory()->create();
        Guru::factory()->create(['lembaga_id' => $lembaga->id, 'nama' => 'Aktif', 'is_active' => true]);
        Guru::factory()->create(['lembaga_id' => $lembaga->id, 'nama' => 'Nonaktif', 'is_active' => false]);

        $result = $this->lister()->list($this->client($lembaga), 'guru', [
            'fields' => 'minimal',
            'active_only' => true,
        ]);

        $this->assertSame(1, $result['meta']['total']);
        $this->assertSame('Aktif', $result['data'][0]['nama']);
    }

    public function test_per_page_clamped_to_200(): void
    {
        $lembaga = Lembaga::factory()->create();
        Guru::factory()->create(['lembaga_id' => $lembaga->id]);

        $result = $this->lister()->list($this->client($lembaga), 'guru', [
            'fields' => 'minimal',
            'per_page' => 500,
        ]);

        $this->assertSame(200, $result['meta']['per_page']);
    }

    public function test_minimal_omits_contact_fields(): void
    {
        $lembaga = Lembaga::factory()->create();
        Guru::factory()->create([
            'lembaga_id' => $lembaga->id,
            'email' => 'guru@example.test',
            'telepon' => '0800',
        ]);

        $result = $this->lister()->list($this->client($lembaga), 'guru', ['fields' => 'minimal']);

        $row = $result['data'][0];
        $this->assertArrayHasKey('nama', $row);
        $this->assertArrayNotHasKey('email', $row);
        $this->assertArrayNotHasKey('telepon', $row);
        $this->assertArrayNotHasKey('alamat', $row);
    }

    public function test_siswa_minimal_omits_embeds(): void
    {
        $lembaga = Lembaga::factory()->create();
        $this->makeSiswaWithPenempatan($lembaga);

        $result = $this->lister()->list($this->client($lembaga, 'minimal'), 'siswa', ['fields' => 'minimal']);

        $row = $result['data'][0];
        $this->assertArrayNotHasKey('penempatan_aktif', $row);
        $this->assertArrayNotHasKey('riwayat_penempatan', $row);
        $this->assertArrayNotHasKey('status_keluarga', $row);
    }

    public function test_siswa_academic_includes_penempatan_aktif_not_riwayat(): void
    {
        $lembaga = Lembaga::factory()->create();
        $this->makeSiswaWithPenempatan($lembaga);

        $result = $this->lister()->list($this->client($lembaga, 'academic'), 'siswa', ['fields' => 'academic']);

        $row = $result['data'][0];
        $this->assertArrayHasKey('penempatan_aktif', $row);
        $this->assertArrayNotHasKey('riwayat_penempatan', $row);
        $this->assertNotNull($row['penempatan_aktif']);
        $this->assertSame('Piatu', $row['status_keluarga']);
        $this->assertArrayNotHasKey('nama_ayah', $row);
        $this->assertSame(
            ['id', 'kelas_id', 'tahun_ajaran_id', 'mulai_at', 'jenis'],
            array_keys($row['penempatan_aktif'])
        );
    }

    public function test_siswa_contact_includes_riwayat(): void
    {
        $lembaga = Lembaga::factory()->create();
        $this->makeSiswaWithPenempatan($lembaga);

        $result = $this->lister()->list($this->client($lembaga, 'contact'), 'siswa', ['fields' => 'contact']);

        $row = $result['data'][0];
        $this->assertArrayHasKey('penempatan_aktif', $row);
        $this->assertArrayHasKey('riwayat_penempatan', $row);
        $this->assertCount(2, $row['riwayat_penempatan']);
        $this->assertSame('Ayah Unit', $row['nama_ayah']);
        $this->assertSame('Ibu Unit', $row['nama_ibu']);
        $this->assertNull($row['nama_wali']);
        $this->assertSame('Ayah Unit', $row['nama_kontak_wali']);
        $this->assertSame(
            ['id', 'kelas_id', 'tahun_ajaran_id', 'mulai_at', 'selesai_at', 'jenis'],
            array_keys($row['riwayat_penempatan'][0])
        );
    }

    public function test_siswa_contact_falls_back_to_ibu_when_wali_and_ayah_empty(): void
    {
        $lembaga = Lembaga::factory()->create();
        Siswa::factory()->create([
            'lembaga_id' => $lembaga->id,
            'nama' => 'Siswa Kontak Ibu',
            'nama_wali' => null,
            'nama_ayah' => ' ',
            'nama_ibu' => 'Ibu Pengganti',
        ]);

        $result = $this->lister()->list($this->client($lembaga, 'contact'), 'siswa', ['fields' => 'contact']);

        $row = $result['data'][0];
        $this->assertNull($row['nama_wali']);
        $this->assertSame(' ', $row['nama_ayah']);
        $this->assertSame('Ibu Pengganti', $row['nama_kontak_wali']);
    }

    private function makeSiswaWithPenempatan(Lembaga $lembaga): Siswa
    {
        $siswa = Siswa::factory()->create([
            'lembaga_id' => $lembaga->id,
            'status_keluarga' => 'Piatu',
            'nama_ayah' => 'Ayah Unit',
            'nama_ibu' => 'Ibu Unit',
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
