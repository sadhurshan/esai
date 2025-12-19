<?php

namespace App\Http\Controllers\Api\V1;

use App\Exceptions\AiServiceUnavailableException;
use App\Http\Controllers\Api\ApiController;
use App\Http\Requests\Api\Ai\AiActionPlanRequest;
use App\Http\Requests\Api\Ai\AiActionRejectRequest;
use App\Http\Resources\AiActionDraftResource;
use App\Models\AiActionDraft;
use App\Http\Requests\Api\Ai\AiActionFeedbackRequest;
use App\Http\Resources\AiActionFeedbackResource;
use App\Models\AiActionFeedback;
use App\Models\AiEvent;
use App\Models\User;
use App\Services\Ai\AiClient;
use App\Services\Ai\AiEventRecorder;
use App\Services\Ai\Converters\AiDraftConversionService;
use App\Support\Permissions\PermissionRegistry;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpFoundation\Response;
use Throwable;

class AiActionsController extends ApiController
{
    public function __construct(
        private readonly AiClient $client,
        private readonly AiEventRecorder $recorder,
        private readonly PermissionRegistry $permissionRegistry,
        private readonly AiDraftConversionService $conversionService,
    ) {
    }

    public function plan(AiActionPlanRequest $request): JsonResponse
    {
        $context = $this->requireCompanyContext($request);

        if ($context instanceof JsonResponse) {
            return $context;
        }

        $user = $context['user'];
        $companyId = $context['companyId'];
        $actionType = $request->actionType();

        if ($this->deniesAction($user, $companyId, $actionType)) {
            return $this->fail('You are not authorized to run this Copilot action.', Response::HTTP_FORBIDDEN, [
                'code' => 'copilot_action_forbidden',
            ]);
        }

        $actionPayload = $request->actionPayload();
        $actionPayload['company_id'] = $companyId;
        $actionPayload['user_context'] = $this->buildUserContext($user, $actionPayload['user_context'], $companyId);

        $startedAt = microtime(true);

        try {
            $response = $this->client->planAction($actionPayload);
        } catch (AiServiceUnavailableException $exception) {
            $latency = $this->calculateLatencyMs($startedAt);
            $this->recordEvent(
                companyId: $companyId,
                userId: $user->id,
                feature: 'copilot_action_plan',
                requestPayload: $actionPayload,
                responsePayload: null,
                latencyMs: $latency,
                status: AiEvent::STATUS_ERROR,
                errorMessage: $exception->getMessage(),
                entityType: $request->entityType(),
                entityId: $request->entityId(),
            );

            return $this->fail('AI service is unavailable.', Response::HTTP_SERVICE_UNAVAILABLE, [
                'service' => ['AI service is unavailable.'],
            ]);
        }

        $latency = $this->calculateLatencyMs($startedAt);

        if ($response['status'] !== 'success' || ! is_array($response['data'])) {
            $this->recordEvent(
                companyId: $companyId,
                userId: $user->id,
                feature: 'copilot_action_plan',
                requestPayload: $actionPayload,
                responsePayload: $response,
                latencyMs: $latency,
                status: AiEvent::STATUS_ERROR,
                errorMessage: $response['message'] ?? 'Failed to generate action draft.',
                entityType: $request->entityType(),
                entityId: $request->entityId(),
            );

            return $this->fail($response['message'] ?? 'Failed to generate action draft.', Response::HTTP_BAD_GATEWAY, $response['errors'] ?? null);
        }

        $actionResponse = $response['data'];
        $citations = Arr::get($actionResponse, 'citations', []);
        $citations = is_array($citations) ? $citations : [];

        $draft = AiActionDraft::query()->create([
            'user_id' => $user->id,
            'action_type' => $actionType,
            'input_json' => $this->snapshotInput($request, $actionPayload),
            'output_json' => $actionResponse,
            'citations_json' => $citations,
            'status' => AiActionDraft::STATUS_DRAFTED,
        ]);

        $this->recordEvent(
            companyId: $companyId,
            userId: $user->id,
            feature: 'copilot_action_plan',
            requestPayload: $actionPayload,
            responsePayload: $actionResponse,
            latencyMs: $latency,
            status: AiEvent::STATUS_SUCCESS,
            errorMessage: null,
            entityType: $request->entityType(),
            entityId: $request->entityId(),
        );

        return $this->ok([
            'draft' => (new AiActionDraftResource($draft))->toArray($request),
        ], 'Action draft generated.');
    }

