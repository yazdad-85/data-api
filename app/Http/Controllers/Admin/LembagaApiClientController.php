<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\StoreApiClientRequest;
use App\Http\Requests\Admin\UpdateApiClientRequest;
use App\Models\ApiClient;
use App\Models\Lembaga;
use App\Services\Api\ApiClientCreator;
use App\Services\Api\ApiKeyIssuer;
use App\Services\AuditLogger;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

class LembagaApiClientController extends Controller
{
    public function __construct(
        private readonly AuditLogger $auditLogger,
        private readonly ApiKeyIssuer $issuer,
        private readonly ApiClientCreator $creator,
    ) {}

    public function store(StoreApiClientRequest $request, Lembaga $lembaga): RedirectResponse
    {
        $this->authorizeCreateForLembaga($request, $lembaga);
        $created = $this->creator->create($lembaga, $request->validated(), $request);
        $client = $created['client'];

        return redirect()
            ->route('admin.lembaga.api-clients.key-once', [$lembaga, $client])
            ->with('generated_api_key', [
                'api_client_id' => (string) $client->id,
                'plain_key' => $created['plain_key'],
            ])
            ->with('status', 'API client berhasil dibuat.');
    }

    public function update(UpdateApiClientRequest $request, Lembaga $lembaga, ApiClient $apiClient): RedirectResponse
    {
        $this->assertClientBelongsToLembaga($lembaga, $apiClient);

        abort_if($apiClient->revoked_at !== null, 403, 'API client yang sudah dicabut tidak dapat diubah.');

        $apiClient->update([
            'nama' => $request->validated('nama'),
            'scopes' => $request->validated('scopes'),
            'field_profile' => $request->validated('field_profile'),
        ]);

        $this->auditLogger->record('api_client.update', 'success', [
            'nama' => $apiClient->nama,
            'scopes' => $apiClient->scopes,
            'field_profile' => $apiClient->field_profile,
        ], subject: $apiClient, lembagaId: $lembaga->id, apiKeyPrefix: $apiClient->api_key_prefix, request: $request);

        return redirect()
            ->route('admin.lembaga.show', $lembaga)
            ->with('status', 'API client berhasil diperbarui.');
    }

    public function rotate(Request $request, Lembaga $lembaga, ApiClient $apiClient): RedirectResponse
    {
        $this->assertClientBelongsToLembaga($lembaga, $apiClient);
        $this->authorize('rotate', $apiClient);

        $oldPrefix = $apiClient->api_key_prefix;
        $issued = $this->issuer->issue();

        $apiClient->update([
            'api_key_prefix' => $issued['prefix'],
            'api_key_digest' => $issued['digest'],
            'last_used_at' => null,
            'last_used_ip' => null,
        ]);

        $this->auditLogger->record('api_key.rotate', 'success', [
            'old_prefix' => $oldPrefix,
            'new_prefix' => $issued['prefix'],
        ], subject: $apiClient, lembagaId: $lembaga->id, apiKeyPrefix: $issued['prefix'], request: $request);

        return redirect()
            ->route('admin.lembaga.api-clients.key-once', [$lembaga, $apiClient])
            ->with('generated_api_key', [
                'api_client_id' => (string) $apiClient->id,
                'plain_key' => $issued['plain'],
            ]);
    }

    public function revoke(Request $request, Lembaga $lembaga, ApiClient $apiClient): RedirectResponse
    {
        $this->assertClientBelongsToLembaga($lembaga, $apiClient);
        $this->authorize('revoke', $apiClient);

        $apiClient->update([
            'is_active' => false,
            'revoked_at' => now(),
        ]);

        $this->auditLogger->record('api_client.revoke', 'success', [
            'prefix' => $apiClient->api_key_prefix,
        ], subject: $apiClient, lembagaId: $lembaga->id, apiKeyPrefix: $apiClient->api_key_prefix, request: $request);

        return redirect()
            ->route('admin.lembaga.show', $lembaga)
            ->with('status', 'API client dicabut.');
    }

    public function keyOnce(Request $request, Lembaga $lembaga, ApiClient $apiClient): Response|RedirectResponse
    {
        $this->assertClientBelongsToLembaga($lembaga, $apiClient);
        $this->authorize('view', $apiClient);

        $flash = $request->session()->pull('generated_api_key');
        $plain = is_array($flash)
            && (string) ($flash['api_client_id'] ?? '') === (string) $apiClient->id
            && is_string($flash['plain_key'] ?? null)
            && $flash['plain_key'] !== ''
                ? $flash['plain_key']
                : null;

        if ($plain === null) {
            $fallbackRoute = $request->user()?->isSuperAdmin()
                ? route('admin.lembaga.show', $lembaga)
                : route('admin.api-clients.index');

            return redirect()
                ->to($fallbackRoute)
                ->with('status', 'API key satu kali sudah tidak tersedia. Rotate ulang jika perlu.');
        }

        $backUrl = $request->user()?->isSuperAdmin()
            ? route('admin.lembaga.show', $lembaga)
            : route('admin.api-clients.index');

        return response()
            ->view('admin.lembaga.api-clients.key-once', [
                'lembaga' => $lembaga,
                'apiClient' => $apiClient,
                'plainKey' => $plain,
                'backUrl' => $backUrl,
                'backLabel' => $request->user()?->isSuperAdmin() ? 'Kembali ke detail lembaga' : 'Kembali ke API client',
            ])
            ->header('Cache-Control', 'no-store, no-cache, must-revalidate');
    }

    private function assertClientBelongsToLembaga(Lembaga $lembaga, ApiClient $apiClient): void
    {
        abort_unless(
            hash_equals((string) $apiClient->lembaga_id, (string) $lembaga->id),
            404
        );
    }

    private function authorizeCreateForLembaga(Request $request, Lembaga $lembaga): void
    {
        $user = $request->user();

        abort_unless(
            $user?->isSuperAdmin()
                || ($user?->isAdminLembaga() && hash_equals((string) $user->lembaga_id, (string) $lembaga->id)),
            403
        );
    }
}
