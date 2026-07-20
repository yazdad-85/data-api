<?php

namespace App\Support\Ui;

final class AppEnvironmentLabel
{
    public static function fromEnv(?string $env = null): string
    {
        $env = strtolower((string) ($env ?? config('app.env')));

        return match ($env) {
            'local' => 'Lokal',
            'staging' => 'Staging',
            'production' => 'Produksi',
            default => $env !== '' ? $env : 'unknown',
        };
    }
}
