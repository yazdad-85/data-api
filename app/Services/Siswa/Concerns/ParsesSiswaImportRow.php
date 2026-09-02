<?php

namespace App\Services\Siswa\Concerns;

use App\Models\Siswa;
use InvalidArgumentException;
use PhpOffice\PhpSpreadsheet\Shared\Date as ExcelDate;

/**
 * Helper parsing baris Excel yang dipakai bersama oleh importer siswa aktif
 * (SiswaImporter) dan importer calon murid SPMB (SiswaCalonImporter). Aturan
 * kolom wajib/opsional tetap didefinisikan masing-masing importer karena
 * keduanya punya kebutuhan berbeda.
 */
trait ParsesSiswaImportRow
{
    /**
     * @param  array<string, mixed>  $headerRow
     * @return array<string, string>
     */
    private function mapHeaders(array $headerRow): array
    {
        $map = [];

        foreach ($headerRow as $column => $label) {
            if (! is_string($label) && ! is_numeric($label)) {
                continue;
            }

            $normalized = $this->normalizeHeader((string) $label);
            if ($normalized !== '') {
                $map[$column] = $normalized;
            }
        }

        return $map;
    }

    private function normalizeHeader(string $label): string
    {
        $normalized = strtolower(trim($label));
        $normalized = str_replace([' ', '-'], '_', $normalized);

        return match ($normalized) {
            'jenis_penerimaan' => 'jenis_masuk',
            'diterima_di_lembaga_tanggal', 'tanggal_diterima', 'status_at' => 'diterima_tanggal',
            'asal', 'status_asal' => 'asal_lembaga',
            default => $normalized,
        };
    }

    /**
     * @param  array<string, mixed>  $row
     * @param  array<string, string>  $columnMap
     * @return array<string, mixed>
     */
    private function extractRow(array $row, array $columnMap): array
    {
        $payload = [];

        foreach ($columnMap as $column => $field) {
            $payload[$field] = $row[$column] ?? null;
        }

        return $payload;
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    private function isEmptyRow(array $payload): bool
    {
        foreach (['nis', 'nama'] as $field) {
            if (trim((string) ($payload[$field] ?? '')) !== '') {
                return false;
            }
        }

        return true;
    }

    private function normalizeJenisMasuk(mixed $value, ?string $asalLembaga): string
    {
        $string = strtolower(trim((string) ($value ?? '')));
        $string = str_replace([' ', '-'], '_', $string);

        if ($string === '') {
            return $asalLembaga !== null ? 'mutasi_masuk' : 'siswa_baru';
        }

        return match ($string) {
            'siswa_baru', 'baru' => 'siswa_baru',
            'mutasi_masuk', 'mutasi' => 'mutasi_masuk',
            default => throw new InvalidArgumentException('Jenis masuk harus Siswa Baru atau Mutasi Masuk.'),
        };
    }

    private function nullableString(mixed $value, ?int $max = null): ?string
    {
        $string = trim((string) ($value ?? ''));

        if ($string === '') {
            return null;
        }

        if ($max !== null && strlen($string) > $max) {
            throw new InvalidArgumentException("Nilai melebihi {$max} karakter.");
        }

        return $string;
    }

    private function nullableStatusKeluarga(mixed $value): ?string
    {
        $string = trim((string) ($value ?? ''));

        if ($string === '' || in_array($string, ['-', '—'], true)) {
            return null;
        }

        $normalized = strtolower(preg_replace('/\s+/', ' ', $string) ?? $string);
        $normalized = str_replace(['&'], ['dan'], $normalized);
        $normalized = str_replace([' - ', '_'], [' ', ' '], $normalized);
        $normalized = preg_replace('/\s*,\s*/', ', ', $normalized) ?? $normalized;

        return match ($normalized) {
            '1' => 'Yatim',
            '2' => 'Piatu',
            '3' => 'Yatim Piatu',
            '4' => 'Anak Guru, Staff, dan Karyawan',
            'yatim' => 'Yatim',
            'piatu' => 'Piatu',
            'yatim piatu', 'yatim_piatu' => 'Yatim Piatu',
            'anak guru, staff, dan karyawan',
            'anak guru staff dan karyawan',
            'anak guru, staf, dan karyawan',
            'anak guru staf dan karyawan' => 'Anak Guru, Staff, dan Karyawan',
            default => throw new InvalidArgumentException('Status keluarga harus kosong atau kode 1, 2, 3, 4.'),
        };
    }

    private function parseDate(mixed $value): ?string
    {
        if ($value === null || $value === '') {
            return null;
        }

        if (is_numeric($value)) {
            return ExcelDate::excelToDateTimeObject((float) $value)->format('Y-m-d');
        }

        $string = trim((string) $value);
        $timestamp = strtotime($string);

        if ($timestamp === false) {
            throw new InvalidArgumentException('Format tanggal_lahir tidak valid. Gunakan YYYY-MM-DD.');
        }

        return date('Y-m-d', $timestamp);
    }

    private function assertNisnAvailable(string $lembagaId, ?string $nisn, ?string $exceptSiswaId = null): void
    {
        if ($nisn === null) {
            return;
        }

        $exists = Siswa::withTrashed()
            ->where('lembaga_id', $lembagaId)
            ->where('nisn', $nisn)
            ->when($exceptSiswaId !== null, fn ($query) => $query->whereKeyNot($exceptSiswaId))
            ->exists();

        if ($exists) {
            throw new InvalidArgumentException("NISN {$nisn} sudah digunakan di lembaga ini.");
        }
    }
}
