<?php

namespace Tests\Unit;

use App\Logging\RedactContextProcessor;
use App\Support\Security\MetadataRedactor;
use DateTimeImmutable;
use Monolog\Level;
use Monolog\LogRecord;
use Tests\TestCase;

class RedactContextProcessorTest extends TestCase
{
    public function test_it_redacts_secret_context_and_preserves_the_message(): void
    {
        $record = new LogRecord(
            datetime: new DateTimeImmutable(),
            channel: 'test',
            level: Level::Info,
            message: 'login attempt',
            context: [
                'password' => 'secret',
                'authorization' => 'Bearer abc',
                'safe' => 'ok',
            ],
        );

        $redacted = (new RedactContextProcessor(new MetadataRedactor()))($record);

        $this->assertSame('login attempt', $redacted->message);
        $this->assertSame('[REDACTED]', $redacted->context['password']);
        $this->assertSame('[REDACTED]', $redacted->context['authorization']);
        $this->assertSame('ok', $redacted->context['safe']);
    }

    public function test_it_redacts_nested_context_and_extra_arrays(): void
    {
        $record = new LogRecord(
            datetime: new DateTimeImmutable(),
            channel: 'test',
            level: Level::Info,
            message: 'nested data',
            context: [
                'request' => [
                    'token' => 'abc123',
                ],
            ],
            extra: [
                'actor' => [
                    'email' => 'user@example.test',
                ],
            ],
        );

        $redacted = (new RedactContextProcessor(new MetadataRedactor()))($record);

        $this->assertSame('[REDACTED]', $redacted->context['request']['token']);
        $this->assertSame('[REDACTED]', $redacted->extra['actor']['email']);
    }
}
