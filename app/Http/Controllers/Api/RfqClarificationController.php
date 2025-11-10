<?php

namespace App\Http\Controllers\Api;

use App\Http\Requests\Rfq\StoreRfqAmendmentRequest;
use App\Http\Requests\Rfq\StoreRfqAnswerRequest;
use App\Http\Requests\Rfq\StoreRfqQuestionRequest;
use App\Http\Resources\RfqClarificationResource;
use App\Models\RFQ;
use App\Services\RfqClarificationService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;

class RfqClarificationController extends ApiController
{
    public function __construct(private readonly RfqClarificationService $clarifications)
    {
    }

    public function index(Request $request, RFQ $rfq): JsonResponse
    {
        $user = $this->resolveRequestUser($request);

        if ($this->authorizeDenied($user, 'viewClarifications', $rfq)) {
            abort(403);
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
        $user = $this->resolveRequestUser($request);

        if ($this->authorizeDenied($user, 'postQuestion', $rfq) || $user === null) {
            abort(403);
        }

        $clarification = $this->clarifications->postQuestion(
            $rfq,
            $user,
            trim($request->validated('message')),
            $this->resolveAttachments($request)
        );

        return response()->json([
            'status' => 'success',
            'message' => 'Question posted',
            'data' => (new RfqClarificationResource($clarification))->toArray($request),
        ], 201);
    }

    public function storeAnswer(StoreRfqAnswerRequest $request, RFQ $rfq): JsonResponse
    {
        $user = $this->resolveRequestUser($request);

        if ($this->authorizeDenied($user, 'postAnswer', $rfq) || $user === null) {
            abort(403);
        }

        $clarification = $this->clarifications->postAnswer(
            $rfq,
            $user,
            trim($request->validated('message')),
            $this->resolveAttachments($request)
        );

        return response()->json([
            'status' => 'success',
            'message' => 'Answer posted',
            'data' => (new RfqClarificationResource($clarification))->toArray($request),
        ], 201);
    }

    public function storeAmendment(StoreRfqAmendmentRequest $request, RFQ $rfq): JsonResponse
    {
        $user = $this->resolveRequestUser($request);

        if ($this->authorizeDenied($user, 'postAmendment', $rfq) || $user === null) {
            abort(403);
        }

        $clarification = $this->clarifications->postAmendment(
            $rfq,
            $user,
            trim($request->validated('message')),
            $this->resolveAttachments($request)
        );

        return response()->json([
            'status' => 'success',
            'message' => 'Amendment posted',
            'data' => (new RfqClarificationResource($clarification))->toArray($request),
        ], 201);
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
}
