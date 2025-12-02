<?php

namespace App\Http\Controllers\Api;

use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\File;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class DocsController extends ApiController
{
    public function openApi(): BinaryFileResponse|JsonResponse
    {
        return $this->streamArtifact('api/openapi.json');
    }

    public function postman(): BinaryFileResponse|JsonResponse
    {
        return $this->streamArtifact('api/postman.json');
    }

    private function streamArtifact(string $relativePath): BinaryFileResponse|JsonResponse
    {
        $path = storage_path($relativePath);

        if (! File::exists($path)) {
            return $this->fail('Artifact not generated yet.', 404, [
                'artifact' => ['Run the corresponding `api:spec:*` command to generate this file.'],
            ]);
        }

        return response()->file($path, [
            'Content-Type' => 'application/json',
            'Cache-Control' => 'no-cache, no-store, must-revalidate',
        ]);
    }
}
