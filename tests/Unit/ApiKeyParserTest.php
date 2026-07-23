<?php

namespace Tests\Unit;

use App\Services\Api\ApiKeyParser;
use Illuminate\Http\Request;
use PHPUnit\Framework\TestCase;

class ApiKeyParserTest extends TestCase
{
    public function test_extract_prefers_x_api_key_over_bearer(): void
    {
        $request = Request::create('/api/v1/me', 'GET');
        $request->headers->set('X-API-Key', 'dc_live_abc123_secretvalue');
        $request->headers->set('Authorization', 'Bearer dc_live_other_token');

        $extracted = (new ApiKeyParser)->extractFromRequest($request);

        $this->assertSame('dc_live_abc123_secretvalue', $extracted);
    }

    public function test_extract_bearer_when_no_x_api_key(): void
    {
        $request = Request::create('/api/v1/me', 'GET');
        $request->headers->set('Authorization', 'Bearer dc_live_abc123_secretvalue');

        $extracted = (new ApiKeyParser)->extractFromRequest($request);

        $this->assertSame('dc_live_abc123_secretvalue', $extracted);
    }

    public function test_extract_returns_null_when_no_headers_present(): void
    {
        $request = Request::create('/api/v1/me', 'GET');

        $extracted = (new ApiKeyParser)->extractFromRequest($request);

        $this->assertNull($extracted);
    }

    public function test_parse_valid_dc_live_key(): void
    {
        $parsed = (new ApiKeyParser)->parse('dc_live_abc123def456_somesecretvalue123');

        $this->assertSame([
            'prefix' => 'abc123def456',
            'secret' => 'somesecretvalue123',
        ], $parsed);
    }

    public function test_parse_invalid_returns_null(): void
    {
        $this->assertNull((new ApiKeyParser)->parse('not-a-valid-key'));
        $this->assertNull((new ApiKeyParser)->parse('dc_live_onlyprefix'));
        $this->assertNull((new ApiKeyParser)->parse('dc_live__secretonly'));
    }
}
