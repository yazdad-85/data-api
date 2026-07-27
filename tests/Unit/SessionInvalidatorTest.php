<?php

namespace Tests\Unit;

use App\Models\User;
use App\Services\Auth\SessionInvalidator;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class SessionInvalidatorTest extends TestCase
{
    use RefreshDatabase;

    public function test_deletes_database_sessions_for_user(): void
    {
        config(['session.driver' => 'database']);

        $user = User::factory()->create();
        $other = User::factory()->create();

        DB::table('sessions')->insert([
            [
                'id' => 'session-target',
                'user_id' => $user->id,
                'ip_address' => '127.0.0.1',
                'user_agent' => 'PHPUnit',
                'payload' => base64_encode('target'),
                'last_activity' => time(),
            ],
            [
                'id' => 'session-other',
                'user_id' => $other->id,
                'ip_address' => '127.0.0.1',
                'user_agent' => 'PHPUnit',
                'payload' => base64_encode('other'),
                'last_activity' => time(),
            ],
        ]);

        app(SessionInvalidator::class)->invalidateUser($user->id);

        $this->assertDatabaseMissing('sessions', ['id' => 'session-target']);
        $this->assertDatabaseHas('sessions', ['id' => 'session-other']);
    }

    public function test_logs_out_current_user_when_invalidating_self(): void
    {
        $user = User::factory()->create();
        Auth::login($user);

        $this->assertTrue(Auth::check());
        $this->assertSame((string) $user->id, (string) Auth::id());

        app(SessionInvalidator::class)->invalidateUser($user->id);

        $this->assertFalse(Auth::check());
        $this->assertNull(Auth::id());
    }

    public function test_invalidating_different_user_does_not_logout_current_user(): void
    {
        $current = User::factory()->create();
        $other = User::factory()->create();

        Auth::login($current);

        app(SessionInvalidator::class)->invalidateUser($other->id);

        $this->assertTrue(Auth::check());
        $this->assertSame((string) $current->id, (string) Auth::id());
    }

    public function test_invalidate_other_sessions_keeps_excepted_session(): void
    {
        config(['session.driver' => 'database']);

        $user = User::factory()->create();

        DB::table('sessions')->insert([
            [
                'id' => 'session-keep',
                'user_id' => $user->id,
                'ip_address' => '127.0.0.1',
                'user_agent' => 'PHPUnit',
                'payload' => base64_encode('keep'),
                'last_activity' => time(),
            ],
            [
                'id' => 'session-drop',
                'user_id' => $user->id,
                'ip_address' => '127.0.0.1',
                'user_agent' => 'PHPUnit',
                'payload' => base64_encode('drop'),
                'last_activity' => time(),
            ],
        ]);

        app(SessionInvalidator::class)->invalidateOtherSessions($user->id, 'session-keep');

        $this->assertDatabaseHas('sessions', ['id' => 'session-keep']);
        $this->assertDatabaseMissing('sessions', ['id' => 'session-drop']);
    }

    public function test_invalidate_other_sessions_does_not_logout_current_user(): void
    {
        $user = User::factory()->create();
        Auth::login($user);

        app(SessionInvalidator::class)->invalidateOtherSessions($user->id, 'any-session-id');

        $this->assertTrue(Auth::check());
        $this->assertSame((string) $user->id, (string) Auth::id());
    }
}
