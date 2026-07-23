<?php

namespace App\Services\Api;

use App\Models\ApiClient;
use App\Support\Api\ApiErrorResponse;
use Illuminate\Http\Request;
use Throwable;

/**
 * Orchestrates API client authentication (SPEC §4.1–4.2):
 * extract → parse → lookup by prefix → verify digest → status checks →
 * best-effort last_used_* update.
 *
 * Never logs the plain API key value.
 */
final class ApiClientAuthenticator
{
    public function __construct(
        private readonly ApiKeyParser $parser,
        private readonly ApiKeyVerifier $verifier,
    ) {}

    /**
     * @return array{ok: true, client: ApiClient}|array{ok: false, code: string, status: int, message: string}
     */
    public function authenticate(Request $request): array
    {
        $plain = $this->parser->extractFromRequest($request);
        if ($plain === null) {
            return $this->unauthenticated();
        }

        $parts = $this->parser->parse($plain);
        if ($parts === null) {
            return $this->unauthenticated();
        }

        $client = ApiClient::query()
            ->where('api_key_prefix', $parts['prefix'])
            ->first();

        if ($client === null) {
            return $this->unauthenticated();
        }

        if (! $this->verifier->matches($plain, (string) $client->api_key_digest)) {
            return $this->unauthenticated();
        }

        if (! $client->is_active || $client->revoked_at !== null) {
            return [
                'ok' => false,
                'code' => ApiErrorResponse::API_CLIENT_INACTIVE,
                'status' => 403,
                'message' => 'API client tidak aktif.',
            ];
        }

        $client->loadMissing('lembaga');
        if ($client->lembaga === null || ! $client->lembaga->is_active) {
            return [
                'ok' => false,
                'code' => ApiErrorResponse::LEMBAGA_INACTIVE,
                'status' => 403,
                'message' => 'Lembaga tidak aktif.',
            ];
        }

        $this->touchLastUsed($client, $request);

        return [
            'ok' => true,
            'client' => $client,
        ];
    }

    /**
     * @return array{ok: false, code: string, status: int, message: string}
     */
    private function unauthenticated(): array
    {
        return [
            'ok' => false,
            'code' => ApiErrorResponse::UNAUTHENTICATED,
            'status' => 401,
            'message' => 'Autentikasi gagal.',
        ];
    }

    /**
     * Best-effort tracking; a failure here must not change the auth outcome.
     */
    private function touchLastUsed(ApiClient $client, Request $request): void
    {
        try {
            $client->forceFill([
                'last_used_at' => now(),
                'last_used_ip' => $request->ip(),
            ])->save();
        } catch (Throwable) {
            // Ignore: last_used tracking is non-critical.
        }
    }
}
