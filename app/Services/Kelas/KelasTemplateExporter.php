<?php

namespace App\Services\Kelas;

use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use Symfony\Component\HttpFoundation\StreamedResponse;

final class KelasTemplateExporter
{
    /**
     * @return list<string>
     */
    public static function dataHeaders(): array
    {
        return [
            'nama',
            'tahun_ajaran',
            'tingkat',
            'wali_kelas_niy',
        ];
    }

    public function downloadResponse(): StreamedResponse
    {
        $spreadsheet = new Spreadsheet;

        $petunjuk = $spreadsheet->getActiveSheet();
        $petunjuk->setTitle('Petunjuk');
        $petunjuk->setCellValue('A1', 'Petunjuk Import Data Kelas');
        $petunjuk->setCellValue('A3', '1. Isi data pada sheet "Data Kelas". Baris pertama adalah header — jangan diubah.');
        $petunjuk->setCellValue('A4', '2. Kolom wajib: nama, tahun_ajaran (teks seperti 2026/2027 yang sudah ada di lembaga).');
        $petunjuk->setCellValue('A5', '3. Kolom opsional: tingkat, wali_kelas_niy (NIY guru wali kelas di lembaga Anda).');
        $petunjuk->setCellValue('A6', '4. Baris kosong akan dilewati.');
        $petunjuk->setCellValue('A7', '5. Nama kelas harus unik per tahun ajaran.');
        $petunjuk->getColumnDimension('A')->setWidth(90);

        $data = $spreadsheet->createSheet();
        $data->setTitle('Data Kelas');

        foreach (self::dataHeaders() as $index => $header) {
            $column = chr(ord('A') + $index);
            $data->setCellValue($column.'1', $header);
        }

        $data->setCellValue('A2', 'VII-A');
        $data->setCellValue('B2', '2026/2027');
        $data->setCellValue('C2', '7');
        $data->setCellValue('D2', '');

        $spreadsheet->setActiveSheetIndex(1);

        return new StreamedResponse(function () use ($spreadsheet) {
            (new Xlsx($spreadsheet))->save('php://output');
        }, 200, [
            'Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
            'Content-Disposition' => 'attachment; filename="template-import-kelas.xlsx"',
            'Cache-Control' => 'max-age=0',
        ]);
    }
}
