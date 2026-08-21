<?php

namespace Tests\Feature;

use App\Models\Guru;
use App\Models\Karyawan;
use App\Models\Lembaga;
use App\Models\Kelas;
use App\Models\Siswa;
use App\Models\SiswaPenempatan;
use App\Models\TahunAjaran;
use App\Models\User;
use App\Support\Master\PenempatanJenis;
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
            ->assertSee('Pusat kendali data lembaga')
            ->assertSee('Perkembangan data')
            ->assertSee('Status siswa')
            ->assertSee('Laporan siswa')
            ->assertSee('Monitoring')
            ->assertSee('MA YASMU')
            ->assertSee(route('admin.monitoring.guru', ['lembaga_id' => $lembaga->id]), false)
            ->assertSee(str_replace('&', '&amp;', route('admin.laporan.siswa', ['lembaga_id' => $lembaga->id, 'status_siswa' => 'aktif'])), false)
            ->assertSee(route('admin.monitoring.karyawan', ['lembaga_id' => $lembaga->id]), false);
    }

    public function test_admin_dashboard_shows_selected_and_comparison_tahun_ajaran_student_history(): void
    {
        $lembaga = Lembaga::factory()->create(['nama' => 'UD Farida']);
        $admin = User::factory()->create(['role' => 'admin_lembaga', 'lembaga_id' => $lembaga->id]);
        $tahunAjaran25 = TahunAjaran::factory()->create([
            'lembaga_id' => $lembaga->id,
            'nama' => '2025/2026',
            'tanggal_mulai' => '2025-07-01',
            'tanggal_selesai' => '2026-06-30',
        ]);
        $tahunAjaran26 = TahunAjaran::factory()->create([
            'lembaga_id' => $lembaga->id,
            'nama' => '2026/2027',
            'tanggal_mulai' => '2026-07-01',
            'tanggal_selesai' => '2027-06-30',
        ]);
        $kelas25 = Kelas::factory()->create([
            'lembaga_id' => $lembaga->id,
            'tahun_ajaran_id' => $tahunAjaran25->id,
            'nama' => 'VII-A',
        ]);
        $kelas26 = Kelas::factory()->create([
            'lembaga_id' => $lembaga->id,
            'tahun_ajaran_id' => $tahunAjaran26->id,
            'nama' => 'VIII-A',
        ]);

        Siswa::factory()->inKelas($kelas25)->create(['nama' => 'Aktif Reguler']);
        $mutasiMasuk = Siswa::factory()->inKelas($kelas25)->create([
            'nama' => 'Mutasi Masuk Aktif',
            'status_at' => '2025-07-20',
        ]);
        SiswaPenempatan::factory()->open()->create([
            'lembaga_id' => $lembaga->id,
            'siswa_id' => $mutasiMasuk->id,
            'tahun_ajaran_id' => $tahunAjaran25->id,
            'kelas_id' => $kelas25->id,
            'mulai_at' => '2025-07-20',
            'jenis' => PenempatanJenis::MUTASI_MASUK,
        ]);
        Siswa::factory()->for($lembaga)->mutasiKeluar()->create([
            'nama' => 'Mutasi Keluar Lama',
            'status_at' => '2026-01-15',
        ]);
        Siswa::factory()->for($lembaga)->lulus()->create([
            'nama' => 'Lulus Lama',
            'status_at' => '2026-06-15',
        ]);
        Siswa::factory()->inKelas($kelas26)->create(['nama' => 'Aktif Tahun Baru']);

        $this->actingAs($admin)
            ->get(route('admin.dashboard', ['tahun_ajaran_id' => $tahunAjaran25->id]))
            ->assertOk()
            ->assertSee('Komposisi siswa tahun ajaran')
            ->assertSee('2025/2026')
            ->assertSee('Mutasi masuk')
            ->assertSee('title="Total: 4"', false)
            ->assertSee('title="Aktif: 1"', false)
            ->assertSee('title="Mutasi masuk: 1"', false)
            ->assertSee('title="Mutasi keluar: 1"', false)
            ->assertSee('title="Lulus: 1"', false)
            ->assertDontSee('Kelengkapan data');

        $this->actingAs($admin)
            ->get(route('admin.dashboard'))
            ->assertOk()
            ->assertSee('Perbandingan tahun ajaran')
            ->assertSee('2025/2026')
            ->assertSee('2026/2027')
            ->assertSee('title="Total: 4"', false)
            ->assertSee('title="Total: 1"', false);
    }

    public function test_super_admin_dashboard_filter_lembaga_limits_cards_and_charts(): void
    {
        $superAdmin = User::factory()->create(['role' => 'super_admin', 'lembaga_id' => null]);
        $lembagaA = Lembaga::factory()->create(['nama' => 'SMP Filter A']);
        $lembagaB = Lembaga::factory()->create(['nama' => 'SMP Filter B']);
        $tahunA = TahunAjaran::factory()->create([
            'lembaga_id' => $lembagaA->id,
            'nama' => '2026/2027',
            'tanggal_mulai' => '2026-07-01',
            'tanggal_selesai' => '2027-06-30',
        ]);
        TahunAjaran::factory()->create([
            'lembaga_id' => $lembagaB->id,
            'nama' => '2027/2028',
            'tanggal_mulai' => '2027-07-01',
            'tanggal_selesai' => '2028-06-30',
        ]);
        $kelasA = Kelas::factory()->create([
            'lembaga_id' => $lembagaA->id,
            'tahun_ajaran_id' => $tahunA->id,
        ]);

        Guru::factory()->create(['lembaga_id' => $lembagaA->id]);
        Guru::factory()->count(3)->create(['lembaga_id' => $lembagaB->id]);
        Karyawan::factory()->create(['lembaga_id' => $lembagaA->id]);
        Karyawan::factory()->count(2)->create(['lembaga_id' => $lembagaB->id]);
        Siswa::factory()->inKelas($kelasA)->create();
        Siswa::factory()->count(4)->create(['lembaga_id' => $lembagaB->id]);

        $this->actingAs($superAdmin)
            ->get(route('admin.dashboard', ['lembaga_id' => $lembagaA->id]))
            ->assertOk()
            ->assertSee('value="'.$lembagaA->id.'" selected', false)
            ->assertSee('1 total data siswa tercatat.')
            ->assertSee('title="Siswa: 1"', false)
            ->assertSee('title="Guru: 1"', false)
            ->assertSee('title="Karyawan: 1"', false)
            ->assertSee('2026/2027')
            ->assertDontSee('2027/2028');
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
            'status_keluarga' => 'Yatim',
            'nama_ayah' => 'Ayah Export',
            'pekerjaan_ayah' => 'Petani',
            'nama_ibu' => 'Ibu Export',
            'pekerjaan_ibu' => 'Pedagang',
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
        $this->assertSame('Status Keluarga', $rows[0][8]);
        $this->assertSame('Asal', $rows[0][21]);
        $this->assertSame('Siswa Export', $rows[1][0]);
        $this->assertSame('SMP Export A', $rows[1][1]);
        $this->assertSame('NIS-EXPORT', $rows[1][2]);
        $this->assertSame('Yatim', $rows[1][8]);
        $this->assertSame('Ayah Export', $rows[1][9]);
        $this->assertSame('Petani', $rows[1][10]);
        $this->assertSame('Ibu Export', $rows[1][11]);
        $this->assertSame('Pedagang', $rows[1][12]);
        $this->assertSame('SMP Asal', $rows[1][21]);
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
            'peg_id' => 'PEG-EXPORT',
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
        $this->assertSame('Peg-ID', $guruRows[0][4]);
        $this->assertSame('PEG-EXPORT', $guruRows[1][4]);
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
