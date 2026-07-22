<?php

namespace Tests\Feature;

use App\Models\Kelas;
use App\Models\Lembaga;
use App\Models\Siswa;
use App\Models\SiswaPenempatan;
use App\Models\TahunAjaran;
use App\Services\Siswa\SiswaLifecycleService;
use App\Support\Master\PenempatanJenis;
use App\Support\Master\SiswaStatus;
use Illuminate\Foundation\Testing\RefreshDatabase;
use InvalidArgumentException;
use Tests\TestCase;

class SiswaLifecycleTest extends TestCase
{
    use RefreshDatabase;

    private function service(): SiswaLifecycleService
    {
        return new SiswaLifecycleService;
    }

    public function test_tempatkan_calon_menjadi_aktif_dengan_satu_penempatan_terbuka(): void
    {
        $lembaga = Lembaga::factory()->create();
        $tahunAjaran = TahunAjaran::factory()->for($lembaga)->create();
        $kelas = Kelas::factory()->for($lembaga)->create(['tahun_ajaran_id' => $tahunAjaran->id]);
        $siswa = Siswa::factory()->for($lembaga)->calon()->create();

        $result = $this->service()->tempatkan($siswa, $kelas);

        $this->assertSame(SiswaStatus::AKTIF, $result->status_siswa);
        $this->assertTrue($result->is_active);
        $this->assertSame($kelas->id, $result->kelas_id);
        $this->assertSame($tahunAjaran->id, $result->tahun_ajaran_id);

        $penempatans = SiswaPenempatan::withoutGlobalScopes()->where('siswa_id', $siswa->id)->get();
        $this->assertCount(1, $penempatans);
        $this->assertNull($penempatans->first()->selesai_at);
        $this->assertSame(PenempatanJenis::AWAL, $penempatans->first()->jenis);
        $this->assertSame($kelas->id, $penempatans->first()->kelas_id);
    }

    public function test_mutasi_masuk_lalu_ditempatkan_menghasilkan_penempatan_jenis_mutasi_masuk(): void
    {
        $lembaga = Lembaga::factory()->create();
        $tahunAjaran = TahunAjaran::factory()->for($lembaga)->create();
        $kelas = Kelas::factory()->for($lembaga)->create(['tahun_ajaran_id' => $tahunAjaran->id]);
        $siswa = Siswa::factory()->for($lembaga)->mutasiMasuk()->create();

        $this->assertFalse($siswa->is_active);

        $result = $this->service()->tempatkan($siswa, $kelas);

        $this->assertSame(SiswaStatus::AKTIF, $result->status_siswa);
        $this->assertTrue($result->is_active);

        $penempatan = SiswaPenempatan::withoutGlobalScopes()
            ->where('siswa_id', $siswa->id)
            ->whereNull('selesai_at')
            ->first();

        $this->assertNotNull($penempatan);
        $this->assertSame(PenempatanJenis::MUTASI_MASUK, $penempatan->jenis);
    }

    public function test_transisi_ilegal_ditolak(): void
    {
        $lembaga = Lembaga::factory()->create();
        $service = $this->service();

        $siswaLulus = Siswa::factory()->for($lembaga)->lulus()->create();

        try {
            $service->setStatus($siswaLulus, SiswaStatus::AKTIF);
            $this->fail('Transisi dari lulus ke aktif seharusnya ditolak.');
        } catch (InvalidArgumentException $exception) {
            $this->assertStringContainsString('tidak diizinkan', $exception->getMessage());
        }

        $siswaCalon = Siswa::factory()->for($lembaga)->calon()->create();

        try {
            $service->mutasiKeluar($siswaCalon);
            $this->fail('Mutasi keluar dari calon seharusnya ditolak.');
        } catch (InvalidArgumentException $exception) {
            $this->assertStringContainsString('tidak diizinkan', $exception->getMessage());
        }

        $this->assertSame(SiswaStatus::LULUS, $siswaLulus->refresh()->status_siswa);
        $this->assertSame(SiswaStatus::CALON, $siswaCalon->refresh()->status_siswa);
    }

