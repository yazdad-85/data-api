<?php

namespace Tests\Unit;

use App\Services\Settings\BrandingLogoProcessor;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
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
        $this->assertStringStartsWith('branding/', $result['logo_path']);
        $this->assertSame('branding/favicon.png', $result['favicon_path']);
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
}
