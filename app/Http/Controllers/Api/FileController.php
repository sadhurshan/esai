<?php

namespace App\Http\Controllers\Api;

use App\Models\RFQ;
use App\Models\RFQQuote;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Storage;
use Throwable;

class FileController extends ApiController
{
    public function cad(RFQ $rfq): JsonResponse
    {
        if (! $rfq->cad_path) {
            return $this->fail('Not found', 404);
        }

        return $this->respondWithPath($rfq->cad_path);
    }

    public function attachment(RFQQuote $quote): JsonResponse
    {
        if (! $quote->attachment_path) {
            return $this->fail('Not found', 404);
        }

        return $this->respondWithPath($quote->attachment_path);
    }

    private function respondWithPath(string $path): JsonResponse
    {
        $normalizedPath = ltrim($path, '/');

        $publicDisk = Storage::disk('public');
        if ($publicDisk->exists($normalizedPath)) {
            return $this->ok([
                'url' => asset('storage/'.$normalizedPath),
            ]);
        }

        $defaultDisk = config('filesystems.default', 'local');

        if ($defaultDisk !== 'public' && $this->diskExists($defaultDisk, $normalizedPath)) {
            if (in_array($defaultDisk, ['local'])) {
                return $this->ok([
                    'url' => asset('storage/'.$normalizedPath),
                ]);
            }

            return $this->ok([
                'url' => Storage::disk($defaultDisk)->temporaryUrl($normalizedPath, now()->addMinutes(15)),
            ]);
        }

        $cloudDisk = config('filesystems.cloud');
        if ($cloudDisk && ! in_array($cloudDisk, ['public', $defaultDisk], true) && $this->diskExists($cloudDisk, $normalizedPath)) {
            return $this->ok([
                'url' => Storage::disk($cloudDisk)->temporaryUrl($normalizedPath, now()->addMinutes(15)),
            ]);
        }

        return $this->fail('Not found', 404);
    }

    private function diskExists(string $disk, string $path): bool
    {
        try {
            return Storage::disk($disk)->exists($path);
        } catch (Throwable) {
            return false;
        }
    }
}
