<?php

namespace Tests\Unit;

use App\Services\Api\ApiFieldProfiler;
use App\Support\Api\ApiErrorResponse;
use App\Support\Api\ApiFieldProfiles;
use PHPUnit\Framework\TestCase;

class ApiFieldProfilerTest extends TestCase
{
    public function test_empty_requested_returns_client_profile(): void
    {
        $result = (new ApiFieldProfiler)->resolve(ApiFieldProfiles::ACADEMIC, null);

        $this->assertSame(['ok' => true, 'profile' => ApiFieldProfiles::ACADEMIC], $result);
    }

    public function test_blank_string_requested_returns_client_profile(): void
    {
        $result = (new ApiFieldProfiler)->resolve(ApiFieldProfiles::CONTACT, '');

        $this->assertSame(['ok' => true, 'profile' => ApiFieldProfiles::CONTACT], $result);
    }

    public function test_requested_equal_to_client_profile_is_allowed(): void
    {
        $result = (new ApiFieldProfiler)->resolve(ApiFieldProfiles::MINIMAL, ApiFieldProfiles::MINIMAL);

        $this->assertSame(['ok' => true, 'profile' => ApiFieldProfiles::MINIMAL], $result);
    }

    public function test_requested_narrower_than_client_profile_is_allowed(): void
    {
        $result = (new ApiFieldProfiler)->resolve(ApiFieldProfiles::CONTACT, ApiFieldProfiles::MINIMAL);

        $this->assertSame(['ok' => true, 'profile' => ApiFieldProfiles::MINIMAL], $result);
    }

    public function test_requested_wider_than_client_profile_is_denied(): void
    {
        $result = (new ApiFieldProfiler)->resolve(ApiFieldProfiles::MINIMAL, ApiFieldProfiles::CONTACT);

        $this->assertFalse($result['ok']);
        $this->assertSame(ApiErrorResponse::FORBIDDEN, $result['code']);
        $this->assertSame(403, $result['status']);
        $this->assertSame('Profil field tidak diizinkan.', $result['message']);
    }

    public function test_requested_academic_when_client_minimal_is_denied(): void
    {
        $result = (new ApiFieldProfiler)->resolve(ApiFieldProfiles::MINIMAL, ApiFieldProfiles::ACADEMIC);

        $this->assertFalse($result['ok']);
        $this->assertSame(ApiErrorResponse::FORBIDDEN, $result['code']);
    }

    public function test_invalid_requested_profile_is_denied(): void
    {
        $result = (new ApiFieldProfiler)->resolve(ApiFieldProfiles::CONTACT, 'bogus');

        $this->assertFalse($result['ok']);
        $this->assertSame(ApiErrorResponse::FORBIDDEN, $result['code']);
        $this->assertSame(403, $result['status']);
    }
}
