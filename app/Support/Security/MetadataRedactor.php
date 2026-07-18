<?php

namespace App\Support\Security;

use Illuminate\Support\Str;

class MetadataRedactor
{
    private const REDACTED = '[REDACTED]';
    private const MAX_DEPTH = 6;
    private const MAX_STRING_LENGTH = 500;
    private const MAX_JSON_BYTES = 8192;

    /** @var array<int, string> */
    private const SECRET_KEYS = [
        'api-key',
        'api-key-digest',
        'authorization',
        'current-password',
        'mfa-secret',
        'password',
        'password-confirmation',
        'plain-key',
        'recovery-code',
        'recovery-codes',
        'recovery-codes-hash',
        'secret',
        'token',
        'x-api-key',
    ];

    /** @var array<int, string> */
    private const PII_KEYS = [
        'alamat',
        'email',
        'nama_wali',
        'nip',
        'nis',
        'nisn',
        'nuptk',
        'tanggal_lahir',
        'telepon',
        'telepon_wali',
    ];

    /**
     * @param array<string, mixed> $metadata
     * @return array<string, mixed>
     */
    public function redact(array $metadata): array
    {
        $redacted = $this->redactValue($metadata);

        if (! is_array($redacted)) {
            return ['value' => $redacted];
        }

        return $this->limitSize($redacted);
    }

    private function redactValue(mixed $value, ?string $key = null, int $depth = 0): mixed
    {
        if ($key !== null && $this->mustRedactKey($key)) {
            return self::REDACTED;
        }

        if ($depth >= self::MAX_DEPTH) {
            return '[DEPTH_LIMIT]';
        }

        if (is_array($value)) {
            $clean = [];

            foreach ($value as $childKey => $childValue) {
                $clean[$childKey] = $this->redactValue(
                    $childValue,
                    is_string($childKey) ? $childKey : null,
                    $depth + 1,
                );
            }

            return $clean;
        }

        if (is_string($value)) {
            return Str::limit($value, self::MAX_STRING_LENGTH, '...');
        }

        if (is_bool($value) || is_int($value) || is_float($value) || $value === null) {
            return $value;
        }

        return '[UNSUPPORTED]';
    }

    private function mustRedactKey(string $key): bool
    {
        $normalized = Str::of($key)->lower()->replace([' ', '_'], '-')->toString();

        return in_array($normalized, self::SECRET_KEYS, true)
            || in_array(str_replace('-', '_', $normalized), self::PII_KEYS, true);
    }

    /**
     * @param array<string, mixed> $metadata
     * @return array<string, mixed>
     */
    private function limitSize(array $metadata): array
    {
        $encoded = json_encode($metadata);

        if ($encoded !== false && strlen($encoded) <= self::MAX_JSON_BYTES) {
            return $metadata;
        }

        return [
            '_truncated' => true,
            '_reason' => 'metadata_too_large',
        ];
    }
}
