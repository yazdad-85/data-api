<?php

namespace App\Providers;

use App\Console\Commands\InstallSuperAdmin;
use App\Models\ApiClient;
use App\Models\Guru;
use App\Models\Karyawan;
use App\Models\Kelas;
use App\Models\Lembaga;
use App\Models\Siswa;
use App\Models\TahunAjaran;
use App\Models\User;
use App\Policies\ApiClientPolicy;
use App\Policies\GuruPolicy;
use App\Policies\KaryawanPolicy;
use App\Policies\KelasPolicy;
use App\Policies\LembagaPolicy;
use App\Policies\SiswaPolicy;
use App\Policies\TahunAjaranPolicy;
use App\Policies\UserPolicy;
use App\Support\Api\ApiClientContext;
use App\Support\Navigation\AdminMenu;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\View;
use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Str;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        $this->app->singleton(\App\Services\Settings\AppSettingsService::class);
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

        RateLimiter::for('admin-profile-password', function (Request $request) {
            $userId = (string) ($request->user()?->getAuthIdentifier() ?? 'guest');

            return [
                Limit::perMinute(5)->by('profile-password:'.$userId),
                Limit::perMinute(20)->by('profile-password-ip:'.$request->ip()),
            ];
        });

        RateLimiter::for('admin-settings-backup', function (Request $request) {
            $userId = (string) ($request->user()?->getAuthIdentifier() ?? 'guest');

            return [
                Limit::perMinute(3)->by('settings-backup:'.$userId),
                Limit::perMinute(10)->by('settings-backup-ip:'.$request->ip()),
            ];
        });

        // Read limits from config at call time so tests can lower them per request.
        RateLimiter::for('api-client-key', function (Request $request) {
            $client = ApiClientContext::get($request);
            $key = $client?->api_key_prefix ?? 'unknown';
            $perMinute = (int) config('security.api_rate_per_minute', 120);

            return Limit::perMinute($perMinute)->by('api-key:'.$key);
        });

        RateLimiter::for('api-client-ip', function (Request $request) {
            $perMinute = (int) config('security.api_ip_rate_per_minute', 240);

            return Limit::perMinute($perMinute)->by('api-ip:'.$request->ip());
        });

        Gate::policy(ApiClient::class, ApiClientPolicy::class);
        Gate::policy(Guru::class, GuruPolicy::class);
        Gate::policy(Siswa::class, SiswaPolicy::class);
        Gate::policy(Kelas::class, KelasPolicy::class);
        Gate::policy(TahunAjaran::class, TahunAjaranPolicy::class);
        Gate::policy(Karyawan::class, KaryawanPolicy::class);
        Gate::policy(Lembaga::class, LembagaPolicy::class);
        Gate::policy(User::class, UserPolicy::class);

        Gate::define('access-admin', fn (User $user) => $user->isSuperAdmin() || $user->isAdminLembaga());
        Gate::define('manage-all-lembaga', fn (User $user) => $user->isSuperAdmin());
        Gate::define('manage-own-lembaga', fn (User $user) => $user->isAdminLembaga());
        Gate::define('manage-app-settings', fn (User $user) => $user->isSuperAdmin());

        View::composer('layouts.admin', function ($view) {
            $user = auth()->user();

            if ($user?->isAdminLembaga()) {
                $user->loadMissing('lembaga');
            }

            $view->with('menu', $user ? app(AdminMenu::class)->forUser($user) : collect());
            $view->with('authUser', $user);
        });
    }
}
