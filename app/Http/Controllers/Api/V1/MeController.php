<?php

namespace App\Http\Controllers\Api\V1;

use App\Support\Api\ApiClientContext;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Authenticated profile summary of the calling API client (SPEC §6).
 * Runs behind api.client + api.throttle, so a bound client is guaranteed.
 */
class MeController
{
    public function __invoke(Request $request): JsonResponse
    {
        $client = ApiClientContext::get($request);
        abort_if($client === null, 500);

        $client->loadMissing('lembaga');
        $lembaga = $client->lembaga;

        return response()->json([
            'lembaga_id' => $lembaga->id,
            'kode' => $lembaga->kode,
            'nama' => $lembaga->nama,
            'is_active' => $lembaga->is_active,
            'client_id' => $client->id,
            'client_name' => $client->nama,
            'scopes' => $client->scopes,
            'field_profile' => $client->field_profile,
        ]);
    }
}
