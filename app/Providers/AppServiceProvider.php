<?php

namespace App\Providers;

use App\Console\Commands\InstallSuperAdmin;
use App\Models\Guru;
use App\Models\Karyawan;
use App\Models\Kelas;
use App\Models\Siswa;
use App\Models\TahunAjaran;
use App\Models\User;
use App\Policies\GuruPolicy;
use App\Policies\KaryawanPolicy;
use App\Policies\KelasPolicy;
use App\Policies\SiswaPolicy;
use App\Policies\TahunAjaranPolicy;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Str;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        if ($this->app->runningInConsole()) {
            $this->commands([
                InstallSuperAdmin::class,
            ]);
        }

        RateLimiter::for('admin-login', function (Request $request) {
            $email = Str::lower((string) $request->input('email', ''));

            return [
                Limit::perMinute(5)->by($email.'|'.$request->ip()),
                Limit::perMinute(20)->by('ip:'.$request->ip()),
            ];
        });

        Gate::policy(Guru::class, GuruPolicy::class);
        Gate::policy(Siswa::class, SiswaPolicy::class);
        Gate::policy(Kelas::class, KelasPolicy::class);
        Gate::policy(TahunAjaran::class, TahunAjaranPolicy::class);
        Gate::policy(Karyawan::class, KaryawanPolicy::class);

        Gate::define('access-admin', fn (User $user) => $user->isSuperAdmin() || $user->isAdminLembaga());
        Gate::define('manage-all-lembaga', fn (User $user) => $user->isSuperAdmin());
        Gate::define('manage-own-lembaga', fn (User $user) => $user->isAdminLembaga());
    }
}
