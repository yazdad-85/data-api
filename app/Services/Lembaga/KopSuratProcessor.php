<?php

namespace App\Services\Lembaga;

use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use RuntimeException;

class KopSuratProcessor
{
    public function store(UploadedFile $file): string
    {
        $sourcePath = $file->getRealPath();
        $info = $sourcePath === false ? false : getimagesize($sourcePath);

        if ($info === false || $info[2] !== IMAGETYPE_PNG) {
            throw new RuntimeException('Kop surat harus berupa gambar PNG yang valid.');
        }

        $disk = Storage::disk('public');
        $disk->makeDirectory('kop-surat');

        $path = 'kop-surat/'.bin2hex(random_bytes(8)).'.png';
        $disk->put($path, file_get_contents($sourcePath));

        return $path;
    }

    public function delete(?string $path): void
    {
        $disk = Storage::disk('public');

        if ($path && $disk->exists($path)) {
            $disk->delete($path);
        }
    }
}
