<?php

namespace App\Support\Master;

use App\Models\Lembaga;
use InvalidArgumentException;

final class LembagaKodeGenerator
{
    private const PREFIX = 'LBG-';

    public function generate(): string
    {
        $max = Lembaga::withTrashed()
            ->where('kode', 'like', self::PREFIX.'%')
            ->lockForUpdate()
            ->pluck('kode')
            ->map(fn (string $kode) => $this->parseSequence($kode))
            ->filter()
            ->max();

        $next = ($max ?? 0) + 1;

        if ($next > 999) {
            throw new InvalidArgumentException('Urutan kode lembaga sudah penuh (maks. 999).');
        }

        return self::PREFIX.str_pad((string) $next, 3, '0', STR_PAD_LEFT);
    }

    private function parseSequence(string $kode): ?int
    {
        if (! preg_match('/^'.preg_quote(self::PREFIX, '/').'(\d+)$/', $kode, $matches)) {
            return null;
        }

        return (int) $matches[1];
    }
}
