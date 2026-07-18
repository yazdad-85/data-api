<?php

namespace App\Services;

use App\Models\AuditLog;
use App\Models\User;
use App\Support\Security\MetadataRedactor;
use App\Support\Security\RequestId;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;

class AuditLogger
{
    public function __construct(
        private readonly MetadataRedactor $redactor,
    ) {
    }

    /**
     * @param array<string, mixed> $metadata
     */
    public function record(
        string $event,
        string $result,
        array $metadata = [],
        ?Model $subject = null,
        ?User $user = null,
        ?string $lembagaId = null,
        ?string $apiKeyPrefix = null,
        ?Request $request = null,
    ): AuditLog {
        $request ??= app()->bound('request') ? request() : null;
        $user ??= $request?->user();

        return AuditLog::query()->create([
            'user_id' => $user?->id,
            'lembaga_id' => $lembagaId,
            'event' => $event,
            'result' => $result,
            'subject_type' => $subject?->getMorphClass(),
            'subject_id' => $subject?->getKey(),
            'api_key_prefix' => $apiKeyPrefix,
            'request_id' => RequestId::current($request),
            'metadata' => $this->redactor->redact($metadata),
            'ip_address' => $request?->ip(),
            'user_agent' => $request !== null ? mb_substr((string) $request->userAgent(), 0, 255) : null,
        ]);
    }
}
