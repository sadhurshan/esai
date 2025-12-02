<?php

namespace App\Support\Security\Drivers;

use Illuminate\Http\UploadedFile;

interface VirusScanDriver
{
    /**
     * @param array<string, mixed> $context
     */
    public function assertClean(UploadedFile $file, array $context = []): void;
}
