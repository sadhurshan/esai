<?php

namespace App\Http\Controllers\Api;

use App\Models\RFQ;
use App\Models\RFQQuote;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Storage;
use Throwable;

class FileController extends ApiController
{
    public function cad(Request $request, RFQ $rfq): JsonResponse
    {
        $context = $this->requireCompanyContext($request);

        if ($context instanceof JsonResponse) {
            return $context;
        }

        ['user' => $user, 'companyId' => $companyId] = $context;

        if ((int) $rfq->company_id !== $companyId) {
            return $this->fail('RFQ not found.', 404);
        }

        Gate::forUser($user)->authorize('view', $rfq);

        $rfq->loadMissing('cadDocument');

        $document = $rfq->cadDocument;

        if (! $document) {
            return $this->fail('Not found', 404);
        }

        return $this->respondWithPath($document->path);
    }

    public function attachment(Request $request, RFQQuote $quote): JsonResponse
    {
        $context = $this->requireCompanyContext($request);

        if ($context instanceof JsonResponse) {
            return $context;
        }

        ['user' => $user] = $context;

        $quote->loadMissing(['rfq', 'supplier']);

        Gate::forUser($user)->authorize('view', $quote);

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
