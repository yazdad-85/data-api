<?php

use App\Http\Controllers\Admin\ComingSoonController;
use App\Http\Controllers\Admin\DashboardController;
use App\Http\Controllers\Admin\LembagaController;
use App\Http\Controllers\Auth\LoginController;
use App\Http\Controllers\Auth\LogoutController;
use App\Http\Controllers\Auth\MfaChallengeController;
use Illuminate\Support\Facades\Route;

Route::redirect('/', '/login');

Route::middleware('guest')->group(function () {
    Route::get('/login', [LoginController::class, 'create'])->name('login');
    Route::post('/login', [LoginController::class, 'store'])
        ->middleware('throttle:admin-login');
    Route::get('/login/mfa', [MfaChallengeController::class, 'create'])->name('login.mfa');
    Route::post('/login/mfa', [MfaChallengeController::class, 'store'])
        ->middleware('throttle:admin-login');
});

Route::post('/logout', LogoutController::class)
    ->middleware('auth')
    ->name('logout');

Route::middleware(['auth', 'active', 'mfa'])->prefix('admin')->group(function () {
    Route::get('/', [DashboardController::class, 'show'])->name('admin.dashboard');
    Route::get('/coming-soon/{feature}', [ComingSoonController::class, 'show'])
        ->where('feature', 'api-client|tahun-ajaran|guru|kelas|siswa|karyawan|api-client-ro')
        ->name('admin.coming-soon');

    Route::get('/lembaga', [LembagaController::class, 'index'])->name('admin.lembaga.index');
    Route::get('/lembaga/create', [LembagaController::class, 'create'])->name('admin.lembaga.create');
    Route::post('/lembaga', [LembagaController::class, 'store'])->name('admin.lembaga.store');
    Route::get('/lembaga/{lembaga}', [LembagaController::class, 'show'])->name('admin.lembaga.show');
    Route::get('/lembaga/{lembaga}/edit', [LembagaController::class, 'edit'])->name('admin.lembaga.edit');
    Route::put('/lembaga/{lembaga}', [LembagaController::class, 'update'])->name('admin.lembaga.update');
    Route::post('/lembaga/{lembaga}/activate', [LembagaController::class, 'activate'])->name('admin.lembaga.activate');
    Route::post('/lembaga/{lembaga}/deactivate', [LembagaController::class, 'deactivate'])->name('admin.lembaga.deactivate');
});
