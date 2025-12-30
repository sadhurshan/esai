<?php

namespace App\Http\Controllers\Api\V1;

use App\Exceptions\AiServiceUnavailableException;
use App\Http\Controllers\Api\ApiController;
use App\Http\Requests\Api\Ai\AiActionApproveRequest;
use App\Http\Requests\Api\Ai\AiActionPlanRequest;
use App\Http\Requests\Api\Ai\AiActionRejectRequest;
use App\Http\Resources\AiActionDraftResource;
use App\Models\AiActionDraft;
use App\Http\Requests\Api\Ai\AiActionFeedbackRequest;
use App\Http\Resources\AiActionFeedbackResource;
use App\Models\AiActionFeedback;
use App\Models\AiChatThread;
use App\Models\AiEvent;
use App\Models\User;
use App\Services\Ai\AiClient;
use App\Services\Ai\AiEventRecorder;
use App\Services\Ai\Converters\AiDraftConversionService;
use App\Services\Ai\ChatService;
use App\Services\Ai\Policies\PolicyCheckService;
use App\Services\Ai\Policies\PolicyDecision;
use App\Support\Permissions\PermissionRegistry;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Arr;
use Illuminate\Support\Str;
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
        private readonly PolicyCheckService $policyCheckService,
        private readonly ChatService $chatService,
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

    public function approve(AiActionApproveRequest $request, int $draft): JsonResponse
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

        $thread = $this->resolveThread($request->threadId(), $companyId);

        if ($request->threadId() !== null && ! $thread instanceof AiChatThread) {
            return $this->fail('Chat thread not found.', Response::HTTP_NOT_FOUND, [
                'code' => 'chat_thread_not_found',
            ]);
        }

        $policyActionType = $this->resolvePolicyActionType($model);

        $policyDecision = $this->policyCheckService->evaluate(
            $companyId,
            $user,
            $policyActionType,
            $this->buildPolicyPayload($model)
        );

        if (! $policyDecision->allowed()) {
            $this->recordEvent(
                companyId: $companyId,
                userId: $user->id,
                feature: 'copilot_action_approve',
                requestPayload: [
                    'draft_id' => $model->id,
                    'action_type' => $model->action_type,
                    'policy_action_type' => $policyActionType,
                    'policy_decision' => $policyDecision->toArray(),
                ],
                responsePayload: null,
                latencyMs: null,
                status: AiEvent::STATUS_ERROR,
                errorMessage: 'Policy check blocked this Copilot action.',
                entityType: $model->entity_type,
                entityId: $model->entity_id,
                thread: $thread,
            );

            return $this->policyCheckFailedResponse($policyDecision, $model);
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
            $model->refresh();
        } catch (ValidationException $exception) {
            return $this->fail('Draft payload is invalid.', Response::HTTP_UNPROCESSABLE_ENTITY, $exception->errors());
        } catch (Throwable $exception) {
            report($exception);

            return $this->fail('Failed to finalize Copilot action.', Response::HTTP_INTERNAL_SERVER_ERROR, [
                'copilot' => ['Unable to convert draft into a record.'],
            ]);
        }

        $this->appendThreadSystemMessage($thread, $user, $model, AiActionDraft::STATUS_APPROVED);

        $requestPayload = [
            'draft_id' => $model->id,
            'action_type' => $model->action_type,
        ];

        if ($thread instanceof AiChatThread) {
            $requestPayload['thread_id'] = $thread->id;
        }

        $this->recordEvent(
            companyId: $companyId,
            userId: $user->id,
            feature: 'copilot_action_approve',
            requestPayload: $requestPayload,
            responsePayload: ['status' => $model->status, 'entity_type' => $model->entity_type, 'entity_id' => $model->entity_id],
            latencyMs: null,
            status: AiEvent::STATUS_SUCCESS,
            errorMessage: null,
            entityType: $model->entity_type,
            entityId: $model->entity_id,
            thread: $thread,
        );

        return $this->ok([
            'draft' => (new AiActionDraftResource($model))->toArray($request),
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

        $thread = $this->resolveThread($request->threadId(), $companyId);

        if ($request->threadId() !== null && ! $thread instanceof AiChatThread) {
            return $this->fail('Chat thread not found.', Response::HTTP_NOT_FOUND, [
                'code' => 'chat_thread_not_found',
            ]);
        }

        $model->forceFill([
            'status' => AiActionDraft::STATUS_REJECTED,
            'approved_by' => null,
            'approved_at' => null,
            'rejected_reason' => $request->reason(),
        ])->save();

        $model->refresh();

        $this->appendThreadSystemMessage($thread, $user, $model, AiActionDraft::STATUS_REJECTED, $request->reason());

        $requestPayload = [
            'draft_id' => $model->id,
            'action_type' => $model->action_type,
            'reason' => $request->reason(),
        ];

        if ($thread instanceof AiChatThread) {
            $requestPayload['thread_id'] = $thread->id;
        }

        $this->recordEvent(
            companyId: $companyId,
            userId: $user->id,
            feature: 'copilot_action_reject',
            requestPayload: $requestPayload,
            responsePayload: ['status' => $model->status],
            latencyMs: null,
            status: AiEvent::STATUS_SUCCESS,
            errorMessage: null,
            entityType: $model->entity_type,
            entityId: $model->entity_id,
            thread: $thread,
        );

        return $this->ok([
            'draft' => (new AiActionDraftResource($model))->toArray($request),
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

    private function buildPolicyPayload(AiActionDraft $draft): array
    {
        $payload = [
            'draft_id' => $draft->id,
        ];

        $output = $draft->output_json;

        if (is_array($output)) {
            $payload = array_merge(
                $payload,
                Arr::except($output, ['payload', 'citations', 'warnings'])
            );

            if (isset($output['payload']) && is_array($output['payload'])) {
                $payload = array_merge($payload, $output['payload']);
            }
        }

        $input = $draft->input_json;

        if (is_array($input)) {
            $payload['inputs'] = Arr::except($input, ['query']);
        }

        if ($draft->entity_type !== null && $draft->entity_id !== null) {
            $payload['entity'] = [
                'type' => $draft->entity_type,
                'id' => $draft->entity_id,
            ];
        }

        return array_filter($payload, static function ($value) {
            if (is_array($value)) {
                return $value !== [];
            }

            if (is_string($value)) {
                return trim($value) !== '';
            }

            return $value !== null;
        });
    }

    private function policyCheckFailedResponse(PolicyDecision $decision, AiActionDraft $draft): JsonResponse
    {
        $primaryReason = $decision->reasons()[0] ?? 'Policy guardrails blocked this draft.';
        $message = sprintf(
            'Unable to approve %s: %s',
            Str::lower($this->formatActionLabel($decision->actionType())),
            $primaryReason
        );

        return $this->fail($message, Response::HTTP_UNPROCESSABLE_ENTITY, [
            'code' => 'policy_check_blocked',
            'policy' => $decision->toArray(),
            'guided_resolution' => $this->buildPolicyGuidedResolution($decision, $draft),
        ]);
    }

    private function buildPolicyGuidedResolution(PolicyDecision $decision, AiActionDraft $draft): array
    {
        $actionLabel = $this->formatActionLabel($decision->actionType());
        $approvalSummary = $this->describeRequiredApproval($decision);
        $suggestion = $decision->suggestedChanges()[0] ?? null;
        $descriptionParts = array_filter([
            $decision->reasons()[0] ?? null,
            $suggestion,
            $approvalSummary,
        ]);

        $description = $descriptionParts !== []
            ? implode(' ', $descriptionParts)
            : 'Follow the approval checklist before finalizing this draft.';

        return [
            'type' => 'guided_resolution',
            'assistant_message_markdown' => $this->formatPolicyGuidedResolutionMarkdown($actionLabel, $decision, $approvalSummary),
            'guided_resolution' => [
                'title' => sprintf('%s blocked by policy', $actionLabel),
                'description' => $description,
                'cta_label' => 'Review approval steps',
                'cta_url' => null,
                'locale' => 'en',
                'available_locales' => ['en'],
            ],
        ];
    }

    private function describeRequiredApproval(PolicyDecision $decision): ?string
    {
        $approvals = $decision->requiredApprovals();

        if ($approvals === []) {
            return null;
        }

        $labels = array_map(static function (array $approval): string {
            $label = $approval['label'] ?? null;

            if (is_string($label) && $label !== '') {
                return $label;
            }

            return Str::headline($approval['value'] ?? '');
        }, $approvals);

        $labels = array_filter($labels);

        if ($labels === []) {
            return null;
        }

        return sprintf('Approvals needed: %s', implode(', ', $labels));
    }

    private function formatPolicyGuidedResolutionMarkdown(string $actionLabel, PolicyDecision $decision, ?string $approvalSummary = null): string
    {
        $lines = array_map(static fn (string $reason): string => sprintf('- %s', $reason), $decision->reasons());

        foreach ($decision->suggestedChanges() as $suggestedChange) {
            $lines[] = sprintf('- Next: %s', $suggestedChange);
        }

        if ($approvalSummary !== null) {
            $lines[] = sprintf('- %s.', rtrim($approvalSummary, '.'));
        }

        if ($lines === []) {
            $lines[] = '- Policy review required.';
        }

        return sprintf(
            "I couldn't approve the %s draft because:\n%s",
            Str::lower($actionLabel),
            implode("\n", $lines)
        );
    }

    private function resolvePolicyActionType(AiActionDraft $draft): string
    {
        $output = $draft->output_json;
        $outputActionType = is_array($output) ? ($output['action_type'] ?? null) : null;

        if (is_string($outputActionType) && $outputActionType !== '') {
            return $outputActionType;
        }

        return $draft->action_type;
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

    private function resolveThread(?int $threadId, int $companyId): ?AiChatThread
    {
        if ($threadId === null) {
            return null;
        }

        return AiChatThread::query()
            ->forCompany($companyId)
            ->whereKey($threadId)
            ->first();
    }

    private function appendThreadSystemMessage(?AiChatThread $thread, User $user, AiActionDraft $draft, string $status, ?string $reason = null): void
    {
        if (! $thread instanceof AiChatThread) {
            return;
        }

        $message = $this->formatDraftSystemMessage($draft, $status, $reason);

        $payload = array_filter([
            'draft_id' => $draft->id,
            'action_type' => $draft->action_type,
            'status' => $draft->status,
            'reason' => $reason,
            'entity_type' => $draft->entity_type,
            'entity_id' => $draft->entity_id,
        ], static fn ($value) => $value !== null && $value !== '');

        $this->chatService->appendSystemMessage($thread, $user, $message, $payload);
    }

    private function formatDraftSystemMessage(AiActionDraft $draft, string $status, ?string $reason = null): string
    {
        $actionLabel = $this->formatActionLabel($draft->action_type);

        if ($status === AiActionDraft::STATUS_APPROVED) {
            $entityRef = $this->formatEntityReference($draft->entity_type, $draft->entity_id);

            return $entityRef !== null
                ? sprintf('Approved %s draft and created %s.', $actionLabel, $entityRef)
                : sprintf('Approved %s draft.', $actionLabel);
        }

        if ($status === AiActionDraft::STATUS_REJECTED) {
            $reasonText = $reason !== null ? Str::limit($reason, 140) : null;

            return $reasonText === null
                ? sprintf('Rejected %s draft.', $actionLabel)
                : sprintf('Rejected %s draft: %s', $actionLabel, $reasonText);
        }

        return sprintf('%s draft updated.', $actionLabel);
    }

    private function formatActionLabel(string $value): string
    {
        return Str::of($value)
            ->replace(['_', '-'], ' ')
            ->headline()
            ->value();
    }

    private function formatEntityReference(?string $entityType, ?int $entityId): ?string
    {
        if ($entityType === null || $entityId === null) {
            return null;
        }

        $label = Str::of($entityType)
            ->replace(['_', '-'], ' ')
            ->headline()
            ->value();

        return sprintf('%s #%s', $label, $entityId);
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
        ?int $entityId,
        ?AiChatThread $thread = null
    ): void {
        if ($thread instanceof AiChatThread) {
            $requestPayload['thread_id'] = $thread->id;
            $entityType = 'ai_chat_thread';
            $entityId = $thread->id;
        }

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
            AiActionDraft::TYPE_SUPPLIER_ONBOARD_DRAFT => ['suppliers.write'],
            AiActionDraft::TYPE_MAINTENANCE_CHECKLIST => ['inventory.write'],
            AiActionDraft::TYPE_INVENTORY_WHATIF => ['inventory.read', 'inventory.write'],
            AiActionDraft::TYPE_ITEM_DRAFT => ['inventory.write'],
            AiActionDraft::TYPE_INVOICE_DRAFT => ['billing.write'],
            AiActionDraft::TYPE_APPROVE_INVOICE => ['billing.write'],
            AiActionDraft::TYPE_INVOICE_MISMATCH_RESOLUTION => ['billing.write'],
        ];

        $permissions = $permissionMap[$actionType] ?? $this->fallbackPermissionsForAction($actionType);

        return ! $this->permissionRegistry->userHasAny($user, $permissions, $companyId);
    }

    /**
     * @return list<string>
     */
    private function fallbackPermissionsForAction(string $actionType): array
    {
        $normalized = Str::of($actionType)->lower()->value();

        if (str_contains($normalized, 'purchase_order') || preg_match('/\bpo\b/', $normalized) === 1) {
            return ['orders.write'];
        }

        if (str_contains($normalized, 'supplier')) {
            return ['suppliers.write'];
        }

        if (str_contains($normalized, 'inventory') || str_contains($normalized, 'item')) {
            return ['inventory.write'];
        }

        if (str_contains($normalized, 'invoice')) {
            return ['billing.write'];
        }

        if (str_contains($normalized, 'payment')) {
            return ['finance.write'];
        }

        if (str_contains($normalized, 'rfq')) {
            return ['rfqs.write'];
        }

        return ['rfqs.write'];
    }
}
