<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\StoreLembagaRequest;
use App\Http\Requests\Admin\UpdateLembagaRequest;
use App\Models\Lembaga;
use App\Models\User;
use App\Services\AuditLogger;
use App\Services\Auth\SessionInvalidator;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class LembagaController extends Controller
{
    public function __construct(
        private readonly AuditLogger $auditLogger,
        private readonly SessionInvalidator $sessionInvalidator,
    ) {}

    public function index(Request $request): View
    {
        $this->authorize('viewAny', Lembaga::class);

        $q = trim((string) $request->query('q', ''));

        $lembagas = Lembaga::query()
            ->when($q !== '', function ($query) use ($q) {
                $like = '%'.$q.'%';
                $query->where(function ($inner) use ($like) {
                    if ($inner->getConnection()->getDriverName() === 'pgsql') {
                        $inner->where('kode', 'ilike', $like)->orWhere('nama', 'ilike', $like);
                    } else {
                        $inner->whereRaw('lower(kode) like lower(?)', [$like])
                            ->orWhereRaw('lower(nama) like lower(?)', [$like]);
                    }
                });
            })
            ->orderBy('nama')
            ->paginate(15)
            ->withQueryString();

        return view('admin.lembaga.index', compact('lembagas', 'q'));
    }

    public function create(): View
    {
        $this->authorize('create', Lembaga::class);

        return view('admin.lembaga.create');
    }

    public function store(StoreLembagaRequest $request): RedirectResponse
    {
        $lembaga = Lembaga::query()->create($request->validated());

        $this->auditLogger->record('lembaga.create', 'success', [
            'kode' => $lembaga->kode,
        ], subject: $lembaga, lembagaId: $lembaga->id, request: $request);

        return redirect()
            ->route('admin.lembaga.show', $lembaga)
            ->with('status', 'Lembaga berhasil dibuat.');
    }

    public function show(Lembaga $lembaga): View
    {
        $this->authorize('view', $lembaga);

        $admins = $lembaga->users()
            ->where('role', 'admin_lembaga')
            ->orderBy('name')
            ->get();

        $adminsAktif = $admins->where('is_active', true)->count();
        $apiClients = $lembaga->apiClients()->orderBy('nama')->get();
        $apiClientsAktif = $apiClients
            ->where('is_active', true)
            ->whereNull('revoked_at')
            ->count();

        return view('admin.lembaga.show', compact(
            'lembaga',
            'admins',
            'adminsAktif',
            'apiClients',
            'apiClientsAktif',
        ));
    }

    public function edit(Lembaga $lembaga): View
    {
        $this->authorize('update', $lembaga);

        return view('admin.lembaga.edit', compact('lembaga'));
    }

    public function update(UpdateLembagaRequest $request, Lembaga $lembaga): RedirectResponse
    {
        $lembaga->update($request->validated());

        $this->auditLogger->record('lembaga.update', 'success', [
            'kode' => $lembaga->kode,
        ], subject: $lembaga, lembagaId: $lembaga->id, request: $request);

        return redirect()
            ->route('admin.lembaga.show', $lembaga)
            ->with('status', 'Lembaga berhasil diperbarui.');
    }

    public function activate(Request $request, Lembaga $lembaga): RedirectResponse
    {
        $this->authorize('activate', $lembaga);

        $lembaga->update(['is_active' => true]);

        $this->auditLogger->record('lembaga.activate', 'success', [], subject: $lembaga, lembagaId: $lembaga->id, request: $request);

        return redirect()
            ->route('admin.lembaga.show', $lembaga)
            ->with('status', 'Lembaga diaktifkan.');
    }

    public function deactivate(Request $request, Lembaga $lembaga): RedirectResponse
    {
        $this->authorize('deactivate', $lembaga);

        $adminsAktif = User::query()
            ->where('role', 'admin_lembaga')
            ->where('lembaga_id', $lembaga->id)
            ->where('is_active', true)
            ->get(['id']);

        $apiClientsAktif = $lembaga->apiClients()
            ->where('is_active', true)
            ->whereNull('revoked_at')
            ->count();

        $lembaga->update(['is_active' => false]);

        foreach ($adminsAktif as $admin) {
            $this->sessionInvalidator->invalidateUser((string) $admin->id);
        }

        $this->auditLogger->record('lembaga.deactivate', 'success', [
            'admins_aktif' => $adminsAktif->count(),
            'api_clients_aktif' => $apiClientsAktif,
        ], subject: $lembaga, lembagaId: $lembaga->id, request: $request);

        return redirect()
            ->route('admin.lembaga.show', $lembaga)
            ->with('status', 'Lembaga dinonaktifkan.');
    }
}
