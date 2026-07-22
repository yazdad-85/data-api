<?php

namespace Tests\Feature;

use App\Models\Karyawan;
use App\Models\Lembaga;
use App\Models\User;
use App\Services\Karyawan\KaryawanTemplateExporter;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use Tests\TestCase;

class KaryawanImportTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        config(['security.mfa.super_admin_required' => false, 'master.niy.npyp' => '0488']);
    }

    public function test_download_template_returns_xlsx(): void
    {
        $lembaga = Lembaga::factory()->create(['niy_kode' => '01']);
        $admin = User::factory()->adminLembaga($lembaga->id)->create();

        $response = $this->actingAs($admin)->get(route('admin.karyawan.template'));

        $response->assertOk();
        $response->assertHeader(
            'content-type',
            'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet'
        );
    }

    public function test_import_creates_karyawan_rows_with_auto_nik(): void
    {
        $lembaga = Lembaga::factory()->create(['niy_kode' => '01']);
        $admin = User::factory()->adminLembaga($lembaga->id)->create();

        $file = $this->makeImportFile([
            ['nama' => 'Karyawan A', 'jenis_kelamin' => 'L', 'tahun_masuk' => 1989],
            ['nama' => 'Karyawan B', 'jenis_kelamin' => 'P', 'tahun_masuk' => 1989],
        ]);

        $response = $this->actingAs($admin)->post(route('admin.karyawan.import'), [
            'file' => $file,
        ]);

        $response->assertRedirect(route('admin.karyawan.index'));
        $response->assertSessionHas('status');

        $this->assertSame(2, Karyawan::query()->count());
        $this->assertSame('048801018901', Karyawan::query()->where('nama', 'Karyawan A')->value('nik_pegawai'));
        $this->assertSame('048801028902', Karyawan::query()->where('nama', 'Karyawan B')->value('nik_pegawai'));
    }

    /**
     * @param  list<array<string, mixed>>  $rows
     */
    private function makeImportFile(array $rows): UploadedFile
    {
        $spreadsheet = new Spreadsheet;
        $sheet = $spreadsheet->getActiveSheet();
        $sheet->setTitle('Data Karyawan');

        foreach (KaryawanTemplateExporter::dataHeaders() as $index => $header) {
            $column = chr(ord('A') + $index);
            $sheet->setCellValue($column.'1', $header);
        }

        foreach ($rows as $rowIndex => $row) {
            $excelRow = $rowIndex + 2;
            $sheet->setCellValue('A'.$excelRow, $row['nama'] ?? '');
            $sheet->setCellValue('B'.$excelRow, $row['jenis_kelamin'] ?? '');
            $sheet->setCellValue('C'.$excelRow, $row['tahun_masuk'] ?? '');
        }

        $path = tempnam(sys_get_temp_dir(), 'karyawan-import-').'.xlsx';
        (new Xlsx($spreadsheet))->save($path);

        return new UploadedFile($path, 'import-karyawan.xlsx', null, null, true);
    }
}
