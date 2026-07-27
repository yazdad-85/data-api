<?php

namespace Tests\Feature;

use App\Models\AuditLog;
use App\Models\Lembaga;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class AdminProfileTest extends TestCase
{
    use RefreshDatabase;

    public function test_guest_is_redirected_from_profile(): void
    {
        $this->get(route('admin.profile.show'))->assertRedirect(route('login'));
    }

    public function test_super_admin_can_view_profile(): void
    {
        config(['security.mfa.super_admin_required' => false]);

        $user = User::factory()->create([
            'role' => 'super_admin',
            'lembaga_id' => null,
            'name' => 'Super Tester',
            'email' => 'super@example.test',
        ]);

        $this->actingAs($user)
            ->get(route('admin.profile.show'))
            ->assertOk()
            ->assertSee('Super Tester')
            ->assertSee('super@example.test')
            ->assertSee('Super Admin');
    }

    public function test_admin_lembaga_can_view_profile_with_lembaga_name(): void
    {
        $lembaga = Lembaga::factory()->create(['nama' => 'Sekolah Profil']);
        $user = User::factory()->adminLembaga($lembaga->id)->create([
            'name' => 'Admin Lokal',
            'email' => 'admin@example.test',
        ]);

        $this->actingAs($user)
            ->get(route('admin.profile.show'))
            ->assertOk()
            ->assertSee('Admin Lokal')
            ->assertSee('admin@example.test')
            ->assertSee('Sekolah Profil');
    }

    public function test_header_name_links_to_profile(): void
    {
        config(['security.mfa.super_admin_required' => false]);

        $user = User::factory()->create([
            'role' => 'super_admin',
            'lembaga_id' => null,
            'name' => 'Header Link User',
        ]);

        $this->actingAs($user)
            ->get(route('admin.dashboard'))
            ->assertOk()
            ->assertSee('href="'.route('admin.profile.show').'"', false)
            ->assertSee('Header Link User');
    }

    public function test_profile_never_renders_old_password_values(): void
    {
        config(['security.mfa.super_admin_required' => false]);

        $user = User::factory()->create([
            'role' => 'super_admin',
            'lembaga_id' => null,
        ]);

        $this->withSession([
            '_old_input' => [
                'current_password' => 'OldPassword123!',
                'password' => 'NewPassword123!',
                'password_confirmation' => 'NewPassword123!',
            ],
        ])->actingAs($user)
            ->get(route('admin.profile.show'))
            ->assertOk()
            ->assertDontSee('OldPassword123!')
            ->assertDontSee('NewPassword123!');
    }

    public function test_update_name_succeeds_and_ignores_email_payload(): void
    {
        config(['security.mfa.super_admin_required' => false]);

        $user = User::factory()->create([
            'role' => 'super_admin',
            'lembaga_id' => null,
            'name' => 'Nama Lama',
            'email' => 'keep@example.test',
        ]);

        $this->actingAs($user)
            ->put(route('admin.profile.update'), [
                'name' => 'Nama Baru',
                'email' => 'hacked@example.test',
            ])
            ->assertRedirect(route('admin.profile.show'))
            ->assertSessionHas('status');

        $user->refresh();
        $this->assertSame('Nama Baru', $user->name);
        $this->assertSame('keep@example.test', $user->email);

        $this->assertDatabaseHas('audit_logs', [
            'event' => 'profile.update',
            'result' => 'success',
        ]);
    }

    public function test_wrong_current_password_is_rejected(): void
    {
        config(['security.mfa.super_admin_required' => false]);

        $user = User::factory()->create([
            'role' => 'super_admin',
            'lembaga_id' => null,
            'password' => 'OldPassword123!',
        ]);

        $this->actingAs($user)
            ->from(route('admin.profile.show'))
            ->put(route('admin.profile.password'), [
                'current_password' => 'WrongPassword999!',
                'password' => 'NewPassword123!',
                'password_confirmation' => 'NewPassword123!',
            ])
            ->assertRedirect(route('admin.profile.show'))
            ->assertSessionHasErrors('current_password');

        $this->assertTrue(Hash::check('OldPassword123!', $user->fresh()->password));
    }

    public function test_password_change_succeeds_and_invalidates_other_sessions(): void
    {
        config([
            'security.mfa.super_admin_required' => false,
            'session.driver' => 'database',
        ]);

        $user = User::factory()->create([
            'role' => 'super_admin',
            'lembaga_id' => null,
            'password' => 'OldPassword123!',
        ]);

        $this->actingAs($user);
        $currentSessionId = session()->getId();

        DB::table('sessions')->insert([
            'id' => 'remote-session',
            'user_id' => $user->id,
            'ip_address' => '10.0.0.2',
            'user_agent' => 'OtherBrowser',
            'payload' => base64_encode('remote'),
            'last_activity' => time(),
        ]);

        if (! DB::table('sessions')->where('id', $currentSessionId)->exists()) {
            DB::table('sessions')->insert([
                'id' => $currentSessionId,
                'user_id' => $user->id,
                'ip_address' => '127.0.0.1',
                'user_agent' => 'PHPUnit',
                'payload' => base64_encode('current'),
                'last_activity' => time(),
            ]);
        }

        $this->put(route('admin.profile.password'), [
            'current_password' => 'OldPassword123!',
            'password' => 'NewPassword123!',
            'password_confirmation' => 'NewPassword123!',
        ])->assertRedirect(route('admin.profile.show'))
            ->assertSessionHas('status');

        $user->refresh();
        $this->assertTrue(Hash::check('NewPassword123!', $user->password));
        $this->assertFalse(Hash::check('OldPassword123!', $user->password));
        $this->assertDatabaseMissing('sessions', ['id' => 'remote-session']);
        $this->assertAuthenticatedAs($user);

        $this->assertDatabaseHas('audit_logs', [
            'event' => 'profile.password_change',
            'result' => 'success',
        ]);

        $audit = AuditLog::query()->where('event', 'profile.password_change')->latest('created_at')->first();
        $this->assertNotNull($audit);
        $encoded = json_encode($audit->metadata ?? []);
        $this->assertStringNotContainsString('NewPassword123!', (string) $encoded);
        $this->assertStringNotContainsString('OldPassword123!', (string) $encoded);
    }
}
