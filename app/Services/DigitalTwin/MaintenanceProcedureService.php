<?php

namespace App\Services\DigitalTwin;

use App\Models\MaintenanceProcedure;
use App\Models\ProcedureStep;
use App\Models\User;
use App\Support\Audit\AuditLogger;
use Illuminate\Database\DatabaseManager;
use Illuminate\Support\Arr;

class MaintenanceProcedureService
{
    public function __construct(
        private readonly DatabaseManager $database,
        private readonly AuditLogger $auditLogger,
    ) {
    }

    /**
     * @param array<string, mixed> $payload
     */
    public function create(User $user, array $payload): MaintenanceProcedure
    {
        $attributes = $this->extractAttributes($payload, (int) $user->company_id, false);
        $steps = $this->normalizeSteps($payload['steps'] ?? [], false);

        /** @var MaintenanceProcedure $procedure */
        $procedure = $this->database->transaction(function () use ($attributes, $steps): MaintenanceProcedure {
            $procedure = MaintenanceProcedure::create($attributes);
            $this->auditLogger->created($procedure, Arr::only($procedure->getAttributes(), array_keys($attributes)));

            foreach ($steps as $stepPayload) {
                $step = $procedure->steps()->create($this->stepAttributes($stepPayload));
                $this->auditLogger->created($step, Arr::only($step->getAttributes(), ['step_no', 'title']));
            }

            return $procedure->load('steps');
        });

        return $procedure;
    }

    /**
     * @param array<string, mixed> $payload
     */
    public function update(MaintenanceProcedure $procedure, array $payload): MaintenanceProcedure
    {
        $attributes = $this->extractAttributes($payload, (int) $procedure->company_id, true);
        $steps = array_key_exists('steps', $payload)
            ? $this->normalizeSteps($payload['steps'], true)
            : null;

        $this->database->transaction(function () use ($procedure, $attributes, $steps): void {
            if ($attributes !== []) {
                $before = Arr::only($procedure->getOriginal(), array_keys($attributes));
                $procedure->fill($attributes);
                $procedure->save();
                $this->auditLogger->updated($procedure, $before, Arr::only($procedure->getAttributes(), array_keys($attributes)));
            }

            if ($steps !== null) {
                $this->syncSteps($procedure, $steps);
            }
        });

        return $procedure->refresh()->load('steps');
    }

    /**
     * @param array<string, mixed> $payload
     * @return array<string, mixed>
     */
    private function extractAttributes(array $payload, int $companyId, bool $isUpdate): array
    {
        $fields = ['code', 'title', 'category', 'estimated_minutes', 'instructions_md'];
        $attributes = Arr::only($payload, $fields);

        if (! $isUpdate) {
            $attributes['company_id'] = $companyId;
        }

        if (array_key_exists('tools', $payload)) {
            $attributes['tools_json'] = $this->normalizeStringArray($payload['tools']);
        }

        if (array_key_exists('safety', $payload)) {
            $attributes['safety_json'] = $this->normalizeStringArray($payload['safety']);
        }

        if (array_key_exists('meta', $payload)) {
            $attributes['meta'] = is_array($payload['meta']) ? $payload['meta'] : [];
        }

        return $attributes;
    }

    /**
     * @param array<int, mixed> $values
     * @return array<int, string>
     */
    private function normalizeStringArray(mixed $values): array
    {
        if (! is_array($values)) {
            return [];
        }

    return array_values(array_filter(array_map(static fn ($value): string => (string) $value, $values), static fn (string $value): bool => $value !== ''));
    }

    /**
     * @param array<int, array<string, mixed>> $steps
     * @return list<array{id:int|null, attributes:array<string, mixed>, estimated_minutes_provided:bool, estimated_minutes:int|null, attachments_provided:bool, attachments:array<int, string>|null}>
     */
    private function normalizeSteps(array $steps, bool $isUpdate): array
    {
        $normalized = [];

        foreach ($steps as $step) {
            if (! is_array($step)) {
                continue;
            }

            $id = isset($step['id']) ? (int) $step['id'] : null;
            $isNew = $id === null;
            $attachmentsProvided = array_key_exists('attachments', $step) || ! $isUpdate || $isNew;
            $estimatedProvided = array_key_exists('estimated_minutes', $step) || ! $isUpdate || $isNew;
            $estimatedValue = $step['estimated_minutes'] ?? null;

            $normalized[] = [
                'id' => $id,
                'attributes' => [
                    'step_no' => (int) $step['step_no'],
                    'title' => (string) $step['title'],
                    'instruction_md' => (string) $step['instruction_md'],
                ],
                'estimated_minutes_provided' => $estimatedProvided,
                'estimated_minutes' => $estimatedProvided
                    ? ($estimatedValue !== null ? (int) $estimatedValue : null)
                    : null,
                'attachments_provided' => $attachmentsProvided,
                'attachments' => $attachmentsProvided
                    ? $this->normalizeStringArray($step['attachments'] ?? [])
                    : null,
            ];
        }

        return $normalized;
    }

    /**
     * @param array{id:int|null, attributes:array<string, mixed>, estimated_minutes_provided:bool, estimated_minutes:int|null, attachments_provided:bool, attachments:array<int, string>|null} $step
     * @return array<string, mixed>
     */
    private function stepAttributes(array $step): array
    {
        $attributes = $step['attributes'];

        if ($step['estimated_minutes_provided']) {
            $attributes['estimated_minutes'] = $step['estimated_minutes'];
        }

        if ($step['attachments_provided']) {
            $attributes['attachments_json'] = $step['attachments'] ?? [];
        }

        return $attributes;
    }

    /**
     * @param list<array{id:int|null, attributes:array<string, mixed>, estimated_minutes_provided:bool, estimated_minutes:int|null, attachments_provided:bool, attachments:array<int, string>|null}> $steps
     */
    private function syncSteps(MaintenanceProcedure $procedure, array $steps): void
    {
        /** @var Collection<int, ProcedureStep> $existing */
        $existing = $procedure->steps()->get()->keyBy('id');
        $kept = [];

        foreach ($steps as $step) {
            $attributes = $this->stepAttributes($step);
            $stepId = $step['id'];

            if ($stepId !== null && $existing->has($stepId)) {
                /** @var ProcedureStep $model */
                $model = $existing->get($stepId);
                $before = Arr::only($model->getAttributes(), ['step_no', 'title', 'instruction_md', 'estimated_minutes', 'attachments_json']);

                $model->fill($attributes);
                if ($model->isDirty()) {
                    $model->save();
                    $this->auditLogger->updated($model, $before, Arr::only($model->getAttributes(), array_keys($attributes)));
                }

                $kept[] = $model->id;

                continue;
            }

            $created = $procedure->steps()->create($attributes);
            $this->auditLogger->created($created, Arr::only($created->getAttributes(), ['step_no', 'title']));
            $kept[] = $created->id;
        }

        foreach ($existing as $id => $model) {
            if (! in_array($id, $kept, true)) {
                $before = $model->getAttributes();
                $model->delete();
                $this->auditLogger->deleted($model, $before);
            }
        }
    }
}
