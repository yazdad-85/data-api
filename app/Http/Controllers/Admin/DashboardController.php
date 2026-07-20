<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Services\Dashboard\DashboardStats;
use Illuminate\Http\Request;
use Illuminate\View\View;

class DashboardController extends Controller
{
    public function show(Request $request, DashboardStats $stats): View
    {
        return view('admin.dashboard', [
            'user' => $request->user(),
            'stats' => $stats->for($request->user()),
        ]);
    }
}