    public function approve(Request $request, int $draft): JsonResponse
    {
        $context = $this->requireCompanyContext($request);

        if ($context instanceof JsonResponse) {
            return $context;
        }

        $user = $context['user'];
        $companyId = $context['companyId'];
        $model = $this->findDraft($draft, $companyId);

        if (! $model instanceof AiActionDraft) {
            return $this->fail('Draft not found.', Response::HTTP_NOT_FOUND);
        }

        if (! $model->isDrafted()) {
            return $this->fail('Draft already resolved.', Response::HTTP_UNPROCESSABLE_ENTITY, [
                'code' => 'draft_not_pending',
            ]);
        }

        if ($this->deniesAction($user, $companyId, $model->action_type)) {
            return $this->fail('You are not authorized to approve this Copilot action.', Response::HTTP_FORBIDDEN, [
                'code' => 'copilot_action_forbidden',
            ]);
        }

        try {
            DB::transaction(function () use ($model, $user): void {
                $model->forceFill([
                    'status' => AiActionDraft::STATUS_APPROVED,
                    'approved_by' => $user->id,
                    'approved_at' => Carbon::now(),
                    'rejected_reason' => null,
                ])->save();

                $this->conversionService->convert($model, $user);
            });
        } catch (ValidationException $exception) {
            return $this->fail('Draft payload is invalid.', Response::HTTP_UNPROCESSABLE_ENTITY, $exception->errors());
        } catch (Throwable $exception) {
            report($exception);

            return $this->fail('Failed to finalize Copilot action.', Response::HTTP_INTERNAL_SERVER_ERROR, [
                'copilot' => ['Unable to convert draft into a record.'],
            ]);
        }

        $this->recordEvent(
            companyId: $companyId,
            userId: $user->id,
            feature: 'copilot_action_approve',
            requestPayload: ['draft_id' => $model->id, 'action_type' => $model->action_type],
            responsePayload: ['status' => $model->status, 'entity_type' => $model->entity_type, 'entity_id' => $model->entity_id],
            latencyMs: null,
            status: AiEvent::STATUS_SUCCESS,
            errorMessage: null,
            entityType: $model->entity_type,
            entityId: $model->entity_id,
        );

        return $this->ok([
            'draft' => (new AiActionDraftResource($model->refresh()))->toArray($request),
        ], 'Action draft approved.');
    }

    public function reject(AiActionRejectRequest $request, int $draft): JsonResponse
    {
        $context = $this->requireCompanyContext($request);

        if ($context instanceof JsonResponse) {
            return $context;
        }

        $user = $context['user'];
        $companyId = $context['companyId'];
        $model = $this->findDraft($draft, $companyId);

        if (! $model instanceof AiActionDraft) {
            return $this->fail('Draft not found.', Response::HTTP_NOT_FOUND);
        }

        if (! $model->isDrafted()) {
            return $this->fail('Draft already resolved.', Response::HTTP_UNPROCESSABLE_ENTITY, [
                'code' => 'draft_not_pending',
            ]);
        }

        if ($this->deniesAction($user, $companyId, $model->action_type)) {
            return $this->fail('You are not authorized to reject this Copilot action.', Response::HTTP_FORBIDDEN, [
                'code' => 'copilot_action_forbidden',
            ]);
        }

        $model->forceFill([
            'status' => AiActionDraft::STATUS_REJECTED,
            'approved_by' => null,
            'approved_at' => null,
            'rejected_reason' => $request->reason(),
        ])->save();

        $this->recordEvent(
            companyId: $companyId,
            userId: $user->id,
            feature: 'copilot_action_reject',
            requestPayload: ['draft_id' => $model->id, 'action_type' => $model->action_type, 'reason' => $request->reason()],
            responsePayload: ['status' => $model->status],
            latencyMs: null,
            status: AiEvent::STATUS_SUCCESS,
            errorMessage: null,
            entityType: $model->entity_type,
            entityId: $model->entity_id,
        );

        return $this->ok([
            'draft' => (new AiActionDraftResource($model->refresh()))->toArray($request),
        ], 'Action draft rejected.');
    }

