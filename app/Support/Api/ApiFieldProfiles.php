<?php

namespace App\Support\Api;

/**
 * Field profile ceiling helpers for API v1 resource lists (SPEC §4.3, design §6).
 *
 * Profiles are strictly ordered: minimal ⊂ academic ⊂ contact.
 */
final class ApiFieldProfiles
{
    public const MINIMAL = 'minimal';

    public const ACADEMIC = 'academic';

    public const CONTACT = 'contact';

    public const ALL = [self::MINIMAL, self::ACADEMIC, self::CONTACT];

    public static function rank(string $profile): int
    {
        return match ($profile) {
            self::MINIMAL => 0,
            self::ACADEMIC => 1,
            self::CONTACT => 2,
            default => -1,
        };
    }

    /**
     * A requested profile is allowed only if it is a valid profile and
     * does not exceed the client's assigned profile.
     */
    public static function allows(string $clientProfile, string $requested): bool
    {
        return self::rank($requested) >= 0
            && self::rank($requested) <= self::rank($clientProfile);
    }
}
