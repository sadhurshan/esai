<?php

namespace App\Http\Controllers\Api\Ai;

use App\Http\Controllers\Api\ApiController;
use App\Http\Requests\Ai\ReindexDocumentRequest;
use App\Jobs\IndexDocumentForSearchJob;
use App\Models\AiEvent;
use App\Models\Document;
use App\Services\Ai\AiEventRecorder;
use Illuminate\Http\JsonResponse;

class AiDocumentIndexController extends ApiController
{
    public function reindex(
        ReindexDocumentRequest $request,
        AiEventRecorder $recorder
    ): JsonResponse {
        $context = $this->requireCompanyContext($request);

        if ($context instanceof JsonResponse) {
            return $context;
        }

        $user = $context['user'];
        $companyId = $context['companyId'];
        $validated = $request->validated();

        $docId = (int) $validated['doc_id'];
        $docVersion = (string) $validated['doc_version'];

        $document = Document::query()
            ->where('company_id', $companyId)
            ->find($docId);

        if (! $document instanceof Document) {
            $recorder->record(
                companyId: $companyId,
                userId: $user->getKey(),
                feature: 'reindex_document',
                requestPayload: [
                    'doc_id' => $docId,
                    'doc_version' => $docVersion,
                ],
                responsePayload: null,
                latencyMs: null,
                status: AiEvent::STATUS_ERROR,
                errorMessage: 'Document not found for company.',
                entityType: Document::class,
                entityId: $docId,
            );

            return $this->fail('Document not found.', 404);
        }

        IndexDocumentForSearchJob::dispatch(
            companyId: $companyId,
            documentId: $docId,
            docVersion: $docVersion,
        );

        $recorder->record(
            companyId: $companyId,
            userId: $user->getKey(),
            feature: 'reindex_document',
            requestPayload: [
                'doc_id' => $docId,
                'doc_version' => $docVersion,
                'dispatched_at' => now()->toIso8601String(),
            ],
            responsePayload: null,
            latencyMs: null,
            status: AiEvent::STATUS_SUCCESS,
            errorMessage: null,
            entityType: Document::class,
            entityId: $document->getKey(),
        );

        return $this->ok(
            [
                'doc_id' => $docId,
                'doc_version' => $docVersion,
            ],
            'Document reindex scheduled.'
        );
    }
}
