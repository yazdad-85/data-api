<?php

namespace App\Services\Kelas;

use App\Models\Guru;
use App\Models\Kelas;
use App\Models\Lembaga;
use App\Models\TahunAjaran;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;
use PhpOffice\PhpSpreadsheet\IOFactory;

final class KelasImporter
{
    /**
     * @return array{success: int, failed: int, errors: list<array{row: int, message: string}>}
     */
    public function import(UploadedFile $file, Lembaga $lembaga, string $lembagaId): array
    {
        $spreadsheet = IOFactory::load($file->getRealPath());
        $sheet = $spreadsheet->getSheetByName('Data Kelas') ?? $spreadsheet->getSheet(1);

        $rows = $sheet->toArray(null, true, true, true);
        $headerRow = array_shift($rows);

        if ($headerRow === null) {
            return [
                'success' => 0,
                'failed' => 0,
                'errors' => [['row' => 1, 'message' => 'Sheet Data Kelas kosong.']],
            ];
        }

        $columnMap = $this->mapHeaders($headerRow);
        $required = ['nama', 'tahun_ajaran'];

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
                $validated = $this->validateRow($payload, $lembagaId);
            } catch (InvalidArgumentException $exception) {
                $failed++;
                $errors[] = ['row' => $excelRow, 'message' => $exception->getMessage()];

                continue;
            }

            if ($this->namaExists($lembagaId, $validated['tahun_ajaran_id'], $validated['nama'])) {
                $failed++;
                $errors[] = [
                    'row' => $excelRow,
                    'message' => "Kelas dengan nama \"{$validated['nama']}\" sudah ada pada tahun ajaran ini.",
                ];

                continue;
            }

            DB::transaction(function () use ($lembagaId, $validated) {
                Kelas::query()->create([
                    'lembaga_id' => $lembagaId,
                    'tahun_ajaran_id' => $validated['tahun_ajaran_id'],
                    'nama' => $validated['nama'],
                    'tingkat' => $validated['tingkat'],
                    'wali_kelas_guru_id' => $validated['wali_kelas_guru_id'],
                ]);
            });

            $success++;
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
        foreach (['nama', 'tahun_ajaran'] as $field) {
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
    private function validateRow(array $payload, string $lembagaId): array
    {
        $nama = trim((string) ($payload['nama'] ?? ''));
        if ($nama === '') {
            throw new InvalidArgumentException('Nama wajib diisi.');
        }
        if (strlen($nama) > 50) {
            throw new InvalidArgumentException('Nama maksimal 50 karakter.');
        }

        $tahunAjaranNama = trim((string) ($payload['tahun_ajaran'] ?? ''));
        if ($tahunAjaranNama === '') {
            throw new InvalidArgumentException('Tahun ajaran wajib diisi.');
        }

        $tahunAjaran = TahunAjaran::query()
            ->where('lembaga_id', $lembagaId)
            ->where('nama', $tahunAjaranNama)
            ->first();

        if ($tahunAjaran === null) {
            throw new InvalidArgumentException("Tahun ajaran \"{$tahunAjaranNama}\" tidak ditemukan.");
        }

        $tingkat = trim((string) ($payload['tingkat'] ?? ''));
        if ($tingkat !== '' && strlen($tingkat) > 20) {
            throw new InvalidArgumentException('Tingkat maksimal 20 karakter.');
        }

        $waliKelasGuruId = null;
        $waliKelasNiy = trim((string) ($payload['wali_kelas_niy'] ?? ''));
        if ($waliKelasNiy !== '') {
            $guru = Guru::query()
                ->where('lembaga_id', $lembagaId)
                ->where('niy', $waliKelasNiy)
                ->first();

            if ($guru === null) {
                throw new InvalidArgumentException("Guru dengan NIY \"{$waliKelasNiy}\" tidak ditemukan.");
            }

            $waliKelasGuruId = $guru->id;
        }

        return [
            'nama' => $nama,
            'tahun_ajaran_id' => $tahunAjaran->id,
            'tingkat' => $tingkat !== '' ? $tingkat : null,
            'wali_kelas_guru_id' => $waliKelasGuruId,
        ];
    }

    private function namaExists(string $lembagaId, string $tahunAjaranId, string $nama): bool
    {
        return Kelas::withTrashed()
            ->where('lembaga_id', $lembagaId)
            ->where('tahun_ajaran_id', $tahunAjaranId)
            ->where('nama', $nama)
            ->exists();
    }
}
