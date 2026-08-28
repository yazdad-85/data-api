<?php

namespace App\Services\Siswa;

use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use Symfony\Component\HttpFoundation\StreamedResponse;

final class SiswaTemplateExporter
{
    /**
     * @return list<string>
     */
    public static function dataHeaders(): array
    {
        return [
            'nis',
            'nama',
            'nisn',
            'jenis_kelamin',
            'tempat_lahir',
            'tanggal_lahir',
            'email',
            'telepon',
            'alamat',
            'status_keluarga',
            'nama_ayah',
            'pekerjaan_ayah',
            'nama_ibu',
            'pekerjaan_ibu',
            'nama_wali',
            'telepon_wali',
            'jenis_masuk',
            'asal_lembaga',
            'diterima_tanggal',
        ];
    }

    public function downloadResponse(): StreamedResponse
    {
        $spreadsheet = new Spreadsheet;

        $petunjuk = $spreadsheet->getActiveSheet();
        $petunjuk->setTitle('Petunjuk');
        $petunjuk->setCellValue('A1', 'Petunjuk Import Data Siswa');
        $petunjuk->setCellValue('A3', '1. Isi data pada sheet "Data Siswa". Baris pertama adalah header — jangan diubah.');
        $petunjuk->setCellValue('A4', '2. Kolom wajib: nis, nama.');
        $petunjuk->setCellValue('A5', '3. Kolom opsional: nisn, jenis_kelamin (L atau P), tempat_lahir, tanggal_lahir, email, telepon, alamat, status_keluarga, nama_ayah, pekerjaan_ayah, nama_ibu, pekerjaan_ibu, nama_wali, telepon_wali, jenis_masuk, asal_lembaga, diterima_tanggal.');
        $petunjuk->setCellValue('A6', '4. status_keluarga boleh kosong. Jika diisi: Yatim, Piatu, Yatim Piatu, atau Anak Guru, Staff, dan Karyawan.');
        $petunjuk->setCellValue('A7', '5. jenis_masuk: Siswa Baru atau Mutasi Masuk. Jika asal_lembaga terisi dan jenis_masuk kosong, baris dianggap Mutasi Masuk.');
        $petunjuk->setCellValue('A8', '6. Untuk Mutasi Masuk, isi asal_lembaga dan diterima_tanggal.');
        $petunjuk->setCellValue('A9', '7. Kelas dan tahun ajaran diisi otomatis dari halaman kelas saat import.');
        $petunjuk->setCellValue('A10', '8. Format tanggal_lahir dan diterima_tanggal: YYYY-MM-DD (mis. 2010-05-17).');
        $petunjuk->setCellValue('A11', '9. Baris kosong akan dilewati.');
        $petunjuk->setCellValue('A12', '10. NIS harus unik di lembaga (termasuk siswa yang pernah dihapus).');
        $petunjuk->getColumnDimension('A')->setWidth(90);

        $data = $spreadsheet->createSheet();
        $data->setTitle('Data Siswa');

        foreach (self::dataHeaders() as $index => $header) {
            $column = chr(ord('A') + $index);
            $data->setCellValue($column.'1', $header);
        }

        $data->setCellValue('A2', 'NIS-001');
        $data->setCellValue('B2', 'Contoh Siswa');
        $data->setCellValue('C2', '');
        $data->setCellValue('D2', 'L');
        $data->setCellValue('E2', 'Jakarta');
        $data->setCellValue('F2', '2010-01-15');
        $data->setCellValue('G2', 'siswa@example.com');
        $data->setCellValue('H2', '08123456789');
        $data->setCellValue('I2', 'Jl. Contoh No. 1');
        $data->setCellValue('J2', '');
        $data->setCellValue('K2', 'Ayah Contoh');
        $data->setCellValue('L2', 'Wiraswasta');
        $data->setCellValue('M2', 'Ibu Contoh');
        $data->setCellValue('N2', 'Guru');
        $data->setCellValue('O2', 'Wali Contoh');
        $data->setCellValue('P2', '08198765432');
        $data->setCellValue('Q2', 'Mutasi Masuk');
        $data->setCellValue('R2', 'SMP Contoh');
        $data->setCellValue('S2', '2026-07-15');

        $spreadsheet->setActiveSheetIndex(1);

        return new StreamedResponse(function () use ($spreadsheet) {
            (new Xlsx($spreadsheet))->save('php://output');
        }, 200, [
            'Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
            'Content-Disposition' => 'attachment; filename="template-import-siswa.xlsx"',
            'Cache-Control' => 'max-age=0',
        ]);
    }
}
