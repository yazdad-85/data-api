<?php

use App\Http\Controllers\Api\V1\HealthController;
use App\Http\Controllers\Api\V1\MeController;
use Illuminate\Support\Facades\Route;

Route::prefix('v1')->group(function () {
    Route::get('/health', HealthController::class)->name('api.v1.health');

    Route::middleware(['api.client', 'api.throttle'])->group(function () {
        Route::get('/me', MeController::class)->name('api.v1.me');
    });
});
