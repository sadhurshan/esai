<?php

namespace App\Services\Ai\Converters;

use App\Models\AiActionDraft;
use App\Models\Asset;
use App\Models\MaintenanceProcedure;
use App\Models\MaintenanceTask;
use App\Models\User;
use Illuminate\Contracts\Validation\Factory as ValidationFactory;
use Illuminate\Support\Carbon;
use Illuminate\Validation\ValidationException;
use Throwable;

class MaintenanceChecklistDraftConverter extends AbstractDraftConverter
{
    public function __construct(private readonly ValidationFactory $validator) {}

    /**
     * @return array{entity:MaintenanceTask}
     */
    public function convert(AiActionDraft $draft, User $user): array
    {
        $result = $this->extractOutputAndPayload($draft, AiActionDraft::TYPE_MAINTENANCE_CHECKLIST);
        $payload = $result['payload'];
        $output = $result['output'];
        $inputs = $this->inputs($draft);

        $validated = $this->validatePayload($payload);

        $assetId = $this->resolveAssetId($inputs['asset_id'] ?? null, $user->company_id);
        $procedureId = $this->resolveProcedureId($inputs['maintenance_procedure_id'] ?? null, $user->company_id);
        $dueAt = $this->parseDueAt($inputs['due_at'] ?? null);

        $task = MaintenanceTask::query()->create([
            'company_id' => $user->company_id,
            'asset_id' => $assetId,
            'maintenance_procedure_id' => $procedureId,
            'created_by' => $user->id,
            'title' => $this->determineTitle($inputs),
            'status' => 'draft',
            'summary' => $output['summary'] ?? null,
            'urgency' => $this->stringValue($inputs['urgency'] ?? null),
            'environment' => $this->stringValue($inputs['environment'] ?? null),
            'asset_reference' => $this->stringValue($inputs['asset_reference'] ?? $inputs['asset_tag'] ?? null),
            'safety_notes_json' => $validated['safety_notes'],
            'diagnostic_steps_json' => $validated['diagnostic_steps'],
            'likely_causes_json' => $validated['likely_causes'],
            'recommended_actions_json' => $validated['recommended_actions'],
            'escalation_rules_json' => $validated['when_to_escalate'],
            'citations_json' => $output['citations'] ?? $draft->citations_json ?? [],
            'warnings_json' => $this->normalizeWarnings($output['warnings'] ?? null),
            'meta' => $this->buildMeta($inputs),
            'confidence' => $output['confidence'] ?? null,
            'needs_human_review' => $output['needs_human_review'] ?? false,
            'due_at' => $dueAt,
        ]);

        $draft->forceFill([
            'entity_type' => $task->getMorphClass(),
            'entity_id' => $task->id,
        ])->save();

        return ['entity' => $task];
    }

    /**
     * @param array<string, mixed> $payload
     * @return array{
     *     safety_notes:array<int, string>,
     *     diagnostic_steps:array<int, string>,
     *     likely_causes:array<int, string>,
     *     recommended_actions:array<int, string>,
     *     when_to_escalate:array<int, string>
     * }
     */
    private function validatePayload(array $payload): array
    {
        $validator = $this->validator->make(
            [
                'safety_notes' => $payload['safety_notes'] ?? null,
                'diagnostic_steps' => $payload['diagnostic_steps'] ?? null,
                'likely_causes' => $payload['likely_causes'] ?? null,
                'recommended_actions' => $payload['recommended_actions'] ?? null,
                'when_to_escalate' => $payload['when_to_escalate'] ?? null,
            ],
            [
                'safety_notes' => ['required', 'array', 'min:1'],
                'safety_notes.*' => ['string', 'max:2000'],
                'diagnostic_steps' => ['required', 'array', 'min:1'],
                'diagnostic_steps.*' => ['string', 'max:2000'],
                'likely_causes' => ['required', 'array', 'min:1'],
                'likely_causes.*' => ['string', 'max:2000'],
                'recommended_actions' => ['required', 'array', 'min:1'],
                'recommended_actions.*' => ['string', 'max:2000'],
                'when_to_escalate' => ['required', 'array', 'min:1'],
                'when_to_escalate.*' => ['string', 'max:2000'],
            ]
        );

        if ($validator->fails()) {
            throw new ValidationException($validator);
        }

        return [
            'safety_notes' => $this->normalizeList($payload['safety_notes']),
            'diagnostic_steps' => $this->normalizeList($payload['diagnostic_steps']),
            'likely_causes' => $this->normalizeList($payload['likely_causes']),
            'recommended_actions' => $this->normalizeList($payload['recommended_actions']),
            'when_to_escalate' => $this->normalizeList($payload['when_to_escalate']),
        ];
    }

    private function resolveAssetId(mixed $value, int $companyId): ?int
    {
        if ($value === null) {
            return null;
        }

        if (! is_numeric($value)) {
            throw $this->validationError('asset_id', 'Asset id must be numeric.');
        }

        $assetId = (int) $value;

        $exists = Asset::query()->whereKey($assetId)->where('company_id', $companyId)->exists();

        if (! $exists) {
            throw $this->validationError('asset_id', 'Asset not found for this company.');
        }

        return $assetId;
    }

    private function resolveProcedureId(mixed $value, int $companyId): ?int
    {
        if ($value === null) {
            return null;
        }

        if (! is_numeric($value)) {
            throw $this->validationError('maintenance_procedure_id', 'Procedure id must be numeric.');
        }

        $procedureId = (int) $value;

        $exists = MaintenanceProcedure::query()
            ->whereKey($procedureId)
            ->where('company_id', $companyId)
            ->exists();

        if (! $exists) {
            throw $this->validationError('maintenance_procedure_id', 'Procedure not found for this company.');
        }

        return $procedureId;
    }

    private function parseDueAt(mixed $value): ?string
    {
        $stringValue = $this->stringValue($value);

        if ($stringValue === null) {
            return null;
        }

        try {
            return Carbon::parse($stringValue)->toDateTimeString();
        } catch (Throwable) {
            throw $this->validationError('due_at', 'Unable to parse due date.');
        }
    }

    /**
     * @param array<string, mixed> $inputs
     */
    private function determineTitle(array $inputs): string
    {
        $title = $this->stringValue($inputs['task_title'] ?? null);

        if ($title !== null) {
            return $title;
        }

        $assetName = $this->stringValue($inputs['asset_name'] ?? null) ?? $this->stringValue($inputs['asset_reference'] ?? null);

        return $assetName ? "Maintenance checklist for {$assetName}" : 'Maintenance checklist';
    }

    /**
     * @param array<string, mixed> $inputs
     */
    private function buildMeta(array $inputs): ?array
    {
        $meta = array_filter([
            'inputs' => $inputs,
        ], fn ($value) => $value !== null && $value !== []);

        return $meta === [] ? null : $meta;
    }

    /**
     * @return array<int, string>|null
     */
    private function normalizeWarnings(mixed $value): ?array
    {
        $warnings = $this->normalizeList($value);

        return $warnings === [] ? null : $warnings;
    }
}
