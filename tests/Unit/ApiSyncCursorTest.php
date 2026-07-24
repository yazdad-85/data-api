<?php

namespace Tests\Unit;

use App\Support\Api\ApiSyncCursor;
use Carbon\Carbon;
use InvalidArgumentException;
use PHPUnit\Framework\TestCase;

class ApiSyncCursorTest extends TestCase
{
    public function test_round_trip(): void
    {
        $at = Carbon::parse('2026-07-15T12:01:00Z');
        $id = '11111111-1111-1111-1111-111111111111';
        $decoded = ApiSyncCursor::decode(ApiSyncCursor::encode($at, $id));
        $this->assertTrue($decoded['changed_at']->eq($at));
        $this->assertSame($id, $decoded['id']);
    }

    public function test_rejects_garbage(): void
    {
        $this->expectException(InvalidArgumentException::class);
        ApiSyncCursor::decode('not-a-cursor!!!');
    }
}
