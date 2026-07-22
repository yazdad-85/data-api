<?php

use App\Http\Controllers\Admin\ApiClientController;
use App\Http\Controllers\Admin\DashboardController;
use App\Http\Controllers\Admin\GuruController;
use App\Http\Controllers\Admin\KaryawanController;
use App\Http\Controllers\Admin\KelasController;
use App\Http\Controllers\Admin\KenaikanKelasController;
use App\Http\Controllers\Admin\SiswaController;
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

    Route::get('/api-clients', [ApiClientController::class, 'index'])->name('admin.api-clients.index');

    Route::get('/tahun-ajaran', [TahunAjaranController::class, 'index'])->name('admin.tahun-ajaran.index');
    Route::get('/tahun-ajaran/create', [TahunAjaranController::class, 'create'])->name('admin.tahun-ajaran.create');
    Route::post('/tahun-ajaran', [TahunAjaranController::class, 'store'])->name('admin.tahun-ajaran.store');
    Route::get('/tahun-ajaran/{tahun_ajaran}/edit', [TahunAjaranController::class, 'edit'])->name('admin.tahun-ajaran.edit');
    Route::put('/tahun-ajaran/{tahun_ajaran}', [TahunAjaranController::class, 'update'])->name('admin.tahun-ajaran.update');
    Route::post('/tahun-ajaran/{tahun_ajaran}/activate', [TahunAjaranController::class, 'activate'])->name('admin.tahun-ajaran.activate');
    Route::delete('/tahun-ajaran/{tahun_ajaran}', [TahunAjaranController::class, 'destroy'])->name('admin.tahun-ajaran.destroy');

    Route::get('/guru', [GuruController::class, 'index'])->name('admin.guru.index');
    Route::get('/guru/template', [GuruController::class, 'downloadTemplate'])->name('admin.guru.template');
    Route::post('/guru/import', [GuruController::class, 'import'])->name('admin.guru.import');
    Route::get('/guru/create', [GuruController::class, 'create'])->name('admin.guru.create');
    Route::post('/guru', [GuruController::class, 'store'])->name('admin.guru.store');
    Route::get('/guru/{guru}', [GuruController::class, 'show'])->name('admin.guru.show');
    Route::get('/guru/{guru}/edit', [GuruController::class, 'edit'])->name('admin.guru.edit');
    Route::put('/guru/{guru}', [GuruController::class, 'update'])->name('admin.guru.update');
    Route::post('/guru/{guru}/activate', [GuruController::class, 'activate'])->name('admin.guru.activate');
    Route::post('/guru/{guru}/deactivate', [GuruController::class, 'deactivate'])->name('admin.guru.deactivate');
    Route::delete('/guru/{guru}', [GuruController::class, 'destroy'])->name('admin.guru.destroy');

    Route::get('/karyawan', [KaryawanController::class, 'index'])->name('admin.karyawan.index');
    Route::get('/karyawan/template', [KaryawanController::class, 'downloadTemplate'])->name('admin.karyawan.template');
    Route::post('/karyawan/import', [KaryawanController::class, 'import'])->name('admin.karyawan.import');
    Route::get('/karyawan/create', [KaryawanController::class, 'create'])->name('admin.karyawan.create');
    Route::post('/karyawan', [KaryawanController::class, 'store'])->name('admin.karyawan.store');
    Route::get('/karyawan/{karyawan}', [KaryawanController::class, 'show'])->name('admin.karyawan.show');
    Route::get('/karyawan/{karyawan}/edit', [KaryawanController::class, 'edit'])->name('admin.karyawan.edit');
    Route::put('/karyawan/{karyawan}', [KaryawanController::class, 'update'])->name('admin.karyawan.update');
    Route::post('/karyawan/{karyawan}/activate', [KaryawanController::class, 'activate'])->name('admin.karyawan.activate');
    Route::post('/karyawan/{karyawan}/deactivate', [KaryawanController::class, 'deactivate'])->name('admin.karyawan.deactivate');
    Route::delete('/karyawan/{karyawan}', [KaryawanController::class, 'destroy'])->name('admin.karyawan.destroy');

    Route::get('/kelas', [KelasController::class, 'index'])->name('admin.kelas.index');
    Route::get('/kelas/template', [KelasController::class, 'downloadTemplate'])->name('admin.kelas.template');
    Route::post('/kelas/import', [KelasController::class, 'import'])->name('admin.kelas.import');
    Route::get('/kelas/create', [KelasController::class, 'create'])->name('admin.kelas.create');
    Route::post('/kelas', [KelasController::class, 'store'])->name('admin.kelas.store');
    Route::get('/kelas/{kelas}', [KelasController::class, 'show'])->name('admin.kelas.show');
    Route::get('/kelas/{kelas}/kenaikan', [KenaikanKelasController::class, 'create'])->name('admin.kelas.kenaikan.create');
    Route::post('/kelas/{kelas}/kenaikan', [KenaikanKelasController::class, 'store'])->name('admin.kelas.kenaikan.store');
    Route::get('/kelas/{kelas}/siswa/template', [KelasController::class, 'siswaTemplate'])->name('admin.kelas.siswa.template');
    Route::post('/kelas/{kelas}/siswa/import', [KelasController::class, 'siswaImport'])->name('admin.kelas.siswa.import');
    Route::get('/kelas/{kelas}/edit', [KelasController::class, 'edit'])->name('admin.kelas.edit');
    Route::put('/kelas/{kelas}', [KelasController::class, 'update'])->name('admin.kelas.update');
    Route::delete('/kelas/{kelas}', [KelasController::class, 'destroy'])->name('admin.kelas.destroy');

    Route::get('/siswa', [SiswaController::class, 'index'])->name('admin.siswa.index');
    Route::get('/siswa/create', [SiswaController::class, 'create'])->name('admin.siswa.create');
    Route::post('/siswa', [SiswaController::class, 'store'])->name('admin.siswa.store');
    Route::get('/siswa/{siswa}', [SiswaController::class, 'show'])->name('admin.siswa.show');
    Route::get('/siswa/{siswa}/edit', [SiswaController::class, 'edit'])->name('admin.siswa.edit');
    Route::put('/siswa/{siswa}', [SiswaController::class, 'update'])->name('admin.siswa.update');
    Route::post('/siswa/{siswa}/activate', [SiswaController::class, 'activate'])->name('admin.siswa.activate');
    Route::post('/siswa/{siswa}/deactivate', [SiswaController::class, 'deactivate'])->name('admin.siswa.deactivate');
    Route::post('/siswa/{siswa}/lifecycle/tempatkan', [SiswaController::class, 'tempatkan'])->name('admin.siswa.lifecycle.tempatkan');
    Route::post('/siswa/{siswa}/lifecycle/pindah-kelas', [SiswaController::class, 'pindahKelas'])->name('admin.siswa.lifecycle.pindah');
    Route::post('/siswa/{siswa}/lifecycle/mutasi-keluar', [SiswaController::class, 'mutasiKeluar'])->name('admin.siswa.lifecycle.mutasi_keluar');
    Route::post('/siswa/{siswa}/lifecycle/luluskan', [SiswaController::class, 'luluskan'])->name('admin.siswa.lifecycle.lulus');
    Route::post('/siswa/{siswa}/lifecycle/set-status', [SiswaController::class, 'setStatus'])->name('admin.siswa.lifecycle.set_status');
    Route::delete('/siswa/{siswa}', [SiswaController::class, 'destroy'])->name('admin.siswa.destroy');

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
