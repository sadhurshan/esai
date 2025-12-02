<?php

namespace App\Http\Controllers\Api;

use App\Actions\Rfq\UploadRfqAttachmentAction;
use App\Http\Requests\Rfq\UploadRfqAttachmentRequest;
use App\Http\Resources\RfqAttachmentResource;
use App\Models\Document;
use App\Models\RFQ;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class RfqAttachmentController extends ApiController
{
    public function __construct(private readonly UploadRfqAttachmentAction $uploadAttachmentAction)
    {
    }

    public function index(RFQ $rfq, Request $request): JsonResponse
    {
        $context = $this->requireCompanyContext($request);

        if ($context instanceof JsonResponse) {
            return $context;
        }

        ['user' => $user, 'companyId' => $companyId] = $context;

        if ((int) $rfq->company_id !== $companyId) {
            return $this->fail('Forbidden.', 403);
        }

        $documents = Document::query()
            ->where('company_id', $companyId)
            ->where('documentable_type', $rfq->getMorphClass())
            ->where('documentable_id', $rfq->getKey())
            ->orderByDesc('created_at')
            ->orderByDesc('id')
            ->cursorPaginate($this->perPage($request, 20, 100));

        ['items' => $items, 'meta' => $meta] = $this->paginate($documents, $request, RfqAttachmentResource::class);

        return $this->ok([
            'items' => $items,
        ], 'RFQ attachments retrieved.', $meta);
    }

    public function store(UploadRfqAttachmentRequest $request, RFQ $rfq): JsonResponse
    {
        $context = $this->requireCompanyContext($request);

        if ($context instanceof JsonResponse) {
            return $context;
        }

        ['user' => $user, 'companyId' => $companyId] = $context;

        if ((int) $rfq->company_id !== $companyId) {
            return $this->fail('Forbidden.', 403);
        }

        $payload = $request->payload();

        /** @var \Illuminate\Http\UploadedFile $file */
        $file = $payload['file'];

        $document = $this->uploadAttachmentAction->execute($user, $rfq, $file, [
            'title' => $payload['title'] ?? null,
            'description' => $payload['description'] ?? null,
        ]);

        return $this->ok(
            (new RfqAttachmentResource($document))->toArray($request),
            'Attachment uploaded.'
        )->setStatusCode(201);
    }
}
