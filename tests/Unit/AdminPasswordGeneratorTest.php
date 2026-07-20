<?php

namespace Tests\Unit;

use App\Services\Auth\AdminPasswordGenerator;
use PHPUnit\Framework\TestCase;

class AdminPasswordGeneratorTest extends TestCase
{
    public function test_generate_produces_passwords_meeting_policy(): void
    {
        $generator = new AdminPasswordGenerator;

        for ($i = 0; $i < 20; $i++) {
            $password = $generator->generate();

            $this->assertGreaterThanOrEqual(12, strlen($password));
            $this->assertMatchesRegularExpression('/[a-zA-Z]/', $password);
            $this->assertMatchesRegularExpression('/[0-9]/', $password);
        }
    }
}
