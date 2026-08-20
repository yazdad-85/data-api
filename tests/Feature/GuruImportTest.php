<?php

namespace Tests\Feature;

use App\Models\Guru;
use App\Models\Lembaga;
use App\Models\User;
use App\Services\Guru\GuruTemplateExporter;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use Tests\TestCase;

class GuruImportTest extends TestCase
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

        $response = $this->actingAs($admin)->get(route('admin.guru.template'));

        $response->assertOk();
        $response->assertHeader(
            'content-type',
            'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet'
        );
        $this->assertStringContainsString(
            'template-import-guru.xlsx',
            (string) $response->headers->get('content-disposition')
        );
    }

    public function test_import_creates_guru_rows_with_auto_niy(): void
    {
        $lembaga = Lembaga::factory()->create(['niy_kode' => '01']);
        $admin = User::factory()->adminLembaga($lembaga->id)->create();

        $file = $this->makeImportFile([
            [
                'nama' => 'Guru Import A',
                'jenis_kelamin' => 'L',
                'tahun_masuk' => 1989,
                'nik' => '3174010101890001',
                'pendidikan_terakhir' => 's1',
                'instansi_pendidikan' => 'Universitas Import',
                'jurusan' => 'PGMI',
                'status_sertifikasi' => 'sudah',
                'status_inpasing' => 'belum',
                'mapel_sertifikasi' => 'Tematik',
                'status_menikah' => 'menikah',
            ],
            ['nama' => 'Guru Import B', 'jenis_kelamin' => 'P', 'tahun_masuk' => 1989],
        ]);

        $response = $this->actingAs($admin)->post(route('admin.guru.import'), [
            'file' => $file,
        ]);

        $response->assertRedirect(route('admin.guru.index'));
        $response->assertSessionHas('status');

        $this->assertSame(2, Guru::query()->count());
        $guruA = Guru::query()->where('nama', 'Guru Import A')->firstOrFail();
        $this->assertSame('048801018901', $guruA->niy);
        $this->assertSame('3174010101890001', $guruA->nik);
        $this->assertSame('S1', $guruA->pendidikan_terakhir);
        $this->assertSame('Universitas Import', $guruA->instansi_pendidikan);
        $this->assertSame('PGMI', $guruA->jurusan);
        $this->assertSame('Sudah', $guruA->status_sertifikasi);
        $this->assertSame('Belum', $guruA->status_inpasing);
        $this->assertSame('Tematik', $guruA->mapel_sertifikasi);
        $this->assertSame('Sudah Menikah', $guruA->status_menikah);
        $this->assertSame('048801028902', Guru::query()->where('nama', 'Guru Import B')->value('niy'));
    }

    public function test_import_reports_row_errors_without_stopping_valid_rows(): void
    {
        $lembaga = Lembaga::factory()->create(['niy_kode' => '01']);
        $admin = User::factory()->adminLembaga($lembaga->id)->create();

        $file = $this->makeImportFile([
            ['nama' => 'Guru Valid', 'jenis_kelamin' => 'L', 'tahun_masuk' => 2020],
            ['nama' => '', 'jenis_kelamin' => 'L', 'tahun_masuk' => 2020],
        ]);

        $response = $this->actingAs($admin)->post(route('admin.guru.import'), [
            'file' => $file,
        ]);

        $response->assertRedirect(route('admin.guru.index'));
        $response->assertSessionHas('import_errors');
        $this->assertSame(1, Guru::query()->count());
    }

    public function test_import_from_old_template_can_be_updated_with_new_profile_fields(): void
    {
        $lembaga = Lembaga::factory()->create(['niy_kode' => '01']);
        $admin = User::factory()->adminLembaga($lembaga->id)->create();

        $oldTemplateFile = $this->makeLegacyImportFile([
            [
                'nama' => 'Guru Legacy',
                'jenis_kelamin' => 'L',
                'tahun_masuk' => 1989,
                'nuptk' => 'OLD-NUPTK',
                'telepon' => '0811',
            ],
        ]);

        $this->actingAs($admin)->post(route('admin.guru.import'), [
            'file' => $oldTemplateFile,
        ])->assertRedirect(route('admin.guru.index'))
            ->assertSessionHas('import_errors', []);

        $newTemplateFile = $this->makeImportFile([
            [
                'nama' => 'Guru Legacy',
                'jenis_kelamin' => 'L',
                'tahun_masuk' => 1989,
                'nik' => '3174010101890001',
                'pendidikan_terakhir' => 'S1',
                'instansi_pendidikan' => 'Universitas Update',
                'jurusan' => 'PGMI',
                'status_sertifikasi' => 'Sudah',
                'status_inpasing' => 'Belum',
                'mapel_sertifikasi' => 'Tematik',
                'status_menikah' => 'Sudah Menikah',
                'peg_id' => 'PEG-UPDATE',
                'telepon' => '',
            ],
        ]);

        $response = $this->actingAs($admin)->post(route('admin.guru.import'), [
            'file' => $newTemplateFile,
        ]);

        $response->assertRedirect(route('admin.guru.index'));
        $response->assertSessionHas('import_errors', []);

        $this->assertSame(1, Guru::query()->count());

        $guru = Guru::query()->where('nama', 'Guru Legacy')->firstOrFail();
        $this->assertSame('048801018901', $guru->niy);
        $this->assertSame('3174010101890001', $guru->nik);
        $this->assertSame('S1', $guru->pendidikan_terakhir);
        $this->assertSame('Universitas Update', $guru->instansi_pendidikan);
        $this->assertSame('PGMI', $guru->jurusan);
        $this->assertSame('Sudah', $guru->status_sertifikasi);
        $this->assertSame('Belum', $guru->status_inpasing);
        $this->assertSame('Tematik', $guru->mapel_sertifikasi);
        $this->assertSame('Sudah Menikah', $guru->status_menikah);
        $this->assertSame('PEG-UPDATE', $guru->peg_id);
        $this->assertSame('0811', $guru->telepon);
    }

    /**
     * @param  list<array<string, mixed>>  $rows
     */
    private function makeImportFile(array $rows): UploadedFile
    {
        return $this->makeImportFileWithHeaders(GuruTemplateExporter::dataHeaders(), $rows);
    }

    /**
     * @param  list<array<string, mixed>>  $rows
     */
    private function makeLegacyImportFile(array $rows): UploadedFile
    {
        return $this->makeImportFileWithHeaders([
            'nama',
            'jenis_kelamin',
            'tahun_masuk',
            'nuptk',
            'tempat_lahir',
            'tanggal_lahir',
            'email',
            'telepon',
            'alamat',
            'status_kepegawaian',
        ], $rows);
    }

    /**
     * @param  list<string>  $headers
     * @param  list<array<string, mixed>>  $rows
     */
    private function makeImportFileWithHeaders(array $headers, array $rows): UploadedFile
    {
        $spreadsheet = new Spreadsheet;
        $sheet = $spreadsheet->getActiveSheet();
        $sheet->setTitle('Data Guru');

        foreach ($headers as $index => $header) {
            $column = chr(ord('A') + $index);
            $sheet->setCellValue($column.'1', $header);
        }

        foreach ($rows as $rowIndex => $row) {
            $excelRow = $rowIndex + 2;
            foreach ($headers as $index => $header) {
                $column = chr(ord('A') + $index);
                $sheet->setCellValue($column.$excelRow, $row[$header] ?? '');
            }
        }

        $path = tempnam(sys_get_temp_dir(), 'guru-import-').'.xlsx';
        (new Xlsx($spreadsheet))->save($path);

        return new UploadedFile($path, 'import-guru.xlsx', null, null, true);
    }
}
