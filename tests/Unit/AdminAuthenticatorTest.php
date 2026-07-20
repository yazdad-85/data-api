<?php

namespace Tests\Unit;

use App\Models\AuditLog;
use App\Models\Lembaga;
use App\Models\User;
use App\Services\Auth\AdminAuthenticator;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Tests\TestCase;

class AdminAuthenticatorTest extends TestCase
{
    use RefreshDatabase;

    public function test_invalid_password_returns_generic_failure_and_audits_invalid_credentials(): void
    {
        $lembaga = Lembaga::factory()->create();
        User::factory()->adminLembaga($lembaga->id)->create([
            'email' => 'admin@example.test',
            'password' => 'StrongPassword123',
        ]);

        $authenticator = app(AdminAuthenticator::class);
        $request = Request::create('/login', 'POST');

        $result = $authenticator->attempt('admin@example.test', 'WrongPassword!', $request);

        $this->assertFalse($result['ok']);
        $this->assertSame(AdminAuthenticator::FAILURE_MESSAGE, $result['message']);
        $this->assertSame('invalid_credentials', $result['reason']);

        $audit = AuditLog::query()->where('event', 'auth.login')->firstOrFail();
        $this->assertSame('failed', $audit->result);
        $this->assertSame('invalid_credentials', $audit->metadata['reason']);
    }

    public function test_inactive_user_returns_generic_failure_and_audits_user_inactive(): void
    {
        $lembaga = Lembaga::factory()->create();
        User::factory()->adminLembaga($lembaga->id)->inactive()->create([
            'email' => 'inactive@example.test',
            'password' => 'StrongPassword123',
        ]);

        $authenticator = app(AdminAuthenticator::class);
        $request = Request::create('/login', 'POST');

        $result = $authenticator->attempt('inactive@example.test', 'StrongPassword123', $request);

        $this->assertFalse($result['ok']);
        $this->assertSame(AdminAuthenticator::FAILURE_MESSAGE, $result['message']);
        $this->assertSame('user_inactive', $result['reason']);

        $audit = AuditLog::query()->where('event', 'auth.login')->firstOrFail();
        $this->assertSame('failed', $audit->result);
        $this->assertSame('user_inactive', $audit->metadata['reason']);
    }

    public function test_admin_lembaga_with_inactive_lembaga_returns_generic_failure(): void
    {
        $lembaga = Lembaga::factory()->inactive()->create();
        User::factory()->adminLembaga($lembaga->id)->create([
            'email' => 'tenant@example.test',
            'password' => 'StrongPassword123',
        ]);

        $authenticator = app(AdminAuthenticator::class);
        $request = Request::create('/login', 'POST');

        $result = $authenticator->attempt('tenant@example.test', 'StrongPassword123', $request);

        $this->assertFalse($result['ok']);
        $this->assertSame(AdminAuthenticator::FAILURE_MESSAGE, $result['message']);
        $this->assertSame('lembaga_inactive', $result['reason']);

        $audit = AuditLog::query()->where('event', 'auth.login')->firstOrFail();
        $this->assertSame('failed', $audit->result);
        $this->assertSame('lembaga_inactive', $audit->metadata['reason']);
    }

    public function test_valid_admin_lembaga_with_active_lembaga_succeeds(): void
    {
        $lembaga = Lembaga::factory()->create();
        $user = User::factory()->adminLembaga($lembaga->id)->create([
            'email' => 'ok@example.test',
            'password' => 'StrongPassword123',
        ]);

        $authenticator = app(AdminAuthenticator::class);
        $request = Request::create('/login', 'POST');

        $result = $authenticator->attempt('ok@example.test', 'StrongPassword123', $request);

        $this->assertTrue($result['ok']);
        $this->assertTrue($result['user']->is($user));
        $this->assertSame(0, AuditLog::query()->where('event', 'auth.login')->count());
    }
}
