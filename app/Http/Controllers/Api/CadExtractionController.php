<?php

namespace App\Http\Controllers\Api;

use App\Jobs\ParseCadDocumentJob;
use App\Models\AiDocumentExtraction;
use App\Models\Document;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class CadExtractionController extends ApiController
{
    public function show(Request $request, Document $document): JsonResponse
    {
        $context = $this->requireCompanyContext($request);

        if ($context instanceof JsonResponse) {
            return $context;
        }

        ['user' => $user, 'companyId' => $companyId] = $context;

        if ($this->authorizeDenied($user, 'view', $document)) {
            return $this->fail('Forbidden.', 403);
        }

        if ((int) $document->company_id !== (int) $companyId) {
            return $this->fail('Document not found.', 404);
        }

        $version = (int) ($document->version_number ?? 1);

        $record = AiDocumentExtraction::query()
            ->where('company_id', $companyId)
            ->where('document_id', $document->getKey())
            ->where('document_version', $version)
            ->first();

        if (! $record && $this->isCadCandidate($document->filename, $document->mime)) {
            ParseCadDocumentJob::dispatch(
                companyId: $companyId,
                documentId: (int) $document->getKey(),
                documentVersion: $version,
            );
        }

        return $this->ok([
            'document_id' => (int) $document->getKey(),
            'document_version' => $version,
            'status' => $record?->status ?? 'pending',
            'extracted' => $record?->extracted_json ?? null,
            'gdt_flags' => $record?->gdt_flags_json ?? null,
            'similar_parts' => $record?->similar_parts_json ?? [],
            'extracted_at' => optional($record?->extracted_at)?->toIso8601String(),
            'last_error' => $record?->last_error,
        ], 'CAD extraction status.');
    }

    private function isCadCandidate(?string $filename, ?string $mime): bool
    {
        $extension = strtolower(pathinfo((string) $filename, PATHINFO_EXTENSION));
        $cadExtensions = ['step', 'stp', 'iges', 'igs', 'dwg', 'dxf', 'sldprt', 'stl', '3mf'];

        if ($extension !== '' && in_array($extension, $cadExtensions, true)) {
            return true;
        }

        $mimeValue = strtolower((string) $mime);

        return str_contains($mimeValue, 'cad') || str_contains($mimeValue, 'step');
    }
}
