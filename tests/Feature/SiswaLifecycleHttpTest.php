<?php

namespace Tests\Feature;

use App\Models\AuditLog;
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

class SiswaLifecycleHttpTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        config(['security.mfa.super_admin_required' => false]);
        $this->withoutVite();
    }

    private function superAdmin(): User
    {
        return User::factory()->create(['role' => 'super_admin', 'lembaga_id' => null]);
    }

    public function test_tempatkan_calon_menjadi_aktif_dengan_penempatan_awal(): void
    {
        $lembaga = Lembaga::factory()->create();
        $admin = User::factory()->adminLembaga($lembaga->id)->create();
        $tahunAjaran = TahunAjaran::factory()->for($lembaga)->create();
        $kelas = Kelas::factory()->for($lembaga)->create(['tahun_ajaran_id' => $tahunAjaran->id]);
        $siswa = Siswa::factory()->for($lembaga)->calon()->create();

        $response = $this->actingAs($admin)->post(route('admin.siswa.lifecycle.tempatkan', $siswa), [
            'kelas_id' => $kelas->id,
        ]);

        $response->assertRedirect(route('admin.siswa.show', $siswa));

        $siswa->refresh();
        $this->assertSame(SiswaStatus::AKTIF, $siswa->status_siswa);
        $this->assertTrue($siswa->is_active);
        $this->assertSame($kelas->id, $siswa->kelas_id);

        $penempatan = SiswaPenempatan::withoutGlobalScopes()
            ->where('siswa_id', $siswa->id)
            ->whereNull('selesai_at')
            ->first();
        $this->assertNotNull($penempatan);
        $this->assertSame(PenempatanJenis::AWAL, $penempatan->jenis);

        $log = AuditLog::query()->where('event', 'siswa.lifecycle.tempatkan')->first();
        $this->assertNotNull($log);
        $this->assertSame('success', $log->result);
        $this->assertArrayNotHasKey('nama', $log->metadata);
    }

    public function test_pindah_kelas_via_http(): void
    {
        $lembaga = Lembaga::factory()->create();
        $admin = User::factory()->adminLembaga($lembaga->id)->create();
        $tahunAjaran = TahunAjaran::factory()->for($lembaga)->create();
        $kelasAsal = Kelas::factory()->for($lembaga)->create(['tahun_ajaran_id' => $tahunAjaran->id]);
        $kelasTujuan = Kelas::factory()->for($lembaga)->create(['tahun_ajaran_id' => $tahunAjaran->id]);
        $siswa = Siswa::factory()->for($lembaga)->inKelas($kelasAsal)->create();
        SiswaPenempatan::factory()->for($lembaga)->for($siswa)->open()->create([
            'tahun_ajaran_id' => $tahunAjaran->id,
            'kelas_id' => $kelasAsal->id,
            'jenis' => PenempatanJenis::AWAL,
        ]);

        $response = $this->actingAs($admin)->post(route('admin.siswa.lifecycle.pindah', $siswa), [
            'kelas_id' => $kelasTujuan->id,
        ]);

        $response->assertRedirect(route('admin.siswa.show', $siswa));
        $this->assertSame($kelasTujuan->id, $siswa->refresh()->kelas_id);

        $terbuka = SiswaPenempatan::withoutGlobalScopes()
            ->where('siswa_id', $siswa->id)
            ->whereNull('selesai_at')
            ->first();
        $this->assertSame($kelasTujuan->id, $terbuka->kelas_id);
        $this->assertSame(PenempatanJenis::PINDAH_KELAS, $terbuka->jenis);
    }

    public function test_mutasi_keluar_via_http(): void
    {
        $lembaga = Lembaga::factory()->create();
        $admin = User::factory()->adminLembaga($lembaga->id)->create();
        $tahunAjaran = TahunAjaran::factory()->for($lembaga)->create();
        $kelas = Kelas::factory()->for($lembaga)->create(['tahun_ajaran_id' => $tahunAjaran->id]);
        $siswa = Siswa::factory()->for($lembaga)->inKelas($kelas)->create();
        SiswaPenempatan::factory()->for($lembaga)->for($siswa)->open()->create([
            'tahun_ajaran_id' => $tahunAjaran->id,
            'kelas_id' => $kelas->id,
            'jenis' => PenempatanJenis::AWAL,
        ]);

        $response = $this->actingAs($admin)->post(route('admin.siswa.lifecycle.mutasi_keluar', $siswa), [
            'alasan' => 'Pindah domisili',
            'tujuan' => 'SMP Negeri 2',
        ]);

        $response->assertRedirect(route('admin.siswa.show', $siswa));

        $siswa->refresh();
        $this->assertSame(SiswaStatus::MUTASI_KELUAR, $siswa->status_siswa);
        $this->assertFalse($siswa->is_active);
        $this->assertNull($siswa->kelas_id);
        $this->assertSame('Pindah domisili', $siswa->status_alasan);
        $this->assertSame('SMP Negeri 2', $siswa->status_tujuan);
    }

    public function test_luluskan_via_http(): void
    {
        $lembaga = Lembaga::factory()->create();
        $admin = User::factory()->adminLembaga($lembaga->id)->create();
        $tahunAjaran = TahunAjaran::factory()->for($lembaga)->create();
        $kelas = Kelas::factory()->for($lembaga)->create(['tahun_ajaran_id' => $tahunAjaran->id]);
        $siswa = Siswa::factory()->for($lembaga)->inKelas($kelas)->create();
        SiswaPenempatan::factory()->for($lembaga)->for($siswa)->open()->create([
            'tahun_ajaran_id' => $tahunAjaran->id,
            'kelas_id' => $kelas->id,
            'jenis' => PenempatanJenis::AWAL,
        ]);

        $response = $this->actingAs($admin)->post(route('admin.siswa.lifecycle.lulus', $siswa));

        $response->assertRedirect(route('admin.siswa.show', $siswa));

        $siswa->refresh();
        $this->assertSame(SiswaStatus::LULUS, $siswa->status_siswa);
        $this->assertFalse($siswa->is_active);
        $this->assertNull($siswa->kelas_id);
    }

    public function test_set_status_calon_menjadi_mutasi_masuk(): void
    {
        $lembaga = Lembaga::factory()->create();
        $admin = User::factory()->adminLembaga($lembaga->id)->create();
        $siswa = Siswa::factory()->for($lembaga)->calon()->create();

        $response = $this->actingAs($admin)->post(route('admin.siswa.lifecycle.set_status', $siswa), [
            'status' => SiswaStatus::MUTASI_MASUK,
            'asal' => 'SD Negeri 1',
        ]);

        $response->assertRedirect(route('admin.siswa.show', $siswa));

        $siswa->refresh();
        $this->assertSame(SiswaStatus::MUTASI_MASUK, $siswa->status_siswa);
        $this->assertSame('SD Negeri 1', $siswa->status_asal);
    }

    public function test_set_status_menolak_target_di_luar_set(): void
    {
        $lembaga = Lembaga::factory()->create();
        $admin = User::factory()->adminLembaga($lembaga->id)->create();
        $siswa = Siswa::factory()->for($lembaga)->calon()->create();

        $this->actingAs($admin)->post(route('admin.siswa.lifecycle.set_status', $siswa), [
            'status' => SiswaStatus::LULUS,
        ])->assertSessionHasErrors('status');

        $this->assertSame(SiswaStatus::CALON, $siswa->refresh()->status_siswa);
    }

    public function test_transisi_ilegal_dikembalikan_dengan_error(): void
    {
        $lembaga = Lembaga::factory()->create();
        $admin = User::factory()->adminLembaga($lembaga->id)->create();
        $tahunAjaran = TahunAjaran::factory()->for($lembaga)->create();
        $kelas = Kelas::factory()->for($lembaga)->create(['tahun_ajaran_id' => $tahunAjaran->id]);
        $siswa = Siswa::factory()->for($lembaga)->calon()->create();

        $response = $this->actingAs($admin)->post(route('admin.siswa.lifecycle.pindah', $siswa), [
            'kelas_id' => $kelas->id,
        ]);

        $response->assertRedirect();
        $response->assertSessionHasErrors('lifecycle');
        $this->assertSame(SiswaStatus::CALON, $siswa->refresh()->status_siswa);

        $log = AuditLog::query()->where('event', 'siswa.lifecycle.pindah')->first();
        $this->assertNotNull($log);
        $this->assertSame('failed', $log->result);
    }

    public function test_super_admin_forbidden_from_lifecycle_actions(): void
    {
        $sa = $this->superAdmin();
        $lembaga = Lembaga::factory()->create();
        $tahunAjaran = TahunAjaran::factory()->for($lembaga)->create();
        $kelas = Kelas::factory()->for($lembaga)->create(['tahun_ajaran_id' => $tahunAjaran->id]);
        $siswa = Siswa::withoutGlobalScopes()->create([
            'lembaga_id' => $lembaga->id,
            'nama' => 'Siswa SA',
            'nis' => 'NIS-SA',
            'is_active' => false,
            'status_siswa' => SiswaStatus::CALON,
        ]);

        $this->actingAs($sa)->post(route('admin.siswa.lifecycle.tempatkan', $siswa), ['kelas_id' => $kelas->id])->assertForbidden();
        $this->actingAs($sa)->post(route('admin.siswa.lifecycle.pindah', $siswa), ['kelas_id' => $kelas->id])->assertForbidden();
        $this->actingAs($sa)->post(route('admin.siswa.lifecycle.mutasi_keluar', $siswa))->assertForbidden();
        $this->actingAs($sa)->post(route('admin.siswa.lifecycle.lulus', $siswa))->assertForbidden();
        $this->actingAs($sa)->post(route('admin.siswa.lifecycle.set_status', $siswa), ['status' => SiswaStatus::MUTASI_MASUK])->assertForbidden();
    }

    public function test_lembaga_a_cannot_lifecycle_siswa_b(): void
    {
        $lembagaA = Lembaga::factory()->create();
        $lembagaB = Lembaga::factory()->create();
        $adminA = User::factory()->adminLembaga($lembagaA->id)->create();
        $tahunAjaranA = TahunAjaran::factory()->for($lembagaA)->create();
        $kelasA = Kelas::factory()->for($lembagaA)->create(['tahun_ajaran_id' => $tahunAjaranA->id]);
        $siswaB = Siswa::withoutGlobalScopes()->create([
            'lembaga_id' => $lembagaB->id,
            'nama' => 'Siswa B',
            'nis' => 'NIS-B',
            'is_active' => false,
            'status_siswa' => SiswaStatus::CALON,
        ]);

        $this->actingAs($adminA)->post(route('admin.siswa.lifecycle.tempatkan', $siswaB), ['kelas_id' => $kelasA->id])->assertNotFound();
        $this->actingAs($adminA)->post(route('admin.siswa.lifecycle.mutasi_keluar', $siswaB))->assertNotFound();
        $this->actingAs($adminA)->post(route('admin.siswa.lifecycle.lulus', $siswaB))->assertNotFound();

        $this->assertSame(SiswaStatus::CALON, $siswaB->refresh()->status_siswa);
    }

    public function test_show_menampilkan_badge_status_dan_riwayat(): void
    {
        $lembaga = Lembaga::factory()->create();
        $admin = User::factory()->adminLembaga($lembaga->id)->create();
        $tahunAjaran = TahunAjaran::factory()->for($lembaga)->create();
        $kelas = Kelas::factory()->for($lembaga)->create(['tahun_ajaran_id' => $tahunAjaran->id, 'nama' => 'VII-A']);
        $siswa = Siswa::factory()->for($lembaga)->inKelas($kelas)->create();
        SiswaPenempatan::factory()->for($lembaga)->for($siswa)->open()->create([
            'tahun_ajaran_id' => $tahunAjaran->id,
            'kelas_id' => $kelas->id,
            'jenis' => PenempatanJenis::AWAL,
        ]);

        $response = $this->actingAs($admin)->get(route('admin.siswa.show', $siswa));

        $response->assertOk()
            ->assertSee('Riwayat penempatan')
            ->assertSee('Penempatan awal')
            ->assertSee('Pindah kelas')
            ->assertSee('Aktif');
    }

    public function test_store_tanpa_kelas_menjadi_calon(): void
    {
        $lembaga = Lembaga::factory()->create();
        $admin = User::factory()->adminLembaga($lembaga->id)->create();

        $this->actingAs($admin)->post(route('admin.siswa.store'), [
            'nis' => 'NIS-CALON',
            'nama' => 'Calon Siswa',
        ])->assertRedirect(route('admin.siswa.index'));

        $siswa = Siswa::query()->where('nis', 'NIS-CALON')->firstOrFail();
        $this->assertSame(SiswaStatus::CALON, $siswa->status_siswa);
        $this->assertFalse($siswa->is_active);
        $this->assertNull($siswa->kelas_id);
    }

    public function test_store_dengan_kelas_menjadi_aktif_dengan_penempatan(): void
    {
        $lembaga = Lembaga::factory()->create();
        $admin = User::factory()->adminLembaga($lembaga->id)->create();
        $tahunAjaran = TahunAjaran::factory()->for($lembaga)->create();
        $kelas = Kelas::factory()->for($lembaga)->create(['tahun_ajaran_id' => $tahunAjaran->id]);

        $this->actingAs($admin)->post(route('admin.siswa.store'), [
            'nis' => 'NIS-AKTIF',
            'nama' => 'Aktif Siswa',
            'kelas_id' => $kelas->id,
            'tahun_ajaran_id' => $tahunAjaran->id,
        ])->assertRedirect(route('admin.siswa.index'));

        $siswa = Siswa::query()->where('nis', 'NIS-AKTIF')->firstOrFail();
        $this->assertSame(SiswaStatus::AKTIF, $siswa->status_siswa);
        $this->assertTrue($siswa->is_active);
        $this->assertSame($kelas->id, $siswa->kelas_id);

        $penempatan = SiswaPenempatan::withoutGlobalScopes()
            ->where('siswa_id', $siswa->id)
            ->whereNull('selesai_at')
            ->first();
        $this->assertNotNull($penempatan);
        $this->assertSame(PenempatanJenis::AWAL, $penempatan->jenis);
    }
}
