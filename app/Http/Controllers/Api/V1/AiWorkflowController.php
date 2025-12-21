<?php

namespace App\Http\Controllers\Api\V1;

use App\Exceptions\AiWorkflowException;
use App\Http\Controllers\Api\ApiController;
use App\Http\Requests\Api\Ai\AiWorkflowCompleteRequest;
use App\Http\Requests\Api\Ai\AiWorkflowStartRequest;
use App\Http\Resources\AiWorkflowEventResource;
use App\Http\Resources\AiWorkflowResource;
use App\Http\Resources\AiWorkflowStepResource;
use App\Models\AiChatThread;
use App\Models\AiEvent;
use App\Models\AiWorkflow;
use App\Models\AiWorkflowStep;
use App\Models\User;
use App\Services\Ai\ChatService;
use App\Services\Ai\WorkflowService;
use App\Support\Permissions\PermissionRegistry;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\Response;
use App\Exceptions\AiServiceUnavailableException;
use App\Services\Ai\AiEventRecorder;

class AiWorkflowController extends ApiController
{
    public function __construct(
        private readonly WorkflowService $workflowService,
        private readonly PermissionRegistry $permissionRegistry,
        private readonly ChatService $chatService,
    ) {
    }

    public function index(Request $request): JsonResponse
    {
        $context = $this->requireCompanyContext($request);

        if ($context instanceof JsonResponse) {
            return $context;
        }

        $user = $context['user'];
        $companyId = $context['companyId'];

        if ($this->deniesWorkflowAccess($user, $companyId)) {
            return $this->fail('You are not authorized to view AI workflows.', Response::HTTP_FORBIDDEN, [
                'code' => 'workflow_forbidden',
            ]);
        }

        $query = AiWorkflow::query()
            ->forCompany($companyId)
            ->orderByDesc('created_at');

        $statuses = $this->normalizeStatusFilter($request->query('status'));

        if ($statuses !== []) {
            $query->whereIn('status', $statuses);
        }

        $workflowType = $request->query('workflow_type');

        if (is_string($workflowType) && $workflowType !== '') {
            $query->where('workflow_type', $workflowType);
        }

        $paginator = $query->cursorPaginate(
            perPage: $this->perPage($request, 10, 25),
            columns: ['*'],
            cursorName: 'cursor',
            cursor: $request->query('cursor')
        );

        if ($request->query()) {
            $paginator->appends(collect($request->query())->except('cursor')->all());
        }

        ['items' => $items, 'meta' => $meta] = $this->paginate($paginator, $request, AiWorkflowResource::class);

        return $this->ok([
            'items' => $items,
        ], 'Workflows loaded.', $meta);
    }

    public function start(AiWorkflowStartRequest $request): JsonResponse
    {
        $context = $this->requireCompanyContext($request);

        if ($context instanceof JsonResponse) {
            return $context;
        }

        $user = $context['user'];
        $companyId = $context['companyId'];

        if ($this->deniesWorkflowAccess($user, $companyId)) {
            return $this->fail('You are not authorized to run AI workflows.', Response::HTTP_FORBIDDEN, [
                'code' => 'workflow_forbidden',
            ]);
        }

        $threadId = $request->threadId();
        $thread = $this->resolveThread($threadId, $companyId);

        if ($threadId !== null && ! $thread instanceof AiChatThread) {
            return $this->fail('Chat thread not found.', Response::HTTP_NOT_FOUND, [
                'code' => 'chat_thread_not_found',
            ]);
        }

        $payload = $request->startPayload();
        $payload['user_context'] = $this->buildUserContext($user, $payload['user_context'], $companyId);

        try {
            $workflow = $this->workflowService->startWorkflow($companyId, $user, $payload);
        } catch (AiServiceUnavailableException $exception) {
            return $this->fail('AI service is unavailable.', Response::HTTP_SERVICE_UNAVAILABLE, [
                'service' => [$exception->getMessage()],
            ]);
        } catch (AiWorkflowException $exception) {
            return $this->fail($exception->getMessage(), Response::HTTP_BAD_GATEWAY);
        }

        $this->appendThreadSystemMessage($thread, $user, $workflow);

        return $this->ok([
            'workflow_id' => $workflow->workflow_id,
        ], 'Workflow planned.');
    }

