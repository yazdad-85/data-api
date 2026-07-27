<?php

namespace Tests\Unit;

use App\Models\AppSettings;
use App\Services\Settings\AppSettingsService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class AppSettingsServiceTest extends TestCase
{
    use RefreshDatabase;

    public function test_current_returns_seeded_default_name(): void
    {
        $settings = app(AppSettingsService::class)->current();

        $this->assertSame('Pusat Data', $settings->app_name);
        $this->assertNull($settings->logo_path);
        $this->assertNull($settings->favicon_path);
        $this->assertTrue(Cache::has('app_settings.current'));
    }

    public function test_update_branding_persists_name(): void
    {
        $service = app(AppSettingsService::class);
        $service->updateBranding(appName: 'Data Yayasan', logoPath: null, faviconPath: null);

        $this->assertDatabaseHas('app_settings', [
            'id' => 1,
            'app_name' => 'Data Yayasan',
        ]);
        $this->assertSame('Data Yayasan', $service->current()->app_name);
    }

    public function test_update_branding_persists_logo_and_favicon_paths(): void
    {
        $service = app(AppSettingsService::class);
        $service->updateBranding(logoPath: 'branding/logo.png', faviconPath: 'branding/favicon.png');

        $this->assertDatabaseHas('app_settings', [
            'id' => 1,
            'logo_path' => 'branding/logo.png',
            'favicon_path' => 'branding/favicon.png',
        ]);
    }

    public function test_clear_logo_nulls_logo_and_favicon_paths(): void
    {
        $service = app(AppSettingsService::class);
        $service->updateBranding(logoPath: 'branding/logo.png', faviconPath: 'branding/favicon.png');

        $service->updateBranding(clearLogo: true);

        $this->assertDatabaseHas('app_settings', [
            'id' => 1,
            'logo_path' => null,
            'favicon_path' => null,
        ]);
    }

    public function test_branding_returns_urls_and_uses_logo_as_favicon_fallback(): void
    {
        Storage::fake('public');
        $service = app(AppSettingsService::class);
        $service->updateBranding(logoPath: 'branding/logo.png');

        $branding = $service->branding();

        $this->assertSame(Storage::disk('public')->url('branding/logo.png'), $branding['logo_url']);
        $this->assertSame(Storage::disk('public')->url('branding/logo.png'), $branding['favicon_url']);
    }

    public function test_creating_a_non_singleton_settings_row_is_rejected(): void
    {
        $this->expectException(\LogicException::class);

        AppSettings::query()->create([
            'id' => 2,
            'app_name' => 'Invalid',
        ]);
    }

    public function test_branding_returns_defaults_when_app_settings_table_missing(): void
    {
        \Illuminate\Support\Facades\Schema::dropIfExists('app_settings');
        Cache::forget('app_settings.current');

        $branding = app(AppSettingsService::class)->branding();

        $this->assertSame('Pusat Data', $branding['name']);
        $this->assertNull($branding['logo_url']);
        $this->assertNull($branding['favicon_url']);
    }

    public function test_current_recovers_from_stale_serialized_model_cache(): void
    {
        Cache::put('app_settings.current', new \stdClass, 60);

        $settings = app(AppSettingsService::class)->current();

        $this->assertInstanceOf(AppSettings::class, $settings);
        $this->assertSame('Pusat Data', $settings->app_name);
        $this->assertIsArray(Cache::get('app_settings.current'));
    }
}
