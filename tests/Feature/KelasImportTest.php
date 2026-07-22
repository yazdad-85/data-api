<?php

namespace Tests\Feature;

use App\Models\Kelas;
use App\Models\Lembaga;
use App\Models\TahunAjaran;
use App\Models\User;
use App\Services\Kelas\KelasTemplateExporter;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use Tests\TestCase;

class KelasImportTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        config(['security.mfa.super_admin_required' => false]);
    }

    public function test_download_template_returns_xlsx(): void
    {
        $lembaga = Lembaga::factory()->create();
        $admin = User::factory()->adminLembaga($lembaga->id)->create();

        $response = $this->actingAs($admin)->get(route('admin.kelas.template'));

        $response->assertOk();
        $response->assertHeader(
            'content-type',
            'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet'
        );
        $this->assertStringContainsString(
            'template-import-kelas.xlsx',
            (string) $response->headers->get('content-disposition')
        );
    }

    public function test_import_creates_kelas_rows(): void
    {
        $lembaga = Lembaga::factory()->create();
        $admin = User::factory()->adminLembaga($lembaga->id)->create();
        TahunAjaran::factory()->for($lembaga)->create(['nama' => '2026/2027']);

        $file = $this->makeImportFile([
            ['nama' => 'VII-A', 'tahun_ajaran' => '2026/2027', 'tingkat' => '7'],
            ['nama' => 'VII-B', 'tahun_ajaran' => '2026/2027', 'tingkat' => '7'],
        ]);

        $response = $this->actingAs($admin)->post(route('admin.kelas.import'), [
            'file' => $file,
        ]);

        $response->assertRedirect(route('admin.kelas.index'));
        $response->assertSessionHas('status');

        $this->assertSame(2, Kelas::query()->count());
        $this->assertSame('VII-A', Kelas::query()->where('nama', 'VII-A')->value('nama'));
        $this->assertSame('VII-B', Kelas::query()->where('nama', 'VII-B')->value('nama'));
    }

    public function test_import_reports_row_errors_without_stopping_valid_rows(): void
    {
        $lembaga = Lembaga::factory()->create();
        $admin = User::factory()->adminLembaga($lembaga->id)->create();
        TahunAjaran::factory()->for($lembaga)->create(['nama' => '2026/2027']);

        $file = $this->makeImportFile([
            ['nama' => 'VIII-A', 'tahun_ajaran' => '2026/2027', 'tingkat' => '8'],
            ['nama' => 'VIII-B', 'tahun_ajaran' => '2099/2100', 'tingkat' => '8'],
        ]);

        $response = $this->actingAs($admin)->post(route('admin.kelas.import'), [
            'file' => $file,
        ]);

        $response->assertRedirect(route('admin.kelas.index'));
        $response->assertSessionHas('import_errors');
        $this->assertSame(1, Kelas::query()->count());
        $this->assertSame('VIII-A', Kelas::query()->value('nama'));
    }

    /**
     * @param  list<array<string, mixed>>  $rows
     */
    private function makeImportFile(array $rows): UploadedFile
    {
        $spreadsheet = new Spreadsheet;
        $sheet = $spreadsheet->getActiveSheet();
        $sheet->setTitle('Data Kelas');

        foreach (KelasTemplateExporter::dataHeaders() as $index => $header) {
            $column = chr(ord('A') + $index);
            $sheet->setCellValue($column.'1', $header);
        }

        foreach ($rows as $rowIndex => $row) {
            $excelRow = $rowIndex + 2;
            $sheet->setCellValue('A'.$excelRow, $row['nama'] ?? '');
            $sheet->setCellValue('B'.$excelRow, $row['tahun_ajaran'] ?? '');
            $sheet->setCellValue('C'.$excelRow, $row['tingkat'] ?? '');
            $sheet->setCellValue('D'.$excelRow, $row['wali_kelas_niy'] ?? '');
        }

        $path = tempnam(sys_get_temp_dir(), 'kelas-import-').'.xlsx';
        (new Xlsx($spreadsheet))->save($path);

        return new UploadedFile($path, 'import-kelas.xlsx', null, null, true);
    }
}
