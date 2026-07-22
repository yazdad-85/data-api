<?php

namespace Tests\Unit;

use App\Models\Kelas;
use App\Models\Siswa;
use App\Models\TahunAjaran;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class KelasSiswaFactoryTest extends TestCase
{
    use RefreshDatabase;

    public function test_kelas_factory_creates_record_with_matching_lembaga_and_tahun_ajaran(): void
    {
        $kelas = Kelas::factory()->create();

        $this->assertNotNull($kelas->lembaga_id);
        $this->assertNotNull($kelas->tahun_ajaran_id);
        $this->assertStringStartsWith('VII-', $kelas->nama);
        $tahunAjaran = TahunAjaran::withoutGlobalScopes()->find($kelas->tahun_ajaran_id);

        $this->assertNotNull($tahunAjaran);
        $this->assertSame($kelas->lembaga_id, $tahunAjaran->lembaga_id);
    }

    public function test_siswa_factory_creates_active_student_without_kelas_by_default(): void
    {
        $siswa = Siswa::factory()->create();

        $this->assertTrue($siswa->is_active);
        $this->assertNotNull($siswa->nis);
        $this->assertNull($siswa->kelas_id);
        $this->assertNull($siswa->tahun_ajaran_id);
    }

    public function test_siswa_factory_in_kelas_state_assigns_kelas_context(): void
    {
        $kelas = Kelas::factory()->create();
        $siswa = Siswa::factory()->inKelas($kelas)->create();

        $this->assertSame($kelas->id, $siswa->kelas_id);
        $this->assertSame($kelas->tahun_ajaran_id, $siswa->tahun_ajaran_id);
        $this->assertSame($kelas->lembaga_id, $siswa->lembaga_id);
    }

    public function test_siswa_factory_without_kelas_state_clears_kelas_fields(): void
    {
        $kelas = Kelas::factory()->create();
        $siswa = Siswa::factory()->inKelas($kelas)->withoutKelas()->create();

        $this->assertNull($siswa->kelas_id);
        $this->assertNull($siswa->tahun_ajaran_id);
    }
}
