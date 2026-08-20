<?php

namespace Tests\Feature;

use App\Models\Kelas;
use App\Models\Lembaga;
use App\Models\Siswa;
use App\Models\SiswaPenempatan;
use App\Models\TahunAjaran;
use App\Models\User;
use App\Services\Siswa\SiswaTemplateExporter;
use App\Support\Master\PenempatanJenis;
use App\Support\Master\SiswaStatus;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use Tests\TestCase;

class KelasSiswaImportTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        config(['security.mfa.super_admin_required' => false]);
        $this->withoutVite();
    }

    public function test_download_template_ok_for_admin_lembaga_owning_kelas(): void
    {
        $lembaga = Lembaga::factory()->create();
        $admin = User::factory()->adminLembaga($lembaga->id)->create();
        $tahunAjaran = TahunAjaran::factory()->for($lembaga)->create();
        $kelas = Kelas::factory()->for($lembaga)->create([
            'tahun_ajaran_id' => $tahunAjaran->id,
        ]);

        $response = $this->actingAs($admin)->get(route('admin.kelas.siswa.template', $kelas));

        $response->assertOk();
        $response->assertHeader(
            'content-type',
            'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet'
        );
        $this->assertStringContainsString(
            'template-import-siswa.xlsx',
            (string) $response->headers->get('content-disposition')
        );
    }

    public function test_import_creates_siswa_attached_to_kelas_with_correct_tahun_ajaran_id(): void
    {
        $lembaga = Lembaga::factory()->create();
        $admin = User::factory()->adminLembaga($lembaga->id)->create();
        $tahunAjaran = TahunAjaran::factory()->for($lembaga)->create(['nama' => '2026/2027']);
        $kelas = Kelas::factory()->for($lembaga)->create([
            'tahun_ajaran_id' => $tahunAjaran->id,
            'nama' => 'VII-A',
        ]);

        $file = $this->makeImportFile([
            [
                'nis' => 'NIS-101',
                'nama' => 'Andi Pratama',
                'status_keluarga' => 'yatim piatu',
                'nama_ayah' => 'Ayah Import',
                'pekerjaan_ayah' => 'Nelayan',
                'nama_ibu' => 'Ibu Import',
                'pekerjaan_ibu' => 'Pedagang',
                'asal_lembaga' => 'SMP Asal',
            ],
            ['nis' => 'NIS-102', 'nama' => 'Siti Rahma'],
        ]);

        $response = $this->actingAs($admin)->post(route('admin.kelas.siswa.import', $kelas), [
            'file' => $file,
        ]);

        $response->assertRedirect(route('admin.kelas.show', $kelas));
        $response->assertSessionHas('status');

        $this->assertSame(2, Siswa::query()->count());

        $siswaA = Siswa::query()->where('nis', 'NIS-101')->firstOrFail();
        $this->assertSame('Andi Pratama', $siswaA->nama);
        $this->assertSame('Yatim Piatu', $siswaA->status_keluarga);
        $this->assertSame('Ayah Import', $siswaA->nama_ayah);
        $this->assertSame('Nelayan', $siswaA->pekerjaan_ayah);
        $this->assertSame('Ibu Import', $siswaA->nama_ibu);
        $this->assertSame('Pedagang', $siswaA->pekerjaan_ibu);
        $this->assertSame('SMP Asal', $siswaA->status_asal);
        $this->assertSame($kelas->id, $siswaA->kelas_id);
        $this->assertSame($tahunAjaran->id, $siswaA->tahun_ajaran_id);
        $this->assertSame($lembaga->id, $siswaA->lembaga_id);
        $this->assertTrue($siswaA->is_active);
        $this->assertSame(SiswaStatus::AKTIF, $siswaA->status_siswa);

        $penempatan = SiswaPenempatan::withoutGlobalScopes()
            ->where('siswa_id', $siswaA->id)
            ->whereNull('selesai_at')
            ->first();
        $this->assertNotNull($penempatan);
        $this->assertSame(PenempatanJenis::AWAL, $penempatan->jenis);
        $this->assertSame($kelas->id, $penempatan->kelas_id);

        $siswaB = Siswa::query()->where('nis', 'NIS-102')->firstOrFail();
        $this->assertSame('Siti Rahma', $siswaB->nama);
        $this->assertSame($kelas->id, $siswaB->kelas_id);
        $this->assertSame($tahunAjaran->id, $siswaB->tahun_ajaran_id);
    }

    public function test_imported_siswa_visible_on_admin_siswa_index(): void
    {
        $lembaga = Lembaga::factory()->create();
        $admin = User::factory()->adminLembaga($lembaga->id)->create();
        $tahunAjaran = TahunAjaran::factory()->for($lembaga)->create();
        $kelas = Kelas::factory()->for($lembaga)->create([
            'tahun_ajaran_id' => $tahunAjaran->id,
        ]);

        $file = $this->makeImportFile([
            ['nis' => 'NIS-201', 'nama' => 'Budi Santoso'],
        ]);

        $this->actingAs($admin)->post(route('admin.kelas.siswa.import', $kelas), [
            'file' => $file,
        ])->assertRedirect(route('admin.kelas.show', $kelas));

        $index = $this->actingAs($admin)->get(route('admin.siswa.index'));
        $index->assertOk()->assertSee('Budi Santoso')->assertSee('NIS-201');
    }

    public function test_import_updates_existing_siswa_in_same_kelas_to_fill_missing_columns(): void
    {
        $lembaga = Lembaga::factory()->create();
        $admin = User::factory()->adminLembaga($lembaga->id)->create();
        $tahunAjaran = TahunAjaran::factory()->for($lembaga)->create();
        $kelas = Kelas::factory()->for($lembaga)->create([
            'tahun_ajaran_id' => $tahunAjaran->id,
        ]);

        Siswa::factory()->for($lembaga)->inKelas($kelas)->create([
            'nis' => 'NIS-301',
            'nisn' => null,
            'nama' => 'Siswa Lama',
            'jenis_kelamin' => null,
            'tanggal_lahir' => null,
            'telepon' => '0811',
        ]);

        $file = $this->makeImportFile([
            [
                'nis' => 'NIS-301',
                'nama' => 'Siswa Lama Revisi',
                'nisn' => 'NISN-301',
                'jenis_kelamin' => 'P',
                'tanggal_lahir' => '2014-02-03',
                'telepon' => '',
                'status_keluarga' => 'Piatu',
                'nama_ayah' => 'Ayah Lama',
                'pekerjaan_ayah' => 'Buruh',
                'nama_ibu' => 'Ibu Lama',
                'pekerjaan_ibu' => 'Ibu Rumah Tangga',
                'asal_lembaga' => 'SMP Lama',
            ],
        ]);

        $response = $this->actingAs($admin)->post(route('admin.kelas.siswa.import', $kelas), [
            'file' => $file,
        ]);

        $response->assertRedirect(route('admin.kelas.show', $kelas));
        $response->assertSessionHas('import_errors', []);

        $this->assertSame(1, Siswa::query()->count());

        $siswa = Siswa::query()->where('nis', 'NIS-301')->firstOrFail();
        $this->assertSame('Siswa Lama Revisi', $siswa->nama);
        $this->assertSame('NISN-301', $siswa->nisn);
        $this->assertSame('P', $siswa->jenis_kelamin);
        $this->assertSame('2014-02-03', $siswa->tanggal_lahir?->toDateString());
        $this->assertSame('0811', $siswa->telepon);
        $this->assertSame('Piatu', $siswa->status_keluarga);
        $this->assertSame('Ayah Lama', $siswa->nama_ayah);
        $this->assertSame('Buruh', $siswa->pekerjaan_ayah);
        $this->assertSame('Ibu Lama', $siswa->nama_ibu);
        $this->assertSame('Ibu Rumah Tangga', $siswa->pekerjaan_ibu);
        $this->assertSame('SMP Lama', $siswa->status_asal);
        $this->assertSame($kelas->id, $siswa->kelas_id);
    }

    public function test_import_restores_soft_deleted_siswa_in_same_kelas(): void
    {
        $lembaga = Lembaga::factory()->create();
        $admin = User::factory()->adminLembaga($lembaga->id)->create();
        $tahunAjaran = TahunAjaran::factory()->for($lembaga)->create();
        $kelas = Kelas::factory()->for($lembaga)->create([
            'tahun_ajaran_id' => $tahunAjaran->id,
        ]);

        $existing = Siswa::factory()->for($lembaga)->inKelas($kelas)->create([
            'nis' => 'NIS-RESTORE',
            'nisn' => null,
            'nama' => 'Siswa Terhapus',
        ]);
        $existing->delete();

        $file = $this->makeImportFile([
            [
                'nis' => 'NIS-RESTORE',
                'nama' => 'Siswa Dipulihkan',
                'nisn' => 'NISN-RESTORE',
            ],
        ]);

        $response = $this->actingAs($admin)->post(route('admin.kelas.siswa.import', $kelas), [
            'file' => $file,
        ]);

        $response->assertRedirect(route('admin.kelas.show', $kelas));
        $response->assertSessionHas('import_errors', []);

        $this->assertSame(1, Siswa::query()->count());
        $this->assertSame(1, Siswa::withTrashed()->count());

        $siswa = Siswa::query()->where('nis', 'NIS-RESTORE')->firstOrFail();
        $this->assertSame($existing->id, $siswa->id);
        $this->assertSame('Siswa Dipulihkan', $siswa->nama);
        $this->assertSame('NISN-RESTORE', $siswa->nisn);
        $this->assertNull($siswa->deleted_at);
        $this->assertSame($kelas->id, $siswa->kelas_id);
    }

    public function test_duplicate_nis_fails_row(): void
    {
        $lembaga = Lembaga::factory()->create();
        $admin = User::factory()->adminLembaga($lembaga->id)->create();
        $tahunAjaran = TahunAjaran::factory()->for($lembaga)->create();
        $kelas = Kelas::factory()->for($lembaga)->create([
            'tahun_ajaran_id' => $tahunAjaran->id,
        ]);

        $existing = Siswa::factory()->for($lembaga)->create([
            'nis' => 'NIS-DUP',
            'nama' => 'Siswa Lama',
        ]);
        $existing->delete();

        $file = $this->makeImportFile([
            ['nis' => 'NIS-OK', 'nama' => 'Siswa Baru'],
            ['nis' => 'NIS-DUP', 'nama' => 'Duplikat'],
        ]);

        $response = $this->actingAs($admin)->post(route('admin.kelas.siswa.import', $kelas), [
            'file' => $file,
        ]);

        $response->assertRedirect(route('admin.kelas.show', $kelas));
        $response->assertSessionHas('import_errors');
        $this->assertSame(1, Siswa::query()->count());
        $this->assertSame('NIS-OK', Siswa::query()->value('nis'));
    }

    public function test_import_rejects_existing_nis_from_different_kelas(): void
    {
        $lembaga = Lembaga::factory()->create();
        $admin = User::factory()->adminLembaga($lembaga->id)->create();
        $tahunAjaran = TahunAjaran::factory()->for($lembaga)->create();
        $kelasTarget = Kelas::factory()->for($lembaga)->create([
            'tahun_ajaran_id' => $tahunAjaran->id,
        ]);
        $kelasLain = Kelas::factory()->for($lembaga)->create([
            'tahun_ajaran_id' => $tahunAjaran->id,
        ]);

        Siswa::factory()->for($lembaga)->inKelas($kelasLain)->create([
            'nis' => 'NIS-KELAS-LAIN',
            'nama' => 'Siswa Kelas Lain',
        ]);

        $file = $this->makeImportFile([
            ['nis' => 'NIS-KELAS-LAIN', 'nama' => 'Siswa Kelas Lain'],
        ]);

        $response = $this->actingAs($admin)->post(route('admin.kelas.siswa.import', $kelasTarget), [
            'file' => $file,
        ]);

        $response->assertRedirect(route('admin.kelas.show', $kelasTarget));
        $response->assertSessionHas('import_errors');

        $errors = session('import_errors');
        $this->assertSame('NIS NIS-KELAS-LAIN sudah terdaftar di kelas lain. Update lewat import hanya untuk siswa di kelas ini.', $errors[0]['message']);
    }

    public function test_other_lembaga_cannot_import_to_kelas(): void
    {
        $lembagaA = Lembaga::factory()->create();
        $lembagaB = Lembaga::factory()->create();
        $adminA = User::factory()->adminLembaga($lembagaA->id)->create();
        $tahunAjaranB = TahunAjaran::factory()->for($lembagaB)->create();
        $kelasB = Kelas::factory()->for($lembagaB)->create([
            'tahun_ajaran_id' => $tahunAjaranB->id,
        ]);

        $file = $this->makeImportFile([
            ['nis' => 'NIS-X', 'nama' => 'Siswa X'],
        ]);

        $this->actingAs($adminA)->post(route('admin.kelas.siswa.import', $kelasB), [
            'file' => $file,
        ])->assertNotFound();

        $this->actingAs($adminA)->get(route('admin.kelas.siswa.template', $kelasB))
            ->assertNotFound();

        $this->assertSame(0, Siswa::query()->count());
    }

    /**
     * @param  list<array<string, mixed>>  $rows
     */
    private function makeImportFile(array $rows): UploadedFile
    {
        $spreadsheet = new Spreadsheet;
        $sheet = $spreadsheet->getActiveSheet();
        $sheet->setTitle('Data Siswa');

        foreach (SiswaTemplateExporter::dataHeaders() as $index => $header) {
            $column = chr(ord('A') + $index);
            $sheet->setCellValue($column.'1', $header);
        }

        $headers = SiswaTemplateExporter::dataHeaders();

        foreach ($rows as $rowIndex => $row) {
            $excelRow = $rowIndex + 2;
            foreach ($headers as $index => $header) {
                $column = chr(ord('A') + $index);
                $sheet->setCellValue($column.$excelRow, $row[$header] ?? '');
            }
        }

        $path = tempnam(sys_get_temp_dir(), 'siswa-import-').'.xlsx';
        (new Xlsx($spreadsheet))->save($path);

        return new UploadedFile($path, 'import-siswa.xlsx', null, null, true);
    }
}
