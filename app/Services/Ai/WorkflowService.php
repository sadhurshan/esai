<?php

namespace App\Services\Ai;

use App\Enums\RiskGrade;
use App\Exceptions\AiWorkflowException;
use App\Models\AiApprovalRequest;
use App\Models\AiWorkflow;
use App\Models\AiWorkflowStep;
use App\Models\User;
use App\Services\Ai\Workflow\AwardQuoteDraftConverter;
use App\Services\Ai\Workflow\PurchaseOrderDraftConverter;
use App\Services\Ai\Workflow\QuoteComparisonDraftConverter;
use App\Services\Ai\Workflow\PaymentProcessConverter;
use App\Services\Ai\Workflow\ReceivingQualityDraftConverter;
use App\Support\Permissions\RoleTemplateDefinitions;
use Carbon\Carbon;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class WorkflowService
{
    public function __construct(
        private readonly AiClient $client,
        private readonly AiEventRecorder $recorder,
        private readonly QuoteComparisonDraftConverter $quoteConverter,
        private readonly AwardQuoteDraftConverter $awardQuoteConverter,
        private readonly PurchaseOrderDraftConverter $purchaseOrderConverter,
        private readonly ReceivingQualityDraftConverter $receivingQualityConverter,
        private readonly PaymentProcessConverter $paymentProcessConverter,
    ) {
    }

    /**
     * @param array{workflow_type:string,rfq_id:?string,inputs:array<string,mixed>,user_context:array<string,mixed>,goal:?string} $payload
     */
    public function startWorkflow(int $companyId, User $user, array $payload): AiWorkflow
    {
        $response = $this->client->planWorkflow([
            'company_id' => $companyId,
            'workflow_type' => $payload['workflow_type'],
            'rfq_id' => $payload['rfq_id'],
            'inputs' => $payload['inputs'],
            'user_context' => $payload['user_context'],
            'goal' => $payload['goal'],
        ]);

        $data = $response['data'] ?? null;

        if ($response['status'] !== 'success' || ! is_array($data)) {
            $message = $response['message'] ?? 'Failed to plan workflow.';
            $this->recordWorkflowEvent($companyId, $user->id, 'workflow_start', null, $payload, 'error', $message);

            throw new AiWorkflowException($message);
        }

        $workflowId = (string) ($data['workflow_id'] ?? '');

        if ($workflowId === '') {
            $this->recordWorkflowEvent($companyId, $user->id, 'workflow_start', null, $payload, 'error', 'Workflow identifier missing.');

            throw new AiWorkflowException('Workflow identifier missing.');
        }

        $workflow = DB::transaction(function () use ($companyId, $user, $workflowId, $payload, $data): AiWorkflow {
            $steps = $this->buildWorkflowBlueprint($payload['workflow_type'], $payload['inputs'], $payload['rfq_id']);
            $stepSnapshots = ['steps' => $steps];

            $model = AiWorkflow::query()->create([
                'company_id' => $companyId,
                'user_id' => $user->id,
                'workflow_id' => $workflowId,
                'workflow_type' => $payload['workflow_type'],
                'status' => $data['status'] ?? AiWorkflow::STATUS_PENDING,
                'current_step' => 0,
                'steps_json' => $stepSnapshots,
                'last_event_type' => 'workflow_start',
                'last_event_time' => Carbon::now(),
            ]);

            foreach ($steps as $definition) {
                AiWorkflowStep::query()->create([
                    'company_id' => $companyId,
                    'workflow_id' => $workflowId,
                    'step_index' => $definition['step_index'],
                    'action_type' => $definition['action_type'],
                    'input_json' => $definition['required_inputs'],
                    'approval_status' => AiWorkflowStep::APPROVAL_PENDING,
                ]);
            }

            $this->refreshWorkflowSnapshot($model);

            return $model->fresh(['steps']);
        });

        $this->recordWorkflowEvent($companyId, $user->id, 'workflow_start', $workflow, $payload);

        return $workflow;
    }

    public function draftNextStep(AiWorkflow $workflow, User $user): ?AiWorkflowStep
    {
        $response = $this->client->nextWorkflowStep($workflow->workflow_id);
        $data = $response['data'] ?? null;

        if ($response['status'] !== 'success' || ! is_array($data)) {
            $message = $response['message'] ?? 'Failed to fetch next workflow step.';
            $this->recordWorkflowEvent((int) $workflow->company_id, $user->id, 'workflow_step_ready', $workflow, [], 'error', $message);

            throw new AiWorkflowException($message);
        }

        $stepPayload = $data['step'] ?? null;

        $stepModel = DB::transaction(function () use ($workflow, $data, $stepPayload): ?AiWorkflowStep {
            $workflow->forceFill([
                'status' => $data['workflow_status'] ?? $workflow->status,
                'current_step' => isset($stepPayload['step_index']) ? (int) $stepPayload['step_index'] : null,
                'last_event_type' => $stepPayload === null ? 'workflow_idle' : 'workflow_step_ready',
                'last_event_time' => Carbon::now(),
            ])->save();

            $stepModel = null;

            if (is_array($stepPayload)) {
                $stepModel = $this->upsertStepFromPayload($workflow, $stepPayload, false);
            }

            $this->refreshWorkflowSnapshot($workflow);

            return $stepModel?->fresh();
        });

        $latestWorkflow = $workflow->fresh();

        if ($stepModel !== null) {
            $this->recordWorkflowEvent((int) $workflow->company_id, $user->id, 'workflow_step_ready', $latestWorkflow, [], 'success', null, $stepModel);
        } elseif ($latestWorkflow->status === AiWorkflow::STATUS_COMPLETED) {
            $this->recordWorkflowEvent((int) $workflow->company_id, $user->id, 'workflow_completed', $latestWorkflow);
        }

        return $stepModel;
    }

    /**
     * @param array{output:array<string,mixed>,approval:bool,notes?:?string} $payload
     * @return array{workflow:AiWorkflow,step:AiWorkflowStep,next_step:?AiWorkflowStep}
     */
    public function completeCurrentStep(
        AiWorkflow $workflow,
        AiWorkflowStep $step,
        User $user,
        array $payload
    ): array {
        if (! $step->isPending()) {
            throw new AiWorkflowException('Workflow step already resolved.');
        }

        $requestPayload = [
            'output' => $payload['output'] ?? [],
            'approval' => (bool) $payload['approval'],
            'approved_by' => (string) $user->id,
        ];

        $response = $this->client->completeWorkflowStep($workflow->workflow_id, $requestPayload);
        $data = $response['data'] ?? null;

        if ($response['status'] !== 'success' || ! is_array($data)) {
            $message = $response['message'] ?? 'Failed to finalize workflow step.';
            $this->recordWorkflowEvent((int) $workflow->company_id, $user->id, 'workflow_step_complete', $workflow, $requestPayload, 'error', $message, $step);

            throw new AiWorkflowException($message);
        }

        return DB::transaction(function () use ($workflow, $step, $user, $payload, $data): array {
            $approvalEvent = $payload['approval'] ? 'workflow_step_approved' : 'workflow_step_rejected';

            $step->forceFill([
                'draft_json' => $step->draft_json,
                'output_json' => $payload['output'] ?? [],
                'approval_status' => $payload['approval'] ? AiWorkflowStep::APPROVAL_APPROVED : AiWorkflowStep::APPROVAL_REJECTED,
                'approved_by' => $user->id,
                'approved_at' => Carbon::now(),
            ])->save();

            $nextPayload = $data['next_step'] ?? null;
            $nextStep = null;

            if (is_array($nextPayload)) {
                $nextStep = $this->upsertStepFromPayload($workflow, $nextPayload, true);
            }

            $workflow->forceFill([
                'status' => $data['workflow_status'] ?? $workflow->status,
                'current_step' => isset($nextPayload['step_index']) ? (int) $nextPayload['step_index'] : null,
                'last_event_type' => $approvalEvent,
                'last_event_time' => Carbon::now(),
            ])->save();

            $this->refreshWorkflowSnapshot($workflow);

            if ($payload['approval']) {
                $this->runConverter($step);
            }

            $this->recordWorkflowEvent((int) $workflow->company_id, $user->id, $approvalEvent, $workflow->fresh(), $payload, 'success', null, $step);

            if ($workflow->status === AiWorkflow::STATUS_COMPLETED) {
                $this->recordWorkflowEvent((int) $workflow->company_id, $user->id, 'workflow_completed', $workflow->fresh());
            }

            if ($workflow->status === AiWorkflow::STATUS_REJECTED && ! $payload['approval']) {
                $this->recordWorkflowEvent((int) $workflow->company_id, $user->id, 'workflow_aborted', $workflow->fresh(), ['reason' => 'Step rejected']);
            }

            return [
                'workflow' => $workflow->fresh(),
                'step' => $step->fresh(),
                'next_step' => $nextStep?->fresh(),
            ];
        });
    }

    /**
     * @return list<array{step_index:int,action_type:string,name:string,required_inputs:array<string,mixed>}> 
     */
    private function buildWorkflowBlueprint(string $workflowType, array $inputs, ?string $rfqId): array
    {
        $templates = config('ai_workflows.templates.' . $workflowType, []);

        if ($templates === []) {
            throw new AiWorkflowException('Unsupported workflow type: ' . $workflowType);
        }

        $steps = [];

        foreach ($templates as $index => $template) {
            $actionType = (string) ($template['action_type'] ?? '');

            if ($actionType === '') {
                continue;
            }

            $requiredInputs = $this->buildStepInputs($actionType, $inputs, $rfqId);

            $steps[] = [
                'step_index' => $index,
                'action_type' => $actionType,
                'name' => $template['name'] ?? Str::title(str_replace('_', ' ', $actionType)),
                'required_inputs' => $requiredInputs,
            ];
        }

        return $steps;
    }

    /**
     * @return array<string, mixed>
     */
    private function buildStepInputs(string $actionType, array $inputs, ?string $rfqId): array
    {
        if ($actionType === 'rfq_draft') {
            $source = $inputs['rfq'] ?? $inputs['rfq_inputs'] ?? [];
            $payload = is_array($source) ? $source : [];
        } elseif ($actionType === 'compare_quotes') {
            $quotes = $inputs['quotes'] ?? [];
            $risk = $inputs['supplier_risk_scores'] ?? ($inputs['risk_scores'] ?? []);
            $payload = [
                'rfq_id' => $rfqId,
                'quotes' => is_array($quotes) ? $quotes : [],
                'supplier_risk_scores' => is_array($risk) ? $risk : [],
            ];
        } elseif ($actionType === 'po_draft') {
            $source = $inputs['po'] ?? $inputs['po_draft'] ?? [];
            $payload = is_array($source) ? $source : [];
        } else {
            $payload = $inputs;
        }

        if ($rfqId !== null && $rfqId !== '') {
            $payload['rfq_id'] = $payload['rfq_id'] ?? $rfqId;
        }

        return $payload;
    }

    private function upsertStepFromPayload(AiWorkflow $workflow, array $payload, bool $resetDraft): AiWorkflowStep
    {
        $stepIndex = (int) ($payload['step_index'] ?? 0);

        $model = $workflow->steps()
            ->where('step_index', $stepIndex)
            ->first();

        if (! $model instanceof AiWorkflowStep) {
            $model = AiWorkflowStep::query()->create([
                'company_id' => $workflow->company_id,
                'workflow_id' => $workflow->workflow_id,
                'step_index' => $stepIndex,
                'action_type' => (string) ($payload['action_type'] ?? ''),
                'input_json' => [],
                'approval_status' => AiWorkflowStep::APPROVAL_PENDING,
            ]);
        }

        $model->forceFill([
            'action_type' => (string) ($payload['action_type'] ?? $model->action_type),
            'input_json' => $payload['required_inputs'] ?? $model->input_json,
            'draft_json' => $resetDraft ? null : ($payload['draft_output'] ?? $model->draft_json),
            'output_json' => $payload['output'] ?? $model->output_json,
        ])->save();

        return $model;
    }

    public function refreshWorkflowSnapshot(AiWorkflow $workflow): void
    {
        $steps = $workflow->steps()->orderBy('step_index')->get();

        $pendingRequests = AiApprovalRequest::query()
            ->where('workflow_id', $workflow->workflow_id)
            ->where('status', AiApprovalRequest::STATUS_PENDING)
            ->pluck('step_index')
            ->filter(static fn ($index) => $index !== null)
            ->mapWithKeys(static fn ($index) => [(int) $index => true])
            ->all();

        $snapshot = $steps->map(fn (AiWorkflowStep $step): array => [
            'step_index' => $step->step_index,
            'action_type' => $step->action_type,
            'approval_status' => $step->approval_status,
            'approved_at' => optional($step->approved_at)->toIso8601String(),
            'approved_by' => $step->approved_by,
            'name' => $this->resolveStepName($workflow, $step),
            'approval_requirements' => $this->resolveRequiredApprovals($step),
            'has_pending_approval_request' => $pendingRequests[$step->step_index] ?? false,
        ])->all();

        $workflow->forceFill([
            'steps_json' => ['steps' => $snapshot],
        ])->save();
    }

    /**
     * @param AiWorkflowStep|array<string, mixed> $step
     * @return array{permissions: list<string>, roles: list<array{slug: string, name: string}>}
     */
    public function resolveRequiredApprovals(AiWorkflowStep|array $step): array
    {
        $actionType = $step instanceof AiWorkflowStep
            ? $step->action_type
            : (string) ($step['action_type'] ?? '');

        if ($actionType === '') {
            return [
                'permissions' => [],
                'roles' => [],
            ];
        }

        $globalPermissions = $this->sanitizePermissionList(config('ai_workflows.approve_permissions', []));
        $basePermissions = $this->stepPermissions($actionType);
        $permissions = array_values(array_unique(array_merge($globalPermissions, $basePermissions)));

        $payload = $this->extractStepPayload($step);
        $permissions = $this->applyDynamicApprovalRules($actionType, $permissions, $payload);

        return [
            'permissions' => $permissions,
            'roles' => $this->resolveRolesForPermissions($permissions),
        ];
    }

    private function resolveStepName(AiWorkflow $workflow, AiWorkflowStep $step): string
    {
        $existing = $workflow->steps_json;

        if (is_array($existing)) {
            $items = $existing['steps'] ?? $existing;

            if (is_array($items)) {
                foreach ($items as $entry) {
                    if ((int) ($entry['step_index'] ?? -1) === $step->step_index) {
                        $name = $entry['name'] ?? null;

                        if (is_string($name) && $name !== '') {
                            return $name;
                        }
                    }
                }
            }
        }

        $templates = config('ai_workflows.templates.' . $workflow->workflow_type, []);

        if (is_array($templates)) {
            foreach ($templates as $template) {
                if (($template['action_type'] ?? null) === $step->action_type) {
                    $name = $template['name'] ?? null;

                    if (is_string($name) && $name !== '') {
                        return $name;
                    }
                }
            }
        }

        return Str::title(str_replace('_', ' ', $step->action_type));
    }

    private function runConverter(AiWorkflowStep $step): void
    {
        if ($step->action_type === 'compare_quotes') {
            $this->quoteConverter->convert($step);
        }

        if ($step->action_type === 'award_quote') {
            $this->awardQuoteConverter->convert($step);
        }

        if ($step->action_type === 'po_draft') {
            $this->purchaseOrderConverter->convert($step);
        }

        if ($step->action_type === 'receiving_quality') {
            $this->receivingQualityConverter->convert($step);
        }

        if ($step->action_type === 'payment_process') {
            $this->paymentProcessConverter->convert($step);
        }
    }

    /**
     * @param AiWorkflowStep|array<string, mixed> $step
     * @return array<string, mixed>
     */
    private function extractStepPayload(AiWorkflowStep|array $step): array
    {
        if ($step instanceof AiWorkflowStep) {
            $inputs = is_array($step->input_json) ? $step->input_json : [];
            $draft = is_array($step->draft_json) ? $step->draft_json : [];
            $output = is_array($step->output_json) ? $step->output_json : [];
        } else {
            $inputs = is_array($step['input_json'] ?? null)
                ? $step['input_json']
                : (is_array($step['required_inputs'] ?? null) ? $step['required_inputs'] : []);
            $draft = is_array($step['draft_json'] ?? null)
                ? $step['draft_json']
                : (is_array($step['draft'] ?? null) ? $step['draft'] : (is_array($step['draft_output'] ?? null) ? $step['draft_output'] : []));
            $output = is_array($step['output_json'] ?? null)
                ? $step['output_json']
                : (is_array($step['output'] ?? null) ? $step['output'] : []);
        }

        $payload = is_array($draft['payload'] ?? null) ? $draft['payload'] : (is_array($draft) ? $draft : []);
        $outputPayload = is_array($output['payload'] ?? null) ? $output['payload'] : (is_array($output) ? $output : []);

        $context = is_array($inputs) ? $inputs : [];

        if ($payload !== []) {
            $context = array_merge($context, $payload);
        }

        if ($outputPayload !== []) {
            $context = array_merge($context, $outputPayload);
        }

        return $context;
    }

    /**
     * @param list<string> $permissions
     * @param array<string, mixed> $payload
     * @return list<string>
     */
    private function applyDynamicApprovalRules(string $actionType, array $permissions, array $payload): array
    {
        $normalized = Str::of($actionType)->lower()->value();

        if (str_contains($normalized, 'po') && $this->requiresFinanceEscalation($payload)) {
            $permissions[] = 'finance.write';
        }

        if ($actionType === 'award_quote' && $this->requiresSupplierEscalation($payload)) {
            $permissions[] = 'suppliers.write';
        }

        return $this->sanitizePermissionList($permissions);
    }

    private function requiresFinanceEscalation(array $payload): bool
    {
        $threshold = (float) config('policy.thresholds.purchase_order_high_value', 50000);

        if ($threshold <= 0) {
            return false;
        }

        $amount = $this->extractNumeric($payload, [
            'totals.grand_total',
            'totals.total',
            'summary.total_value',
            'summary.total',
            'total_value',
            'grand_total',
            'total',
        ]);

        return $amount !== null && $amount >= $threshold;
    }

    private function requiresSupplierEscalation(array $payload): bool
    {
        $riskGrade = strtolower((string) (
            Arr::get($payload, 'supplier.risk_grade')
                ?? Arr::get($payload, 'supplier.profile.risk_grade')
                ?? Arr::get($payload, 'risk_grade')
                ?? ''
        ));

        $maxGrade = strtolower((string) config('policy.supplier.max_risk_grade', RiskGrade::Medium->value));
        $gradeOrder = ['low' => 1, 'medium' => 2, 'high' => 3];

        if ($riskGrade !== '' && isset($gradeOrder[$riskGrade], $gradeOrder[$maxGrade]) && $gradeOrder[$riskGrade] > $gradeOrder[$maxGrade]) {
            return true;
        }

        $riskScore = $this->normalizeRiskScore(
            Arr::get($payload, 'supplier.risk_score')
                ?? Arr::get($payload, 'supplier.risk.overall_score')
                ?? Arr::get($payload, 'risk_score')
                ?? null
        );

        if ($riskScore === null) {
            return false;
        }

        $maxRiskIndex = (float) config('policy.supplier.max_risk_index', 0.25);

        return (1 - $riskScore) > $maxRiskIndex;
    }

    private function extractNumeric(array $payload, array $paths): ?float
    {
        foreach ($paths as $path) {
            $value = Arr::get($payload, $path);
            $numeric = $this->toFloat($value);

            if ($numeric !== null) {
                return $numeric;
            }
        }

        return null;
    }

    private function toFloat(mixed $value): ?float
    {
        if (is_int($value) || is_float($value)) {
            return (float) $value;
        }

        if (is_string($value)) {
            $clean = preg_replace('/[^0-9.\-]/', '', $value);

            if ($clean === '' || ! is_numeric($clean)) {
                return null;
            }

            return (float) $clean;
        }

        if (is_array($value)) {
            if (isset($value['amount']) && is_numeric($value['amount'])) {
                return (float) $value['amount'];
            }

            if (isset($value['value']) && is_numeric($value['value'])) {
                return (float) $value['value'];
            }
        }

        return null;
    }

    private function normalizeRiskScore(mixed $value): ?float
    {
        $numeric = $this->toFloat($value);

        if ($numeric === null) {
            return null;
        }

        if ($numeric > 1) {
            $numeric = $numeric / 100;
        }

        if ($numeric < 0 || $numeric > 1) {
            return null;
        }

        return $numeric;
    }

    /**
     * @param list<string> $permissions
     * @return list<array{slug: string, name: string}>
     */
    private function resolveRolesForPermissions(array $permissions): array
    {
        if ($permissions === []) {
            return [];
        }

        $roles = [];

        foreach (RoleTemplateDefinitions::all() as $role) {
            $slug = $role['slug'] ?? null;
            $name = $role['name'] ?? null;
            $rolePermissions = $role['permissions'] ?? [];

            if (! is_string($slug) || $slug === '' || ! is_string($name) || $name === '' || ! is_array($rolePermissions) || $rolePermissions === []) {
                continue;
            }

            if (array_diff($permissions, $rolePermissions) !== []) {
                continue;
            }

            $roles[] = [
                'slug' => $slug,
                'name' => $name,
            ];
        }

        return $roles;
    }

    /**
     * @param mixed $permissions
     * @return list<string>
     */
    private function sanitizePermissionList(mixed $permissions): array
    {
        if (! is_array($permissions)) {
            return [];
        }

        $list = [];

        foreach ($permissions as $permission) {
            if (! is_string($permission)) {
                continue;
            }

            $permission = trim($permission);

            if ($permission === '') {
                continue;
            }

            $list[] = $permission;
        }

        return array_values(array_unique($list));
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
                    return $this->sanitizePermissionList($step['approval_permissions'] ?? []);
                }
            }
        }

        return [];
    }

    /**
     * @param array<string, mixed>|null $payload
     */
    private function recordWorkflowEvent(
        int $companyId,
        ?int $userId,
        string $event,
        ?AiWorkflow $workflow = null,
        ?array $payload = null,
        string $status = 'success',
        ?string $error = null,
        ?AiWorkflowStep $step = null
    ): void {
        $context = $workflow ? [
            'workflow_id' => $workflow->workflow_id,
            'workflow_type' => $workflow->workflow_type,
            'status' => $workflow->status,
            'current_step' => $workflow->current_step,
        ] : [];

        if ($step !== null) {
            $context['step_index'] = $step->step_index;
            $context['action_type'] = $step->action_type;
        }

        $payloadData = $payload ?? [];

        $method = match ($event) {
            AiEventRecorder::EVENT_WORKFLOW_START => 'workflowStart',
            AiEventRecorder::EVENT_WORKFLOW_STEP_APPROVED => 'workflowStepApproved',
            AiEventRecorder::EVENT_WORKFLOW_STEP_REJECTED => 'workflowStepRejected',
            AiEventRecorder::EVENT_WORKFLOW_COMPLETED => 'workflowCompleted',
            AiEventRecorder::EVENT_WORKFLOW_ABORTED => 'workflowAborted',
            AiEventRecorder::EVENT_WORKFLOW_STEP_READY => 'workflowStepReady',
            AiEventRecorder::EVENT_WORKFLOW_STEP_COMPLETE => 'workflowStepComplete',
            default => null,
        };

        if ($method !== null && method_exists($this->recorder, $method)) {
            $this->recorder->{$method}(
                companyId: $companyId,
                userId: $userId,
                workflowContext: $context,
                payload: $payloadData,
                status: $status,
                errorMessage: $error
            );

            return;
        }

        $this->recorder->workflowEvent(
            event: $event,
            companyId: $companyId,
            userId: $userId,
            workflowContext: $context,
            payload: $payloadData,
            status: $status,
            errorMessage: $error
        );
    }
}
