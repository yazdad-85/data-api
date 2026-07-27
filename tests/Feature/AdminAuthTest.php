<?php

namespace Tests\Feature;

use App\Models\AuditLog;
use App\Models\Lembaga;
use App\Models\User;
use App\Services\Auth\AdminAuthenticator;
use App\Support\Security\TotpVerifier;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Str;
use Tests\TestCase;

class AdminAuthTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->clearAdminLoginRateLimiter('admin@example.test');
        $this->clearAdminLoginRateLimiter('super@example.test');
        $this->clearAdminLoginRateLimiter('inactive@example.test');
        $this->clearAdminLoginRateLimiter('wrong@example.test');
    }

    public function test_valid_admin_lembaga_can_login_to_dashboard(): void
    {
        $lembaga = Lembaga::factory()->create();
        $user = User::factory()->adminLembaga($lembaga->id)->create([
            'email' => 'admin@example.test',
            'password' => 'StrongPassword123',
        ]);

        $this->get(route('login'));
        $sessionIdBefore = $this->app['session']->getId();

        $response = $this->post('/login', [
            'email' => 'admin@example.test',
            'password' => 'StrongPassword123',
        ]);

        $response->assertRedirect(route('admin.dashboard'));
        $this->assertAuthenticatedAs($user);
        $this->assertNotSame($sessionIdBefore, $this->app['session']->getId());

        $this->get(route('admin.dashboard'))
            ->assertOk()
            ->assertSee($user->name)
            ->assertSee('Admin Lembaga');

        $this->assertSame('success', AuditLog::query()->where('event', 'auth.login')->value('result'));
    }

    public function test_wrong_password_returns_generic_message_and_keeps_guest(): void
    {
        $lembaga = Lembaga::factory()->create();
        User::factory()->adminLembaga($lembaga->id)->create([
            'email' => 'admin@example.test',
            'password' => 'StrongPassword123',
        ]);

        $response = $this->from(route('login'))->post('/login', [
            'email' => 'admin@example.test',
            'password' => 'WrongPassword123',
        ]);

        $response->assertRedirect(route('login'));
        $response->assertSessionHasErrors([
            'email' => AdminAuthenticator::FAILURE_MESSAGE,
        ]);
        $this->assertGuest();
    }

    public function test_inactive_lembaga_returns_generic_message(): void
    {
        $lembaga = Lembaga::factory()->inactive()->create();
        User::factory()->adminLembaga($lembaga->id)->create([
            'email' => 'admin@example.test',
            'password' => 'StrongPassword123',
        ]);

        $response = $this->from(route('login'))->post('/login', [
            'email' => 'admin@example.test',
            'password' => 'StrongPassword123',
        ]);

        $response->assertRedirect(route('login'));
        $response->assertSessionHasErrors([
            'email' => AdminAuthenticator::FAILURE_MESSAGE,
        ]);
        $this->assertGuest();
    }

    public function test_inactive_user_returns_generic_message(): void
    {
        $lembaga = Lembaga::factory()->create();
        User::factory()->adminLembaga($lembaga->id)->inactive()->create([
            'email' => 'inactive@example.test',
            'password' => 'StrongPassword123',
        ]);

        $response = $this->from(route('login'))->post('/login', [
            'email' => 'inactive@example.test',
            'password' => 'StrongPassword123',
        ]);

        $response->assertRedirect(route('login'));
        $response->assertSessionHasErrors([
            'email' => AdminAuthenticator::FAILURE_MESSAGE,
        ]);
        $this->assertGuest();
    }

    public function test_super_admin_mfa_required_pending_then_totp_success_and_wrong_code_fails(): void
    {
        config(['security.mfa.super_admin_required' => true]);

        $secret = 'JBSWY3DPEHPK3PXP';
        $user = User::factory()->withMfa($secret)->create([
            'email' => 'super@example.test',
            'password' => 'StrongPassword123',
            'role' => 'super_admin',
            'lembaga_id' => null,
        ]);

        $this->post('/login', [
            'email' => 'super@example.test',
            'password' => 'StrongPassword123',
        ])->assertRedirect(route('login.mfa'));

        $this->assertGuest();
        $this->get(route('admin.dashboard'))->assertRedirect(route('login.mfa'));
        $this->get(route('login.mfa'))->assertOk();

        $this->from(route('login.mfa'))->post('/login/mfa', [
            'code' => '000000',
        ])->assertRedirect(route('login.mfa'))
            ->assertSessionHasErrors([
                'code' => 'Kode autentikasi tidak valid',
            ]);

        $this->assertGuest();

        $sessionIdBeforeMfa = $this->app['session']->getId();
        $code = app(TotpVerifier::class)->currentCode($secret);

        $this->post('/login/mfa', ['code' => $code])
            ->assertRedirect(route('admin.dashboard'));

        $this->assertAuthenticatedAs($user);
        $this->assertNotSame($sessionIdBeforeMfa, $this->app['session']->getId());
        $this->assertSame('failed', AuditLog::query()->where('event', 'auth.mfa')->where('result', 'failed')->value('result'));
        $this->assertSame('success', AuditLog::query()->where('event', 'auth.mfa')->where('result', 'success')->value('result'));
    }

    public function test_super_admin_direct_login_when_mfa_required_false(): void
    {
        config(['security.mfa.super_admin_required' => false]);

        $user = User::factory()->create([
            'email' => 'super@example.test',
            'password' => 'StrongPassword123',
            'role' => 'super_admin',
            'lembaga_id' => null,
        ]);

        $this->post('/login', [
            'email' => 'super@example.test',
            'password' => 'StrongPassword123',
        ])->assertRedirect(route('admin.dashboard'));

        $this->assertAuthenticatedAs($user);
    }

    public function test_login_is_throttled_after_five_failed_attempts(): void
    {
        $email = 'wrong@example.test';
        $this->clearAdminLoginRateLimiter($email);

        $lembaga = Lembaga::factory()->create();
        User::factory()->adminLembaga($lembaga->id)->create([
            'email' => $email,
            'password' => 'StrongPassword123',
        ]);

        for ($i = 0; $i < 5; $i++) {
            $this->from(route('login'))->post('/login', [
                'email' => $email,
                'password' => 'WrongPassword123',
            ])->assertRedirect(route('login'));
        }

        $this->post('/login', [
            'email' => $email,
            'password' => 'WrongPassword123',
        ])->assertStatus(429);
    }

    public function test_logout_clears_authentication(): void
    {
        $lembaga = Lembaga::factory()->create();
        $user = User::factory()->adminLembaga($lembaga->id)->create([
            'email' => 'admin@example.test',
            'password' => 'StrongPassword123',
        ]);

        $this->actingAs($user)
            ->post('/logout')
            ->assertRedirect(route('login'));

        $this->assertGuest();
        $this->assertSame('success', AuditLog::query()->where('event', 'auth.logout')->value('result'));
    }

    public function test_ensure_user_is_active_revokes_inactive_user_session(): void
    {
        $lembaga = Lembaga::factory()->create();
        $user = User::factory()->adminLembaga($lembaga->id)->create([
            'email' => 'admin@example.test',
            'password' => 'StrongPassword123',
        ]);

        $this->actingAs($user);

        $user->forceFill(['is_active' => false])->save();

        $this->get(route('admin.dashboard'))
            ->assertRedirect(route('login'));

        $this->assertGuest();
        $this->assertSame('auth.session_revoked', AuditLog::query()->where('event', 'auth.session_revoked')->value('event'));
        $this->assertSame('user_inactive', AuditLog::query()->where('event', 'auth.session_revoked')->value('metadata')['reason'] ?? null);
    }

    public function test_ensure_user_is_active_revokes_session_when_lembaga_inactive(): void
    {
        $lembaga = Lembaga::factory()->create();
        $user = User::factory()->adminLembaga($lembaga->id)->create([
            'email' => 'admin@example.test',
            'password' => 'StrongPassword123',
        ]);

        $this->actingAs($user);

        $lembaga->forceFill(['is_active' => false])->save();

        $this->get(route('admin.dashboard'))
            ->assertRedirect(route('login'));

        $this->assertGuest();
        $this->assertSame('lembaga_inactive', AuditLog::query()->where('event', 'auth.session_revoked')->value('metadata')['reason'] ?? null);
    }

    public function test_audit_logs_do_not_contain_password_or_totp_plaintext(): void
    {
        config(['security.mfa.super_admin_required' => true]);

        $secret = 'JBSWY3DPEHPK3PXP';
        $password = 'StrongPassword123';
        User::factory()->withMfa($secret)->create([
            'email' => 'super@example.test',
            'password' => $password,
            'role' => 'super_admin',
            'lembaga_id' => null,
        ]);

        $this->post('/login', [
            'email' => 'super@example.test',
            'password' => $password,
        ])->assertRedirect(route('login.mfa'));

        $code = app(TotpVerifier::class)->currentCode($secret);

        $this->post('/login/mfa', ['code' => $code])
            ->assertRedirect(route('admin.dashboard'));

        $payload = json_encode(AuditLog::query()->get()->toArray());

        $this->assertIsString($payload);
        $this->assertStringNotContainsString($password, $payload);
        $this->assertStringNotContainsString($code, $payload);
        $this->assertStringNotContainsString($secret, $payload);
    }

    public function test_login_page_renders_modern_guest_shell_with_branding(): void
    {
        $response = $this->get(route('login'));

        $response->assertOk()
            ->assertSee('Pusat Data')
            ->assertSee('Masuk ke panel administrasi.')
            ->assertSee('data-auth-shell', false)
            ->assertSee('data-auth-hero', false)
            ->assertSee('email')
            ->assertSee('password');
    }

    public function test_mfa_page_renders_shared_guest_shell(): void
    {
        config(['security.mfa.super_admin_required' => true]);

        $secret = 'JBSWY3DPEHPK3PXP';
        User::factory()->withMfa($secret)->create([
            'email' => 'super@example.test',
            'password' => 'StrongPassword123',
            'role' => 'super_admin',
            'lembaga_id' => null,
        ]);

        $this->post('/login', [
            'email' => 'super@example.test',
            'password' => 'StrongPassword123',
        ])->assertRedirect(route('login.mfa'));

        $this->get(route('login.mfa'))
            ->assertOk()
            ->assertSee('Verifikasi MFA')
            ->assertSee('data-auth-shell', false)
            ->assertSee('data-auth-hero', false)
            ->assertSee('Kode autentikasi');
    }

    private function clearAdminLoginRateLimiter(string $email, string $ip = '127.0.0.1'): void
    {
        $email = Str::lower($email);

        RateLimiter::clear(md5('admin-login'.$email.'|'.$ip));
        RateLimiter::clear(md5('admin-login'.'ip:'.$ip));
    }
}
