<?php

use App\Http\Controllers\Admin\DashboardController;
use App\Http\Controllers\Auth\LoginController;
use App\Http\Controllers\Auth\LogoutController;
use App\Http\Controllers\Auth\MfaChallengeController;
use Illuminate\Support\Facades\Route;

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
});
