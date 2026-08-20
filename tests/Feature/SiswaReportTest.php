<?php

namespace Tests\Feature;

use App\Models\Kelas;
use App\Models\Lembaga;
use App\Models\Siswa;
use App\Models\SiswaPenempatan;
use App\Models\TahunAjaran;
use App\Models\User;
use App\Support\Master\PenempatanJenis;
use App\Support\Master\SiswaStatus;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SiswaReportTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        config(['security.mfa.super_admin_required' => false]);
        $this->withoutVite();
    }

    public function test_admin_lembaga_sees_only_own_siswa_report(): void
    {
        $lembagaA = Lembaga::factory()->create();
        $lembagaB = Lembaga::factory()->create();
        $adminA = User::factory()->adminLembaga($lembagaA->id)->create();

        Siswa::factory()->for($lembagaA)->create(['nama' => 'Siswa Lembaga A']);
        Siswa::factory()->for($lembagaB)->create(['nama' => 'Siswa Lembaga B']);

        $response = $this->actingAs($adminA)->get(route('admin.laporan.siswa'));

        $response->assertOk()
            ->assertSee('Laporan Siswa')
            ->assertSee('Siswa Lembaga A')
            ->assertDontSee('Siswa Lembaga B')
            ->assertSee('Total data siswa')
            ->assertSee('Mutasi keluar');
    }

    public function test_super_admin_can_view_cross_lembaga_siswa_report(): void
    {
        $superAdmin = User::factory()->create(['role' => 'super_admin', 'lembaga_id' => null]);
        $lembagaA = Lembaga::factory()->create(['nama' => 'Lembaga A']);
        $lembagaB = Lembaga::factory()->create(['nama' => 'Lembaga B']);

        Siswa::factory()->for($lembagaA)->create(['nama' => 'Siswa Super A']);
        Siswa::factory()->for($lembagaB)->mutasiKeluar()->create(['nama' => 'Siswa Super B']);

        $response = $this->actingAs($superAdmin)->get(route('admin.laporan.siswa'));

        $response->assertOk()
            ->assertSee('Siswa Super A')
            ->assertSee('Siswa Super B')
            ->assertSee('Lembaga A')
            ->assertSee('Lembaga B')
            ->assertSee('Read-only');
    }

    public function test_mutasi_masuk_filter_includes_active_students_with_mutasi_placement(): void
    {
        $lembaga = Lembaga::factory()->create();
        $admin = User::factory()->adminLembaga($lembaga->id)->create();
        $tahunAjaran = TahunAjaran::factory()->for($lembaga)->create(['nama' => '2026/2027']);
        $kelas = Kelas::factory()->for($lembaga)->create([
            'tahun_ajaran_id' => $tahunAjaran->id,
            'nama' => 'VIII-A',
        ]);

        $mutasiAktif = Siswa::factory()->for($lembaga)->inKelas($kelas)->create([
            'nama' => 'Siswa Mutasi Aktif',
            'status_siswa' => SiswaStatus::AKTIF,
            'status_asal' => 'SMP Lama',
        ]);
        SiswaPenempatan::factory()->for($lembaga)->for($mutasiAktif)->open()->create([
            'tahun_ajaran_id' => $tahunAjaran->id,
            'kelas_id' => $kelas->id,
            'jenis' => PenempatanJenis::MUTASI_MASUK,
        ]);

        Siswa::factory()->for($lembaga)->inKelas($kelas)->create([
            'nama' => 'Siswa Reguler',
            'status_siswa' => SiswaStatus::AKTIF,
        ]);

        $response = $this->actingAs($admin)->get(route('admin.laporan.siswa', [
            'status_siswa' => SiswaStatus::MUTASI_MASUK,
        ]));

        $response->assertOk()
            ->assertSee('Siswa Mutasi Aktif')
            ->assertSee('Riwayat mutasi masuk')
            ->assertDontSee('Siswa Reguler');
    }
}
