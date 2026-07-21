<?php

namespace Tests\Unit;

use App\Services\Api\ApiKeyIssuer;
use App\Services\Api\ApiKeyVerifier;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ApiKeyIssuerTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        config(['security.api_key_pepper' => 'test-pepper-not-for-production']);
    }

    public function test_issue_returns_dc_live_format_and_verifiable_digest(): void
    {
        $issued = app(ApiKeyIssuer::class)->issue();

        $this->assertMatchesRegularExpression('/^dc_live_[a-z0-9]{12}_[a-z0-9]+$/', $issued['plain']);
        $this->assertSame(12, strlen($issued['prefix']));
        $this->assertSame(64, strlen($issued['digest']));
        $this->assertTrue(app(ApiKeyVerifier::class)->matches($issued['plain'], $issued['digest']));
        $this->assertFalse(app(ApiKeyVerifier::class)->matches($issued['plain'].'x', $issued['digest']));
    }
}
