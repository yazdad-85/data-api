<?php

namespace Tests\Unit;

use App\Services\Settings\BrandingLogoProcessor;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use RuntimeException;
use Tests\TestCase;

class BrandingLogoProcessorTest extends TestCase
{
    public function test_store_writes_logo_and_favicon(): void
    {
        Storage::fake('public');

        if (! extension_loaded('gd')) {
            $this->markTestSkipped('GD extension required');
        }

        $file = UploadedFile::fake()->image('logo.png', 200, 100);

        $result = app(BrandingLogoProcessor::class)->store($file);

        Storage::disk('public')->assertExists($result['logo_path']);
        Storage::disk('public')->assertExists($result['favicon_path']);
        $this->assertMatchesRegularExpression('#^branding/logo-[a-f0-9]{16}\.png$#', $result['logo_path']);
        $this->assertMatchesRegularExpression('#^branding/favicon-[a-f0-9]{16}\.png$#', (string) $result['favicon_path']);
    }

    public function test_delete_removes_existing_files(): void
    {
        Storage::fake('public');
        Storage::disk('public')->put('branding/logo.png', 'x');
        Storage::disk('public')->put('branding/favicon.png', 'y');

        app(BrandingLogoProcessor::class)->delete('branding/logo.png', 'branding/favicon.png');

        Storage::disk('public')->assertMissing('branding/logo.png');
        Storage::disk('public')->assertMissing('branding/favicon.png');
    }

    public function test_store_rejects_malformed_upload_before_writing_public_files(): void
    {
        Storage::fake('public');

        $file = UploadedFile::fake()->createWithContent('logo.png', 'not an image');

        $this->expectException(RuntimeException::class);

        try {
            app(BrandingLogoProcessor::class)->store($file);
        } finally {
            $this->assertSame([], Storage::disk('public')->allFiles());
        }
    }

    public function test_store_skips_favicon_when_gd_is_unavailable(): void
    {
        Storage::fake('public');

        $file = UploadedFile::fake()->image('logo.png', 200, 100);
        $processor = new class extends BrandingLogoProcessor
        {
            protected function gdAvailable(): bool
            {
                return false;
            }
        };

        $result = $processor->store($file);

        Storage::disk('public')->assertExists($result['logo_path']);
        $this->assertMatchesRegularExpression('#^branding/logo-[a-f0-9]{16}\.png$#', $result['logo_path']);
        $this->assertNull($result['favicon_path']);
    }
}
