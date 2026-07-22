<?php

namespace Tests\Feature;

use App\Models\AuditLog;
use App\Models\Kelas;
use App\Models\Lembaga;
use App\Models\Siswa;
use App\Models\TahunAjaran;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class MasterSiswaTest extends TestCase
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

    public function test_admin_lembaga_creates_siswa_with_full_fields_and_nis(): void
    {
        $lembaga = Lembaga::factory()->create();
        $admin = User::factory()->adminLembaga($lembaga->id)->create();
        $tahunAjaran = TahunAjaran::factory()->for($lembaga)->create();
        $kelas = Kelas::factory()->for($lembaga)->create([
            'tahun_ajaran_id' => $tahunAjaran->id,
            'nama' => 'VII-A',
        ]);

        $response = $this->actingAs($admin)->post(route('admin.siswa.store'), [
            'nis' => 'NIS-001',
            'nisn' => 'NISN-001',
            'nama' => 'Andi Pratama',
            'jenis_kelamin' => 'L',
            'tempat_lahir' => 'Bandung',
            'tanggal_lahir' => '2010-05-15',
            'email' => 'andi@example.com',
            'telepon' => '08123456789',
            'alamat' => 'Jl. Merdeka No. 1',
            'nama_wali' => 'Budi Pratama',
            'telepon_wali' => '08198765432',
            'kelas_id' => $kelas->id,
            'tahun_ajaran_id' => $tahunAjaran->id,
        ]);

        $response->assertRedirect(route('admin.siswa.index'));

        $siswa = Siswa::query()->where('lembaga_id', $lembaga->id)->firstOrFail();
        $this->assertSame('NIS-001', $siswa->nis);
        $this->assertSame('NISN-001', $siswa->nisn);
        $this->assertSame('Andi Pratama', $siswa->nama);
        $this->assertSame($kelas->id, $siswa->kelas_id);
        $this->assertSame($tahunAjaran->id, $siswa->tahun_ajaran_id);
        $this->assertTrue($siswa->is_active);

        $log = AuditLog::query()->where('event', 'siswa.create')->first();
        $this->assertNotNull($log);
        $this->assertSame('success', $log->result);
        $this->assertSame($siswa->id, $log->subject_id);
        $this->assertSame($lembaga->id, $log->lembaga_id);
    }

    public function test_create_without_kelas_shows_badge_and_null_kelas_id(): void
    {
        $lembaga = Lembaga::factory()->create();
        $admin = User::factory()->adminLembaga($lembaga->id)->create();

        $response = $this->actingAs($admin)->post(route('admin.siswa.store'), [
            'nis' => 'NIS-002',
            'nama' => 'Siti Rahma',
        ]);

        $response->assertRedirect(route('admin.siswa.index'));

        $siswa = Siswa::query()->where('nis', 'NIS-002')->firstOrFail();
        $this->assertNull($siswa->kelas_id);
        $this->assertNull($siswa->tahun_ajaran_id);

        $index = $this->actingAs($admin)->get(route('admin.siswa.index'));
        $index->assertOk()->assertSee('Belum ada kelas');
    }

    public function test_kelas_id_with_wrong_tahun_ajaran_id_is_rejected(): void
    {
        $lembaga = Lembaga::factory()->create();
        $admin = User::factory()->adminLembaga($lembaga->id)->create();
        $tahunAjaranA = TahunAjaran::factory()->for($lembaga)->create(['nama' => '2024/2025']);
        $tahunAjaranB = TahunAjaran::factory()->for($lembaga)->create(['nama' => '2025/2026']);
        $kelas = Kelas::factory()->for($lembaga)->create([
            'tahun_ajaran_id' => $tahunAjaranA->id,
        ]);

        $response = $this->actingAs($admin)->post(route('admin.siswa.store'), [
            'nis' => 'NIS-003',
            'nama' => 'Budi Santoso',
            'kelas_id' => $kelas->id,
            'tahun_ajaran_id' => $tahunAjaranB->id,
        ]);

        $response->assertSessionHasErrors('tahun_ajaran_id');
        $this->assertSame(0, Siswa::query()->count());
    }

    public function test_soft_deleted_nis_blocks_recreate_with_session_errors(): void
    {
        $lembaga = Lembaga::factory()->create();
        $admin = User::factory()->adminLembaga($lembaga->id)->create();
        $deleted = Siswa::factory()->for($lembaga)->create([
            'nis' => 'NIS-DUP',
            'nama' => 'Siswa Lama',
        ]);
        $deleted->delete();

        $response = $this->actingAs($admin)->post(route('admin.siswa.store'), [
            'nis' => 'NIS-DUP',
            'nama' => 'Siswa Baru',
        ]);

        $response->assertSessionHasErrors('nis');
        $this->assertSame(1, Siswa::withTrashed()->count());
    }

    public function test_destroy_soft_deletes_siswa(): void
    {
        $lembaga = Lembaga::factory()->create();
        $admin = User::factory()->adminLembaga($lembaga->id)->create();
        $siswa = Siswa::factory()->for($lembaga)->create();

        $response = $this->actingAs($admin)->delete(route('admin.siswa.destroy', $siswa));

        $response->assertRedirect(route('admin.siswa.index'));
        $this->assertSoftDeleted('siswa', ['id' => $siswa->id]);

        $log = AuditLog::query()->where('event', 'siswa.delete')->first();
        $this->assertNotNull($log);
        $this->assertSame('success', $log->result);
    }

    public function test_deactivate_and_activate_siswa(): void
    {
        $lembaga = Lembaga::factory()->create();
        $admin = User::factory()->adminLembaga($lembaga->id)->create();
        $siswa = Siswa::factory()->for($lembaga)->create(['is_active' => true]);

        $this->actingAs($admin)->post(route('admin.siswa.deactivate', $siswa))
            ->assertRedirect(route('admin.siswa.index'));
        $this->assertFalse($siswa->refresh()->is_active);

        $this->actingAs($admin)->post(route('admin.siswa.activate', $siswa))
            ->assertRedirect(route('admin.siswa.index'));
        $this->assertTrue($siswa->refresh()->is_active);

        $this->assertNotNull(AuditLog::query()->where('event', 'siswa.deactivate')->first());
        $this->assertNotNull(AuditLog::query()->where('event', 'siswa.activate')->first());
    }

    public function test_show_records_master_view_audit_without_pii_dump(): void
    {
        $lembaga = Lembaga::factory()->create();
        $admin = User::factory()->adminLembaga($lembaga->id)->create();
        $siswa = Siswa::factory()->for($lembaga)->create([
            'nama' => 'Data Rahasia',
            'email' => 'rahasia@example.com',
        ]);

        $response = $this->actingAs($admin)->get(route('admin.siswa.show', $siswa));
        $response->assertOk();

        $log = AuditLog::query()->where('event', 'master.view')->first();
        $this->assertNotNull($log);
        $this->assertSame('siswa', $log->metadata['resource']);
        $this->assertArrayNotHasKey('nama', $log->metadata);
        $this->assertArrayNotHasKey('email', $log->metadata);
    }

    public function test_super_admin_is_forbidden_from_siswa_routes(): void
    {
        $sa = $this->superAdmin();
        $lembaga = Lembaga::factory()->create();
        $siswa = Siswa::withoutGlobalScopes()->create([
            'lembaga_id' => $lembaga->id,
            'nama' => 'Siswa SA',
            'nis' => 'NIS-SA',
            'is_active' => true,
        ]);

        $this->actingAs($sa)->get(route('admin.siswa.index'))->assertForbidden();
        $this->actingAs($sa)->get(route('admin.siswa.create'))->assertForbidden();
        $this->actingAs($sa)->post(route('admin.siswa.store'), ['nama' => 'X', 'nis' => 'NIS-X'])->assertForbidden();
        $this->actingAs($sa)->get(route('admin.siswa.show', $siswa))->assertForbidden();
        $this->actingAs($sa)->get(route('admin.siswa.edit', $siswa))->assertForbidden();
        $this->actingAs($sa)->put(route('admin.siswa.update', $siswa), ['nama' => 'Y', 'nis' => 'NIS-Y'])->assertForbidden();
        $this->actingAs($sa)->post(route('admin.siswa.activate', $siswa))->assertForbidden();
        $this->actingAs($sa)->post(route('admin.siswa.deactivate', $siswa))->assertForbidden();
        $this->actingAs($sa)->delete(route('admin.siswa.destroy', $siswa))->assertForbidden();
    }

    public function test_admin_lembaga_cannot_access_siswa_of_another_lembaga(): void
    {
        $lembagaA = Lembaga::factory()->create();
        $lembagaB = Lembaga::factory()->create();
        $adminA = User::factory()->adminLembaga($lembagaA->id)->create();
        $siswaB = Siswa::withoutGlobalScopes()->create([
            'lembaga_id' => $lembagaB->id,
            'nama' => 'Siswa B',
            'nis' => 'NIS-B',
            'is_active' => true,
        ]);

        $this->actingAs($adminA)->get(route('admin.siswa.show', $siswaB))->assertNotFound();
        $this->actingAs($adminA)->get(route('admin.siswa.edit', $siswaB))->assertNotFound();
        $this->actingAs($adminA)->put(route('admin.siswa.update', $siswaB), [
            'nama' => 'Z',
            'nis' => 'NIS-Z',
        ])->assertNotFound();
        $this->actingAs($adminA)->post(route('admin.siswa.activate', $siswaB))->assertNotFound();
        $this->actingAs($adminA)->post(route('admin.siswa.deactivate', $siswaB))->assertNotFound();
        $this->actingAs($adminA)->delete(route('admin.siswa.destroy', $siswaB))->assertNotFound();

        $this->assertNotSoftDeleted('siswa', ['id' => $siswaB->id]);
    }

    public function test_index_search_matches_nis(): void
    {
        $lembaga = Lembaga::factory()->create();
        $admin = User::factory()->adminLembaga($lembaga->id)->create();

        Siswa::factory()->for($lembaga)->create([
            'nama' => 'Siti Aminah',
            'nis' => 'NIS-100',
        ]);
        Siswa::factory()->for($lembaga)->create([
            'nama' => 'Joko Susilo',
            'nis' => 'NIS-200',
        ]);

        $byNis = $this->actingAs($admin)->get(route('admin.siswa.index', ['q' => 'NIS-200']));
        $byNis->assertOk()->assertSee('Joko Susilo')->assertDontSee('Siti Aminah');
    }
}
