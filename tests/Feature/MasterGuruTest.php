<?php

namespace Tests\Feature;

use App\Models\AuditLog;
use App\Models\Guru;
use App\Models\Lembaga;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class MasterGuruTest extends TestCase
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

    public function test_admin_lembaga_creates_guru_with_full_fields(): void
    {
        $lembaga = Lembaga::factory()->create();
        $admin = User::factory()->adminLembaga($lembaga->id)->create();

        $response = $this->actingAs($admin)->post(route('admin.guru.store'), [
            'nama' => 'Budi Santoso',
            'niy' => 'NIY-001',
            'nuptk' => 'NUPTK-001',
            'jenis_kelamin' => 'L',
            'tempat_lahir' => 'Jakarta',
            'tanggal_lahir' => '1990-01-01',
            'email' => 'budi@example.com',
            'telepon' => '08123456789',
            'alamat' => 'Jl. Merdeka No. 1',
            'status_kepegawaian' => 'GTY',
        ]);

        $response->assertRedirect(route('admin.guru.index'));

        $guru = Guru::query()->where('lembaga_id', $lembaga->id)->firstOrFail();
        $this->assertSame('Budi Santoso', $guru->nama);
        $this->assertSame('NIY-001', $guru->niy);
        $this->assertTrue($guru->is_active);

        $log = AuditLog::query()->where('event', 'guru.create')->first();
        $this->assertNotNull($log);
        $this->assertSame('success', $log->result);
        $this->assertSame($guru->id, $log->subject_id);
        $this->assertSame($lembaga->id, $log->lembaga_id);
    }

    public function test_client_supplied_lembaga_id_and_is_active_are_ignored_on_create(): void
    {
        $lembaga = Lembaga::factory()->create();
        $otherLembaga = Lembaga::factory()->create();
        $admin = User::factory()->adminLembaga($lembaga->id)->create();

        $response = $this->actingAs($admin)->post(route('admin.guru.store'), [
            'nama' => 'Guru Aman',
            'lembaga_id' => $otherLembaga->id,
            'is_active' => false,
        ]);

        $response->assertRedirect(route('admin.guru.index'));

        $guru = Guru::query()->where('nama', 'Guru Aman')->firstOrFail();
        $this->assertSame($lembaga->id, $guru->lembaga_id);
        $this->assertTrue($guru->is_active);
    }

    public function test_admin_lembaga_updates_guru(): void
    {
        $lembaga = Lembaga::factory()->create();
        $admin = User::factory()->adminLembaga($lembaga->id)->create();
        $guru = Guru::factory()->for($lembaga)->create(['nama' => 'Nama Lama']);

        $response = $this->actingAs($admin)->put(route('admin.guru.update', $guru), [
            'nama' => 'Nama Baru',
            'niy' => 'NIY-999',
        ]);

        $response->assertRedirect(route('admin.guru.index'));

        $guru->refresh();
        $this->assertSame('Nama Baru', $guru->nama);
        $this->assertSame('NIY-999', $guru->niy);
    }

    public function test_index_search_matches_nama_or_niy(): void
    {
        $lembaga = Lembaga::factory()->create();
        $admin = User::factory()->adminLembaga($lembaga->id)->create();

        Guru::factory()->for($lembaga)->create(['nama' => 'Siti Aminah', 'niy' => 'NIY-100']);
        Guru::factory()->for($lembaga)->create(['nama' => 'Joko Susilo', 'niy' => 'NIY-200']);

        $byName = $this->actingAs($admin)->get(route('admin.guru.index', ['q' => 'Siti']));
        $byName->assertOk()->assertSee('Siti Aminah')->assertDontSee('Joko Susilo');

        $byNiy = $this->actingAs($admin)->get(route('admin.guru.index', ['q' => 'NIY-200']));
        $byNiy->assertOk()->assertSee('Joko Susilo')->assertDontSee('Siti Aminah');
    }

    public function test_destroy_soft_deletes_guru(): void
    {
        $lembaga = Lembaga::factory()->create();
        $admin = User::factory()->adminLembaga($lembaga->id)->create();
        $guru = Guru::factory()->for($lembaga)->create();

        $response = $this->actingAs($admin)->delete(route('admin.guru.destroy', $guru));

        $response->assertRedirect(route('admin.guru.index'));
        $this->assertSoftDeleted('guru', ['id' => $guru->id]);

        $log = AuditLog::query()->where('event', 'guru.delete')->first();
        $this->assertNotNull($log);
        $this->assertSame('success', $log->result);
    }

    public function test_deactivate_and_activate_guru(): void
    {
        $lembaga = Lembaga::factory()->create();
        $admin = User::factory()->adminLembaga($lembaga->id)->create();
        $guru = Guru::factory()->for($lembaga)->create(['is_active' => true]);

        $this->actingAs($admin)->post(route('admin.guru.deactivate', $guru))
            ->assertRedirect(route('admin.guru.index'));
        $this->assertFalse($guru->refresh()->is_active);

        $this->actingAs($admin)->post(route('admin.guru.activate', $guru))
            ->assertRedirect(route('admin.guru.index'));
        $this->assertTrue($guru->refresh()->is_active);

        $this->assertNotNull(AuditLog::query()->where('event', 'guru.deactivate')->first());
        $this->assertNotNull(AuditLog::query()->where('event', 'guru.activate')->first());
    }

    public function test_show_records_master_view_audit_without_pii_dump(): void
    {
        $lembaga = Lembaga::factory()->create();
        $admin = User::factory()->adminLembaga($lembaga->id)->create();
        $guru = Guru::factory()->for($lembaga)->create([
            'nama' => 'Data Rahasia',
            'email' => 'rahasia@example.com',
        ]);

        $response = $this->actingAs($admin)->get(route('admin.guru.show', $guru));
        $response->assertOk();

        $log = AuditLog::query()->where('event', 'master.view')->first();
        $this->assertNotNull($log);
        $this->assertSame('guru', $log->metadata['resource']);
        $this->assertArrayNotHasKey('nama', $log->metadata);
        $this->assertArrayNotHasKey('email', $log->metadata);
    }

    public function test_super_admin_is_forbidden_from_guru_routes(): void
    {
        $sa = $this->superAdmin();
        $lembaga = Lembaga::factory()->create();
        $guru = Guru::withoutGlobalScopes()->create(['lembaga_id' => $lembaga->id, 'nama' => 'Guru SA']);

        $this->actingAs($sa)->get(route('admin.guru.index'))->assertForbidden();
        $this->actingAs($sa)->get(route('admin.guru.create'))->assertForbidden();
        $this->actingAs($sa)->post(route('admin.guru.store'), ['nama' => 'X'])->assertForbidden();
        $this->actingAs($sa)->get(route('admin.guru.show', $guru))->assertForbidden();
        $this->actingAs($sa)->get(route('admin.guru.edit', $guru))->assertForbidden();
        $this->actingAs($sa)->put(route('admin.guru.update', $guru), ['nama' => 'Y'])->assertForbidden();
        $this->actingAs($sa)->post(route('admin.guru.activate', $guru))->assertForbidden();
        $this->actingAs($sa)->post(route('admin.guru.deactivate', $guru))->assertForbidden();
        $this->actingAs($sa)->delete(route('admin.guru.destroy', $guru))->assertForbidden();
    }

    public function test_admin_lembaga_cannot_access_guru_of_another_lembaga(): void
    {
        $lembagaA = Lembaga::factory()->create();
        $lembagaB = Lembaga::factory()->create();
        $adminA = User::factory()->adminLembaga($lembagaA->id)->create();
        $guruB = Guru::withoutGlobalScopes()->create(['lembaga_id' => $lembagaB->id, 'nama' => 'Guru B']);

        $this->actingAs($adminA)->get(route('admin.guru.show', $guruB))->assertNotFound();
        $this->actingAs($adminA)->get(route('admin.guru.edit', $guruB))->assertNotFound();
        $this->actingAs($adminA)->put(route('admin.guru.update', $guruB), ['nama' => 'Z'])->assertNotFound();
        $this->actingAs($adminA)->post(route('admin.guru.activate', $guruB))->assertNotFound();
        $this->actingAs($adminA)->post(route('admin.guru.deactivate', $guruB))->assertNotFound();
        $this->actingAs($adminA)->delete(route('admin.guru.destroy', $guruB))->assertNotFound();

        $this->assertNotSoftDeleted('guru', ['id' => $guruB->id]);
    }
}
