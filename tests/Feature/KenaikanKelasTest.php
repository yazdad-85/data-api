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

class KenaikanKelasTest extends TestCase
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

    public function test_batch_naik_atomic_success(): void
    {
        $lembaga = Lembaga::factory()->create();
        $taLama = TahunAjaran::factory()->for($lembaga)->create(['nama' => '2025/2026']);
        $taBaru = TahunAjaran::factory()->for($lembaga)->create(['nama' => '2026/2027']);
        $kelasAsal = Kelas::factory()->for($lembaga)->create(['tahun_ajaran_id' => $taLama->id]);
        $kelasTujuan = Kelas::factory()->for($lembaga)->create(['tahun_ajaran_id' => $taBaru->id]);

        $s1 = $this->aktifInKelas($lembaga, $kelasAsal);
        $s2 = $this->aktifInKelas($lembaga, $kelasAsal);

        $rows = [
            ['siswa_id' => $s1->id, 'aksi' => 'naik'],
            ['siswa_id' => $s2->id, 'aksi' => 'naik'],
        ];

        $result = $this->service()->commit($kelasAsal, $taBaru, $kelasTujuan, $rows, Carbon::now());

        $this->assertSame(2, $result['success']);
        $this->assertSame(0, $result['failed']);
        $this->assertSame([], $result['errors']);

        foreach ([$s1, $s2] as $siswa) {
            $siswa->refresh();
            $this->assertSame(SiswaStatus::AKTIF, $siswa->status_siswa);
            $this->assertSame($kelasTujuan->id, $siswa->kelas_id);
            $this->assertSame($taBaru->id, $siswa->tahun_ajaran_id);

            $terbuka = SiswaPenempatan::withoutGlobalScopes()
                ->where('siswa_id', $siswa->id)
                ->whereNull('selesai_at')
                ->first();
            $this->assertNotNull($terbuka);
            $this->assertSame($kelasTujuan->id, $terbuka->kelas_id);
            $this->assertSame(PenempatanJenis::KENAIKAN, $terbuka->jenis);

            $this->assertSame(
                2,
                SiswaPenempatan::withoutGlobalScopes()->where('siswa_id', $siswa->id)->count()
            );
        }
    }

    public function test_batch_rollback_jika_satu_baris_invalid(): void
    {
        $lembaga = Lembaga::factory()->create();
        $taLama = TahunAjaran::factory()->for($lembaga)->create(['nama' => '2025/2026']);
        $taBaru = TahunAjaran::factory()->for($lembaga)->create(['nama' => '2026/2027']);
        $kelasAsal = Kelas::factory()->for($lembaga)->create(['tahun_ajaran_id' => $taLama->id]);
        $kelasTujuan = Kelas::factory()->for($lembaga)->create(['tahun_ajaran_id' => $taBaru->id]);

        $s1 = $this->aktifInKelas($lembaga, $kelasAsal);
        $s2 = $this->aktifInKelas($lembaga, $kelasAsal);

        // Baris kedua tidak valid (aksi tak dikenal) sehingga seluruh batch dibatalkan
        // walaupun baris pertama valid.
        $rows = [
            ['siswa_id' => $s1->id, 'aksi' => 'naik'],
            ['siswa_id' => $s2->id, 'aksi' => 'terbang'],
        ];

        $result = $this->service()->commit($kelasAsal, $taBaru, $kelasTujuan, $rows, Carbon::now());

        $this->assertSame(0, $result['success']);
        $this->assertGreaterThan(0, $result['failed']);
        $this->assertNotEmpty($result['errors']);

        // Baris valid pun tidak boleh ikut tersimpan (atomic).
        $s1->refresh();
        $this->assertSame($kelasAsal->id, $s1->kelas_id);
        $this->assertSame(SiswaStatus::AKTIF, $s1->status_siswa);
        $this->assertSame(
            1,
            SiswaPenempatan::withoutGlobalScopes()->where('siswa_id', $s1->id)->count()
        );

        $terbuka = SiswaPenempatan::withoutGlobalScopes()
            ->where('siswa_id', $s1->id)
            ->whereNull('selesai_at')
            ->first();
        $this->assertNotNull($terbuka);
        $this->assertSame($kelasAsal->id, $terbuka->kelas_id);
    }

    public function test_hanya_siswa_aktif_kelas_asal(): void
    {
        $lembaga = Lembaga::factory()->create();
        $ta = TahunAjaran::factory()->for($lembaga)->create(['nama' => '2025/2026']);
        $taBaru = TahunAjaran::factory()->for($lembaga)->create(['nama' => '2026/2027']);
        $kelasAsal = Kelas::factory()->for($lembaga)->create(['tahun_ajaran_id' => $ta->id]);
        $kelasLain = Kelas::factory()->for($lembaga)->create(['tahun_ajaran_id' => $ta->id]);
        $kelasTujuan = Kelas::factory()->for($lembaga)->create(['tahun_ajaran_id' => $taBaru->id]);

        $aktif = $this->aktifInKelas($lembaga, $kelasAsal);

        // Calon dengan snapshot kelas asal namun status bukan aktif -> tidak eligible.
        $calon = Siswa::factory()->for($lembaga)->create([
            'kelas_id' => $kelasAsal->id,
            'tahun_ajaran_id' => $ta->id,
            'status_siswa' => SiswaStatus::CALON,
            'is_active' => false,
        ]);

        // Siswa aktif tetapi di kelas lain -> tidak eligible untuk kelas asal ini.
        $aktifKelasLain = $this->aktifInKelas($lembaga, $kelasLain);

        $rows = [
            ['siswa_id' => $calon->id, 'aksi' => 'naik'],
            ['siswa_id' => $aktifKelasLain->id, 'aksi' => 'naik'],
        ];

        $result = $this->service()->commit($kelasAsal, $taBaru, $kelasTujuan, $rows, Carbon::now());

        $this->assertSame(0, $result['success']);
        $this->assertSame(2, $result['failed']);
        $this->assertCount(2, $result['errors']);
        $this->assertStringContainsString('aktif di kelas asal', $result['errors'][0]['message']);

        // Yang benar-benar eligible tidak diubah karena tidak diminta di batch.
        $aktif->refresh();
        $this->assertSame($kelasAsal->id, $aktif->kelas_id);
    }

    public function test_tinggal_tanpa_kelas_tujuan_adalah_no_op(): void
    {
        $lembaga = Lembaga::factory()->create();
        $ta = TahunAjaran::factory()->for($lembaga)->create();
        $kelasAsal = Kelas::factory()->for($lembaga)->create(['tahun_ajaran_id' => $ta->id]);

        $siswa = $this->aktifInKelas($lembaga, $kelasAsal);

        $result = $this->service()->commit($kelasAsal, null, null, [
            ['siswa_id' => $siswa->id, 'aksi' => 'tinggal'],
        ], Carbon::now());

        $this->assertSame(1, $result['success']);
        $this->assertSame(0, $result['failed']);

        $siswa->refresh();
        $this->assertSame($kelasAsal->id, $siswa->kelas_id);
        $this->assertSame(
            1,
            SiswaPenempatan::withoutGlobalScopes()->where('siswa_id', $siswa->id)->count()
        );
    }

    public function test_batch_lulus_dan_mutasi_keluar(): void
    {
        $lembaga = Lembaga::factory()->create();
        $ta = TahunAjaran::factory()->for($lembaga)->create();
        $kelasAsal = Kelas::factory()->for($lembaga)->create(['tahun_ajaran_id' => $ta->id]);

        $lulus = $this->aktifInKelas($lembaga, $kelasAsal);
        $keluar = $this->aktifInKelas($lembaga, $kelasAsal);

        $result = $this->service()->commit($kelasAsal, null, null, [
            ['siswa_id' => $lulus->id, 'aksi' => 'lulus'],
            ['siswa_id' => $keluar->id, 'aksi' => 'mutasi_keluar', 'meta' => ['alasan' => 'Pindah kota']],
        ], Carbon::now());

        $this->assertSame(2, $result['success']);
        $this->assertSame(0, $result['failed']);

        $lulus->refresh();
        $this->assertSame(SiswaStatus::LULUS, $lulus->status_siswa);
        $this->assertNull($lulus->kelas_id);
        $this->assertFalse($lulus->is_active);

        $keluar->refresh();
        $this->assertSame(SiswaStatus::MUTASI_KELUAR, $keluar->status_siswa);
        $this->assertNull($keluar->kelas_id);
        $this->assertSame('Pindah kota', $keluar->status_alasan);
    }

    public function test_super_admin_forbidden_from_kenaikan_routes(): void
    {
        $lembaga = Lembaga::factory()->create();
        $ta = TahunAjaran::factory()->for($lembaga)->create();
        $kelas = Kelas::factory()->for($lembaga)->create(['tahun_ajaran_id' => $ta->id]);
        $sa = User::factory()->create(['role' => 'super_admin', 'lembaga_id' => null]);

        $this->actingAs($sa)->get(route('admin.kelas.kenaikan.create', $kelas))->assertForbidden();
        $this->actingAs($sa)->post(route('admin.kelas.kenaikan.store', $kelas), [
            'rows' => [['siswa_id' => (string) \Illuminate\Support\Str::uuid(), 'aksi' => 'naik']],
        ])->assertForbidden();
    }

    public function test_admin_lembaga_can_view_form_with_only_aktif_siswa(): void
    {
        $lembaga = Lembaga::factory()->create();
        $admin = User::factory()->adminLembaga($lembaga->id)->create();
        $ta = TahunAjaran::factory()->for($lembaga)->create();
        $kelas = Kelas::factory()->for($lembaga)->create(['tahun_ajaran_id' => $ta->id, 'nama' => 'VII-A']);

        $aktif = $this->aktifInKelas($lembaga, $kelas);
        $aktif->update(['nama' => 'Aktif Andi']);

        $calon = Siswa::factory()->for($lembaga)->create([
            'kelas_id' => $kelas->id,
            'tahun_ajaran_id' => $ta->id,
            'status_siswa' => SiswaStatus::CALON,
            'is_active' => false,
            'nama' => 'Calon Budi',
        ]);

        $response = $this->actingAs($admin)->get(route('admin.kelas.kenaikan.create', $kelas));

        $response->assertOk();
        $response->assertSee('Aktif Andi');
        $response->assertDontSee('Calon Budi');
    }

    public function test_admin_lembaga_commits_batch_via_http(): void
    {
        $lembaga = Lembaga::factory()->create();
        $admin = User::factory()->adminLembaga($lembaga->id)->create();
        $taLama = TahunAjaran::factory()->for($lembaga)->create(['nama' => '2025/2026']);
        $taBaru = TahunAjaran::factory()->for($lembaga)->create(['nama' => '2026/2027']);
        $kelasAsal = Kelas::factory()->for($lembaga)->create(['tahun_ajaran_id' => $taLama->id]);
        $kelasTujuan = Kelas::factory()->for($lembaga)->create(['tahun_ajaran_id' => $taBaru->id]);

        $siswa = $this->aktifInKelas($lembaga, $kelasAsal);

        $response = $this->actingAs($admin)->post(route('admin.kelas.kenaikan.store', $kelasAsal), [
            'tahun_tujuan_id' => $taBaru->id,
            'kelas_tujuan_default_id' => $kelasTujuan->id,
            'rows' => [
                ['siswa_id' => $siswa->id, 'aksi' => 'naik'],
            ],
        ]);

        $response->assertRedirect(route('admin.kelas.show', $kelasAsal));

        $siswa->refresh();
        $this->assertSame($kelasTujuan->id, $siswa->kelas_id);
        $this->assertSame($taBaru->id, $siswa->tahun_ajaran_id);
    }
}
