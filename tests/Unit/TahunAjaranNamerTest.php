<?php

namespace Tests\Unit;

use App\Support\Master\TahunAjaranNamer;
use PHPUnit\Framework\TestCase;

class TahunAjaranNamerTest extends TestCase
{
    public function test_formats_year_slash_next_year(): void
    {
        $this->assertSame('2026/2027', TahunAjaranNamer::fromTahunMulai(2026));
    }
}
