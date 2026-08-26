<?php

namespace Tests\Feature;

use App\Models\Kelas;
use App\Models\Lembaga;
use App\Models\Siswa;
use App\Models\SiswaPenempatan;
use App\Models\TahunAjaran;
use App\Models\User;
use App\Services\Siswa\KenaikanKelasService;
use App\Services\Siswa\SiswaLifecycleService;
use App\Support\Master\PenempatanJenis;
use App\Support\Master\SiswaStatus;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

class KenaikanKelasBulkTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        config(['security.mfa.super_admin_required' => false]);
        $this->withoutVite();
    }

    private function service(): KenaikanKelasService
    {
        return new KenaikanKelasService(new SiswaLifecycleService);
    }

    private function aktifInKelas(Lembaga $lembaga, Kelas $kelas): Siswa
    {
        $siswa = Siswa::factory()->for($lembaga)->inKelas($kelas)->create();

        SiswaPenempatan::factory()->for($lembaga)->for($siswa)->open()->create([
            'tahun_ajaran_id' => $kelas->tahun_ajaran_id,
            'kelas_id' => $kelas->id,
            'jenis' => PenempatanJenis::AWAL,
        ]);

        return $siswa;
    }

    public function test_commit_bulk_memproses_semua_pemetaan_kelas(): void
    {
        $lembaga = Lembaga::factory()->create();
        $taLama = TahunAjaran::factory()->for($lembaga)->create(['nama' => '2025/2026']);
        $taBaru = TahunAjaran::factory()->for($lembaga)->create(['nama' => '2026/2027']);

        $kelas7a = Kelas::factory()->for($lembaga)->create(['tahun_ajaran_id' => $taLama->id, 'nama' => '7A']);
        $kelas7b = Kelas::factory()->for($lembaga)->create(['tahun_ajaran_id' => $taLama->id, 'nama' => '7B']);
        $kelas8a = Kelas::factory()->for($lembaga)->create(['tahun_ajaran_id' => $taBaru->id, 'nama' => '8A']);
        $kelas8b = Kelas::factory()->for($lembaga)->create(['tahun_ajaran_id' => $taBaru->id, 'nama' => '8B']);

        $s1 = $this->aktifInKelas($lembaga, $kelas7a);
        $s2 = $this->aktifInKelas($lembaga, $kelas7b);

        $hasil = $this->service()->commitBulk([
            ['kelas_asal' => $kelas7a, 'kelas_tujuan' => $kelas8a],
            ['kelas_asal' => $kelas7b, 'kelas_tujuan' => $kelas8b],
        ], $taBaru, Carbon::now());

        $this->assertCount(2, $hasil);
        $this->assertSame(1, $hasil[0]['success']);
        $this->assertSame(0, $hasil[0]['failed']);
        $this->assertSame(1, $hasil[1]['success']);
        $this->assertSame(0, $hasil[1]['failed']);

        $s1->refresh();
        $s2->refresh();
        $this->assertSame($kelas8a->id, $s1->kelas_id);
        $this->assertSame($kelas8b->id, $s2->kelas_id);
    }

    public function test_kegagalan_satu_kelas_tidak_membatalkan_kelas_lain(): void
    {
        $lembaga = Lembaga::factory()->create();
        $lembagaLain = Lembaga::factory()->create();
        $taLama = TahunAjaran::factory()->for($lembaga)->create();
        $taBaru = TahunAjaran::factory()->for($lembaga)->create();

        $kelasAsalValid = Kelas::factory()->for($lembaga)->create(['tahun_ajaran_id' => $taLama->id]);
        $kelasTujuanValid = Kelas::factory()->for($lembaga)->create(['tahun_ajaran_id' => $taBaru->id]);

        $kelasAsalBermasalah = Kelas::factory()->for($lembaga)->create(['tahun_ajaran_id' => $taLama->id]);
        // Kelas tujuan sengaja milik lembaga lain supaya pindahKelas() gagal (assertSameLembaga).
        $kelasTujuanBermasalah = Kelas::factory()->for($lembagaLain)->create();

        $siswaValid = $this->aktifInKelas($lembaga, $kelasAsalValid);
        $siswaBermasalah = $this->aktifInKelas($lembaga, $kelasAsalBermasalah);

        $hasil = $this->service()->commitBulk([
            ['kelas_asal' => $kelasAsalValid, 'kelas_tujuan' => $kelasTujuanValid],
            ['kelas_asal' => $kelasAsalBermasalah, 'kelas_tujuan' => $kelasTujuanBermasalah],
        ], $taBaru, Carbon::now());

        $this->assertSame(1, $hasil[0]['success']);
        $this->assertSame(0, $hasil[0]['failed']);
        $this->assertSame(0, $hasil[1]['success']);
        $this->assertSame(1, $hasil[1]['failed']);

        $siswaValid->refresh();
        $this->assertSame($kelasTujuanValid->id, $siswaValid->kelas_id);

        // Kelas yang gagal tidak berubah sama sekali (atomic per kelas).
        $siswaBermasalah->refresh();
        $this->assertSame($kelasAsalBermasalah->id, $siswaBermasalah->kelas_id);
        $this->assertSame(SiswaStatus::AKTIF, $siswaBermasalah->status_siswa);
    }

    public function test_kelas_tanpa_siswa_aktif_adalah_no_op(): void
    {
        $lembaga = Lembaga::factory()->create();
        $taLama = TahunAjaran::factory()->for($lembaga)->create();
        $taBaru = TahunAjaran::factory()->for($lembaga)->create();

        $kelasAsalKosong = Kelas::factory()->for($lembaga)->create(['tahun_ajaran_id' => $taLama->id]);
        $kelasTujuan = Kelas::factory()->for($lembaga)->create(['tahun_ajaran_id' => $taBaru->id]);

        $hasil = $this->service()->commitBulk([
            ['kelas_asal' => $kelasAsalKosong, 'kelas_tujuan' => $kelasTujuan],
        ], $taBaru, Carbon::now());

        $this->assertSame(0, $hasil[0]['success']);
        $this->assertSame(0, $hasil[0]['failed']);
        $this->assertSame([], $hasil[0]['errors']);
    }

    public function test_super_admin_forbidden_from_kenaikan_massal(): void
    {
        $sa = User::factory()->create(['role' => 'super_admin', 'lembaga_id' => null]);

        $this->actingAs($sa)->get(route('admin.kenaikan-massal.create'))->assertForbidden();
        $this->actingAs($sa)->post(route('admin.kenaikan-massal.store'), [])->assertForbidden();
    }

    public function test_view_menampilkan_empty_state_jika_tahun_tujuan_belum_punya_kelas(): void
    {
        $lembaga = Lembaga::factory()->create();
        $admin = User::factory()->adminLembaga($lembaga->id)->create();
        $taLama = TahunAjaran::factory()->for($lembaga)->create();
        $taBaru = TahunAjaran::factory()->for($lembaga)->create();
        Kelas::factory()->for($lembaga)->create(['tahun_ajaran_id' => $taLama->id]);

        $response = $this->actingAs($admin)->get(route('admin.kenaikan-massal.create', [
            'tahun_asal_id' => $taLama->id,
            'tahun_tujuan_id' => $taBaru->id,
        ]));

        $response->assertOk();
        $response->assertSee('Kelas untuk tahun ajaran tujuan belum ada');
    }

    public function test_duplikat_kelas_asal_dalam_payload_ditolak(): void
    {
        $lembaga = Lembaga::factory()->create();
        $admin = User::factory()->adminLembaga($lembaga->id)->create();
        $taLama = TahunAjaran::factory()->for($lembaga)->create();
        $taBaru = TahunAjaran::factory()->for($lembaga)->create();
        $kelasAsal = Kelas::factory()->for($lembaga)->create(['tahun_ajaran_id' => $taLama->id]);
        $kelasTujuan1 = Kelas::factory()->for($lembaga)->create(['tahun_ajaran_id' => $taBaru->id]);
        $kelasTujuan2 = Kelas::factory()->for($lembaga)->create(['tahun_ajaran_id' => $taBaru->id]);

        $response = $this->actingAs($admin)->post(route('admin.kenaikan-massal.store'), [
            'tahun_asal_id' => $taLama->id,
            'tahun_tujuan_id' => $taBaru->id,
            'mappings' => [
                ['kelas_asal_id' => $kelasAsal->id, 'kelas_tujuan_id' => $kelasTujuan1->id],
                ['kelas_asal_id' => $kelasAsal->id, 'kelas_tujuan_id' => $kelasTujuan2->id],
            ],
        ]);

        $response->assertSessionHasErrors('mappings.1.kelas_asal_id');
    }

    public function test_admin_lembaga_commits_bulk_via_http_dan_audit_log_satu_entri(): void
    {
        $lembaga = Lembaga::factory()->create();
        $admin = User::factory()->adminLembaga($lembaga->id)->create();
        $taLama = TahunAjaran::factory()->for($lembaga)->create();
        $taBaru = TahunAjaran::factory()->for($lembaga)->create();

        $kelas7a = Kelas::factory()->for($lembaga)->create(['tahun_ajaran_id' => $taLama->id, 'nama' => '7A']);
        $kelas7b = Kelas::factory()->for($lembaga)->create(['tahun_ajaran_id' => $taLama->id, 'nama' => '7B']);
        $kelas8a = Kelas::factory()->for($lembaga)->create(['tahun_ajaran_id' => $taBaru->id, 'nama' => '8A']);
        $kelas8b = Kelas::factory()->for($lembaga)->create(['tahun_ajaran_id' => $taBaru->id, 'nama' => '8B']);

        $s1 = $this->aktifInKelas($lembaga, $kelas7a);
        $s2 = $this->aktifInKelas($lembaga, $kelas7b);

        $response = $this->actingAs($admin)->post(route('admin.kenaikan-massal.store'), [
            'tahun_asal_id' => $taLama->id,
            'tahun_tujuan_id' => $taBaru->id,
            'mappings' => [
                ['kelas_asal_id' => $kelas7a->id, 'kelas_tujuan_id' => $kelas8a->id],
                ['kelas_asal_id' => $kelas7b->id, 'kelas_tujuan_id' => $kelas8b->id],
            ],
        ]);

        $response->assertRedirect(route('admin.kenaikan-massal.create', [
            'tahun_asal_id' => $taLama->id,
            'tahun_tujuan_id' => $taBaru->id,
        ]));

        $s1->refresh();
        $s2->refresh();
        $this->assertSame($kelas8a->id, $s1->kelas_id);
        $this->assertSame($kelas8b->id, $s2->kelas_id);

        $this->assertDatabaseHas('audit_logs', [
            'event' => 'siswa.kenaikan_massal',
            'result' => 'success',
        ]);
        $this->assertSame(1, \App\Models\AuditLog::where('event', 'siswa.kenaikan_massal')->count());
    }
}
