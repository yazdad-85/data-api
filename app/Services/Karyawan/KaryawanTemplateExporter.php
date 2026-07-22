<?php

namespace App\Services\Karyawan;

use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use Symfony\Component\HttpFoundation\StreamedResponse;

final class KaryawanTemplateExporter
{
    /**
     * @return list<string>
     */
    public static function dataHeaders(): array
    {
        return [
            'nama',
            'jenis_kelamin',
            'tahun_masuk',
            'jabatan',
            'email',
            'telepon',
            'alamat',
        ];
    }

    public function downloadResponse(): StreamedResponse
    {
        $spreadsheet = new Spreadsheet;

        $petunjuk = $spreadsheet->getActiveSheet();
        $petunjuk->setTitle('Petunjuk');
        $petunjuk->setCellValue('A1', 'Petunjuk Import Data Karyawan');
        $petunjuk->setCellValue('A3', '1. Isi data pada sheet "Data Karyawan". Baris pertama adalah header — jangan diubah.');
        $petunjuk->setCellValue('A4', '2. Kolom wajib: nama, jenis_kelamin (L atau P), tahun_masuk (4 digit, mis. 1989).');
        $petunjuk->setCellValue('A5', '3. NIK pegawai (format NIY) digenerate otomatis oleh sistem saat import.');
        $petunjuk->setCellValue('A6', '4. Baris kosong akan dilewati.');
        $petunjuk->setCellValue('A7', '5. Pastikan lembaga Anda sudah memiliki kode NIY (2 digit) dari Super Admin.');
        $petunjuk->getColumnDimension('A')->setWidth(90);

        $data = $spreadsheet->createSheet();
        $data->setTitle('Data Karyawan');

        foreach (self::dataHeaders() as $index => $header) {
            $column = chr(ord('A') + $index);
            $data->setCellValue($column.'1', $header);
        }

        $data->setCellValue('A2', 'Contoh Karyawan');
        $data->setCellValue('B2', 'P');
        $data->setCellValue('C2', 1989);
        $data->setCellValue('D2', 'Staf Tata Usaha');
        $data->setCellValue('E2', 'karyawan@example.com');
        $data->setCellValue('F2', '08123456789');
        $data->setCellValue('G2', 'Jl. Contoh No. 1');

        $spreadsheet->setActiveSheetIndex(1);

        return new StreamedResponse(function () use ($spreadsheet) {
            (new Xlsx($spreadsheet))->save('php://output');
        }, 200, [
            'Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
            'Content-Disposition' => 'attachment; filename="template-import-karyawan.xlsx"',
            'Cache-Control' => 'max-age=0',
        ]);
    }
}