    public function next(Request $request, string $workflowId): JsonResponse
    {
        $context = $this->requireCompanyContext($request);

        if ($context instanceof JsonResponse) {
            return $context;
        }

        $user = $context['user'];
        $companyId = $context['companyId'];
        $workflow = $this->findWorkflow($workflowId, $companyId);

        if (! $workflow instanceof AiWorkflow) {
            return $this->fail('Workflow not found.', Response::HTTP_NOT_FOUND);
        }

        if ($this->deniesWorkflowAccess($user, $companyId)) {
            return $this->fail('You are not authorized to view this workflow.', Response::HTTP_FORBIDDEN, [
                'code' => 'workflow_forbidden',
            ]);
        }

        try {
            $step = $this->workflowService->draftNextStep($workflow, $user);
        } catch (AiServiceUnavailableException $exception) {
            return $this->fail('AI service is unavailable.', Response::HTTP_SERVICE_UNAVAILABLE, [
                'service' => [$exception->getMessage()],
            ]);
        } catch (AiWorkflowException $exception) {
            return $this->fail($exception->getMessage(), Response::HTTP_BAD_GATEWAY);
        }

        $workflow->refresh();

        return $this->ok([
            'workflow' => (new AiWorkflowResource($workflow))->toArray($request),
            'step' => $step ? (new AiWorkflowStepResource($step->loadMissing('workflow')))->toArray($request) : null,
        ], $step ? 'Workflow step drafted.' : 'Workflow complete.');
    }

    public function events(Request $request, string $workflowId): JsonResponse
    {
        $context = $this->requireCompanyContext($request);

        if ($context instanceof JsonResponse) {
            return $context;
        }

        $user = $context['user'];
        $companyId = $context['companyId'];

        $workflow = $this->findWorkflow($workflowId, $companyId);

        if (! $workflow instanceof AiWorkflow) {
            return $this->fail('Workflow not found.', Response::HTTP_NOT_FOUND);
        }

        if ($this->deniesWorkflowAccess($user, $companyId)) {
            return $this->fail('You are not authorized to view this workflow.', Response::HTTP_FORBIDDEN, [
                'code' => 'workflow_forbidden',
            ]);
        }

        $query = AiEvent::query()
            ->forCompany($companyId)
            ->whereIn('feature', AiEventRecorder::workflowEventKeys())
            ->where('request_json->workflow->workflow_id', $workflow->workflow_id)
            ->with('user:id,name,email')
            ->orderByDesc('created_at')
            ->orderByDesc('id');

        $paginator = $query->cursorPaginate(
            perPage: $this->perPage($request, 15, 50),
            columns: ['*'],
            cursorName: 'cursor',
            cursor: $request->query('cursor')
        );

        if ($request->query()) {
            $paginator->appends(collect($request->query())->except('cursor')->all());
        }

        ['items' => $items, 'meta' => $meta] = $this->paginate($paginator, $request, AiWorkflowEventResource::class);

        return $this->ok([
            'items' => $items,
        ], 'Workflow events loaded.', $meta);
    }

