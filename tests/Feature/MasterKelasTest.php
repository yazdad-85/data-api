<?php

namespace Tests\Feature;

use App\Models\AuditLog;
use App\Models\Guru;
use App\Models\Kelas;
use App\Models\Lembaga;
use App\Models\Siswa;
use App\Models\TahunAjaran;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class MasterKelasTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        config(['security.mfa.super_admin_required' => false]);
    }

    private function superAdmin(): User
    {
        return User::factory()->create(['role' => 'super_admin', 'lembaga_id' => null]);
    }

    public function test_admin_lembaga_creates_kelas(): void
    {
        $lembaga = Lembaga::factory()->create();
        $admin = User::factory()->adminLembaga($lembaga->id)->create();
        $tahunAjaran = TahunAjaran::factory()->for($lembaga)->create(['nama' => '2026/2027']);
        $guru = Guru::factory()->for($lembaga)->create(['nama' => 'Guru Wali']);

        $response = $this->actingAs($admin)->post(route('admin.kelas.store'), [
            'tahun_ajaran_id' => $tahunAjaran->id,
            'nama' => 'VII-A',
            'tingkat' => '7',
            'wali_kelas_guru_id' => $guru->id,
        ]);

        $response->assertRedirect(route('admin.kelas.index'));

        $kelas = Kelas::query()->where('lembaga_id', $lembaga->id)->firstOrFail();
        $this->assertSame('VII-A', $kelas->nama);
        $this->assertSame('7', $kelas->tingkat);
        $this->assertSame($tahunAjaran->id, $kelas->tahun_ajaran_id);
        $this->assertSame($guru->id, $kelas->wali_kelas_guru_id);

        $log = AuditLog::query()->where('event', 'kelas.create')->first();
        $this->assertNotNull($log);
        $this->assertSame('success', $log->result);
        $this->assertSame('VII-A', $log->metadata['nama']);
        $this->assertSame($kelas->id, $log->subject_id);
        $this->assertSame($lembaga->id, $log->lembaga_id);
    }

    public function test_admin_lembaga_updates_kelas(): void
    {
        $lembaga = Lembaga::factory()->create();
        $admin = User::factory()->adminLembaga($lembaga->id)->create();
        $tahunAjaran = TahunAjaran::factory()->for($lembaga)->create(['nama' => '2026/2027']);
        $kelas = Kelas::factory()->for($lembaga)->create([
            'tahun_ajaran_id' => $tahunAjaran->id,
            'nama' => 'VII-A',
            'tingkat' => '7',
        ]);

        $response = $this->actingAs($admin)->put(route('admin.kelas.update', $kelas), [
            'tahun_ajaran_id' => $tahunAjaran->id,
            'nama' => 'VII-B',
            'tingkat' => '7',
        ]);

        $response->assertRedirect(route('admin.kelas.index'));

        $kelas->refresh();
        $this->assertSame('VII-B', $kelas->nama);

        $log = AuditLog::query()->where('event', 'kelas.update')->first();
        $this->assertNotNull($log);
        $this->assertSame('success', $log->result);
        $this->assertSame('VII-B', $log->metadata['nama']);
    }

    public function test_super_admin_is_forbidden_from_kelas_routes(): void
    {
        $sa = $this->superAdmin();
        $lembaga = Lembaga::factory()->create();
        $tahunAjaran = TahunAjaran::factory()->for($lembaga)->create();
        $kelas = Kelas::withoutGlobalScopes()->create([
            'lembaga_id' => $lembaga->id,
            'tahun_ajaran_id' => $tahunAjaran->id,
            'nama' => 'VII-A',
        ]);

        $this->actingAs($sa)->get(route('admin.kelas.index'))->assertForbidden();
        $this->actingAs($sa)->get(route('admin.kelas.create'))->assertForbidden();
        $this->actingAs($sa)->post(route('admin.kelas.store'), [
            'tahun_ajaran_id' => $tahunAjaran->id,
            'nama' => 'VII-B',
        ])->assertForbidden();
        $this->actingAs($sa)->get(route('admin.kelas.show', $kelas))->assertForbidden();
        $this->actingAs($sa)->get(route('admin.kelas.edit', $kelas))->assertForbidden();
        $this->actingAs($sa)->put(route('admin.kelas.update', $kelas), [
            'tahun_ajaran_id' => $tahunAjaran->id,
            'nama' => 'VII-C',
        ])->assertForbidden();
        $this->actingAs($sa)->delete(route('admin.kelas.destroy', $kelas))->assertForbidden();
    }

    public function test_admin_lembaga_cannot_access_kelas_of_another_lembaga(): void
    {
        $lembagaA = Lembaga::factory()->create();
        $lembagaB = Lembaga::factory()->create();
        $adminA = User::factory()->adminLembaga($lembagaA->id)->create();
        $tahunAjaranB = TahunAjaran::factory()->for($lembagaB)->create();
        $kelasB = Kelas::withoutGlobalScopes()->create([
            'lembaga_id' => $lembagaB->id,
            'tahun_ajaran_id' => $tahunAjaranB->id,
            'nama' => 'VII-A',
        ]);

        $this->actingAs($adminA)->get(route('admin.kelas.show', $kelasB))->assertNotFound();
        $this->actingAs($adminA)->get(route('admin.kelas.edit', $kelasB))->assertNotFound();
        $this->actingAs($adminA)->put(route('admin.kelas.update', $kelasB), [
            'tahun_ajaran_id' => $tahunAjaranB->id,
            'nama' => 'VII-Z',
        ])->assertNotFound();
        $this->actingAs($adminA)->delete(route('admin.kelas.destroy', $kelasB))->assertNotFound();

        $this->assertDatabaseHas('kelas', ['id' => $kelasB->id, 'nama' => 'VII-A']);
    }

    public function test_destroy_is_blocked_when_kelas_has_siswa(): void
    {
        $lembaga = Lembaga::factory()->create();
        $admin = User::factory()->adminLembaga($lembaga->id)->create();
        $kelas = Kelas::factory()->for($lembaga)->create(['nama' => 'VII-A']);
        Siswa::factory()->inKelas($kelas)->create(['nama' => 'Siswa Satu']);

        $response = $this->actingAs($admin)->delete(route('admin.kelas.destroy', $kelas));

        $response->assertRedirect(route('admin.kelas.index'));
        $response->assertSessionHasErrors('kelas');
        $this->assertDatabaseHas('kelas', ['id' => $kelas->id]);
        $this->assertNull(AuditLog::query()->where('event', 'kelas.delete')->first());
    }

    public function test_destroy_force_deletes_empty_kelas(): void
    {
        $lembaga = Lembaga::factory()->create();
        $admin = User::factory()->adminLembaga($lembaga->id)->create();
        $kelas = Kelas::factory()->for($lembaga)->create(['nama' => 'VII-A']);

        $response = $this->actingAs($admin)->delete(route('admin.kelas.destroy', $kelas));

        $response->assertRedirect(route('admin.kelas.index'));
        $this->assertDatabaseMissing('kelas', ['id' => $kelas->id]);

        $log = AuditLog::query()->where('event', 'kelas.delete')->first();
        $this->assertNotNull($log);
        $this->assertSame('success', $log->result);
        $this->assertSame('VII-A', $log->metadata['nama']);
    }

    public function test_duplicate_nama_in_same_tahun_ajaran_returns_session_errors(): void
    {
        $lembaga = Lembaga::factory()->create();
        $admin = User::factory()->adminLembaga($lembaga->id)->create();
        $tahunAjaran = TahunAjaran::factory()->for($lembaga)->create(['nama' => '2026/2027']);
        Kelas::factory()->for($lembaga)->create([
            'tahun_ajaran_id' => $tahunAjaran->id,
            'nama' => 'VII-A',
        ]);

        $response = $this->actingAs($admin)->post(route('admin.kelas.store'), [
            'tahun_ajaran_id' => $tahunAjaran->id,
            'nama' => 'VII-A',
        ]);

        $response->assertSessionHasErrors('nama');
        $this->assertSame(1, Kelas::query()->where('nama', 'VII-A')->count());
    }

    public function test_duplicate_nama_including_soft_deleted_returns_session_errors(): void
    {
        $lembaga = Lembaga::factory()->create();
        $admin = User::factory()->adminLembaga($lembaga->id)->create();
        $tahunAjaran = TahunAjaran::factory()->for($lembaga)->create(['nama' => '2026/2027']);
        $kelas = Kelas::factory()->for($lembaga)->create([
            'tahun_ajaran_id' => $tahunAjaran->id,
            'nama' => 'VII-A',
        ]);
        $kelas->delete();

        $response = $this->actingAs($admin)->post(route('admin.kelas.store'), [
            'tahun_ajaran_id' => $tahunAjaran->id,
            'nama' => 'VII-A',
        ]);

        $response->assertSessionHasErrors('nama');
        $this->assertSame(1, Kelas::withTrashed()->where('nama', 'VII-A')->count());
    }
}
