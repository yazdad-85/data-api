<?php

use App\Http\Controllers\Admin\ApiClientController;
use App\Http\Controllers\Admin\ComingSoonController;
use App\Http\Controllers\Admin\DashboardController;
use App\Http\Controllers\Admin\LembagaAdminController;
use App\Http\Controllers\Admin\LembagaApiClientController;
use App\Http\Controllers\Admin\LembagaController;
use App\Http\Controllers\Admin\TahunAjaranController;
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
        ->where('feature', 'guru|kelas|siswa|karyawan')
        ->name('admin.coming-soon');

    Route::get('/api-clients', [ApiClientController::class, 'index'])->name('admin.api-clients.index');

    Route::get('/tahun-ajaran', [TahunAjaranController::class, 'index'])->name('admin.tahun-ajaran.index');
    Route::get('/tahun-ajaran/create', [TahunAjaranController::class, 'create'])->name('admin.tahun-ajaran.create');
    Route::post('/tahun-ajaran', [TahunAjaranController::class, 'store'])->name('admin.tahun-ajaran.store');
    Route::get('/tahun-ajaran/{tahun_ajaran}/edit', [TahunAjaranController::class, 'edit'])->name('admin.tahun-ajaran.edit');
    Route::put('/tahun-ajaran/{tahun_ajaran}', [TahunAjaranController::class, 'update'])->name('admin.tahun-ajaran.update');
    Route::post('/tahun-ajaran/{tahun_ajaran}/activate', [TahunAjaranController::class, 'activate'])->name('admin.tahun-ajaran.activate');
    Route::delete('/tahun-ajaran/{tahun_ajaran}', [TahunAjaranController::class, 'destroy'])->name('admin.tahun-ajaran.destroy');

    Route::get('/lembaga', [LembagaController::class, 'index'])->name('admin.lembaga.index');
    Route::get('/lembaga/create', [LembagaController::class, 'create'])->name('admin.lembaga.create');
    Route::post('/lembaga', [LembagaController::class, 'store'])->name('admin.lembaga.store');
    Route::get('/lembaga/{lembaga}', [LembagaController::class, 'show'])->name('admin.lembaga.show');
    Route::get('/lembaga/{lembaga}/edit', [LembagaController::class, 'edit'])->name('admin.lembaga.edit');
    Route::put('/lembaga/{lembaga}', [LembagaController::class, 'update'])->name('admin.lembaga.update');
    Route::post('/lembaga/{lembaga}/activate', [LembagaController::class, 'activate'])->name('admin.lembaga.activate');
    Route::post('/lembaga/{lembaga}/deactivate', [LembagaController::class, 'deactivate'])->name('admin.lembaga.deactivate');

    Route::post('/lembaga/{lembaga}/admins', [LembagaAdminController::class, 'store'])->name('admin.lembaga.admins.store');
    Route::put('/lembaga/{lembaga}/admins/{user}', [LembagaAdminController::class, 'update'])->name('admin.lembaga.admins.update');
    Route::post('/lembaga/{lembaga}/admins/{user}/activate', [LembagaAdminController::class, 'activate'])->name('admin.lembaga.admins.activate');
    Route::post('/lembaga/{lembaga}/admins/{user}/deactivate', [LembagaAdminController::class, 'deactivate'])->name('admin.lembaga.admins.deactivate');
    Route::post('/lembaga/{lembaga}/admins/{user}/reset-password', [LembagaAdminController::class, 'resetPassword'])->name('admin.lembaga.admins.reset-password');
    Route::get('/lembaga/{lembaga}/admins/{user}/password-once', [LembagaAdminController::class, 'passwordOnce'])->name('admin.lembaga.admins.password-once');

    Route::post('/lembaga/{lembaga}/api-clients', [LembagaApiClientController::class, 'store'])->name('admin.lembaga.api-clients.store');
    Route::put('/lembaga/{lembaga}/api-clients/{apiClient}', [LembagaApiClientController::class, 'update'])->name('admin.lembaga.api-clients.update');
    Route::post('/lembaga/{lembaga}/api-clients/{apiClient}/rotate', [LembagaApiClientController::class, 'rotate'])->name('admin.lembaga.api-clients.rotate');
    Route::post('/lembaga/{lembaga}/api-clients/{apiClient}/revoke', [LembagaApiClientController::class, 'revoke'])->name('admin.lembaga.api-clients.revoke');
    Route::get('/lembaga/{lembaga}/api-clients/{apiClient}/key-once', [LembagaApiClientController::class, 'keyOnce'])->name('admin.lembaga.api-clients.key-once');
});
