<?php

namespace App\Support\Master;

final class TahunAjaranNamer
{
    public static function fromTahunMulai(int $tahunMulai): string
    {
        return sprintf('%d/%d', $tahunMulai, $tahunMulai + 1);
    }
}
