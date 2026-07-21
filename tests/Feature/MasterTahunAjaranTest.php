<?php

namespace Tests\Feature;

use App\Models\AuditLog;
use App\Models\Kelas;
use App\Models\Lembaga;
use App\Models\TahunAjaran;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class MasterTahunAjaranTest extends TestCase
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

    public function test_admin_lembaga_creates_tahun_ajaran_with_standard_name(): void
    {
        $lembaga = Lembaga::factory()->create();
        $admin = User::factory()->adminLembaga($lembaga->id)->create();

        $response = $this->actingAs($admin)->post(route('admin.tahun-ajaran.store'), [
            'tahun_mulai' => 2026,
            'tanggal_mulai' => '2026-07-01',
            'tanggal_selesai' => '2027-06-30',
        ]);

        $response->assertRedirect(route('admin.tahun-ajaran.index'));

        $tahunAjaran = TahunAjaran::query()->where('lembaga_id', $lembaga->id)->firstOrFail();
        $this->assertSame('2026/2027', $tahunAjaran->nama);
        $this->assertFalse($tahunAjaran->is_aktif);
        $this->assertSame('2026-07-01', $tahunAjaran->tanggal_mulai->format('Y-m-d'));
        $this->assertSame('2027-06-30', $tahunAjaran->tanggal_selesai->format('Y-m-d'));

        $log = AuditLog::query()->where('event', 'tahun_ajaran.create')->first();
        $this->assertNotNull($log);
        $this->assertSame('success', $log->result);
        $this->assertSame($tahunAjaran->id, $log->subject_id);
        $this->assertSame($lembaga->id, $log->lembaga_id);
    }

    public function test_client_supplied_nama_and_lembaga_id_are_ignored_on_create(): void
    {
        $lembaga = Lembaga::factory()->create();
        $otherLembaga = Lembaga::factory()->create();
        $admin = User::factory()->adminLembaga($lembaga->id)->create();

        $response = $this->actingAs($admin)->post(route('admin.tahun-ajaran.store'), [
            'tahun_mulai' => 2028,
            'tanggal_mulai' => '2028-07-01',
            'tanggal_selesai' => '2029-06-30',
            'nama' => 'Nama Curang',
            'lembaga_id' => $otherLembaga->id,
            'is_aktif' => true,
        ]);

        $response->assertRedirect(route('admin.tahun-ajaran.index'));

        $tahunAjaran = TahunAjaran::query()->where('lembaga_id', $lembaga->id)->firstOrFail();
        $this->assertSame('2028/2029', $tahunAjaran->nama);
        $this->assertSame($lembaga->id, $tahunAjaran->lembaga_id);
        $this->assertFalse($tahunAjaran->is_aktif);
    }

    public function test_activating_tahun_ajaran_deactivates_previously_active_one_in_transaction(): void
    {
        $lembaga = Lembaga::factory()->create();
        $admin = User::factory()->adminLembaga($lembaga->id)->create();

        $taA = TahunAjaran::factory()->for($lembaga)->aktif()->create(['nama' => '2025/2026']);
        $taB = TahunAjaran::factory()->for($lembaga)->create(['nama' => '2026/2027']);

        $response = $this->actingAs($admin)->post(route('admin.tahun-ajaran.activate', $taB));

        $response->assertRedirect(route('admin.tahun-ajaran.index'));

        $this->assertFalse($taA->refresh()->is_aktif);
        $this->assertTrue($taB->refresh()->is_aktif);

        $activeCount = TahunAjaran::query()
            ->where('lembaga_id', $lembaga->id)
            ->where('is_aktif', true)
            ->count();
        $this->assertSame(1, $activeCount);

        $log = AuditLog::query()->where('event', 'tahun_ajaran.activate')->first();
        $this->assertNotNull($log);
        $this->assertSame('success', $log->result);
        $this->assertSame($taB->id, $log->subject_id);
    }

    public function test_activating_tahun_ajaran_of_another_lembaga_is_not_found(): void
    {
        $lembagaA = Lembaga::factory()->create();
        $lembagaB = Lembaga::factory()->create();
        $adminA = User::factory()->adminLembaga($lembagaA->id)->create();

        $taB = TahunAjaran::factory()->for($lembagaB)->create();

        $this->actingAs($adminA)->post(route('admin.tahun-ajaran.activate', $taB))
            ->assertNotFound();

        $this->assertFalse($taB->refresh()->is_aktif);
    }

    public function test_creating_tahun_ajaran_with_soft_deleted_nama_restores_the_record(): void
    {
        $lembaga = Lembaga::factory()->create();
        $admin = User::factory()->adminLembaga($lembaga->id)->create();

        $tahunAjaran = TahunAjaran::factory()->for($lembaga)->create([
            'nama' => '2026/2027',
            'tanggal_mulai' => '2026-07-01',
            'tanggal_selesai' => '2027-06-30',
            'is_aktif' => true,
        ]);

        $tahunAjaran->delete();

        $response = $this->actingAs($admin)->post(route('admin.tahun-ajaran.store'), [
            'tahun_mulai' => 2026,
            'tanggal_mulai' => '2026-08-01',
            'tanggal_selesai' => '2027-07-31',
        ]);

        $response->assertRedirect(route('admin.tahun-ajaran.index'));
        $response->assertSessionHas('status');

        $restored = TahunAjaran::query()
            ->where('lembaga_id', $lembaga->id)
            ->where('nama', '2026/2027')
            ->sole();

        $this->assertSame($tahunAjaran->id, $restored->id);
        $this->assertFalse($restored->is_aktif);
        $this->assertSame('2026-08-01', $restored->tanggal_mulai->toDateString());
        $this->assertSame('2027-07-31', $restored->tanggal_selesai->toDateString());
        $this->assertSame(
            1,
            TahunAjaran::withTrashed()->where('lembaga_id', $lembaga->id)->where('nama', '2026/2027')->count()
        );
    }

    public function test_duplicate_nama_in_same_lembaga_is_rejected_as_validation_error(): void
    {
        // This admin web app renders validation failures as a redirect with
        // session errors (its exception handler reserves JSON rendering for
        // `api/*` routes only), so a 422-equivalent surfaces as
        // `assertSessionHasErrors` rather than a literal JSON 422 response.
        $lembaga = Lembaga::factory()->create();
        $admin = User::factory()->adminLembaga($lembaga->id)->create();

        TahunAjaran::factory()->for($lembaga)->create(['nama' => '2026/2027']);

        $response = $this->actingAs($admin)->post(route('admin.tahun-ajaran.store'), [
            'tahun_mulai' => 2026,
            'tanggal_mulai' => '2026-07-01',
            'tanggal_selesai' => '2027-06-30',
        ]);

        $response->assertSessionHasErrors('tahun_mulai');

        $this->assertSame(
            1,
            TahunAjaran::query()->where('lembaga_id', $lembaga->id)->where('nama', '2026/2027')->count()
        );
    }

    public function test_super_admin_is_forbidden_from_tahun_ajaran_routes(): void
    {
        $sa = $this->superAdmin();
        $lembaga = Lembaga::factory()->create();
        $tahunAjaran = TahunAjaran::factory()->for($lembaga)->create();

        $this->actingAs($sa)->get(route('admin.tahun-ajaran.index'))->assertForbidden();
        $this->actingAs($sa)->get(route('admin.tahun-ajaran.create'))->assertForbidden();
        $this->actingAs($sa)->post(route('admin.tahun-ajaran.store'), [
            'tahun_mulai' => 2030,
            'tanggal_mulai' => '2030-07-01',
            'tanggal_selesai' => '2031-06-30',
        ])->assertForbidden();
        $this->actingAs($sa)->get(route('admin.tahun-ajaran.edit', $tahunAjaran))->assertForbidden();
        $this->actingAs($sa)->put(route('admin.tahun-ajaran.update', $tahunAjaran), [
            'tanggal_mulai' => '2026-07-01',
            'tanggal_selesai' => '2027-06-30',
        ])->assertForbidden();
        $this->actingAs($sa)->post(route('admin.tahun-ajaran.activate', $tahunAjaran))->assertForbidden();
        $this->actingAs($sa)->delete(route('admin.tahun-ajaran.destroy', $tahunAjaran))->assertForbidden();
    }

    public function test_admin_lembaga_updates_only_dates_nama_stays_stable(): void
    {
        $lembaga = Lembaga::factory()->create();
        $admin = User::factory()->adminLembaga($lembaga->id)->create();
        $tahunAjaran = TahunAjaran::factory()->for($lembaga)->create([
            'nama' => '2026/2027',
            'tanggal_mulai' => '2026-07-01',
            'tanggal_selesai' => '2027-06-30',
        ]);

        $response = $this->actingAs($admin)->put(route('admin.tahun-ajaran.update', $tahunAjaran), [
            'tanggal_mulai' => '2026-07-15',
            'tanggal_selesai' => '2027-06-15',
            'nama' => '2099/2100',
        ]);

        $response->assertRedirect(route('admin.tahun-ajaran.index'));

        $tahunAjaran->refresh();
        $this->assertSame('2026/2027', $tahunAjaran->nama);
        $this->assertSame('2026-07-15', $tahunAjaran->tanggal_mulai->format('Y-m-d'));
        $this->assertSame('2027-06-15', $tahunAjaran->tanggal_selesai->format('Y-m-d'));
    }

    public function test_destroy_force_deletes_tahun_ajaran_without_dependents(): void
    {
        $lembaga = Lembaga::factory()->create();
        $admin = User::factory()->adminLembaga($lembaga->id)->create();
        $tahunAjaran = TahunAjaran::factory()->for($lembaga)->create();
        $tahunAjaranId = $tahunAjaran->id;

        $response = $this->actingAs($admin)->delete(route('admin.tahun-ajaran.destroy', $tahunAjaran));

        $response->assertRedirect(route('admin.tahun-ajaran.index'));
        $response->assertSessionHas('status');

        $this->assertDatabaseMissing('tahun_ajaran', ['id' => $tahunAjaranId]);

        $log = AuditLog::query()->where('event', 'tahun_ajaran.delete')->first();
        $this->assertNotNull($log);
        $this->assertSame('success', $log->result);
        $this->assertSame($tahunAjaranId, $log->subject_id);
    }

    public function test_destroy_is_blocked_when_kelas_still_references_tahun_ajaran(): void
    {
        $lembaga = Lembaga::factory()->create();
        $admin = User::factory()->adminLembaga($lembaga->id)->create();
        $tahunAjaran = TahunAjaran::factory()->for($lembaga)->create();

        Kelas::query()->create([
            'lembaga_id' => $lembaga->id,
            'tahun_ajaran_id' => $tahunAjaran->id,
            'nama' => 'Kelas 1A',
        ]);

        $response = $this->actingAs($admin)->delete(route('admin.tahun-ajaran.destroy', $tahunAjaran));

        $response->assertRedirect(route('admin.tahun-ajaran.index'));
        $response->assertSessionHasErrors('tahun_ajaran');

        $this->assertDatabaseHas('tahun_ajaran', ['id' => $tahunAjaran->id]);
    }

    public function test_create_after_force_delete_reuses_nama(): void
    {
        $lembaga = Lembaga::factory()->create();
        $admin = User::factory()->adminLembaga($lembaga->id)->create();
        $tahunAjaran = TahunAjaran::factory()->for($lembaga)->create(['nama' => '2026/2027']);

        $this->actingAs($admin)->delete(route('admin.tahun-ajaran.destroy', $tahunAjaran))
            ->assertRedirect(route('admin.tahun-ajaran.index'));

        $this->actingAs($admin)->post(route('admin.tahun-ajaran.store'), [
            'tahun_mulai' => 2026,
            'tanggal_mulai' => '2026-07-01',
            'tanggal_selesai' => '2027-06-30',
        ])->assertRedirect(route('admin.tahun-ajaran.index'));

        $this->assertSame(
            1,
            TahunAjaran::query()->where('lembaga_id', $lembaga->id)->where('nama', '2026/2027')->count()
        );
    }
}
