<?php

namespace Tests\Feature;

use App\Models\Kelas;
use App\Models\Lembaga;
use App\Models\Siswa;
use App\Models\SiswaPenempatan;
use App\Models\TahunAjaran;
use App\Support\Master\SiswaStatus;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SiswaPenempatanBackfillTest extends TestCase
{
    use RefreshDatabase;

    public function test_can_create_open_penempatan_for_siswa_with_kelas(): void
    {
        $lembaga = Lembaga::factory()->create();
        $tahunAjaran = TahunAjaran::factory()->for($lembaga)->create();
        $kelas = Kelas::factory()->for($lembaga)->create([
            'tahun_ajaran_id' => $tahunAjaran->id,
        ]);
        $siswa = Siswa::factory()->for($lembaga)->inKelas($kelas)->create();

        $penempatan = SiswaPenempatan::factory()
            ->for($lembaga)
            ->for($siswa)
            ->open()
            ->create([
                'tahun_ajaran_id' => $tahunAjaran->id,
                'kelas_id' => $kelas->id,
            ]);

        $this->assertNull($penempatan->selesai_at);
        $this->assertSame($kelas->id, $penempatan->kelas_id);
        $this->assertSame($siswa->id, $penempatan->siswa_id);
        $this->assertSame(
            1,
            SiswaPenempatan::withoutGlobalScopes()->where('siswa_id', $siswa->id)->count()
        );
    }

    public function test_second_open_penempatan_for_same_siswa_violates_unique_index(): void
    {
        $lembaga = Lembaga::factory()->create();
        $siswa = Siswa::factory()->for($lembaga)->withoutKelas()->create();

        SiswaPenempatan::factory()->for($lembaga)->for($siswa)->open()->create();

        $this->expectException(QueryException::class);

        SiswaPenempatan::factory()->for($lembaga)->for($siswa)->open()->create();
    }

    public function test_backfill_creates_awal_penempatan_for_existing_siswa_with_kelas_only(): void
    {
        $lembaga = Lembaga::factory()->create();
        $tahunAjaran = TahunAjaran::factory()->for($lembaga)->create();
        $kelas = Kelas::factory()->for($lembaga)->create([
            'tahun_ajaran_id' => $tahunAjaran->id,
        ]);

        // Simulasikan baris lama yang sudah ada sebelum migration ini pernah dijalankan
        // (backfill dalam migration nyata hanya berjalan sekali saat kolom/tabel baru dibuat).
        $siswaDenganKelas = Siswa::factory()->for($lembaga)->inKelas($kelas)->create();
        $siswaTanpaKelas = Siswa::factory()->for($lembaga)->withoutKelas()->create();
        SiswaPenempatan::withoutGlobalScopes()->where('siswa_id', $siswaDenganKelas->id)->delete();

        $migration = require base_path('database/migrations/2026_07_22_000002_add_siswa_lifecycle_and_penempatan.php');
        $backfill = new \ReflectionMethod($migration, 'backfill');
        $backfill->setAccessible(true);
        $backfill->invoke($migration);

        $backfilled = SiswaPenempatan::withoutGlobalScopes()->where('siswa_id', $siswaDenganKelas->id)->first();
        $this->assertNotNull($backfilled);
        $this->assertSame('awal', $backfilled->jenis);
        $this->assertNull($backfilled->selesai_at);
        $this->assertSame($kelas->id, $backfilled->kelas_id);
        $this->assertSame($tahunAjaran->id, $backfilled->tahun_ajaran_id);

        $this->assertSame(
            0,
            SiswaPenempatan::withoutGlobalScopes()->where('siswa_id', $siswaTanpaKelas->id)->count()
        );
    }

    public function test_siswa_status_transitions_and_active_flag_semantics(): void
    {
        $this->assertSame(
            [SiswaStatus::MUTASI_MASUK, SiswaStatus::AKTIF],
            SiswaStatus::allowedTransitions(SiswaStatus::CALON)
        );
        $this->assertSame(
            [SiswaStatus::AKTIF, SiswaStatus::MUTASI_KELUAR],
            SiswaStatus::allowedTransitions(SiswaStatus::MUTASI_MASUK)
        );
        $this->assertSame(
            [SiswaStatus::AKTIF, SiswaStatus::MUTASI_KELUAR, SiswaStatus::LULUS],
            SiswaStatus::allowedTransitions(SiswaStatus::AKTIF)
        );
        $this->assertSame([], SiswaStatus::allowedTransitions(SiswaStatus::LULUS));
        $this->assertSame([], SiswaStatus::allowedTransitions(SiswaStatus::MUTASI_KELUAR));

        $this->assertTrue(SiswaStatus::isActiveFlag(SiswaStatus::AKTIF));
        $this->assertTrue(SiswaStatus::isActiveFlag(SiswaStatus::MUTASI_MASUK));
        $this->assertFalse(SiswaStatus::isActiveFlag(SiswaStatus::CALON));
        $this->assertFalse(SiswaStatus::isActiveFlag(SiswaStatus::MUTASI_KELUAR));
        $this->assertFalse(SiswaStatus::isActiveFlag(SiswaStatus::LULUS));
    }
}
