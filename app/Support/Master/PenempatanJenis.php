<?php

namespace App\Support\Master;

final class PenempatanJenis
{
    public const AWAL = 'awal';

    public const KENAIKAN = 'kenaikan';

    public const PINDAH_KELAS = 'pindah_kelas';

    public const MUTASI_MASUK = 'mutasi_masuk';

    public const MUTASI_KELUAR = 'mutasi_keluar';

    public const LULUS = 'lulus';

    public const ALL = [
        self::AWAL,
        self::KENAIKAN,
        self::PINDAH_KELAS,
        self::MUTASI_MASUK,
        self::MUTASI_KELUAR,
        self::LULUS,
    ];
}
