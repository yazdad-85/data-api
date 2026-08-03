<?php

namespace App\Services\Siswa;

use App\Models\Kelas;
use App\Models\Siswa;
use App\Support\Master\SiswaStatus;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Shared\Date as ExcelDate;

final class SiswaImporter
{
    public function __construct(
        private readonly SiswaLifecycleService $lifecycle,
    ) {}

    /**
     * @return array{success: int, failed: int, errors: list<array{row: int, message: string}>}
     */
    public function import(UploadedFile $file, Kelas $kelas): array
    {
        $spreadsheet = IOFactory::load($file->getRealPath());
        $sheet = $spreadsheet->getSheetByName('Data Siswa');

        if ($sheet === null) {
            if ($spreadsheet->getSheetByName('Data Kelas') !== null) {
                return [
                    'success' => 0,
                    'failed' => 0,
                    'errors' => [[
                        'row' => 1,
                        'message' => 'File ini tampak template import kelas. Untuk siswa, unduh "template siswa" dari halaman detail kelas ini.',
                    ]],
                ];
            }

            $sheet = $spreadsheet->getSheetCount() > 1
                ? $spreadsheet->getSheet(1)
                : $spreadsheet->getSheet(0);
        }

        $rows = $sheet->toArray(null, true, true, true);
        $headerRow = array_shift($rows);

        if ($headerRow === null) {
            return [
                'success' => 0,
                'failed' => 0,
                'errors' => [['row' => 1, 'message' => 'Sheet Data Siswa kosong.']],
            ];
        }

        $columnMap = $this->mapHeaders($headerRow);
        $required = ['nis', 'nama'];

        $headerValues = array_values($columnMap);
        if (in_array('tahun_ajaran', $headerValues, true) && ! in_array('nis', $headerValues, true)) {
            return [
                'success' => 0,
                'failed' => 0,
                'errors' => [[
                    'row' => 1,
                    'message' => 'File ini tampak template import kelas. Untuk siswa, unduh "template siswa" dari halaman detail kelas ini.',
                ]],
            ];
        }

        foreach ($required as $column) {
            if (! in_array($column, $columnMap, true)) {
                return [
                    'success' => 0,
                    'failed' => 0,
                    'errors' => [['row' => 1, 'message' => "Kolom wajib \"{$column}\" tidak ditemukan. Gunakan template import siswa (kolom: nis, nama, …). Tahun ajaran diisi otomatis dari kelas."]],
                ];
            }
        }

        $success = 0;
        $failed = 0;
        $errors = [];
        $lembagaId = (string) $kelas->lembaga_id;
        $seenNis = [];

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

            if (isset($seenNis[$validated['nis']])) {
                $failed++;
                $errors[] = [
                    'row' => $excelRow,
                    'message' => "NIS {$validated['nis']} muncul lebih dari sekali di file import.",
                ];

                continue;
            }

            $seenNis[$validated['nis']] = true;
            $existing = $this->findExistingSiswa($lembagaId, $validated['nis']);

            if ($existing?->trashed()) {
                $failed++;
                $errors[] = [
                    'row' => $excelRow,
                    'message' => "NIS {$validated['nis']} sudah digunakan di lembaga ini.",
                ];

                continue;
            }

            if ($existing !== null && ! hash_equals((string) $existing->kelas_id, (string) $kelas->id)) {
                $failed++;
                $errors[] = [
                    'row' => $excelRow,
                    'message' => "NIS {$validated['nis']} sudah terdaftar di kelas lain. Update lewat import hanya untuk siswa di kelas ini.",
                ];

                continue;
            }

            try {
                $this->assertNisnAvailable($lembagaId, $validated['nisn'], $existing?->id);
            } catch (InvalidArgumentException $exception) {
                $failed++;
                $errors[] = ['row' => $excelRow, 'message' => $exception->getMessage()];

                continue;
            }

            DB::transaction(function () use ($kelas, $lembagaId, $validated, $existing) {
                if ($existing !== null) {
                    $existing->fill($this->updatePayload($validated));
                    $existing->save();

                    return;
                }

                $this->createAndPlace($kelas, $lembagaId, $validated);
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
        foreach (['nis', 'nama'] as $field) {
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
        $nis = trim((string) ($payload['nis'] ?? ''));
        if ($nis === '') {
            throw new InvalidArgumentException('NIS wajib diisi.');
        }
        if (strlen($nis) > 30) {
            throw new InvalidArgumentException('NIS maksimal 30 karakter.');
        }

        $nama = trim((string) ($payload['nama'] ?? ''));
        if ($nama === '') {
            throw new InvalidArgumentException('Nama wajib diisi.');
        }
        if (strlen($nama) > 150) {
            throw new InvalidArgumentException('Nama maksimal 150 karakter.');
        }

        $jenisKelamin = trim((string) ($payload['jenis_kelamin'] ?? ''));
        if ($jenisKelamin !== '' && ! in_array(strtoupper($jenisKelamin), ['L', 'P'], true)) {
            throw new InvalidArgumentException('Jenis kelamin harus L atau P.');
        }

        $tanggalLahir = $this->parseDate($payload['tanggal_lahir'] ?? null);

        $email = trim((string) ($payload['email'] ?? ''));
        if ($email !== '' && ! filter_var($email, FILTER_VALIDATE_EMAIL)) {
            throw new InvalidArgumentException('Format email tidak valid.');
        }

        return [
            'nis' => $nis,
            'nama' => $nama,
            'nisn' => $this->nullableString($payload['nisn'] ?? null, 30),
            'jenis_kelamin' => $jenisKelamin !== '' ? strtoupper($jenisKelamin) : null,
            'tempat_lahir' => $this->nullableString($payload['tempat_lahir'] ?? null, 100),
            'tanggal_lahir' => $tanggalLahir,
            'email' => $email !== '' ? $email : null,
            'telepon' => $this->nullableString($payload['telepon'] ?? null, 30),
            'alamat' => $this->nullableString($payload['alamat'] ?? null),
            'nama_wali' => $this->nullableString($payload['nama_wali'] ?? null, 150),
            'telepon_wali' => $this->nullableString($payload['telepon_wali'] ?? null, 30),
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

    private function findExistingSiswa(string $lembagaId, string $nis): ?Siswa
    {
        return Siswa::withTrashed()
            ->where('lembaga_id', $lembagaId)
            ->where('nis', $nis)
            ->first();
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

    /**
     * @param  array<string, mixed>  $validated
     * @return array<string, mixed>
     */
    private function updatePayload(array $validated): array
    {
        $payload = ['nama' => $validated['nama']];

        foreach (['nisn', 'jenis_kelamin', 'tempat_lahir', 'tanggal_lahir', 'email', 'telepon', 'alamat', 'nama_wali', 'telepon_wali'] as $field) {
            if ($validated[$field] !== null) {
                $payload[$field] = $validated[$field];
            }
        }

        return $payload;
    }

    /**
     * @param  array<string, mixed>  $validated
     */
    private function createAndPlace(Kelas $kelas, string $lembagaId, array $validated): void
    {
        // Buat sebagai calon lalu tempatkan via service agar siswa berakhir
        // "aktif" dengan tepat satu penempatan terbuka jenis "awal".
        $siswa = Siswa::query()->create([
            ...$validated,
            'lembaga_id' => $lembagaId,
            'kelas_id' => null,
            'tahun_ajaran_id' => null,
            'status_siswa' => SiswaStatus::CALON,
            'is_active' => false,
        ]);

        $this->lifecycle->tempatkan($siswa, $kelas);
    }
}
