<?php

namespace App\Services\Siswa;

use App\Models\Siswa;
use App\Services\Siswa\Concerns\ParsesSiswaImportRow;
use App\Support\Master\SiswaStatus;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;
use PhpOffice\PhpSpreadsheet\IOFactory;

/**
 * Import calon murid (SPMB). Sengaja terpisah dari SiswaImporter karena
 * aturannya berbeda: calon belum resmi diterima, jadi NIS boleh kosong dan
 * tidak ada kelas/tanggal diterima — itu ditentukan belakangan lewat
 * Distribusi SPMB.
 */
final class SiswaCalonImporter
{
    use ParsesSiswaImportRow;

    /**
     * @return array{success: int, failed: int, errors: list<array{row: int, message: string}>}
     */
    public function import(UploadedFile $file, string $lembagaId, ?string $tahunAjaranId = null): array
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
                        'message' => 'File ini tampak template import kelas. Untuk calon murid, unduh "template calon murid" dari halaman SPMB.',
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

        if (! in_array('nama', $columnMap, true)) {
            return [
                'success' => 0,
                'failed' => 0,
                'errors' => [['row' => 1, 'message' => 'Kolom wajib "nama" tidak ditemukan. Gunakan template calon murid dari halaman SPMB.']],
            ];
        }

        $success = 0;
        $failed = 0;
        $errors = [];
        $seenNis = [];

        foreach (array_values($rows) as $rowIndex => $row) {
            $excelRow = $rowIndex + 2;
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

            // NIS opsional untuk calon: baris tanpa NIS tidak dicek duplikat
            // dan tidak pernah dicocokkan ke siswa lama (selalu jadi baris baru).
            if ($validated['nis'] !== null) {
                if (isset($seenNis[$validated['nis']])) {
                    $failed++;
                    $errors[] = [
                        'row' => $excelRow,
                        'message' => "NIS {$validated['nis']} muncul lebih dari sekali di file import.",
                    ];

                    continue;
                }

                $seenNis[$validated['nis']] = true;
            }

            $existing = $this->findExistingSiswa($lembagaId, $validated['nis']);

            if ($existing !== null && $existing->kelas_id !== null) {
                $failed++;
                $errors[] = [
                    'row' => $excelRow,
                    'message' => "NIS {$validated['nis']} sudah ditempatkan di kelas. Gunakan menu Siswa untuk mengubah datanya.",
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

            DB::transaction(function () use ($lembagaId, $tahunAjaranId, $validated, $existing) {
                if ($existing !== null) {
                    $this->updateExisting($existing, $validated);

                    return;
                }

                $this->createAsCalon($lembagaId, $tahunAjaranId, $validated);
            });

            $success++;
        }

        return compact('success', 'failed', 'errors');
    }

    /**
     * @param  array<string, mixed>  $payload
     * @return array<string, mixed>
     */
    private function validateRow(array $payload): array
    {
        $nis = $this->nullableString($payload['nis'] ?? null, 30);

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
        ];
    }

    private function findExistingSiswa(string $lembagaId, ?string $nis): ?Siswa
    {
        if ($nis === null) {
            return null;
        }

        return Siswa::withTrashed()
            ->where('lembaga_id', $lembagaId)
            ->where('nis', $nis)
            ->first();
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

        $payload = ['nama' => $validated['nama']];
        foreach (['nisn', 'jenis_kelamin', 'tempat_lahir', 'tanggal_lahir', 'email', 'telepon', 'alamat', 'status_keluarga', 'nama_ayah', 'pekerjaan_ayah', 'nama_ibu', 'pekerjaan_ibu', 'nama_wali', 'telepon_wali', 'status_asal'] as $field) {
            if ($validated[$field] !== null) {
                $payload[$field] = $validated[$field];
            }
        }

        $siswa->fill($payload);
        $siswa->save();
    }

    /**
     * @param  array<string, mixed>  $validated
     */
    private function createAsCalon(string $lembagaId, ?string $tahunAjaranId, array $validated): void
    {
        $jenisMasuk = $validated['jenis_masuk'];
        unset($validated['jenis_masuk']);

        Siswa::query()->create([
            ...$validated,
            'lembaga_id' => $lembagaId,
            'kelas_id' => null,
            'tahun_ajaran_id' => $tahunAjaranId,
            'status_siswa' => $jenisMasuk === 'mutasi_masuk' ? SiswaStatus::MUTASI_MASUK : SiswaStatus::CALON,
            'status_at' => null,
            'is_active' => false,
        ]);
    }
}
