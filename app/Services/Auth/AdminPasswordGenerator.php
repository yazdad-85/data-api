<?php

namespace App\Services\Auth;

final class AdminPasswordGenerator
{
    private const LENGTH = 16;

    /** URL/copy-safe alphabet (no ambiguous O/0/I/l). */
    private const ALPHABET = 'ABCDEFGHJKLMNPQRSTUVWXYZabcdefghijkmnopqrstuvwxyz23456789';

    public function generate(): string
    {
        $max = strlen(self::ALPHABET) - 1;

        do {
            $password = '';

            for ($i = 0; $i < self::LENGTH; $i++) {
                $password .= self::ALPHABET[random_int(0, $max)];
            }
        } while (! preg_match('/[A-Za-z]/', $password) || ! preg_match('/[0-9]/', $password));

        return $password;
    }
}
