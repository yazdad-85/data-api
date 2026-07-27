<?php

namespace App\Services\Settings;

use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use RuntimeException;
use Throwable;

class BrandingLogoProcessor
{
    /**
     * @return array{logo_path: string, favicon_path: string|null}
     */
    public function store(UploadedFile $file): array
    {
        $sourcePath = $file->getRealPath();
        $info = $sourcePath === false ? false : getimagesize($sourcePath);
        $extension = $info === false ? null : match ($info[2]) {
            IMAGETYPE_JPEG => 'jpg',
            IMAGETYPE_PNG => 'png',
            IMAGETYPE_WEBP => 'webp',
            default => null,
        };

        if ($extension === null) {
            throw new RuntimeException('Unsupported or malformed logo image.');
        }

        $disk = Storage::disk('public');
        $disk->makeDirectory('branding');

        $suffix = bin2hex(random_bytes(8));
        $logoPath = 'branding/logo-'.$suffix.'.'.$extension;
        $faviconPath = null;

        try {
            $disk->put($logoPath, file_get_contents($sourcePath));

            if ($this->gdAvailable()) {
                $faviconPath = 'branding/favicon-'.$suffix.'.png';
                $this->writeFaviconPng($sourcePath, $disk->path($faviconPath));
            }
        } catch (Throwable $exception) {
            $disk->delete([$logoPath, $faviconPath]);

            throw $exception;
        }

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

    protected function gdAvailable(): bool
    {
        return extension_loaded('gd');
    }

    private function writeFaviconPng(string $sourcePath, string $destPath): void
    {
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
