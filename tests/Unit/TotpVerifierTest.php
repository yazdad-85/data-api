<?php

namespace Tests\Unit;

use App\Models\User;
use App\Support\Security\TotpVerifier;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class TotpVerifierTest extends TestCase
{
    use RefreshDatabase;

    public function test_verifies_current_totp_for_known_secret(): void
    {
        $secret = 'JBSWY3DPEHPK3PXP';
        $verifier = new TotpVerifier;
        $user = User::factory()->withMfa($secret)->create();

        $this->assertTrue($verifier->verify($user, $verifier->currentCode($secret)));
    }

    public function test_rejects_wrong_totp_code(): void
    {
        $secret = 'JBSWY3DPEHPK3PXP';
        $verifier = new TotpVerifier;
        $user = User::factory()->withMfa($secret)->create();

        $this->assertFalse($verifier->verify($user, '000000'));
    }

    public function test_recovery_code_is_consumed_once(): void
    {
        $verifier = new TotpVerifier;
        $user = User::factory()->withMfa(
            recoveryCodes: ['ABCD-EFGH', 'CCCC-DDDD'],
        )->create();

        $this->assertTrue($verifier->verify($user, 'ABCD-EFGH'));
        $user->refresh();
        $this->assertCount(1, $user->recovery_codes_hash);

        $this->assertFalse($verifier->verify($user, 'ABCD-EFGH'));
        $user->refresh();
        $this->assertCount(1, $user->recovery_codes_hash);
    }
}
