<?php

namespace App\Services\Api;

final class ApiKeyVerifier
{
    public function matches(string $plainApiKey, string $storedDigest): bool
    {
        $pepper = (string) config('security.api_key_pepper');
        if ($pepper === '' || $storedDigest === '') {
            return false;
        }

        $computed = hash_hmac('sha256', $plainApiKey, $pepper);

        return hash_equals($storedDigest, $computed);
    }
}
