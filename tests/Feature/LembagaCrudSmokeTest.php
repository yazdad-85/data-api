<?php

namespace Tests\Feature;

use App\Models\Lembaga;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class LembagaCrudSmokeTest extends TestCase
{
    use RefreshDatabase;

    private function superAdmin(): User
    {
        config(['security.mfa.super_admin_required' => false]);

        return User::factory()->create(['role' => 'super_admin', 'lembaga_id' => null]);
    }

    public function test_super_admin_can_view_index_create_and_show(): void
    {
        $sa = $this->superAdmin();
        $lembaga = Lembaga::factory()->create(['nama' => 'SMA Contoh']);

        $this->actingAs($sa)->get(route('admin.lembaga.index'))
            ->assertOk()
            ->assertSee('SMA Contoh')
            ->assertSee('Tambah lembaga');

        $this->actingAs($sa)->get(route('admin.lembaga.create'))
            ->assertOk()
            ->assertSee('Simpan lembaga');

        $this->actingAs($sa)->get(route('admin.lembaga.show', $lembaga))
            ->assertOk()
            ->assertSee('SMA Contoh')
            ->assertSee('Nonaktifkan lembaga');
    }

    public function test_super_admin_can_create_update_and_toggle_lembaga(): void
    {
        $sa = $this->superAdmin();

        $this->actingAs($sa)->post(route('admin.lembaga.store'), [
            'niy_kode' => '03',
            'nama' => 'Lembaga Smoke Test',
        ])->assertRedirect();

        $lembaga = Lembaga::query()->where('nama', 'Lembaga Smoke Test')->firstOrFail();
        $this->assertTrue($lembaga->is_active);

        $this->actingAs($sa)->put(route('admin.lembaga.update', $lembaga), [
            'niy_kode' => '03',
            'nama' => 'Lembaga Smoke Test Updated',
        ])->assertRedirect(route('admin.lembaga.show', $lembaga));

        $this->assertSame('Lembaga Smoke Test Updated', $lembaga->refresh()->nama);

        $this->actingAs($sa)->post(route('admin.lembaga.deactivate', $lembaga))
            ->assertRedirect(route('admin.lembaga.show', $lembaga));
        $this->assertFalse($lembaga->refresh()->is_active);

        $this->actingAs($sa)->post(route('admin.lembaga.activate', $lembaga))
            ->assertRedirect(route('admin.lembaga.show', $lembaga));
        $this->assertTrue($lembaga->refresh()->is_active);
    }

    public function test_admin_lembaga_cannot_access_lembaga_routes(): void
    {
        $lembaga = Lembaga::factory()->create();
        $admin = User::factory()->adminLembaga($lembaga->id)->create();

        $this->actingAs($admin)->get(route('admin.lembaga.index'))
            ->assertForbidden();
    }
}
