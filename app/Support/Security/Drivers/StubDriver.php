<?php

namespace App\Support\Security\Drivers;

use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Log;

class StubDriver implements VirusScanDriver
{
    /**
     * @param array<string, mixed> $context
     */
    public function assertClean(UploadedFile $file, array $context = []): void
    {
        Log::debug('Virus scan stub executed', [
            'driver' => 'stub',
            'filename' => $file->getClientOriginalName(),
            'size' => $file->getSize(),
            'context' => $context,
        ]);
    }
}
