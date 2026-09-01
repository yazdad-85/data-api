<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\StoreApiClientRequest;
use App\Models\ApiClient;
use App\Models\Lembaga;
use App\Services\Api\ApiClientCreator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class ApiClientController extends Controller
{
    public function __construct(
        private readonly ApiClientCreator $creator,
    ) {}

    public function index(Request $request): View
    {
        $this->authorize('viewAny', ApiClient::class);

        $user = $request->user();
        $selectedLembagaId = '';
        $lembagaOptions = collect();

        if ($user->isSuperAdmin()) {
            $lembagaOptions = Lembaga::query()->orderBy('nama')->get();
            $requestedLembagaId = (string) $request->query('lembaga_id', '');
            $selectedLembagaId = $lembagaOptions->contains('id', $requestedLembagaId) ? $requestedLembagaId : '';
        }

        if ($user->isAdminLembaga() && $user->lembaga !== null) {
            $lembagaOptions = collect([$user->lembaga]);
        }

        $clients = ApiClient::query()
            ->with('lembaga')
            ->when($user->isAdminLembaga(), fn (Builder $query) => $query->where('lembaga_id', $user->lembaga_id))
            ->when($user->isSuperAdmin() && $selectedLembagaId !== '', fn (Builder $query) => $query->where('lembaga_id', $selectedLembagaId))
            ->orderBy(
                Lembaga::query()
                    ->select('nama')
                    ->whereColumn('lembaga.id', 'api_clients.lembaga_id')
                    ->limit(1)
            )
            ->orderBy('nama')
            ->get();

        return view('admin.api-clients.index', compact('clients', 'lembagaOptions', 'selectedLembagaId'));
    }

    public function store(StoreApiClientRequest $request): RedirectResponse
    {
        $user = $request->user();

        if ($user->isSuperAdmin()) {
            $validatedLembaga = $request->validate([
                'lembaga_id' => ['required', 'string', Rule::exists('lembaga', 'id')],
            ]);
            $lembaga = Lembaga::query()->findOrFail($validatedLembaga['lembaga_id']);
        } else {
            $lembaga = $user->lembaga;
        }

        abort_unless($lembaga instanceof Lembaga, 403);

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
}
