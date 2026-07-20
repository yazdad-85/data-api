<?php

namespace App\Providers;

use App\Console\Commands\InstallSuperAdmin;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
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
    }
}
