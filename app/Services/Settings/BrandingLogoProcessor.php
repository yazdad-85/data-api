<?php

namespace App\Services\Settings;

use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use RuntimeException;

class BrandingLogoProcessor
{
    /**
     * @return array{logo_path: string, favicon_path: string}
     */
    public function store(UploadedFile $file): array
    {
        $disk = Storage::disk('public');
        $disk->makeDirectory('branding');

        $extension = strtolower($file->getClientOriginalExtension() ?: $file->extension() ?: 'png');
        if (! in_array($extension, ['png', 'jpg', 'jpeg', 'webp'], true)) {
            throw new RuntimeException('Unsupported logo format.');
        }

        $logoPath = 'branding/logo.'.$extension;
        $disk->put($logoPath, file_get_contents($file->getRealPath()));

        $faviconPath = 'branding/favicon.png';
        $this->writeFaviconPng($file->getRealPath(), $disk->path($faviconPath));

        return [
            'logo_path' => $logoPath,
            'favicon_path' => $faviconPath,
        ];
    }

    public function delete(?string $logoPath, ?string $faviconPath): void
    {
        $disk = Storage::disk('public');

        foreach ([$logoPath, $faviconPath] as $path) {
            if ($path && $disk->exists($path)) {
                $disk->delete($path);
            }
        }
    }

    private function writeFaviconPng(string $sourcePath, string $destPath): void
    {
        if (! extension_loaded('gd')) {
            copy($sourcePath, $destPath);

            return;
        }

        $info = getimagesize($sourcePath);
        if ($info === false) {
            throw new RuntimeException('Unable to read logo image.');
        }

        $source = match ($info[2]) {
            IMAGETYPE_JPEG => imagecreatefromjpeg($sourcePath),
            IMAGETYPE_PNG => imagecreatefrompng($sourcePath),
            IMAGETYPE_WEBP => function_exists('imagecreatefromwebp') ? imagecreatefromwebp($sourcePath) : false,
            default => false,
        };

        if ($source === false) {
            throw new RuntimeException('Unable to decode logo image.');
        }

        $canvas = imagecreatetruecolor(32, 32);
        imagealphablending($canvas, false);
        imagesavealpha($canvas, true);
        $transparent = imagecolorallocatealpha($canvas, 0, 0, 0, 127);
        imagefilledrectangle($canvas, 0, 0, 32, 32, $transparent);
        imagecopyresampled($canvas, $source, 0, 0, 0, 0, 32, 32, imagesx($source), imagesy($source));
        imagepng($canvas, $destPath);
        imagedestroy($source);
        imagedestroy($canvas);
    }
}
