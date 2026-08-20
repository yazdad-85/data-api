<?php

namespace Tests\Feature;

use App\Models\Guru;
use App\Models\Karyawan;
use App\Models\Lembaga;
use App\Models\Siswa;
use App\Models\TahunAjaran;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PhpOffice\PhpSpreadsheet\IOFactory;
use Tests\TestCase;

class SuperAdminMonitoringTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        config(['security.mfa.super_admin_required' => false]);
    }

    public function test_super_admin_dashboard_shows_monitoring_summary_and_links(): void
    {
        $superAdmin = User::factory()->create(['role' => 'super_admin', 'lembaga_id' => null]);
        $lembaga = Lembaga::factory()->create(['nama' => 'MA YASMU']);

        Guru::factory()->create(['lembaga_id' => $lembaga->id, 'nama' => 'Guru Dashboard']);
        Siswa::factory()->create(['lembaga_id' => $lembaga->id, 'nama' => 'Siswa Dashboard']);
        Karyawan::factory()->create(['lembaga_id' => $lembaga->id, 'nama' => 'Karyawan Dashboard']);

        $this->actingAs($superAdmin)
            ->get(route('admin.dashboard'))
            ->assertOk()
            ->assertSee('Pantau kesiapan data semua lembaga')
            ->assertSee('Monitoring siswa')
            ->assertSee('MA YASMU')
            ->assertSee(route('admin.monitoring.guru', ['lembaga_id' => $lembaga->id]), false)
            ->assertSee(route('admin.monitoring.siswa', ['lembaga_id' => $lembaga->id]), false)
            ->assertSee(route('admin.monitoring.karyawan', ['lembaga_id' => $lembaga->id]), false);
    }

    public function test_super_admin_can_filter_siswa_monitoring_by_lembaga_and_tahun_ajaran(): void
    {
        $superAdmin = User::factory()->create(['role' => 'super_admin', 'lembaga_id' => null]);
        $lembagaA = Lembaga::factory()->create(['nama' => 'SMP A']);
        $lembagaB = Lembaga::factory()->create(['nama' => 'SMP B']);
        $tahunA = TahunAjaran::factory()->create(['lembaga_id' => $lembagaA->id, 'nama' => '2026/2027']);
        $tahunB = TahunAjaran::factory()->create(['lembaga_id' => $lembagaB->id, 'nama' => '2027/2028']);

        Siswa::factory()->create([
            'lembaga_id' => $lembagaA->id,
            'tahun_ajaran_id' => $tahunA->id,
            'nama' => 'Siswa Terpilih',
            'nis' => 'NIS-A',
        ]);
        Siswa::factory()->create([
            'lembaga_id' => $lembagaB->id,
            'tahun_ajaran_id' => $tahunB->id,
            'nama' => 'Siswa Tidak Tampil',
            'nis' => 'NIS-B',
        ]);

        $this->actingAs($superAdmin)
            ->get(route('admin.monitoring.siswa', [
                'lembaga_id' => $lembagaA->id,
                'tahun_ajaran_id' => $tahunA->id,
            ]))
            ->assertOk()
            ->assertSee('Export Excel')
            ->assertSee('Siswa Terpilih')
            ->assertSee('2026/2027')
            ->assertDontSee('Siswa Tidak Tampil');
    }

    public function test_super_admin_can_export_filtered_siswa_monitoring_to_excel(): void
    {
        $superAdmin = User::factory()->create(['role' => 'super_admin', 'lembaga_id' => null]);
        $lembagaA = Lembaga::factory()->create(['nama' => 'SMP Export A']);
        $lembagaB = Lembaga::factory()->create(['nama' => 'SMP Export B']);
        $tahunA = TahunAjaran::factory()->create(['lembaga_id' => $lembagaA->id, 'nama' => '2026/2027']);
        $tahunB = TahunAjaran::factory()->create(['lembaga_id' => $lembagaB->id, 'nama' => '2027/2028']);

        Siswa::factory()->create([
            'lembaga_id' => $lembagaA->id,
            'tahun_ajaran_id' => $tahunA->id,
            'nama' => 'Siswa Export',
            'nis' => 'NIS-EXPORT',
            'status_asal' => 'SMP Asal',
        ]);
        Siswa::factory()->create([
            'lembaga_id' => $lembagaB->id,
            'tahun_ajaran_id' => $tahunB->id,
            'nama' => 'Siswa Tidak Diexport',
            'nis' => 'NIS-NO',
        ]);

        $response = $this->actingAs($superAdmin)
            ->get(route('admin.monitoring.siswa.export', [
                'lembaga_id' => $lembagaA->id,
                'tahun_ajaran_id' => $tahunA->id,
            ]));

        $response->assertOk();
        $response->assertHeader('content-type', 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
        $this->assertStringContainsString(
            'monitoring-siswa-',
            (string) $response->headers->get('content-disposition')
        );

        $rows = $this->xlsxRows($response->streamedContent());
        $this->assertSame('Nama', $rows[0][0]);
        $this->assertSame('Asal', $rows[0][16]);
        $this->assertSame('Siswa Export', $rows[1][0]);
        $this->assertSame('SMP Export A', $rows[1][1]);
        $this->assertSame('NIS-EXPORT', $rows[1][2]);
        $this->assertSame('SMP Asal', $rows[1][16]);
        $this->assertCount(2, $rows);
    }

    public function test_super_admin_can_filter_guru_and_karyawan_monitoring(): void
    {
        $superAdmin = User::factory()->create(['role' => 'super_admin', 'lembaga_id' => null]);
        $lembagaA = Lembaga::factory()->create(['nama' => 'Lembaga A']);
        $lembagaB = Lembaga::factory()->create(['nama' => 'Lembaga B']);

        Guru::factory()->create(['lembaga_id' => $lembagaA->id, 'nama' => 'Guru Terpilih', 'niy' => 'NIY-A', 'tahun_masuk' => 2026]);
        Guru::factory()->create(['lembaga_id' => $lembagaB->id, 'nama' => 'Guru Lain', 'niy' => 'NIY-B', 'tahun_masuk' => 2025]);
        Karyawan::factory()->create(['lembaga_id' => $lembagaA->id, 'nama' => 'Karyawan Terpilih', 'nik_pegawai' => 'NIK-A', 'tahun_masuk' => 2026]);
        Karyawan::factory()->create(['lembaga_id' => $lembagaB->id, 'nama' => 'Karyawan Lain', 'nik_pegawai' => 'NIK-B', 'tahun_masuk' => 2025]);

        $this->actingAs($superAdmin)
            ->get(route('admin.monitoring.guru', ['lembaga_id' => $lembagaA->id, 'tahun' => 2026]))
            ->assertOk()
            ->assertSee('Guru Terpilih')
            ->assertDontSee('Guru Lain');

        $this->actingAs($superAdmin)
            ->get(route('admin.monitoring.karyawan', ['lembaga_id' => $lembagaA->id, 'tahun' => 2026]))
            ->assertOk()
            ->assertSee('Karyawan Terpilih')
            ->assertDontSee('Karyawan Lain');
    }

    public function test_super_admin_can_export_guru_and_karyawan_monitoring_to_excel(): void
    {
        $superAdmin = User::factory()->create(['role' => 'super_admin', 'lembaga_id' => null]);
        $lembaga = Lembaga::factory()->create(['nama' => 'Lembaga Export']);

        Guru::factory()->create([
            'lembaga_id' => $lembaga->id,
            'nama' => 'Guru Export',
            'niy' => 'NIY-EXPORT',
            'nik' => 'NIK-GURU-EXPORT',
            'tahun_masuk' => 2026,
            'pendidikan_terakhir' => 'S1',
        ]);
        Karyawan::factory()->create([
            'lembaga_id' => $lembaga->id,
            'nama' => 'Karyawan Export',
            'nik_pegawai' => 'NIK-EXPORT',
            'tahun_masuk' => 2026,
        ]);

        $guru = $this->actingAs($superAdmin)
            ->get(route('admin.monitoring.guru.export', ['lembaga_id' => $lembaga->id]));
        $guru->assertOk();
        $guruRows = $this->xlsxRows($guru->streamedContent());
        $this->assertSame('Nama', $guruRows[0][0]);
        $this->assertSame('Guru Export', $guruRows[1][0]);
        $this->assertSame('NIY-EXPORT', $guruRows[1][2]);
        $this->assertSame('NIK-GURU-EXPORT', $guruRows[1][3]);
        $this->assertSame('S1', $guruRows[1][6]);

        $karyawan = $this->actingAs($superAdmin)
            ->get(route('admin.monitoring.karyawan.export', ['lembaga_id' => $lembaga->id]));
        $karyawan->assertOk();
        $karyawanRows = $this->xlsxRows($karyawan->streamedContent());
        $this->assertSame('Nama', $karyawanRows[0][0]);
        $this->assertSame('Karyawan Export', $karyawanRows[1][0]);
        $this->assertSame('NIK-EXPORT', $karyawanRows[1][2]);
    }

    public function test_admin_lembaga_is_forbidden_from_super_admin_monitoring(): void
    {
        $lembaga = Lembaga::factory()->create();
        $admin = User::factory()->create(['role' => 'admin_lembaga', 'lembaga_id' => $lembaga->id]);

        $this->actingAs($admin)->get(route('admin.monitoring.guru'))->assertForbidden();
        $this->actingAs($admin)->get(route('admin.monitoring.siswa'))->assertForbidden();
        $this->actingAs($admin)->get(route('admin.monitoring.karyawan'))->assertForbidden();
        $this->actingAs($admin)->get(route('admin.monitoring.guru.export'))->assertForbidden();
        $this->actingAs($admin)->get(route('admin.monitoring.siswa.export'))->assertForbidden();
        $this->actingAs($admin)->get(route('admin.monitoring.karyawan.export'))->assertForbidden();
    }

    /**
     * @return list<list<mixed>>
     */
    private function xlsxRows(string $content): array
    {
        $path = tempnam(sys_get_temp_dir(), 'monitoring-export-').'.xlsx';
        file_put_contents($path, $content);

        try {
            $spreadsheet = IOFactory::load($path);

            return $spreadsheet->getActiveSheet()->toArray();
        } finally {
            @unlink($path);
        }
    }
}
