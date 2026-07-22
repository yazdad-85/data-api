<?php

namespace App\Services\Karyawan;

use App\Models\Karyawan;
use App\Models\Lembaga;
use App\Support\Master\GuruNiyGenerator;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;
use PhpOffice\PhpSpreadsheet\IOFactory;

final class KaryawanImporter
{
    public function __construct(
        private readonly GuruNiyGenerator $niyGenerator,
    ) {}

    /**
     * @return array{success: int, failed: int, errors: list<array{row: int, message: string}>}
     */
    public function import(UploadedFile $file, Lembaga $lembaga, string $lembagaId): array
    {
        $spreadsheet = IOFactory::load($file->getRealPath());
        $sheet = $spreadsheet->getSheetByName('Data Karyawan') ?? $spreadsheet->getSheet(1);

        $rows = $sheet->toArray(null, true, true, true);
        $headerRow = array_shift($rows);

        if ($headerRow === null) {
            return [
                'success' => 0,
                'failed' => 0,
                'errors' => [['row' => 1, 'message' => 'Sheet Data Karyawan kosong.']],
            ];
        }

        $columnMap = $this->mapHeaders($headerRow);
        $required = ['nama', 'jenis_kelamin', 'tahun_masuk'];

        foreach ($required as $column) {
            if (! in_array($column, $columnMap, true)) {
                return [
                    'success' => 0,
                    'failed' => 0,
                    'errors' => [['row' => 1, 'message' => "Kolom wajib \"{$column}\" tidak ditemukan."]],
                ];
            }
        }

        $success = 0;
        $failed = 0;
        $errors = [];

        foreach ($rows as $rowNumber => $row) {
            $excelRow = (int) $rowNumber;
            $payload = $this->extractRow($row, $columnMap);

            if ($this->isEmptyRow($payload)) {
                continue;
            }

            try {
                $validated = $this->validateRow($payload);
            } catch (InvalidArgumentException $exception) {
                $failed++;
                $errors[] = ['row' => $excelRow, 'message' => $exception->getMessage()];

                continue;
            }

            if ($this->namaExists($lembagaId, $validated['nama'])) {
                $failed++;
                $errors[] = [
                    'row' => $excelRow,
                    'message' => "Karyawan dengan nama \"{$validated['nama']}\" sudah ada.",
                ];

                continue;
            }

            try {
                DB::transaction(function () use ($lembaga, $lembagaId, $validated) {
                    $nik = $this->niyGenerator->generate(
                        $lembaga,
                        $validated['jenis_kelamin'],
                        $validated['tahun_masuk'],
                    );

                    Karyawan::query()->create([
                        ...$validated,
                        'lembaga_id' => $lembagaId,
                        'nik_pegawai' => $nik,
                        'is_active' => true,
                    ]);
                });

                $success++;
            } catch (InvalidArgumentException $exception) {
                $failed++;
                $errors[] = ['row' => $excelRow, 'message' => $exception->getMessage()];
            }
        }

        return compact('success', 'failed', 'errors');
    }

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

            $normalized = strtolower(trim((string) $label));
            if ($normalized !== '') {
                $map[$column] = $normalized;
            }
        }

        return $map;
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
        foreach (['nama', 'jenis_kelamin', 'tahun_masuk'] as $field) {
            if (trim((string) ($payload[$field] ?? '')) !== '') {
                return false;
            }
        }

        return true;
    }

    /**
     * @param  array<string, mixed>  $payload
     * @return array<string, mixed>
     */
    private function validateRow(array $payload): array
    {
        $nama = trim((string) ($payload['nama'] ?? ''));
        if ($nama === '') {
            throw new InvalidArgumentException('Nama wajib diisi.');
        }
        if (strlen($nama) > 150) {
            throw new InvalidArgumentException('Nama maksimal 150 karakter.');
        }

        $jenisKelamin = strtoupper(trim((string) ($payload['jenis_kelamin'] ?? '')));
        if (! in_array($jenisKelamin, ['L', 'P'], true)) {
            throw new InvalidArgumentException('Jenis kelamin harus L atau P.');
        }

        $tahunMasukRaw = trim((string) ($payload['tahun_masuk'] ?? ''));
        if ($tahunMasukRaw === '' || ! ctype_digit($tahunMasukRaw)) {
            throw new InvalidArgumentException('Tahun masuk wajib berupa angka 4 digit.');
        }

        $tahunMasuk = (int) $tahunMasukRaw;
        $year = (int) now()->year;
        if ($tahunMasuk < 1950 || $tahunMasuk > $year + 1) {
            throw new InvalidArgumentException("Tahun masuk harus antara 1950 dan {$year}.");
        }

        $email = trim((string) ($payload['email'] ?? ''));
        if ($email !== '' && ! filter_var($email, FILTER_VALIDATE_EMAIL)) {
            throw new InvalidArgumentException('Format email tidak valid.');
        }

        return [
            'nama' => $nama,
            'jenis_kelamin' => $jenisKelamin,
            'tahun_masuk' => $tahunMasuk,
            'jabatan' => $this->nullableString($payload['jabatan'] ?? null, 100),
            'email' => $email !== '' ? $email : null,
            'telepon' => $this->nullableString($payload['telepon'] ?? null, 30),
            'alamat' => $this->nullableString($payload['alamat'] ?? null),
        ];
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

    private function namaExists(string $lembagaId, string $nama): bool
    {
        $query = Karyawan::query()->where('lembaga_id', $lembagaId);

        if ($query->getConnection()->getDriverName() === 'pgsql') {
            return $query->where('nama', 'ilike', $nama)->exists();
        }

        return $query->whereRaw('lower(nama) = lower(?)', [$nama])->exists();
    }
}
