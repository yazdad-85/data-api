<?php

use App\Http\Controllers\Api\V1\HealthController;
use App\Http\Controllers\Api\V1\MeController;
use App\Http\Controllers\Api\V1\ResourceListController;
use Illuminate\Support\Facades\Route;

Route::prefix('v1')->group(function () {
    Route::get('/health', HealthController::class)->name('api.v1.health');

    Route::middleware(['api.client', 'api.throttle'])->group(function () {
        Route::get('/me', MeController::class)->name('api.v1.me');

        Route::get('/{resource}', ResourceListController::class)
            ->where('resource', 'tahun-ajaran|guru|kelas|siswa|karyawan')
            ->name('api.v1.resource.index');
    });
});
