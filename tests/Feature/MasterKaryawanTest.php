<?php

namespace Tests\Feature;

use App\Models\AuditLog;
use App\Models\Karyawan;
use App\Models\Lembaga;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class MasterKaryawanTest extends TestCase
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

    public function test_admin_lembaga_creates_karyawan_with_auto_nik(): void
    {
        config(['master.niy.npyp' => '0488']);

        $lembaga = Lembaga::factory()->create(['niy_kode' => '01']);
        $admin = User::factory()->adminLembaga($lembaga->id)->create();

        $response = $this->actingAs($admin)->post(route('admin.karyawan.store'), [
            'nama' => 'Wati Rahayu',
            'jenis_kelamin' => 'P',
            'tahun_masuk' => 1989,
            'jabatan' => 'Staf Tata Usaha',
            'email' => 'wati@example.com',
            'telepon' => '08129876543',
            'alamat' => 'Jl. Pahlawan No. 2',
        ]);

        $response->assertRedirect(route('admin.karyawan.index'));

        $karyawan = Karyawan::query()->where('lembaga_id', $lembaga->id)->firstOrFail();
        $this->assertSame('Wati Rahayu', $karyawan->nama);
        $this->assertSame('048801028901', $karyawan->nik_pegawai);
        $this->assertSame(1989, $karyawan->tahun_masuk);
        $this->assertTrue($karyawan->is_active);

        $log = AuditLog::query()->where('event', 'karyawan.create')->first();
        $this->assertNotNull($log);
        $this->assertSame('success', $log->result);
        $this->assertSame($karyawan->id, $log->subject_id);
        $this->assertSame($lembaga->id, $log->lembaga_id);
    }

    public function test_create_fails_when_lembaga_has_no_niy_kode(): void
    {
        $lembaga = Lembaga::factory()->create(['niy_kode' => null]);
        $admin = User::factory()->adminLembaga($lembaga->id)->create();

        $response = $this->actingAs($admin)->post(route('admin.karyawan.store'), [
            'nama' => 'Wati Rahayu',
            'jenis_kelamin' => 'P',
            'tahun_masuk' => 1989,
        ]);

        $response->assertSessionHasErrors('tahun_masuk');
        $this->assertSame(0, Karyawan::query()->count());
    }

    public function test_client_supplied_lembaga_id_and_is_active_are_ignored_on_create(): void
    {
        $lembaga = Lembaga::factory()->create();
        $otherLembaga = Lembaga::factory()->create();
        $admin = User::factory()->adminLembaga($lembaga->id)->create();

        $response = $this->actingAs($admin)->post(route('admin.karyawan.store'), [
            'nama' => 'Karyawan Aman',
            'jenis_kelamin' => 'L',
            'tahun_masuk' => 2020,
            'lembaga_id' => $otherLembaga->id,
            'is_active' => false,
        ]);

        $response->assertRedirect(route('admin.karyawan.index'));

        $karyawan = Karyawan::query()->where('nama', 'Karyawan Aman')->firstOrFail();
        $this->assertSame($lembaga->id, $karyawan->lembaga_id);
        $this->assertTrue($karyawan->is_active);
    }

    public function test_admin_lembaga_updates_karyawan(): void
    {
        $lembaga = Lembaga::factory()->create();
        $admin = User::factory()->adminLembaga($lembaga->id)->create();
        $karyawan = Karyawan::factory()->for($lembaga)->create([
            'nama' => 'Nama Lama',
            'nik_pegawai' => '048801012401',
            'tahun_masuk' => 2020,
            'jenis_kelamin' => 'L',
        ]);

        $response = $this->actingAs($admin)->put(route('admin.karyawan.update', $karyawan), [
            'nama' => 'Nama Baru',
            'jabatan' => 'Kepala TU',
        ]);

        $response->assertRedirect(route('admin.karyawan.index'));

        $karyawan->refresh();
        $this->assertSame('Nama Baru', $karyawan->nama);
        $this->assertSame('Kepala TU', $karyawan->jabatan);
        $this->assertSame('048801012401', $karyawan->nik_pegawai);
    }

    public function test_index_search_matches_nama_or_nik_pegawai(): void
    {
        $lembaga = Lembaga::factory()->create();
        $admin = User::factory()->adminLembaga($lembaga->id)->create();

        Karyawan::factory()->for($lembaga)->create(['nama' => 'Rina Marlina', 'nik_pegawai' => 'NIK-100']);
        Karyawan::factory()->for($lembaga)->create(['nama' => 'Agus Setiawan', 'nik_pegawai' => 'NIK-200']);

        $byName = $this->actingAs($admin)->get(route('admin.karyawan.index', ['q' => 'Rina']));
        $byName->assertOk()->assertSee('Rina Marlina')->assertDontSee('Agus Setiawan');

        $byNik = $this->actingAs($admin)->get(route('admin.karyawan.index', ['q' => 'NIK-200']));
        $byNik->assertOk()->assertSee('Agus Setiawan')->assertDontSee('Rina Marlina');
    }

    public function test_destroy_soft_deletes_karyawan(): void
    {
        $lembaga = Lembaga::factory()->create();
        $admin = User::factory()->adminLembaga($lembaga->id)->create();
        $karyawan = Karyawan::factory()->for($lembaga)->create();

        $response = $this->actingAs($admin)->delete(route('admin.karyawan.destroy', $karyawan));

        $response->assertRedirect(route('admin.karyawan.index'));
        $this->assertSoftDeleted('karyawan', ['id' => $karyawan->id]);

        $log = AuditLog::query()->where('event', 'karyawan.delete')->first();
        $this->assertNotNull($log);
        $this->assertSame('success', $log->result);
    }

    public function test_deactivate_and_activate_karyawan(): void
    {
        $lembaga = Lembaga::factory()->create();
        $admin = User::factory()->adminLembaga($lembaga->id)->create();
        $karyawan = Karyawan::factory()->for($lembaga)->create(['is_active' => true]);

        $this->actingAs($admin)->post(route('admin.karyawan.deactivate', $karyawan))
            ->assertRedirect(route('admin.karyawan.index'));
        $this->assertFalse($karyawan->refresh()->is_active);

        $this->actingAs($admin)->post(route('admin.karyawan.activate', $karyawan))
            ->assertRedirect(route('admin.karyawan.index'));
        $this->assertTrue($karyawan->refresh()->is_active);

        $this->assertNotNull(AuditLog::query()->where('event', 'karyawan.deactivate')->first());
        $this->assertNotNull(AuditLog::query()->where('event', 'karyawan.activate')->first());
    }

    public function test_show_records_master_view_audit_without_pii_dump(): void
    {
        $lembaga = Lembaga::factory()->create();
        $admin = User::factory()->adminLembaga($lembaga->id)->create();
        $karyawan = Karyawan::factory()->for($lembaga)->create([
            'nama' => 'Data Rahasia',
            'email' => 'rahasia@example.com',
        ]);

        $response = $this->actingAs($admin)->get(route('admin.karyawan.show', $karyawan));
        $response->assertOk();

        $log = AuditLog::query()->where('event', 'master.view')->first();
        $this->assertNotNull($log);
        $this->assertSame('karyawan', $log->metadata['resource']);
        $this->assertArrayNotHasKey('nama', $log->metadata);
        $this->assertArrayNotHasKey('email', $log->metadata);
    }

    public function test_super_admin_is_forbidden_from_karyawan_routes(): void
    {
        $sa = $this->superAdmin();
        $lembaga = Lembaga::factory()->create();
        $karyawan = Karyawan::withoutGlobalScopes()->create(['lembaga_id' => $lembaga->id, 'nama' => 'Karyawan SA']);

        $this->actingAs($sa)->get(route('admin.karyawan.index'))->assertForbidden();
        $this->actingAs($sa)->get(route('admin.karyawan.create'))->assertForbidden();
        $this->actingAs($sa)->post(route('admin.karyawan.store'), ['nama' => 'X'])->assertForbidden();
        $this->actingAs($sa)->get(route('admin.karyawan.show', $karyawan))->assertForbidden();
        $this->actingAs($sa)->get(route('admin.karyawan.edit', $karyawan))->assertForbidden();
        $this->actingAs($sa)->put(route('admin.karyawan.update', $karyawan), ['nama' => 'Y'])->assertForbidden();
        $this->actingAs($sa)->post(route('admin.karyawan.activate', $karyawan))->assertForbidden();
        $this->actingAs($sa)->post(route('admin.karyawan.deactivate', $karyawan))->assertForbidden();
        $this->actingAs($sa)->delete(route('admin.karyawan.destroy', $karyawan))->assertForbidden();
    }

    public function test_admin_lembaga_cannot_access_karyawan_of_another_lembaga(): void
    {
        $lembagaA = Lembaga::factory()->create();
        $lembagaB = Lembaga::factory()->create();
        $adminA = User::factory()->adminLembaga($lembagaA->id)->create();
        $karyawanB = Karyawan::withoutGlobalScopes()->create(['lembaga_id' => $lembagaB->id, 'nama' => 'Karyawan B']);

        $this->actingAs($adminA)->get(route('admin.karyawan.show', $karyawanB))->assertNotFound();
        $this->actingAs($adminA)->get(route('admin.karyawan.edit', $karyawanB))->assertNotFound();
        $this->actingAs($adminA)->put(route('admin.karyawan.update', $karyawanB), ['nama' => 'Z'])->assertNotFound();
        $this->actingAs($adminA)->post(route('admin.karyawan.activate', $karyawanB))->assertNotFound();
        $this->actingAs($adminA)->post(route('admin.karyawan.deactivate', $karyawanB))->assertNotFound();
        $this->actingAs($adminA)->delete(route('admin.karyawan.destroy', $karyawanB))->assertNotFound();

        $this->assertNotSoftDeleted('karyawan', ['id' => $karyawanB->id]);
    }
}
