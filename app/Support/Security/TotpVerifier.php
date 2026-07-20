<?php

namespace App\Support\Security;

use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;

class TotpVerifier
{
    private const BASE32_ALPHABET = 'ABCDEFGHIJKLMNOPQRSTUVWXYZ234567';

    public function verify(User $user, string $code): bool
    {
        $code = strtoupper(trim($code));

        if ($code === '') {
            return false;
        }

        if (str_contains($code, '-')) {
            return $this->consumeRecoveryCode($user, $code);
        }

        $secret = (string) $user->mfa_secret;
        if ($secret === '') {
            return false;
        }

        $normalized = preg_replace('/\s+/', '', $code) ?? '';
        if (! preg_match('/^\d{6}$/', $normalized)) {
            return false;
        }

        $window = (int) config('security.mfa.totp_window', 1);
        $timeSlice = intdiv(time(), 30);

        for ($i = -$window; $i <= $window; $i++) {
            if (hash_equals($this->hotp($secret, $timeSlice + $i), $normalized)) {
                return true;
            }
        }

        return false;
    }

    private function consumeRecoveryCode(User $user, string $code): bool
    {
        return (bool) DB::transaction(function () use ($user, $code): bool {
            $locked = User::query()
                ->whereKey($user->getKey())
                ->lockForUpdate()
                ->first();

            if ($locked === null) {
                return false;
            }

            $hashes = $locked->recovery_codes_hash ?? [];
            if (! is_array($hashes) || $hashes === []) {
                return false;
            }

            foreach ($hashes as $index => $hash) {
                if (is_string($hash) && Hash::check($code, $hash)) {
                    unset($hashes[$index]);
                    $remaining = array_values($hashes);

                    $locked->forceFill([
                        'recovery_codes_hash' => $remaining,
                    ])->save();

                    $user->forceFill([
                        'recovery_codes_hash' => $remaining,
                    ]);

                    return true;
                }
            }

            return false;
        });
    }

    public function currentCode(string $base32Secret): string
    {
        return $this->hotp($base32Secret, intdiv(time(), 30));
    }

    private function hotp(string $base32Secret, int $counter): string
    {
        $secret = $this->base32Decode($base32Secret);
        $binaryCounter = pack('N*', 0, $counter);
        $hash = hash_hmac('sha1', $binaryCounter, $secret, true);
        $offset = ord($hash[19]) & 0x0F;
        $value = (
            ((ord($hash[$offset]) & 0x7F) << 24)
            | ((ord($hash[$offset + 1]) & 0xFF) << 16)
            | ((ord($hash[$offset + 2]) & 0xFF) << 8)
            | (ord($hash[$offset + 3]) & 0xFF)
        );

        return str_pad((string) ($value % 1_000_000), 6, '0', STR_PAD_LEFT);
    }

    private function base32Decode(string $input): string
    {
        $input = strtoupper(preg_replace('/[^A-Z2-7]/', '', $input) ?? '');
        $buffer = 0;
        $bitsLeft = 0;
        $result = '';

        foreach (str_split($input) as $char) {
            $value = strpos(self::BASE32_ALPHABET, $char);
            if ($value === false) {
                continue;
            }
            $buffer = ($buffer << 5) | $value;
            $bitsLeft += 5;
            if ($bitsLeft >= 8) {
                $bitsLeft -= 8;
                $result .= chr(($buffer >> $bitsLeft) & 0xFF);
            }
        }

        return $result;
    }
}
