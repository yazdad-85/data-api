<?php

namespace App\Services\Siswa;

use App\Models\Kelas;
use App\Models\Siswa;
use App\Models\SiswaPenempatan;
use App\Support\Master\PenempatanJenis;
use App\Support\Master\SiswaStatus;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Carbon;
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
                    $this->updateExisting($existing, $validated);

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
        $asalLembaga = $this->nullableString($payload['asal_lembaga'] ?? null, 150);
        $jenisMasuk = $this->normalizeJenisMasuk($payload['jenis_masuk'] ?? null, $asalLembaga);
        $diterimaTanggal = $this->parseDiterimaTanggal($payload, $jenisMasuk);

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
            'status_keluarga' => $this->nullableStatusKeluarga($payload['status_keluarga'] ?? null),
            'nama_ayah' => $this->nullableString($payload['nama_ayah'] ?? null, 150),
            'pekerjaan_ayah' => $this->nullableString($payload['pekerjaan_ayah'] ?? null, 100),
            'nama_ibu' => $this->nullableString($payload['nama_ibu'] ?? null, 150),
            'pekerjaan_ibu' => $this->nullableString($payload['pekerjaan_ibu'] ?? null, 100),
            'nama_wali' => $this->nullableString($payload['nama_wali'] ?? null, 150),
            'telepon_wali' => $this->nullableString($payload['telepon_wali'] ?? null, 30),
            'status_asal' => $asalLembaga,
            'jenis_masuk' => $jenisMasuk,
            'diterima_tanggal' => $diterimaTanggal,
        ];
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

    /**
     * @param  array<string, mixed>  $payload
     */
    private function parseDiterimaTanggal(array $payload, string $jenisMasuk): ?string
    {
        $hasColumn = array_key_exists('diterima_tanggal', $payload);
        $value = $payload['diterima_tanggal'] ?? null;

        if ($jenisMasuk !== 'mutasi_masuk' && ($value === null || $value === '')) {
            return null;
        }

        if ($value === null || $value === '') {
            if ($hasColumn) {
                throw new InvalidArgumentException('diterima_tanggal wajib diisi untuk mutasi masuk.');
            }

            return null;
        }

        return $this->parseDate($value);
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

        if ($string === '') {
            return null;
        }

        $normalized = strtolower(preg_replace('/\s+/', ' ', $string) ?? $string);

        return match ($normalized) {
            'yatim' => 'Yatim',
            'piatu' => 'Piatu',
            'yatim piatu', 'yatim_piatu' => 'Yatim Piatu',
            default => throw new InvalidArgumentException('Status keluarga harus Yatim, Piatu, atau Yatim Piatu.'),
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
     */
    private function updateExisting(Siswa $siswa, array $validated): void
    {
        if ($siswa->trashed()) {
            $siswa->restore();
            $siswa->refresh();
        }

        $siswa->fill($this->updatePayload($validated));
        $siswa->save();

        if ($validated['jenis_masuk'] === 'mutasi_masuk') {
            $this->markOpenPlacementAsMutasiMasuk($siswa, $validated['diterima_tanggal']);
        }
    }

    /**
     * @param  array<string, mixed>  $validated
     * @return array<string, mixed>
     */
    private function updatePayload(array $validated): array
    {
        $payload = ['nama' => $validated['nama']];

        foreach (['nisn', 'jenis_kelamin', 'tempat_lahir', 'tanggal_lahir', 'email', 'telepon', 'alamat', 'status_keluarga', 'nama_ayah', 'pekerjaan_ayah', 'nama_ibu', 'pekerjaan_ibu', 'nama_wali', 'telepon_wali', 'status_asal'] as $field) {
            if ($validated[$field] !== null) {
                $payload[$field] = $validated[$field];
            }
        }

        if ($validated['jenis_masuk'] === 'mutasi_masuk' && $validated['diterima_tanggal'] !== null) {
            $payload['status_at'] = $validated['diterima_tanggal'];
        }

        return $payload;
    }

    /**
     * @param  array<string, mixed>  $validated
     */
    private function createAndPlace(Kelas $kelas, string $lembagaId, array $validated): void
    {
        // Buat sebagai calon lalu tempatkan via service agar siswa berakhir
        // "aktif" dengan tepat satu penempatan terbuka.
        $siswa = Siswa::query()->create([
            ...$this->studentPayload($validated),
            'lembaga_id' => $lembagaId,
            'kelas_id' => null,
            'tahun_ajaran_id' => null,
            'status_siswa' => $validated['jenis_masuk'] === 'mutasi_masuk' ? SiswaStatus::MUTASI_MASUK : SiswaStatus::CALON,
            'status_at' => $validated['diterima_tanggal'],
            'is_active' => false,
        ]);

        $this->lifecycle->tempatkan(
            $siswa,
            $kelas,
            $validated['diterima_tanggal'] !== null ? Carbon::parse($validated['diterima_tanggal']) : null,
            $validated['jenis_masuk'] === 'mutasi_masuk' ? PenempatanJenis::MUTASI_MASUK : PenempatanJenis::AWAL,
        );
    }

    /**
     * @param  array<string, mixed>  $validated
     * @return array<string, mixed>
     */
    private function studentPayload(array $validated): array
    {
        $payload = $validated;
        unset($payload['jenis_masuk'], $payload['diterima_tanggal']);

        return $payload;
    }

    private function markOpenPlacementAsMutasiMasuk(Siswa $siswa, ?string $diterimaTanggal): void
    {
        $placement = SiswaPenempatan::withoutGlobalScopes()
            ->where('lembaga_id', $siswa->lembaga_id)
            ->where('siswa_id', $siswa->id)
            ->whereNull('selesai_at')
            ->first();

        if ($placement === null) {
            return;
        }

        $placement->jenis = PenempatanJenis::MUTASI_MASUK;
        if ($diterimaTanggal !== null) {
            $placement->mulai_at = $diterimaTanggal;
        }
        $placement->save();
    }
}
