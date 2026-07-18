<?php

namespace App\Services;

use Illuminate\Http\UploadedFile;

class BusinessDocumentVerifier
{
    public function validate(UploadedFile $file, string $purpose): ?string
    {
        $path = $file->getRealPath();
        if (!$path || !is_file($path)) {
            return $this->message($purpose);
        }

        $mime = $this->mimeType($path);
        $allowedMimes = $purpose === 'shop_image'
            ? ['image/jpeg', 'image/png']
            : ['application/pdf', 'image/jpeg', 'image/png'];

        if (!in_array($mime, $allowedMimes, true)) {
            return $this->message($purpose);
        }

        if ($mime === 'application/pdf') {
            return $this->isPdf($path) ? null : $this->message($purpose);
        }

        return $this->isReadableImage($path, $purpose) ? null : $this->message($purpose);
    }

    public function hash(UploadedFile $file): ?string
    {
        $path = $file->getRealPath();

        return $path && is_file($path) ? hash_file('sha256', $path) : null;
    }

    private function mimeType(string $path): string
    {
        $finfo = new \finfo(FILEINFO_MIME_TYPE);

        return (string) ($finfo->file($path) ?: '');
    }

    private function isPdf(string $path): bool
    {
        $header = file_get_contents($path, false, null, 0, 5);

        return $header === '%PDF-';
    }

    private function isReadableImage(string $path, string $purpose): bool
    {
        $image = @getimagesize($path);
        if (!$image || !in_array($image[2] ?? null, [IMAGETYPE_JPEG, IMAGETYPE_PNG], true)) {
            return false;
        }

        [$minimumWidth, $minimumHeight] = $purpose === 'shop_image'
            ? [320, 180]
            : [200, 120];

        return ($image[0] ?? 0) >= $minimumWidth && ($image[1] ?? 0) >= $minimumHeight;
    }

    private function message(string $purpose): string
    {
        return match ($purpose) {
            'cnic_image' => 'Please upload a valid CNIC document.',
            'business_document' => 'Please upload a valid business document.',
            default => 'Please upload a valid shop or business premises image.',
        };
    }
}
