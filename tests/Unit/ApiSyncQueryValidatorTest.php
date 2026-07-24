<?php

namespace Tests\Unit;

use App\Services\Api\ApiSyncQueryValidator;
use App\Support\Api\ApiErrorResponse;
use App\Support\Api\ApiSyncCursor;
use Carbon\Carbon;
use Tests\TestCase;

class ApiSyncQueryValidatorTest extends TestCase
{
    private Carbon $now;

    private ApiSyncQueryValidator $validator;

    protected function setUp(): void
    {
        parent::setUp();

        $this->now = Carbon::parse('2026-07-24T10:00:00Z');
        $this->validator = new ApiSyncQueryValidator;
    }

    public function test_missing_since_is_invalid(): void
    {
        $result = $this->validator->validate([], $this->now);

        $this->assertFalse($result['ok']);
        $this->assertSame(ApiErrorResponse::INVALID_SINCE, $result['code']);
        $this->assertSame(400, $result['status']);
    }

    public function test_future_since_is_invalid(): void
    {
        $result = $this->validator->validate([
            'since' => '2026-07-25T00:00:00Z',
        ], $this->now);

        $this->assertFalse($result['ok']);
        $this->assertSame(ApiErrorResponse::INVALID_SINCE, $result['code']);
        $this->assertSame(400, $result['status']);
    }

    public function test_since_older_than_90_days_is_rejected(): void
    {
        $result = $this->validator->validate([
            'since' => '2026-04-24T09:59:59Z',
        ], $this->now);

        $this->assertFalse($result['ok']);
        $this->assertSame(ApiErrorResponse::SINCE_TOO_OLD, $result['code']);
        $this->assertSame(400, $result['status']);
        $this->assertSame('Parameter since terlalu lama; gunakan tarik penuh.', $result['message']);
    }

    public function test_first_page_ok(): void
    {
        $since = '2026-07-01T00:00:00Z';
        $result = $this->validator->validate(['since' => $since], $this->now);

        $this->assertTrue($result['ok']);
        $this->assertTrue($result['since']->eq(Carbon::parse($since)));
        $this->assertNull($result['watermark']);
        $this->assertNull($result['cursor']);
        $this->assertSame(100, $result['per_page']);
        $this->assertTrue($result['is_first_page']);
    }

    public function test_cursor_without_watermark_is_invalid(): void
    {
        $cursor = ApiSyncCursor::encode(
            Carbon::parse('2026-07-15T12:00:00Z'),
            '11111111-1111-1111-1111-111111111111'
        );

        $result = $this->validator->validate([
            'since' => '2026-07-01T00:00:00Z',
            'cursor' => $cursor,
        ], $this->now);

        $this->assertFalse($result['ok']);
        $this->assertSame(ApiErrorResponse::INVALID_CURSOR, $result['code']);
        $this->assertSame(400, $result['status']);
    }

    public function test_bad_cursor_is_invalid(): void
    {
        $result = $this->validator->validate([
            'since' => '2026-07-01T00:00:00Z',
            'cursor' => 'not-a-cursor!!!',
            'watermark' => '2026-07-24T10:00:00Z',
        ], $this->now);

        $this->assertFalse($result['ok']);
        $this->assertSame(ApiErrorResponse::INVALID_CURSOR, $result['code']);
        $this->assertSame(400, $result['status']);
    }

    public function test_cursor_outside_window_is_invalid(): void
    {
        $since = '2026-07-01T00:00:00Z';
        $watermark = '2026-07-24T10:00:00Z';
        $cursor = ApiSyncCursor::encode(
            Carbon::parse('2026-06-15T12:00:00Z'),
            '11111111-1111-1111-1111-111111111111'
        );

        $result = $this->validator->validate([
            'since' => $since,
            'cursor' => $cursor,
            'watermark' => $watermark,
        ], $this->now);

        $this->assertFalse($result['ok']);
        $this->assertSame(ApiErrorResponse::INVALID_CURSOR, $result['code']);
        $this->assertSame(400, $result['status']);
    }

    public function test_per_page_500_is_clamped_to_200(): void
    {
        $result = $this->validator->validate([
            'since' => '2026-07-01T00:00:00Z',
            'per_page' => 500,
        ], $this->now);

        $this->assertTrue($result['ok']);
        $this->assertSame(200, $result['per_page']);
    }
}
