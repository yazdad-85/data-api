<?php

namespace Tests\Feature;

use App\Models\AuditLog;
use App\Models\User;
use App\Services\AuditLogger;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Route;
use RuntimeException;
use Tests\TestCase;

class SecurityFoundationTest extends TestCase
{
    use RefreshDatabase;

    public function test_install_super_admin_creates_first_super_admin_with_mfa_and_audit_log(): void
    {
        $this->artisan('install:super-admin', [
            '--name' => 'Super Admin',
            '--email' => 'super@example.test',
            '--password' => 'StrongPassword123',
        ])->assertSuccessful();

        $user = User::query()->where('email', 'super@example.test')->firstOrFail();

        $this->assertSame('super_admin', $user->role);
        $this->assertNull($user->lembaga_id);
        $this->assertTrue(Hash::check('StrongPassword123', $user->password));
        $this->assertNotNull($user->mfa_enabled_at);
        $this->assertNotEmpty($user->mfa_secret);
        $this->assertCount(8, $user->recovery_codes_hash);
        $this->assertStringNotContainsString('StrongPassword123', json_encode($user->recovery_codes_hash));

        $auditLog = AuditLog::query()->where('event', 'super_admin.bootstrap')->firstOrFail();

        $this->assertSame('success', $auditLog->result);
        $this->assertSame($user->id, $auditLog->subject_id);
        $this->assertSame('[REDACTED]', $auditLog->metadata['email']);
        $this->assertStringNotContainsString('StrongPassword123', json_encode($auditLog->metadata));
        $this->assertStringNotContainsString((string) $user->mfa_secret, json_encode($auditLog->metadata));
    }

    public function test_install_super_admin_is_rejected_when_super_admin_already_exists(): void
    {
        User::factory()->create([
            'email' => 'existing@example.test',
            'role' => 'super_admin',
            'lembaga_id' => null,
        ]);

        $this->artisan('install:super-admin', [
            '--name' => 'Second Super Admin',
            '--email' => 'second@example.test',
            '--password' => 'StrongPassword123',
        ])->assertFailed();

        $this->assertSame(1, User::query()->where('role', 'super_admin')->count());

        $auditLog = AuditLog::query()->where('event', 'super_admin.bootstrap')->firstOrFail();

        $this->assertSame('blocked', $auditLog->result);
        $this->assertSame('super_admin_exists', $auditLog->metadata['reason']);
    }

    public function test_install_super_admin_rejects_short_password_without_creating_user(): void
    {
        $this->artisan('install:super-admin', [
            '--name' => 'Super Admin',
            '--email' => 'super@example.test',
            '--password' => 'short',
        ])->assertFailed();

        $this->assertSame(0, User::query()->count());

        $auditLog = AuditLog::query()->where('event', 'super_admin.bootstrap')->firstOrFail();

        $this->assertSame('failed', $auditLog->result);
        $this->assertSame('validation_failed', $auditLog->metadata['reason']);
        $this->assertSame(['password'], $auditLog->metadata['fields']);
        $this->assertStringNotContainsString('short', json_encode($auditLog->metadata));
    }

    public function test_audit_logger_redacts_secret_and_pii_metadata(): void
    {
        app(AuditLogger::class)->record('api_key.rotate', 'success', [
            'safe' => 'ok',
            'password' => 'StrongPassword123',
            'api_key' => 'dc_live_PREFIX_SECRET',
            'nested' => [
                'email' => 'admin@example.test',
                'token' => 'secret-token',
            ],
        ]);

        $auditLog = AuditLog::query()->firstOrFail();

        $this->assertSame('ok', $auditLog->metadata['safe']);
        $this->assertSame('[REDACTED]', $auditLog->metadata['password']);
        $this->assertSame('[REDACTED]', $auditLog->metadata['api_key']);
        $this->assertSame('[REDACTED]', $auditLog->metadata['nested']['email']);
        $this->assertSame('[REDACTED]', $auditLog->metadata['nested']['token']);
        $this->assertStringNotContainsString('dc_live_PREFIX_SECRET', json_encode($auditLog->metadata));
    }

    public function test_audit_log_model_is_append_only(): void
    {
        $auditLog = app(AuditLogger::class)->record('user.deactivate', 'blocked', [
            'reason' => 'test',
        ]);

        $this->expectException(RuntimeException::class);

        $auditLog->update([
            'result' => 'success',
        ]);
    }

    public function test_audit_log_model_cannot_be_deleted(): void
    {
        $auditLog = app(AuditLogger::class)->record('user.deactivate', 'blocked', [
            'reason' => 'test',
        ]);

        $this->expectException(RuntimeException::class);

        $auditLog->delete();
    }

    public function test_request_id_middleware_sets_header_and_helper_value(): void
    {
        Route::get('/_test/request-id', fn () => response(request_id()));

        $response = $this->get('/_test/request-id', [
            'X-Request-ID' => 'req-test-123456',
        ]);

        $response->assertOk();
        $response->assertSeeText('req-test-123456');
        $response->assertHeader('X-Request-ID', 'req-test-123456');
    }
}
