<?php

namespace Tests\Feature;

use App\Models\Lembaga;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class LembagaProfileTest extends TestCase
{
    use RefreshDatabase;

    private function superAdmin(): User
    {
        config(['security.mfa.super_admin_required' => false]);

        return User::factory()->create([
            'role' => 'super_admin',
            'lembaga_id' => null,
        ]);
    }

    public function test_guest_is_redirected_from_lembaga_profile(): void
    {
        $this->get(route('admin.lembaga-profile.show'))->assertRedirect(route('login'));
    }

    public function test_super_admin_cannot_access_lembaga_profile(): void
    {
        $this->actingAs($this->superAdmin())
            ->get(route('admin.lembaga-profile.show'))
            ->assertForbidden();
    }

    public function test_admin_lembaga_can_view_own_lembaga_profile(): void
    {
        $lembaga = Lembaga::factory()->create(['nama' => 'Sekolah Contoh', 'nama_kepala' => 'Budi Santoso']);
        $user = User::factory()->adminLembaga($lembaga->id)->create();

        $this->actingAs($user)
            ->get(route('admin.lembaga-profile.show'))
            ->assertOk()
            ->assertSee('Profil Lembaga')
            ->assertSee('Budi Santoso');
    }

    public function test_admin_lembaga_can_update_profile_fields(): void
    {
        $lembaga = Lembaga::factory()->create();
        $user = User::factory()->adminLembaga($lembaga->id)->create();

        $this->actingAs($user)
            ->put(route('admin.lembaga-profile.update'), [
                'nama_kepala' => 'Siti Aminah',
                'jenis' => 'Madrasah',
                'telepon' => '081234567890',
                'email' => 'lembaga@example.test',
                'kota' => 'Bandung',
                'provinsi' => 'Jawa Barat',
                'alamat' => 'Jl. Contoh No. 1',
            ])
            ->assertRedirect(route('admin.lembaga-profile.show'));

        $lembaga->refresh();
        $this->assertSame('Siti Aminah', $lembaga->nama_kepala);
        $this->assertSame('Madrasah', $lembaga->jenis);
        $this->assertSame('Bandung', $lembaga->kota);
    }

    public function test_admin_lembaga_can_upload_and_remove_kop_surat(): void
    {
        Storage::fake('public');
        $lembaga = Lembaga::factory()->create();
        $user = User::factory()->adminLembaga($lembaga->id)->create();

        if (! extension_loaded('gd')) {
            $this->markTestSkipped('GD extension required');
        }

        $this->actingAs($user)
            ->put(route('admin.lembaga-profile.update'), [
                'kop_surat' => UploadedFile::fake()->image('kop.png', 800, 200),
            ])
            ->assertRedirect(route('admin.lembaga-profile.show'));

        $lembaga->refresh();
        $this->assertNotNull($lembaga->kop_surat_path);
        Storage::disk('public')->assertExists($lembaga->kop_surat_path);

        $oldPath = $lembaga->kop_surat_path;

        $this->actingAs($user)
            ->put(route('admin.lembaga-profile.update'), [
                'remove_kop_surat' => '1',
            ])
            ->assertRedirect(route('admin.lembaga-profile.show'));

        $lembaga->refresh();
        $this->assertNull($lembaga->kop_surat_path);
        Storage::disk('public')->assertMissing($oldPath);
    }

    public function test_reuploading_kop_surat_replaces_and_deletes_old_file(): void
    {
        Storage::fake('public');
        $lembaga = Lembaga::factory()->create();
        $user = User::factory()->adminLembaga($lembaga->id)->create();

        if (! extension_loaded('gd')) {
            $this->markTestSkipped('GD extension required');
        }

        $this->actingAs($user)->put(route('admin.lembaga-profile.update'), [
            'kop_surat' => UploadedFile::fake()->image('kop-lama.png', 800, 200),
        ]);

        $lembaga->refresh();
        $oldPath = $lembaga->kop_surat_path;
        $this->assertNotNull($oldPath);
        Storage::disk('public')->assertExists($oldPath);

        $this->actingAs($user)
            ->put(route('admin.lembaga-profile.update'), [
                'kop_surat' => UploadedFile::fake()->image('kop-baru.png', 800, 200),
            ])
            ->assertRedirect(route('admin.lembaga-profile.show'));

        $lembaga->refresh();
        $this->assertNotNull($lembaga->kop_surat_path);
        $this->assertNotSame($oldPath, $lembaga->kop_surat_path);
        Storage::disk('public')->assertExists($lembaga->kop_surat_path);
        Storage::disk('public')->assertMissing($oldPath);
    }

    public function test_kop_surat_upload_rejects_non_png(): void
    {
        Storage::fake('public');
        $lembaga = Lembaga::factory()->create();
        $user = User::factory()->adminLembaga($lembaga->id)->create();

        $this->actingAs($user)
            ->put(route('admin.lembaga-profile.update'), [
                'kop_surat' => UploadedFile::fake()->create('evil.svg', 10, 'image/svg+xml'),
            ])
            ->assertSessionHasErrors('kop_surat');
    }

    public function test_admin_lembaga_cannot_edit_official_name_or_kode(): void
    {
        $lembaga = Lembaga::factory()->create(['nama' => 'Nama Resmi']);
        $user = User::factory()->adminLembaga($lembaga->id)->create();

        $this->actingAs($user)
            ->put(route('admin.lembaga-profile.update'), [
                'nama' => 'Nama Diubah Paksa',
                'nama_kepala' => 'Kepala Baru',
            ])
            ->assertRedirect(route('admin.lembaga-profile.show'));

        $lembaga->refresh();
        $this->assertSame('Nama Resmi', $lembaga->nama);
        $this->assertSame('Kepala Baru', $lembaga->nama_kepala);
    }
}
