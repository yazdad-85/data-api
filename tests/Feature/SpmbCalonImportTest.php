<?php

namespace Tests\Feature;

use App\Models\Kelas;
use App\Models\Lembaga;
use App\Models\Siswa;
use App\Models\TahunAjaran;
use App\Models\User;
use App\Services\Siswa\SiswaCalonTemplateExporter;
use App\Support\Master\SiswaStatus;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use Tests\TestCase;

class SpmbCalonImportTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        config(['security.mfa.super_admin_required' => false]);
        $this->withoutVite();
    }

    public function test_import_creates_calon_siswa_without_kelas(): void
    {
        $lembaga = Lembaga::factory()->create();
        $admin = User::factory()->adminLembaga($lembaga->id)->create();
        $ta = TahunAjaran::factory()->for($lembaga)->create();

        $file = $this->makeImportFile([
            ['nis' => 'CALON-001', 'nama' => 'Calon Satu'],
            [
                'nis' => 'CALON-002',
                'nama' => 'Calon Dua',
                'jenis_masuk' => 'Mutasi Masuk',
                'asal_lembaga' => 'SMP Asal',
            ],
        ]);

        $response = $this->actingAs($admin)->post(route('admin.spmb-calon.store'), [
            'tahun_ajaran_id' => $ta->id,
            'file' => $file,
        ]);

        $response->assertRedirect(route('admin.spmb-distribusi.create'));
        $response->assertSessionHas('import_errors', []);

        $this->assertSame(2, Siswa::query()->count());

        $s1 = Siswa::query()->where('nis', 'CALON-001')->firstOrFail();
        $this->assertSame(SiswaStatus::CALON, $s1->status_siswa);
        $this->assertNull($s1->kelas_id);
        $this->assertSame($ta->id, $s1->tahun_ajaran_id);
        $this->assertFalse($s1->is_active);

        $s2 = Siswa::query()->where('nis', 'CALON-002')->firstOrFail();
        $this->assertSame(SiswaStatus::MUTASI_MASUK, $s2->status_siswa);
        $this->assertNull($s2->kelas_id);
        $this->assertSame('SMP Asal', $s2->status_asal);
        $this->assertNull($s2->status_at);
    }

    public function test_import_allows_blank_nis_for_calon_yang_belum_resmi_diterima(): void
    {
        $lembaga = Lembaga::factory()->create();
        $admin = User::factory()->adminLembaga($lembaga->id)->create();

        $file = $this->makeImportFile([
            ['nama' => 'Calon Tanpa NIS Satu'],
            ['nama' => 'Calon Tanpa NIS Dua'],
        ]);

        $response = $this->actingAs($admin)->post(route('admin.spmb-calon.store'), [
            'file' => $file,
        ]);

        $response->assertRedirect(route('admin.spmb-distribusi.create'));
        $response->assertSessionHas('import_errors', []);

        $this->assertSame(2, Siswa::query()->count());

        $siswas = Siswa::query()->orderBy('nama')->get();
        foreach ($siswas as $siswa) {
            $this->assertNull($siswa->nis);
            $this->assertSame(SiswaStatus::CALON, $siswa->status_siswa);
        }
    }

    public function test_imported_calon_siswa_muncul_di_distribusi_spmb(): void
    {
        $lembaga = Lembaga::factory()->create();
        $admin = User::factory()->adminLembaga($lembaga->id)->create();

        $file = $this->makeImportFile([
            ['nis' => 'CALON-010', 'nama' => 'Calon Distribusi'],
        ]);

        $this->actingAs($admin)->post(route('admin.spmb-calon.store'), [
            'file' => $file,
        ])->assertRedirect(route('admin.spmb-distribusi.create'));

        $this->actingAs($admin)->get(route('admin.spmb-distribusi.create'))
            ->assertOk()
            ->assertSee('Calon Distribusi');
    }

    public function test_import_rejects_nis_already_placed_in_kelas(): void
    {
        $lembaga = Lembaga::factory()->create();
        $admin = User::factory()->adminLembaga($lembaga->id)->create();
        $ta = TahunAjaran::factory()->for($lembaga)->create();
        $kelas = Kelas::factory()->for($lembaga)->create(['tahun_ajaran_id' => $ta->id]);

        Siswa::factory()->for($lembaga)->inKelas($kelas)->create(['nis' => 'NIS-AKTIF']);

        $file = $this->makeImportFile([
            ['nis' => 'NIS-AKTIF', 'nama' => 'Siswa Aktif'],
        ]);

        $response = $this->actingAs($admin)->post(route('admin.spmb-calon.store'), [
            'file' => $file,
        ]);

        $response->assertRedirect(route('admin.spmb-distribusi.create'));
        $errors = session('import_errors');
        $this->assertNotEmpty($errors);
        $this->assertStringContainsString('sudah ditempatkan di kelas', $errors[0]['message']);
    }

    public function test_import_selalu_discope_ke_lembaga_admin_yang_login(): void
    {
        $lembagaA = Lembaga::factory()->create();
        $adminA = User::factory()->adminLembaga($lembagaA->id)->create();

        $file = $this->makeImportFile([
            ['nis' => 'NIS-X', 'nama' => 'Siswa X'],
        ]);

        $this->actingAs($adminA)->post(route('admin.spmb-calon.store'), [
            'file' => $file,
        ])->assertRedirect(route('admin.spmb-distribusi.create'));

        $siswa = Siswa::query()->where('nis', 'NIS-X')->firstOrFail();
        $this->assertSame($lembagaA->id, $siswa->lembaga_id);
        $this->assertSame(SiswaStatus::CALON, $siswa->status_siswa);
    }

    public function test_super_admin_forbidden_from_calon_import(): void
    {
        $lembaga = Lembaga::factory()->create();
        $sa = User::factory()->create(['role' => 'super_admin', 'lembaga_id' => null]);

        $file = $this->makeImportFile([
            ['nis' => 'NIS-Y', 'nama' => 'Siswa Y'],
        ]);

        $this->actingAs($sa)->get(route('admin.spmb-calon.create'))->assertForbidden();
        $this->actingAs($sa)->post(route('admin.spmb-calon.store'), [
            'file' => $file,
        ])->assertForbidden();
    }

    /**
     * @param  list<array<string, mixed>>  $rows
     */
    private function makeImportFile(array $rows): UploadedFile
    {
        $headers = SiswaCalonTemplateExporter::dataHeaders();

        $spreadsheet = new Spreadsheet;
        $sheet = $spreadsheet->getActiveSheet();
        $sheet->setTitle('Data Siswa');

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

        $path = tempnam(sys_get_temp_dir(), 'calon-import-').'.xlsx';
        (new Xlsx($spreadsheet))->save($path);

        return new UploadedFile($path, 'import-calon.xlsx', null, null, true);
    }
}
