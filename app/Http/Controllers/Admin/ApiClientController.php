<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\ApiClient;
use Illuminate\Http\Request;
use Illuminate\View\View;

class ApiClientController extends Controller
{
    public function index(Request $request): View
    {
        $this->authorize('viewAny', ApiClient::class);

        $user = $request->user();
        abort_unless($user->isAdminLembaga(), 403);

        $clients = ApiClient::query()
            ->where('lembaga_id', $user->lembaga_id)
            ->orderBy('nama')
            ->get();

        return view('admin.api-clients.index', compact('clients'));
    }
}
