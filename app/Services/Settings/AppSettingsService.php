<?php

namespace App\Services\Settings;

use App\Models\AppSettings;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;

class AppSettingsService
{
    public function current(): AppSettings
    {
        return Cache::remember(
            'app_settings.current',
            60,
            fn (): AppSettings => AppSettings::query()->findOrFail(1),
        );
    }

    public function updateBranding(?string $appName = null, ?string $logoPath = null, ?string $faviconPath = null, bool $clearLogo = false): AppSettings
    {
        $settings = $this->current();

        if ($appName !== null) {
            $settings->app_name = $appName;
        }

        if ($clearLogo) {
            $settings->logo_path = null;
            $settings->favicon_path = null;
        } else {
            if ($logoPath !== null) {
                $settings->logo_path = $logoPath;
            }

            if ($faviconPath !== null) {
                $settings->favicon_path = $faviconPath;
            }
        }

        $settings->save();
        $this->forget();

        return $this->current();
    }

    public function forget(): void
    {
        Cache::forget('app_settings.current');
    }

    /**
     * @return array{name: string, logo_url: string|null, favicon_url: string|null}
     */
    public function branding(): array
    {
        try {
            if (! Schema::hasTable('app_settings')) {
                return $this->defaultBranding();
            }

            $settings = $this->current();
        } catch (QueryException|ModelNotFoundException) {
            return $this->defaultBranding();
        }

        return [
            'name' => $settings->app_name,
            'logo_url' => $settings->logo_path
                ? Storage::disk('public')->url($settings->logo_path)
                : null,
            'favicon_url' => $settings->favicon_path
                ? Storage::disk('public')->url($settings->favicon_path)
                : ($settings->logo_path ? Storage::disk('public')->url($settings->logo_path) : null),
        ];
    }

    /**
     * @return array{name: string, logo_url: string|null, favicon_url: string|null}
     */
    private function defaultBranding(): array
    {
        return [
            'name' => 'Pusat Data',
            'logo_url' => null,
            'favicon_url' => null,
        ];
    }
}
