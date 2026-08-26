<?php

namespace Tests\Feature;

use App\Models\Kelas;
use App\Models\Lembaga;
use App\Models\Siswa;
use App\Models\SiswaPenempatan;
use App\Models\TahunAjaran;
use App\Models\User;
use App\Services\Siswa\SiswaLifecycleService;
use App\Services\Siswa\SpmbDistribusiService;
use App\Support\Master\PenempatanJenis;
use App\Support\Master\SiswaStatus;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

class SpmbDistribusiTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        config(['security.mfa.super_admin_required' => false]);
        $this->withoutVite();
    }

    private function service(): SpmbDistribusiService
    {
        return new SpmbDistribusiService(new SiswaLifecycleService);
    }

    private function calon(Lembaga $lembaga, ?TahunAjaran $tahunAjaran = null): Siswa
    {
        return Siswa::factory()->for($lembaga)->calon()->create([
            'tahun_ajaran_id' => $tahunAjaran?->id,
        ]);
    }

    public function test_batch_distribusi_atomic_success(): void
    {
        $lembaga = Lembaga::factory()->create();
        $ta = TahunAjaran::factory()->for($lembaga)->create();
        $kelasTujuan = Kelas::factory()->for($lembaga)->create(['tahun_ajaran_id' => $ta->id]);

        $s1 = $this->calon($lembaga, $ta);
        $s2 = $this->calon($lembaga, $ta);

        $result = $this->service()->commit($lembaga->id, $kelasTujuan, [$s1->id, $s2->id], Carbon::now());

        $this->assertSame(2, $result['success']);
        $this->assertSame(0, $result['failed']);
        $this->assertSame([], $result['errors']);

        foreach ([$s1, $s2] as $siswa) {
            $siswa->refresh();
            $this->assertSame(SiswaStatus::AKTIF, $siswa->status_siswa);
            $this->assertSame($kelasTujuan->id, $siswa->kelas_id);
            $this->assertSame($ta->id, $siswa->tahun_ajaran_id);

            $terbuka = SiswaPenempatan::withoutGlobalScopes()
                ->where('siswa_id', $siswa->id)
                ->whereNull('selesai_at')
                ->first();
            $this->assertNotNull($terbuka);
            $this->assertSame($kelasTujuan->id, $terbuka->kelas_id);
            $this->assertSame(PenempatanJenis::AWAL, $terbuka->jenis);
        }
    }

    public function test_batch_rollback_jika_satu_siswa_id_tidak_eligible(): void
    {
        $lembaga = Lembaga::factory()->create();
        $lembagaLain = Lembaga::factory()->create();
        $ta = TahunAjaran::factory()->for($lembaga)->create();
        $kelasTujuan = Kelas::factory()->for($lembaga)->create(['tahun_ajaran_id' => $ta->id]);

        $valid = $this->calon($lembaga, $ta);
        $siswaLembagaLain = $this->calon($lembagaLain);

        $result = $this->service()->commit($lembaga->id, $kelasTujuan, [$valid->id, $siswaLembagaLain->id]);

        $this->assertSame(0, $result['success']);
        $this->assertSame(2, $result['failed']);
        $this->assertNotEmpty($result['errors']);

        $valid->refresh();
        $this->assertSame(SiswaStatus::CALON, $valid->status_siswa);
        $this->assertNull($valid->kelas_id);
    }

    public function test_duplicate_siswa_id_in_batch_rejected(): void
    {
        $lembaga = Lembaga::factory()->create();
        $ta = TahunAjaran::factory()->for($lembaga)->create();
        $kelasTujuan = Kelas::factory()->for($lembaga)->create(['tahun_ajaran_id' => $ta->id]);

        $siswa = $this->calon($lembaga, $ta);

        $result = $this->service()->commit($lembaga->id, $kelasTujuan, [$siswa->id, $siswa->id]);

        $this->assertSame(0, $result['success']);
        $this->assertSame(2, $result['failed']);
        $this->assertStringContainsString('lebih dari satu kali', $result['errors'][0]['message']);
    }

    public function test_only_calon_siswa_muncul_di_halaman_distribusi(): void
    {
        $lembaga = Lembaga::factory()->create();
        $admin = User::factory()->adminLembaga($lembaga->id)->create();
        $ta = TahunAjaran::factory()->for($lembaga)->create();
        $kelas = Kelas::factory()->for($lembaga)->create(['tahun_ajaran_id' => $ta->id]);

        $calon = $this->calon($lembaga, $ta);
        $calon->update(['nama' => 'Calon Budi']);

        $aktif = Siswa::factory()->for($lembaga)->inKelas($kelas)->create(['nama' => 'Aktif Andi']);

        $response = $this->actingAs($admin)->get(route('admin.spmb-distribusi.create'));

        $response->assertOk();
        $response->assertSee('Calon Budi');
        $response->assertDontSee('Aktif Andi');
    }

    public function test_calon_legacy_tanpa_tahun_ajaran_tetap_muncul_saat_filter(): void
    {
        $lembaga = Lembaga::factory()->create();
        $admin = User::factory()->adminLembaga($lembaga->id)->create();
        $ta = TahunAjaran::factory()->for($lembaga)->create();

        $legacy = $this->calon($lembaga, null);
        $legacy->update(['nama' => 'Calon Legacy']);

        $response = $this->actingAs($admin)->get(route('admin.spmb-distribusi.create', ['tahun_ajaran_id' => $ta->id]));

        $response->assertOk();
        $response->assertSee('Calon Legacy');
    }

    public function test_super_admin_forbidden_from_spmb_distribusi(): void
    {
        $lembaga = Lembaga::factory()->create();
        $ta = TahunAjaran::factory()->for($lembaga)->create();
        $kelas = Kelas::factory()->for($lembaga)->create(['tahun_ajaran_id' => $ta->id]);
        $sa = User::factory()->create(['role' => 'super_admin', 'lembaga_id' => null]);

        $this->actingAs($sa)->get(route('admin.spmb-distribusi.create'))->assertForbidden();
        $this->actingAs($sa)->post(route('admin.spmb-distribusi.store'), [
            'kelas_id' => $kelas->id,
            'siswa_ids' => [(string) \Illuminate\Support\Str::uuid()],
        ])->assertForbidden();
    }

    public function test_admin_lembaga_commits_batch_via_http(): void
    {
        $lembaga = Lembaga::factory()->create();
        $admin = User::factory()->adminLembaga($lembaga->id)->create();
        $ta = TahunAjaran::factory()->for($lembaga)->create();
        $kelasTujuan = Kelas::factory()->for($lembaga)->create(['tahun_ajaran_id' => $ta->id]);

        $s1 = $this->calon($lembaga, $ta);
        $s2 = $this->calon($lembaga, $ta);

        $response = $this->actingAs($admin)->post(route('admin.spmb-distribusi.store'), [
            'kelas_id' => $kelasTujuan->id,
            'siswa_ids' => [$s1->id, $s2->id],
        ]);

        $response->assertRedirect(route('admin.siswa.index'));

        foreach ([$s1, $s2] as $siswa) {
            $siswa->refresh();
            $this->assertSame(SiswaStatus::AKTIF, $siswa->status_siswa);
            $this->assertSame($kelasTujuan->id, $siswa->kelas_id);
        }
    }

    public function test_http_batch_rejects_siswa_id_bukan_calon(): void
    {
        $lembaga = Lembaga::factory()->create();
        $admin = User::factory()->adminLembaga($lembaga->id)->create();
        $ta = TahunAjaran::factory()->for($lembaga)->create();
        $kelas = Kelas::factory()->for($lembaga)->create(['tahun_ajaran_id' => $ta->id]);

        $aktif = Siswa::factory()->for($lembaga)->inKelas($kelas)->create();

        $response = $this->actingAs($admin)->post(route('admin.spmb-distribusi.store'), [
            'kelas_id' => $kelas->id,
            'siswa_ids' => [$aktif->id],
        ]);

        $response->assertSessionHasErrors('siswa_ids.0');
    }
}