    public function test_kelas_beda_lembaga_ditolak_saat_tempatkan(): void
    {
        $lembagaA = Lembaga::factory()->create();
        $lembagaB = Lembaga::factory()->create();
        $tahunAjaranB = TahunAjaran::factory()->for($lembagaB)->create();
        $kelasB = Kelas::factory()->for($lembagaB)->create(['tahun_ajaran_id' => $tahunAjaranB->id]);
        $siswaA = Siswa::factory()->for($lembagaA)->calon()->create();

        $this->expectException(InvalidArgumentException::class);

        $this->service()->tempatkan($siswaA, $kelasB);
    }

    public function test_mutasi_keluar_mengosongkan_kelas_dan_menutup_penempatan(): void
    {
        $lembaga = Lembaga::factory()->create();
        $tahunAjaran = TahunAjaran::factory()->for($lembaga)->create();
        $kelas = Kelas::factory()->for($lembaga)->create(['tahun_ajaran_id' => $tahunAjaran->id]);
        $siswa = Siswa::factory()->for($lembaga)->inKelas($kelas)->create();
        SiswaPenempatan::factory()->for($lembaga)->for($siswa)->open()->create([
            'tahun_ajaran_id' => $tahunAjaran->id,
            'kelas_id' => $kelas->id,
            'jenis' => PenempatanJenis::AWAL,
        ]);

        $result = $this->service()->mutasiKeluar($siswa, ['alasan' => 'Pindah domisili']);

        $this->assertSame(SiswaStatus::MUTASI_KELUAR, $result->status_siswa);
        $this->assertFalse($result->is_active);
        $this->assertNull($result->kelas_id);
        $this->assertNull($result->tahun_ajaran_id);
        $this->assertSame('Pindah domisili', $result->status_alasan);

        $penempatans = SiswaPenempatan::withoutGlobalScopes()->where('siswa_id', $siswa->id)->get();
        $this->assertCount(1, $penempatans);
        $penempatan = $penempatans->first();
        $this->assertNotNull($penempatan->selesai_at);
        // Jejak historis dipertahankan: jenis dan kelas_id tetap merujuk penempatan asli.
        $this->assertSame(PenempatanJenis::AWAL, $penempatan->jenis);
        $this->assertSame($kelas->id, $penempatan->kelas_id);
    }

    public function test_luluskan_mengosongkan_kelas_dan_menutup_penempatan(): void
    {
        $lembaga = Lembaga::factory()->create();
        $tahunAjaran = TahunAjaran::factory()->for($lembaga)->create();
        $kelas = Kelas::factory()->for($lembaga)->create(['tahun_ajaran_id' => $tahunAjaran->id]);
        $siswa = Siswa::factory()->for($lembaga)->inKelas($kelas)->create();
        SiswaPenempatan::factory()->for($lembaga)->for($siswa)->open()->create([
            'tahun_ajaran_id' => $tahunAjaran->id,
            'kelas_id' => $kelas->id,
            'jenis' => PenempatanJenis::AWAL,
        ]);

        $result = $this->service()->luluskan($siswa);

        $this->assertSame(SiswaStatus::LULUS, $result->status_siswa);
        $this->assertFalse($result->is_active);
        $this->assertNull($result->kelas_id);
        $this->assertNull($result->tahun_ajaran_id);

        $penempatan = SiswaPenempatan::withoutGlobalScopes()->where('siswa_id', $siswa->id)->first();
        $this->assertNotNull($penempatan->selesai_at);
        // Jejak historis dipertahankan: jenis dan kelas_id tetap merujuk penempatan asli.
        $this->assertSame(PenempatanJenis::AWAL, $penempatan->jenis);
        $this->assertSame($kelas->id, $penempatan->kelas_id);
    }