    public function complete(AiWorkflowCompleteRequest $request, string $workflowId): JsonResponse
    {
        $context = $this->requireCompanyContext($request);

        if ($context instanceof JsonResponse) {
            return $context;
        }

        $user = $context['user'];
        $companyId = $context['companyId'];
        $workflow = $this->findWorkflow($workflowId, $companyId);

        if (! $workflow instanceof AiWorkflow) {
            return $this->fail('Workflow not found.', Response::HTTP_NOT_FOUND);
        }

        $payload = $request->completionPayload();
        $step = $workflow->steps()->where('step_index', $payload['step_index'])->first();

        if (! $step instanceof AiWorkflowStep) {
            return $this->fail('Workflow step not found.', Response::HTTP_NOT_FOUND);
        }

        if (! $step->isPending()) {
            return $this->fail('Workflow step already resolved.', Response::HTTP_UNPROCESSABLE_ENTITY, [
                'code' => 'workflow_step_resolved',
            ]);
        }

        if ($this->deniesStepApproval($user, $companyId, $step)) {
            return $this->fail('You are not authorized to resolve this step.', Response::HTTP_FORBIDDEN, [
                'code' => 'workflow_approval_forbidden',
            ]);
        }

        try {
            $result = $this->workflowService->completeCurrentStep($workflow, $step, $user, $payload);
        } catch (AiServiceUnavailableException $exception) {
            return $this->fail('AI service is unavailable.', Response::HTTP_SERVICE_UNAVAILABLE, [
                'service' => [$exception->getMessage()],
            ]);
        } catch (AiWorkflowException $exception) {
            return $this->fail($exception->getMessage(), Response::HTTP_BAD_GATEWAY);
        }

        $workflowResource = new AiWorkflowResource($result['workflow']);
        $stepResource = new AiWorkflowStepResource($result['step']->loadMissing('workflow'));
        $nextResource = $result['next_step']
            ? (new AiWorkflowStepResource($result['next_step']->loadMissing('workflow')))->toArray($request)
            : null;

        $message = $payload['approval'] ? 'Workflow step approved.' : 'Workflow step rejected.';

        return $this->ok([
            'workflow' => $workflowResource->toArray($request),
            'step' => $stepResource->toArray($request),
            'next_step' => $nextResource,
        ], $message);
    }

    private function findWorkflow(string $workflowId, int $companyId): ?AiWorkflow
    {
        return AiWorkflow::query()
            ->forCompany($companyId)
            ->where('workflow_id', $workflowId)
            ->first();
    }

    private function deniesWorkflowAccess(User $user, int $companyId): bool
    {
        $permissions = config('ai_workflows.start_permissions', []);

        return ! $this->permissionRegistry->userHasAny($user, $permissions, $companyId);
    }

    private function deniesStepApproval(User $user, int $companyId, AiWorkflowStep $step): bool
    {
        $approvalPermissions = config('ai_workflows.approve_permissions', []);
        $stepPermissions = $this->stepPermissions($step->action_type);
        $required = array_values(array_unique(array_merge($approvalPermissions, $stepPermissions)));

        return ! $this->permissionRegistry->userHasAll($user, $required, $companyId);
    }

    /**
     * @return list<string>
     */
    private function stepPermissions(string $actionType): array
    {
        $templates = config('ai_workflows.templates', []);

        if (! is_array($templates)) {
            return [];
        }

        foreach ($templates as $steps) {
            if (! is_array($steps)) {
                continue;
            }

            foreach ($steps as $step) {
                if (($step['action_type'] ?? null) === $actionType) {
                    $permissions = $step['approval_permissions'] ?? [];

                    return is_array($permissions) ? $permissions : [];
                }
            }
        }

        return [];
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

    private function appendThreadSystemMessage(?AiChatThread $thread, User $user, AiWorkflow $workflow): void
    {
        if (! $thread instanceof AiChatThread) {
            return;
        }

        $workflowLabel = $this->formatWorkflowLabel($workflow->workflow_type);
        $message = sprintf('Started %s workflow (%s).', $workflowLabel, $workflow->workflow_id);

        $payload = array_filter([
            'workflow_id' => $workflow->workflow_id,
            'workflow_type' => $workflow->workflow_type,
            'status' => $workflow->status,
            'current_step' => $workflow->current_step,
        ], static fn ($value) => $value !== null && $value !== '');

        $this->chatService->appendSystemMessage($thread, $user, $message, $payload);
    }

    private function formatWorkflowLabel(string $value): string
    {
        return Str::of($value)
            ->replace(['_', '-'], ' ')
            ->headline()
            ->value();
    }

    /**
     * @return list<string>
     */
    private function normalizeStatusFilter(mixed $statuses): array
    {
        if ($statuses === null || $statuses === '') {
            return [];
        }

        $values = is_array($statuses) ? $statuses : explode(',', (string) $statuses);

        $allowed = array_map('strtolower', AiWorkflow::STATUSES);

        return collect($values)
            ->filter(static fn ($value) => is_string($value))
            ->map(static fn ($value) => strtolower(trim($value)))
            ->filter(static fn ($value) => $value !== '' && in_array($value, $allowed, true))
            ->unique()
            ->values()
            ->all();
    }
}
