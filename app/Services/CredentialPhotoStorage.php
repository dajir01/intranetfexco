<?php

namespace App\Services;

use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use RuntimeException;

class CredentialPhotoStorage
{
    public function store(UploadedFile $photo): string
    {
        $filename = 'acreditado_'.Str::uuid().'.'.$photo->extension();
        $storedPath = Storage::disk('credential_photos')->putFileAs('', $photo, $filename);

        if (! $storedPath) {
            throw new RuntimeException('No se pudo guardar la fotografía del acreditado.');
        }

        return 'images/fotos/'.$filename;
    }

    public function path(?string $storedPath): ?string
    {
        if (! $storedPath) {
            return null;
        }

        $filename = basename(str_replace('\\', '/', $storedPath));
        if (! preg_match('/^acreditado_[a-f0-9-]+\.(jpg|jpeg|png)$/i', $filename)) {
            return null;
        }

        $path = Storage::disk('credential_photos')->path($filename);

        return is_file($path) ? $path : null;
    }
}
