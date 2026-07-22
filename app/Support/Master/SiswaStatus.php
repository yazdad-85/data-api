<?php

namespace App\Support\Master;

final class SiswaStatus
{
    public const CALON = 'calon';

    public const MUTASI_MASUK = 'mutasi_masuk';

    public const AKTIF = 'aktif';

    public const MUTASI_KELUAR = 'mutasi_keluar';

    public const LULUS = 'lulus';

    public const ALL = [
        self::CALON,
        self::MUTASI_MASUK,
        self::AKTIF,
        self::MUTASI_KELUAR,
        self::LULUS,
    ];

    /** @return list<string> */
    public static function allowedTransitions(string $from): array
    {
        return match ($from) {
            self::CALON => [self::MUTASI_MASUK, self::AKTIF],
            self::MUTASI_MASUK => [self::AKTIF, self::MUTASI_KELUAR],
            self::AKTIF => [self::AKTIF, self::MUTASI_KELUAR, self::LULUS],
            default => [],
        };
    }

    public static function isActiveFlag(string $status): bool
    {
        return match ($status) {
            self::AKTIF => true,
            self::MUTASI_MASUK => true, // setelah penempatan; calon false; service set false jika belum kelas
            self::CALON, self::MUTASI_KELUAR, self::LULUS => false,
            default => false,
        };
    }

    public static function label(string $status): string
    {
        return match ($status) {
            self::CALON => 'Calon',
            self::MUTASI_MASUK => 'Mutasi masuk',
            self::AKTIF => 'Aktif',
            self::MUTASI_KELUAR => 'Mutasi keluar',
            self::LULUS => 'Lulus',
            default => $status,
        };
    }

    public static function tone(string $status): string
    {
        return match ($status) {
            self::CALON, self::MUTASI_MASUK => 'warn',
            self::AKTIF => 'ok',
            self::MUTASI_KELUAR => 'neutral',
            self::LULUS => 'brand',
            default => 'neutral',
        };
    }
}
