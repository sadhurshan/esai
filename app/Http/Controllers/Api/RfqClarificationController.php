<?php

namespace App\Http\Controllers\Api;

use App\Exceptions\RfqResponseWindowException;
use App\Http\Requests\Rfq\StoreRfqAmendmentRequest;
use App\Http\Requests\Rfq\StoreRfqAnswerRequest;
use App\Http\Requests\Rfq\StoreRfqQuestionRequest;
use App\Http\Resources\RfqClarificationResource;
use App\Models\Document;
use App\Models\RfqClarification;
use App\Models\RFQ;
use App\Policies\RfqClarificationPolicy;
use App\Services\RfqClarificationService;
use App\Support\CompanyContext;
use App\Support\RfqResponseWindowGuard;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

class RfqClarificationController extends ApiController
{
    public function __construct(
        private readonly RfqClarificationService $clarifications,
        private readonly RfqClarificationPolicy $clarificationPolicy,
        private readonly RfqResponseWindowGuard $rfqResponseWindowGuard,
    ) {
    }

    public function index(Request $request, RFQ $rfq): JsonResponse
    {
        $context = $this->requireCompanyContext($request);

        if ($context instanceof JsonResponse) {
            return $context;
        }

        ['user' => $user] = $context;

        if (! $this->clarificationPolicy->viewClarifications($user, $rfq)) {
            return $this->fail('Forbidden.', 403);
        }

        $clarifications = $rfq->clarifications()
            ->with('user')
            ->orderBy('created_at')
            ->get();

        $items = RfqClarificationResource::collection($clarifications)->resolve();

        return $this->ok([
            'items' => $items,
        ]);
    }

    public function storeQuestion(StoreRfqQuestionRequest $request, RFQ $rfq): JsonResponse
    {
        $context = $this->requireCompanyContext($request);

        if ($context instanceof JsonResponse) {
            return $context;
        }

        ['user' => $user] = $context;

        if (! $this->clarificationPolicy->postQuestion($user, $rfq)) {
            return $this->fail('Forbidden.', 403);
        }

        $response = $this->guardRfqForResponses($rfq, 'post clarification questions');

        if ($response instanceof JsonResponse) {
            return $response;
        }

        $clarification = $this->clarifications->postQuestion(
            $rfq,
            $user,
            trim($request->validated('message')),
            $this->resolveAttachments($request)
        );

        return $this->ok(
            (new RfqClarificationResource($clarification))->toArray($request),
            'Question posted'
        )->setStatusCode(201);
    }

    public function storeAnswer(StoreRfqAnswerRequest $request, RFQ $rfq): JsonResponse
    {
        $context = $this->requireCompanyContext($request);

        if ($context instanceof JsonResponse) {
            return $context;
        }

        ['user' => $user] = $context;

        if (! $this->clarificationPolicy->postAnswer($user, $rfq)) {
            return $this->fail('Forbidden.', 403);
        }

        $response = $this->guardRfqForResponses($rfq, 'post clarification answers');

        if ($response instanceof JsonResponse) {
            return $response;
        }

        $clarification = $this->clarifications->postAnswer(
            $rfq,
            $user,
            trim($request->validated('message')),
            $this->resolveAttachments($request)
        );

        return $this->ok(
            (new RfqClarificationResource($clarification))->toArray($request),
            'Answer posted'
        )->setStatusCode(201);
    }

    public function storeAmendment(StoreRfqAmendmentRequest $request, RFQ $rfq): JsonResponse
    {
        $context = $this->requireCompanyContext($request);

        if ($context instanceof JsonResponse) {
            return $context;
        }

        ['user' => $user] = $context;

        if (! $this->clarificationPolicy->postAmendment($user, $rfq)) {
            return $this->fail('Forbidden.', 403);
        }

        $response = $this->guardRfqForResponses($rfq, 'post RFQ amendments');

        if ($response instanceof JsonResponse) {
            return $response;
        }

        $clarification = $this->clarifications->postAmendment(
            $rfq,
            $user,
            trim($request->validated('message')),
            $this->resolveAttachments($request)
        );

        return $this->ok(
            (new RfqClarificationResource($clarification))->toArray($request),
            'Amendment posted'
        )->setStatusCode(201);
    }

    public function downloadAttachment(Request $request, RFQ $rfq, RfqClarification $clarification, int $attachment): JsonResponse|StreamedResponse
    {
        $context = $this->requireCompanyContext($request);

        if ($context instanceof JsonResponse) {
            return $context;
        }

        ['user' => $user] = $context;

        if ((int) $clarification->rfq_id !== (int) $rfq->id) {
            return $this->fail('Clarification not found.', 404);
        }

        if (! $this->clarificationPolicy->viewClarifications($user, $rfq)) {
            return $this->fail('Forbidden.', 403);
        }

        $document = $this->resolveAttachmentDocument($clarification, $attachment);

        if (! $document instanceof Document) {
            return $this->fail('Attachment not found.', 404);
        }

        $response = $this->streamDocument($document);

        if (! $response instanceof StreamedResponse) {
            return $this->fail('Attachment unavailable.', 404);
        }

        return $response;
    }

    /**
     * @return list<UploadedFile|null>
     */
    private function resolveAttachments(Request $request): array
    {
        $files = $request->file('attachments');

        if ($files instanceof UploadedFile) {
            return [$files];
        }

        if (is_array($files)) {
            return $files;
        }

        return [];
    }

    private function guardRfqForResponses(RFQ $rfq, string $action): ?JsonResponse
    {
        try {
            $this->rfqResponseWindowGuard->ensureOpenForResponses($rfq, $action);
        } catch (RfqResponseWindowException $exception) {
            return $this->fail($exception->getMessage(), $exception->getStatus(), $exception->getErrors());
        }

        return null;
    }

    private function resolveAttachmentDocument(RfqClarification $clarification, int $documentId): ?Document
    {
        $attachmentIds = $clarification->attachmentIds();

        if (! in_array($documentId, $attachmentIds, true)) {
            return null;
        }

        return CompanyContext::forCompany((int) $clarification->company_id, static function () use ($documentId): ?Document {
            return Document::query()->find($documentId);
        });
    }

    private function streamDocument(Document $document): ?StreamedResponse
    {
        $disk = config('documents.disk', config('filesystems.default', 'public'));
        $path = (string) ($document->path ?? '');

        if ($path === '') {
            return null;
        }

        try {
            $storage = Storage::disk($disk);
        } catch (\Throwable) {
            return null;
        }

        if (! $storage->exists($path)) {
            return null;
        }

        try {
            return $storage->download($path, $document->filename ?? basename($path), [
                'Content-Type' => $document->mime ?? 'application/octet-stream',
            ]);
        } catch (\Throwable) {
            return null;
        }
    }
}
