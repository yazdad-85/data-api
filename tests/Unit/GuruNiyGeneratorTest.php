<?php

namespace Tests\Unit;

use App\Models\Guru;
use App\Models\Lembaga;
use App\Support\Master\GuruNiyGenerator;
use Illuminate\Foundation\Testing\RefreshDatabase;
use InvalidArgumentException;
use Tests\TestCase;

class GuruNiyGeneratorTest extends TestCase
{
    use RefreshDatabase;

    public function test_generates_niy_with_expected_format(): void
    {
        config(['master.niy.npyp' => '0488']);

        $lembaga = Lembaga::factory()->create(['niy_kode' => '01']);
        $generator = new GuruNiyGenerator;

        $niy = $generator->generate($lembaga, 'L', 1989);

        $this->assertSame('048801018901', $niy);
    }

    public function test_increments_urut_for_same_tahun_masuk(): void
    {
        $lembaga = Lembaga::factory()->create(['niy_kode' => '01']);
        $generator = new GuruNiyGenerator;

        Guru::factory()->for($lembaga)->create([
            'jenis_kelamin' => 'L',
            'tahun_masuk' => 1989,
            'niy' => '048801018901',
        ]);

        $niy = $generator->generate($lembaga, 'P', 1989);

        $this->assertSame('048801028902', $niy);
    }

    public function test_counts_soft_deleted_guru_for_urut(): void
    {
        $lembaga = Lembaga::factory()->create(['niy_kode' => '01']);
        $generator = new GuruNiyGenerator;

        $deleted = Guru::factory()->for($lembaga)->create([
            'jenis_kelamin' => 'L',
            'tahun_masuk' => 2024,
            'niy' => '048801012401',
        ]);
        $deleted->delete();

        $niy = $generator->generate($lembaga, 'L', 2024);

        $this->assertSame('048801012402', $niy);
    }

    public function test_urut_is_shared_with_karyawan(): void
    {
        $lembaga = Lembaga::factory()->create(['niy_kode' => '01']);
        $generator = new GuruNiyGenerator;

        \App\Models\Karyawan::factory()->for($lembaga)->create([
            'jenis_kelamin' => 'L',
            'tahun_masuk' => 1989,
            'nik_pegawai' => '048801018901',
        ]);

        $niy = $generator->generate($lembaga, 'P', 1989);

        $this->assertSame('048801028902', $niy);
    }

    public function test_fails_when_lembaga_has_no_niy_kode(): void
    {
        $lembaga = Lembaga::factory()->create(['niy_kode' => null]);
        $generator = new GuruNiyGenerator;

        $this->expectException(InvalidArgumentException::class);

        $generator->generate($lembaga, 'L', 2024);
    }
}
