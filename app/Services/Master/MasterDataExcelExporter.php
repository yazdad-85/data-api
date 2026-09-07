<?php

namespace App\Services\Master;

use App\Support\Master\SiswaStatus;
use Illuminate\Support\Collection;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use Symfony\Component\HttpFoundation\StreamedResponse;

final class MasterDataExcelExporter
{
    /**
     * @param  Collection<int, mixed>  $rows
     */
    public function downloadResponse(string $resource, Collection $rows, string $prefix = 'master'): StreamedResponse
    {
        $spreadsheet = new Spreadsheet;
        $sheet = $spreadsheet->getActiveSheet();
        $sheet->setTitle(ucfirst($resource));

        $headers = $this->headers($resource);
        $sheet->fromArray($headers, null, 'A1');
        $sheet->getStyle('A1:'.$this->columnLetter(count($headers)).'1')->getFont()->setBold(true);

        $rowNumber = 2;
        foreach ($rows as $row) {
            $sheet->fromArray($this->row($resource, $row), null, 'A'.$rowNumber);
            $rowNumber++;
        }

        $sheet->freezePane('A2');
        foreach (range(1, count($headers)) as $columnIndex) {
            $sheet->getColumnDimension($this->columnLetter($columnIndex))->setAutoSize(true);
        }

        $filename = $prefix.'-'.$resource.'-'.now()->format('Ymd-His').'.xlsx';

        return new StreamedResponse(function () use ($spreadsheet) {
            (new Xlsx($spreadsheet))->save('php://output');
        }, 200, [
            'Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
            'Content-Disposition' => 'attachment; filename="'.$filename.'"',
            'Cache-Control' => 'max-age=0',
        ]);
    }

    /**
     * @return list<string>
     */
    private function headers(string $resource): array
    {
        return match ($resource) {
            'guru' => ['Nama', 'Lembaga', 'NIY', 'NIK', 'Peg-ID', 'Tahun Masuk', 'Pendidikan Terakhir', 'Instansi Pendidikan', 'Jurusan', 'Status Sertifikasi', 'Status Inpasing', 'Mapel Sertifikasi', 'Status Menikah', 'Jenis Kelamin', 'Tempat Lahir', 'Tanggal Lahir', 'Email', 'Telepon', 'Alamat', 'Status Kepegawaian', 'Status Aktif'],
            'siswa' => ['Nama', 'Lembaga', 'NIS', 'NISN', 'Tahun Ajaran', 'Kelas', 'Status Siswa', 'Status Aktif', 'Status Keluarga', 'Nama Ayah', 'Pekerjaan Ayah', 'Nama Ibu', 'Pekerjaan Ibu', 'Jenis Kelamin', 'Tempat Lahir', 'Tanggal Lahir', 'Email', 'Telepon', 'Alamat', 'Nama Wali', 'Telepon Wali', 'Asal', 'Tujuan', 'Alasan Status'],
            'karyawan' => ['Nama', 'Lembaga', 'NIK Pegawai', 'Tahun Masuk', 'Jenis Kelamin', 'Jabatan', 'Email', 'Telepon', 'Alamat', 'Status Aktif'],
            default => [],
        };
    }

    /**
     * @return list<mixed>
     */
    private function row(string $resource, mixed $row): array
    {
        return match ($resource) {
            'guru' => [
                $row->nama,
                $row->lembaga?->nama,
                $row->niy,
                $row->nik,
                $row->peg_id,
                $row->tahun_masuk,
                $row->pendidikan_terakhir,
                $row->instansi_pendidikan,
                $row->jurusan,
                $row->status_sertifikasi,
                $row->status_inpasing,
                $row->mapel_sertifikasi,
                $row->status_menikah,
                $row->jenis_kelamin,
                $row->tempat_lahir,
                $row->tanggal_lahir?->format('Y-m-d'),
                $row->email,
                $row->telepon,
                $row->alamat,
                $row->status_kepegawaian,
                $row->is_active ? 'Aktif' : 'Nonaktif',
            ],
            'siswa' => [
                $row->nama,
                $row->lembaga?->nama,
                $row->nis,
                $row->nisn,
                $row->tahunAjaran?->nama,
                $row->kelas?->nama,
                SiswaStatus::label($row->status_siswa),
                $row->is_active ? 'Aktif' : 'Nonaktif',
                $row->status_keluarga,
                $row->nama_ayah,
                $row->pekerjaan_ayah,
                $row->nama_ibu,
                $row->pekerjaan_ibu,
                $row->jenis_kelamin,
                $row->tempat_lahir,
                $row->tanggal_lahir?->format('Y-m-d'),
                $row->email,
                $row->telepon,
                $row->alamat,
                $row->nama_wali,
                $row->telepon_wali,
                $row->status_asal,
                $row->status_tujuan,
                $row->status_alasan,
            ],
            'karyawan' => [
                $row->nama,
                $row->lembaga?->nama,
                $row->nik_pegawai,
                $row->tahun_masuk,
                $row->jenis_kelamin,
                $row->jabatan,
                $row->email,
                $row->telepon,
                $row->alamat,
                $row->is_active ? 'Aktif' : 'Nonaktif',
            ],
            default => [],
        };
    }

    private function columnLetter(int $columnIndex): string
    {
        $letter = '';
        while ($columnIndex > 0) {
            $remainder = ($columnIndex - 1) % 26;
            $letter = chr(65 + $remainder).$letter;
            $columnIndex = intdiv($columnIndex - 1, 26);
        }

        return $letter;
    }
}
