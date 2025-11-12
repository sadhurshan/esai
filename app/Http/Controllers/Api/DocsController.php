<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\File;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class DocsController extends Controller
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
            return response()->json([
                'status' => 'error',
                'message' => 'Artifact not generated yet.',
                'errors' => [
                    'artifact' => ['Run the corresponding `api:spec:*` command to generate this file.'],
                ],
            ], 404);
        }

        return response()->file($path, [
            'Content-Type' => 'application/json',
            'Cache-Control' => 'no-cache, no-store, must-revalidate',
        ]);
    }
}
