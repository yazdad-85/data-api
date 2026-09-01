<?php

namespace App\Services\Api;

use App\Models\ApiClient;
use App\Models\Lembaga;
use App\Services\AuditLogger;
use Illuminate\Http\Request;

final class ApiClientCreator
{
    public function __construct(
        private readonly ApiKeyIssuer $issuer,
        private readonly AuditLogger $auditLogger,
    ) {}

    /**
     * @param  array{nama: string, scopes: list<string>, field_profile: string}  $validated
     * @return array{client: ApiClient, plain_key: string}
     */
    public function create(Lembaga $lembaga, array $validated, Request $request): array
    {
        $issued = $this->issuer->issue();

        $client = ApiClient::query()->create([
            'lembaga_id' => $lembaga->id,
            'nama' => $validated['nama'],
            'scopes' => $validated['scopes'],
            'field_profile' => $validated['field_profile'],
            'api_key_prefix' => $issued['prefix'],
            'api_key_digest' => $issued['digest'],
            'is_active' => true,
            'revoked_at' => null,
        ]);

        $this->auditLogger->record('api_client.create', 'success', [
            'nama' => $client->nama,
            'prefix' => $client->api_key_prefix,
            'scopes' => $client->scopes,
        ], subject: $client, lembagaId: $lembaga->id, apiKeyPrefix: $client->api_key_prefix, request: $request);

        return [
            'client' => $client,
            'plain_key' => $issued['plain'],
        ];
    }
}