    public function test_pindah_kelas_menutup_lama_membuka_baru(): void
    {
        $lembaga = Lembaga::factory()->create();
        $tahunAjaran = TahunAjaran::factory()->for($lembaga)->create();
        $kelasAsal = Kelas::factory()->for($lembaga)->create(['tahun_ajaran_id' => $tahunAjaran->id]);
        $kelasTujuan = Kelas::factory()->for($lembaga)->create(['tahun_ajaran_id' => $tahunAjaran->id]);
        $siswa = Siswa::factory()->for($lembaga)->inKelas($kelasAsal)->create();
        $penempatanLama = SiswaPenempatan::factory()->for($lembaga)->for($siswa)->open()->create([
            'tahun_ajaran_id' => $tahunAjaran->id,
            'kelas_id' => $kelasAsal->id,
            'jenis' => PenempatanJenis::AWAL,
        ]);

        $result = $this->service()->pindahKelas($siswa, $kelasTujuan);

        $this->assertSame(SiswaStatus::AKTIF, $result->status_siswa);
        $this->assertSame($kelasTujuan->id, $result->kelas_id);
        $this->assertSame($tahunAjaran->id, $result->tahun_ajaran_id);

        $penempatanLama->refresh();
        $this->assertNotNull($penempatanLama->selesai_at);

        $terbuka = SiswaPenempatan::withoutGlobalScopes()
            ->where('siswa_id', $siswa->id)
            ->whereNull('selesai_at')
            ->first();
        $this->assertNotNull($terbuka);
        $this->assertSame($kelasTujuan->id, $terbuka->kelas_id);
        $this->assertSame(PenempatanJenis::PINDAH_KELAS, $terbuka->jenis);

        $this->assertSame(
            2,
            SiswaPenempatan::withoutGlobalScopes()->where('siswa_id', $siswa->id)->count()
        );
    }

    public function test_pindah_kelas_ke_tahun_ajaran_berbeda_tercatat_sebagai_kenaikan(): void
    {
        $lembaga = Lembaga::factory()->create();
        $tahunAjaranLama = TahunAjaran::factory()->for($lembaga)->create();
        $tahunAjaranBaru = TahunAjaran::factory()->for($lembaga)->create();
        $kelasAsal = Kelas::factory()->for($lembaga)->create(['tahun_ajaran_id' => $tahunAjaranLama->id]);
        $kelasTujuan = Kelas::factory()->for($lembaga)->create(['tahun_ajaran_id' => $tahunAjaranBaru->id]);
        $siswa = Siswa::factory()->for($lembaga)->inKelas($kelasAsal)->create();
        SiswaPenempatan::factory()->for($lembaga)->for($siswa)->open()->create([
            'tahun_ajaran_id' => $tahunAjaranLama->id,
            'kelas_id' => $kelasAsal->id,
            'jenis' => PenempatanJenis::AWAL,
        ]);

        $result = $this->service()->pindahKelas($siswa, $kelasTujuan);

        $this->assertSame($tahunAjaranBaru->id, $result->tahun_ajaran_id);

        $terbuka = SiswaPenempatan::withoutGlobalScopes()
            ->where('siswa_id', $siswa->id)
            ->whereNull('selesai_at')
            ->first();
        $this->assertSame(PenempatanJenis::KENAIKAN, $terbuka->jenis);
    }

    public function test_pindah_kelas_hanya_dari_status_aktif(): void
    {
        $lembaga = Lembaga::factory()->create();
        $tahunAjaran = TahunAjaran::factory()->for($lembaga)->create();
        $kelasTujuan = Kelas::factory()->for($lembaga)->create(['tahun_ajaran_id' => $tahunAjaran->id]);
        $siswa = Siswa::factory()->for($lembaga)->calon()->create();

        $this->expectException(InvalidArgumentException::class);

        $this->service()->pindahKelas($siswa, $kelasTujuan);
    }
}
