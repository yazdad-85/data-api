<?php

namespace Tests\Feature;

use App\Models\AuditLog;
use App\Models\Lembaga;
use App\Models\User;
use App\Services\Settings\DatabaseBackupExporter;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class AdminSettingsTest extends TestCase
{
    use RefreshDatabase;

    private function superAdmin(): User
    {
        config(['security.mfa.super_admin_required' => false]);

        return User::factory()->create([
            'role' => 'super_admin',
            'lembaga_id' => null,
            'password' => 'OldPassword123!',
        ]);
    }

    public function test_guest_is_redirected_from_settings(): void
    {
        $this->get(route('admin.settings.show'))->assertRedirect(route('login'));
    }

    public function test_admin_lembaga_cannot_access_settings(): void
    {
        $lembaga = Lembaga::factory()->create();
        $user = User::factory()->adminLembaga($lembaga->id)->create();

        $this->actingAs($user)
            ->get(route('admin.settings.show'))
            ->assertForbidden();
    }

    public function test_super_admin_can_view_settings_and_menu_item(): void
    {
        $user = $this->superAdmin();

        $this->actingAs($user)
            ->get(route('admin.settings.show'))
            ->assertOk()
            ->assertSee('Pengaturan')
            ->assertSee('Branding')
            ->assertSee('Backup database');

        $this->actingAs($user)
            ->get(route('admin.dashboard'))
            ->assertOk()
            ->assertSee('href="'.route('admin.settings.show').'"', false)
            ->assertSee('Pengaturan');
    }

    public function test_update_branding_name_updates_sidebar(): void
    {
        $user = $this->superAdmin();

        $this->actingAs($user)
            ->put(route('admin.settings.branding'), [
                'app_name' => 'Yayasan Data',
            ])
            ->assertRedirect(route('admin.settings.show'))
            ->assertSessionHas('status');

        $this->actingAs($user)
            ->get(route('admin.dashboard'))
            ->assertSee('Yayasan Data');

        $this->assertDatabaseHas('audit_logs', [
            'event' => 'settings.branding_update',
            'result' => 'success',
        ]);
    }

    public function test_upload_logo_stores_files_and_rejects_svg(): void
    {
        Storage::fake('public');
        $user = $this->superAdmin();

        if (! extension_loaded('gd')) {
            $this->markTestSkipped('GD extension required');
        }

        $this->actingAs($user)
            ->put(route('admin.settings.branding'), [
                'app_name' => 'Pusat Data',
                'logo' => UploadedFile::fake()->image('brand.png', 120, 60),
            ])
            ->assertRedirect(route('admin.settings.show'));

        Storage::disk('public')->assertExists('branding/logo.png');
        Storage::disk('public')->assertExists('branding/favicon.png');

        $this->actingAs($user)
            ->put(route('admin.settings.branding'), [
                'app_name' => 'Pusat Data',
                'logo' => UploadedFile::fake()->create('evil.svg', 10, 'image/svg+xml'),
            ])
            ->assertSessionHasErrors('logo');
    }

    public function test_backup_rejects_wrong_password(): void
    {
        $user = $this->superAdmin();

        $this->actingAs($user)
            ->from(route('admin.settings.show'))
            ->post(route('admin.settings.backup'), [
                'current_password' => 'WrongPassword999!',
            ])
            ->assertRedirect(route('admin.settings.show'))
            ->assertSessionHasErrors('current_password');
    }

    public function test_backup_rejects_non_pgsql(): void
    {
        config(['database.default' => 'sqlite']);
        $user = $this->superAdmin();

        $this->actingAs($user)
            ->from(route('admin.settings.show'))
            ->post(route('admin.settings.backup'), [
                'current_password' => 'OldPassword123!',
            ])
            ->assertRedirect(route('admin.settings.show'))
            ->assertSessionHasErrors('backup');
    }

    public function test_backup_streams_download_when_exporter_succeeds(): void
    {
        $user = $this->superAdmin();

        $this->mock(DatabaseBackupExporter::class, function ($mock) {
            $mock->shouldReceive('export')
                ->once()
                ->andReturn(["-- SQL DUMP\n", 'pusat-data-test.sql']);
        });

        $response = $this->actingAs($user)
            ->post(route('admin.settings.backup'), [
                'current_password' => 'OldPassword123!',
            ]);

        $response->assertOk();
        $response->assertHeader('content-disposition');
        $this->assertStringContainsString('pusat-data-test.sql', $response->headers->get('content-disposition'));
        $this->assertSame("-- SQL DUMP\n", $response->streamedContent());

        $this->assertDatabaseHas('audit_logs', [
            'event' => 'settings.backup_download',
            'result' => 'success',
        ]);

        $audit = AuditLog::query()->where('event', 'settings.backup_download')->first();
        $encoded = json_encode($audit->metadata ?? []);
        $this->assertStringNotContainsString('OldPassword123!', (string) $encoded);
    }
}
