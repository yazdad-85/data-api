<?php

namespace App\Support\Security;

class MfaCredentialFactory
{
    private const BASE32_ALPHABET = 'ABCDEFGHIJKLMNOPQRSTUVWXYZ234567';
    private const RECOVERY_CODE_COUNT = 8;

    /**
     * @return array{secret: string, recovery_codes: array<int, string>}
     */
    public function generate(): array
    {
        return [
            'secret' => $this->base32(random_bytes(20)),
            'recovery_codes' => $this->recoveryCodes(),
        ];
    }

    private function base32(string $bytes): string
    {
        $bits = '';

        foreach (str_split($bytes) as $byte) {
            $bits .= str_pad(decbin(ord($byte)), 8, '0', STR_PAD_LEFT);
        }

        $secret = '';

        foreach (str_split($bits, 5) as $chunk) {
            $secret .= self::BASE32_ALPHABET[bindec(str_pad($chunk, 5, '0'))];
        }

        return $secret;
    }

    /**
     * @return array<int, string>
     */
    private function recoveryCodes(): array
    {
        $codes = [];

        for ($i = 0; $i < self::RECOVERY_CODE_COUNT; $i++) {
            $codes[] = strtoupper(bin2hex(random_bytes(4))).'-'.strtoupper(bin2hex(random_bytes(4)));
        }

        return $codes;
    }
}
