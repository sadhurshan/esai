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
        $user = $this->resolveRequestUser($request);

        if ($user === null) {
            return $this->fail('Authentication required.', 401);
        }

        if ($user->company_id === null || (int) $rfq->company_id !== (int) $user->company_id) {
            return $this->fail('Forbidden.', 403);
        }

        $documents = Document::query()
            ->where('company_id', $user->company_id)
            ->where('documentable_type', $rfq->getMorphClass())
            ->where('documentable_id', $rfq->getKey())
            ->orderByDesc('created_at')
            ->get();

        return $this->ok([
            'items' => RfqAttachmentResource::collection($documents)->resolve(),
        ]);
    }

    public function store(UploadRfqAttachmentRequest $request, RFQ $rfq): JsonResponse
    {
        $user = $this->resolveRequestUser($request);

        if ($user === null) {
            return $this->fail('Authentication required.', 401);
        }

        if ($user->company_id === null || (int) $rfq->company_id !== (int) $user->company_id) {
            return $this->fail('Forbidden.', 403);
        }

        $payload = $request->payload();

        /** @var \Illuminate\Http\UploadedFile $file */
        $file = $payload['file'];

        $document = $this->uploadAttachmentAction->execute($user, $rfq, $file, [
            'title' => $payload['title'] ?? null,
            'description' => $payload['description'] ?? null,
        ]);

        return response()->json([
            'status' => 'success',
            'message' => 'Attachment uploaded.',
            'data' => (new RfqAttachmentResource($document))->toArray($request),
        ], 201);
    }
}
