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
    private const CACHE_KEY = 'app_settings.current';

    public function current(): AppSettings
    {
        $attributes = Cache::remember(
            self::CACHE_KEY,
            60,
            function (): array {
                return AppSettings::query()->findOrFail(1)->getAttributes();
            },
        );

        // Stale cache from older deploys may contain a serialized Eloquent model
        // (__PHP_Incomplete_Class) instead of a plain attributes array.
        if (! is_array($attributes)) {
            Cache::forget(self::CACHE_KEY);
            $attributes = AppSettings::query()->findOrFail(1)->getAttributes();
            Cache::put(self::CACHE_KEY, $attributes, 60);
        }

        return $this->hydrate($attributes);
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
        Cache::forget(self::CACHE_KEY);
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
     * @param  array<string, mixed>  $attributes
     */
    private function hydrate(array $attributes): AppSettings
    {
        $settings = new AppSettings;
        $settings->forceFill($attributes);
        $settings->exists = true;
        $settings->syncOriginal();

        return $settings;
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
