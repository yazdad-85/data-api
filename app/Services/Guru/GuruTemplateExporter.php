<?php

namespace App\Services\Guru;

use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use Symfony\Component\HttpFoundation\StreamedResponse;

final class GuruTemplateExporter
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
            'nik',
            'pendidikan_terakhir',
            'instansi_pendidikan',
            'jurusan',
            'status_sertifikasi',
            'status_inpasing',
            'mapel_sertifikasi',
            'status_menikah',
            'peg_id',
            'tempat_lahir',
            'tanggal_lahir',
            'email',
            'telepon',
            'alamat',
            'status_kepegawaian',
        ];
    }

    public function downloadResponse(): StreamedResponse
    {
        $spreadsheet = new Spreadsheet;

        $petunjuk = $spreadsheet->getActiveSheet();
        $petunjuk->setTitle('Petunjuk');
        $petunjuk->setCellValue('A1', 'Petunjuk Import Data Guru');
        $petunjuk->setCellValue('A3', '1. Isi data pada sheet "Data Guru". Baris pertama adalah header — jangan diubah.');
        $petunjuk->setCellValue('A4', '2. Kolom wajib: nama, jenis_kelamin (L atau P), tahun_masuk (4 digit, mis. 1989).');
        $petunjuk->setCellValue('A5', '3. NIY digenerate otomatis oleh sistem saat import.');
        $petunjuk->setCellValue('A6', '4. Format tanggal_lahir: YYYY-MM-DD (mis. 1990-05-17).');
        $petunjuk->setCellValue('A7', '5. pendidikan_terakhir: SMP, SMA, S1, S2, atau S3.');
        $petunjuk->setCellValue('A8', '6. status_sertifikasi dan status_inpasing: Sudah atau Belum.');
        $petunjuk->setCellValue('A9', '7. status_menikah: Sudah Menikah atau Belum Menikah.');
        $petunjuk->setCellValue('A10', '8. Baris kosong akan dilewati.');
        $petunjuk->setCellValue('A11', '9. Pastikan lembaga Anda sudah memiliki kode NIY (2 digit) dari Super Admin.');
        $petunjuk->getColumnDimension('A')->setWidth(90);

        $data = $spreadsheet->createSheet();
        $data->setTitle('Data Guru');

        foreach (self::dataHeaders() as $index => $header) {
            $column = chr(ord('A') + $index);
            $data->setCellValue($column.'1', $header);
        }

        $data->setCellValue('A2', 'Contoh Guru');
        $data->setCellValue('B2', 'L');
        $data->setCellValue('C2', 1989);
        $data->setCellValue('D2', '3174010101890001');
        $data->setCellValue('E2', 'S1');
        $data->setCellValue('F2', 'Universitas Contoh');
        $data->setCellValue('G2', 'Pendidikan Agama Islam');
        $data->setCellValue('H2', 'Sudah');
        $data->setCellValue('I2', 'Belum');
        $data->setCellValue('J2', 'PAI');
        $data->setCellValue('K2', 'Sudah Menikah');
        $data->setCellValue('L2', '');
        $data->setCellValue('M2', 'Jakarta');
        $data->setCellValue('N2', '1989-01-15');
        $data->setCellValue('O2', 'guru@example.com');
        $data->setCellValue('P2', '08123456789');
        $data->setCellValue('Q2', 'Jl. Contoh No. 1');
        $data->setCellValue('R2', 'GTY');

        $spreadsheet->setActiveSheetIndex(1);

        return new StreamedResponse(function () use ($spreadsheet) {
            (new Xlsx($spreadsheet))->save('php://output');
        }, 200, [
            'Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
            'Content-Disposition' => 'attachment; filename="template-import-guru.xlsx"',
            'Cache-Control' => 'max-age=0',
        ]);
    }
}
