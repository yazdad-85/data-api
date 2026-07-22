<?php

namespace App\Support\Master;

use App\Models\Guru;
use App\Models\Karyawan;
use App\Models\Lembaga;
use InvalidArgumentException;

final class GuruNiyGenerator
{
    public function generate(Lembaga $lembaga, string $jenisKelamin, int $tahunMasuk): string
    {
        $this->assertLembagaHasKode($lembaga);
        $this->assertJenisKelamin($jenisKelamin);

        $urut = $this->nextUrut($lembaga->id, $tahunMasuk);

        return $this->format(
            lembaga: $lembaga,
            jenisKelamin: $jenisKelamin,
            tahunMasuk: $tahunMasuk,
            urut: $urut,
        );
    }

    public function preview(Lembaga $lembaga, string $jenisKelamin, int $tahunMasuk): string
    {
        $this->assertLembagaHasKode($lembaga);
        $this->assertJenisKelamin($jenisKelamin);

        return $this->format(
            lembaga: $lembaga,
            jenisKelamin: $jenisKelamin,
            tahunMasuk: $tahunMasuk,
            urut: $this->countPersonel($lembaga->id, $tahunMasuk) + 1,
        );
    }

    private function nextUrut(string $lembagaId, int $tahunMasuk): int
    {
        // PostgreSQL rejects FOR UPDATE with aggregate COUNT(*).
        // Lock the lembaga row to serialize NIY allocation, then count.
        Lembaga::query()->whereKey($lembagaId)->lockForUpdate()->firstOrFail();

        $urut = $this->countPersonel($lembagaId, $tahunMasuk) + 1;

        if ($urut > 99) {
            throw new InvalidArgumentException(
                "Urutan personel untuk tahun masuk {$tahunMasuk} sudah penuh (maks. 99)."
            );
        }

        return $urut;
    }

    private function countPersonel(string $lembagaId, int $tahunMasuk): int
    {
        $guruCount = Guru::withoutGlobalScopes()
            ->withTrashed()
            ->where('lembaga_id', $lembagaId)
            ->where('tahun_masuk', $tahunMasuk)
            ->count();

        $karyawanCount = Karyawan::withoutGlobalScopes()
            ->withTrashed()
            ->where('lembaga_id', $lembagaId)
            ->where('tahun_masuk', $tahunMasuk)
            ->count();

        return $guruCount + $karyawanCount;
    }

    private function format(Lembaga $lembaga, string $jenisKelamin, int $tahunMasuk, int $urut): string
    {
        $npyp = (string) config('master.niy.npyp', '0488');
        $jkCode = $jenisKelamin === 'L' ? '01' : '02';
        $tahunSuffix = str_pad((string) ($tahunMasuk % 100), 2, '0', STR_PAD_LEFT);
        $urutCode = str_pad((string) $urut, 2, '0', STR_PAD_LEFT);

        return $npyp.$lembaga->niy_kode.$jkCode.$tahunSuffix.$urutCode;
    }

    private function assertLembagaHasKode(Lembaga $lembaga): void
    {
        if ($lembaga->niy_kode === null || $lembaga->niy_kode === '') {
            throw new InvalidArgumentException(
                'Lembaga belum memiliki kode NIY. Hubungi Super Admin untuk melengkapi data lembaga.'
            );
        }
    }

    private function assertJenisKelamin(string $jenisKelamin): void
    {
        if (! in_array($jenisKelamin, ['L', 'P'], true)) {
            throw new InvalidArgumentException('Jenis kelamin tidak valid.');
        }
    }
}
