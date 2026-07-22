<?php

namespace Tests\Unit;

use App\Models\Lembaga;
use App\Support\Master\LembagaKodeGenerator;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class LembagaKodeGeneratorTest extends TestCase
{
    use RefreshDatabase;

    public function test_generates_sequential_lbg_kode(): void
    {
        Lembaga::factory()->create(['kode' => 'LBG-001']);

        $generator = new LembagaKodeGenerator;

        $this->assertSame('LBG-002', $generator->generate());
    }

    public function test_skips_non_lbg_kode_when_finding_sequence(): void
    {
        Lembaga::factory()->create(['kode' => 'CUSTOM-999']);

        $generator = new LembagaKodeGenerator;

        $this->assertSame('LBG-001', $generator->generate());
    }
}
