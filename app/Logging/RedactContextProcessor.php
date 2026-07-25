<?php

namespace App\Logging;

use App\Support\Security\MetadataRedactor;
use Monolog\LogRecord;
use Monolog\Processor\ProcessorInterface;

final class RedactContextProcessor implements ProcessorInterface
{
    public function __construct(
        private readonly MetadataRedactor $redactor,
    ) {}

    public function __invoke(LogRecord $record): LogRecord
    {
        $context = $record->context === [] ? [] : $this->redactor->redact($record->context);
        $extra = $record->extra === [] ? [] : $this->redactor->redact($record->extra);

        return $record->with(context: $context, extra: $extra);
    }
}