    public function feedback(AiActionFeedbackRequest $request, int $draft): JsonResponse
    {
        $context = $this->requireCompanyContext($request);

        if ($context instanceof JsonResponse) {
            return $context;
        }

        $user = $context['user'];
        $companyId = $context['companyId'];
        $model = $this->findDraft($draft, $companyId);

        if (! $model instanceof AiActionDraft) {
            return $this->fail('Draft not found.', Response::HTTP_NOT_FOUND);
        }

        if ($this->deniesAction($user, $companyId, $model->action_type)) {
            return $this->fail('You are not authorized to review this Copilot action.', Response::HTTP_FORBIDDEN, [
                'code' => 'copilot_action_forbidden',
            ]);
        }

        $feedback = AiActionFeedback::query()->create([
            'company_id' => $companyId,
            'ai_action_draft_id' => $model->id,
            'user_id' => $user->id,
            'rating' => $request->rating(),
            'comment' => $request->comment(),
        ]);

        $this->recordEvent(
            companyId: $companyId,
            userId: $user->id,
            feature: 'copilot_action_feedback',
            requestPayload: ['draft_id' => $model->id, 'rating' => $request->rating()],
            responsePayload: ['feedback_id' => $feedback->id],
            latencyMs: null,
            status: AiEvent::STATUS_SUCCESS,
            errorMessage: null,
            entityType: $model->entity_type,
            entityId: $model->entity_id,
        );

        return $this->ok([
            'feedback' => (new AiActionFeedbackResource($feedback))->toArray($request),
        ], 'Feedback recorded.');
    }

    /**
     * @param array<string, mixed> $actionPayload
     * @return array<string, mixed>
     */
    private function snapshotInput(AiActionPlanRequest $request, array $actionPayload): array
    {
        return [
            'query' => $actionPayload['query'],
            'inputs' => $actionPayload['inputs'],
            'user_context' => $actionPayload['user_context'],
            'filters' => $actionPayload['filters'],
            'top_k' => $actionPayload['top_k'],
            'entity_context' => $request->entityContext(),
        ];
    }

    private function calculateLatencyMs(float $startedAt): int
    {
        return (int) round((microtime(true) - $startedAt) * 1000);
    }

    private function findDraft(int $draftId, int $companyId): ?AiActionDraft
    {
        return AiActionDraft::query()
            ->forCompany($companyId)
            ->whereKey($draftId)
            ->first();
    }

    /**
     * @param array<string, mixed> $requestPayload
     * @param array<string, mixed>|null $responsePayload
     */
    private function recordEvent(
        int $companyId,
        int $userId,
        string $feature,
        array $requestPayload,
        ?array $responsePayload,
        ?int $latencyMs,
        string $status,
        ?string $errorMessage,
        ?string $entityType,
        ?int $entityId
    ): void {
        $this->recorder->record(
            companyId: $companyId,
            userId: $userId,
            feature: $feature,
            requestPayload: $requestPayload,
            responsePayload: $responsePayload,
            latencyMs: $latencyMs,
            status: $status,
            errorMessage: $errorMessage,
            entityType: $entityType,
            entityId: $entityId,
        );
    }

    private function buildUserContext(User $user, array $clientContext, int $companyId): array
    {
        $user->loadMissing('company:id,name');
        $defaults = array_filter([
            'user_id' => $user->id,
            'user_role' => $user->role,
            'user_name' => $user->name,
            'job_title' => $user->job_title,
            'timezone' => $user->timezone,
            'locale' => $user->locale,
            'company_id' => $companyId,
            'company_name' => $user->company?->name,
        ], static fn ($value) => $value !== null && $value !== '');

        return $defaults + $clientContext;
    }

    private function deniesAction(User $user, int $companyId, string $actionType): bool
    {
        $permissionMap = [
            AiActionDraft::TYPE_RFQ_DRAFT => ['rfqs.write'],
            AiActionDraft::TYPE_SUPPLIER_MESSAGE => ['suppliers.write'],
            AiActionDraft::TYPE_MAINTENANCE_CHECKLIST => ['inventory.write'],
            AiActionDraft::TYPE_INVENTORY_WHATIF => ['inventory.read', 'inventory.write'],
        ];

        $permissions = $permissionMap[$actionType] ?? ['rfqs.write'];

        return ! $this->permissionRegistry->userHasAny($user, $permissions, $companyId);
    }
}
