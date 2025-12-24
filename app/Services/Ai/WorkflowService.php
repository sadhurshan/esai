<?php

namespace App\Services\Ai;

use App\Exceptions\AiWorkflowException;
use App\Models\AiWorkflow;
use App\Models\AiWorkflowStep;
use App\Models\User;
use App\Services\Ai\Workflow\AwardQuoteDraftConverter;
use App\Services\Ai\Workflow\PurchaseOrderDraftConverter;
use App\Services\Ai\Workflow\QuoteComparisonDraftConverter;
use App\Services\Ai\Workflow\PaymentProcessConverter;
use App\Services\Ai\Workflow\ReceivingQualityDraftConverter;
use Carbon\Carbon;
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

    private function refreshWorkflowSnapshot(AiWorkflow $workflow): void
    {
        $steps = $workflow->steps()->orderBy('step_index')->get();

        $snapshot = $steps->map(fn (AiWorkflowStep $step): array => [
            'step_index' => $step->step_index,
            'action_type' => $step->action_type,
            'approval_status' => $step->approval_status,
            'approved_at' => optional($step->approved_at)->toIso8601String(),
            'approved_by' => $step->approved_by,
            'name' => $this->resolveStepName($workflow, $step),
        ])->all();

        $workflow->forceFill([
            'steps_json' => ['steps' => $snapshot],
        ])->save();
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
