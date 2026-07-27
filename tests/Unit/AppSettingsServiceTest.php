<?php

namespace Tests\Unit;

use App\Models\AppSettings;
use App\Services\Settings\AppSettingsService;
use Illuminate\Foundation\Testing\RefreshDatabase;
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
}
