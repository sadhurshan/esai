<?php

namespace App\Services\Ai\Converters;

use App\Models\AiActionDraft;
use App\Models\InventoryWhatIfSnapshot;
use App\Models\User;
use Illuminate\Contracts\Validation\Factory as ValidationFactory;
use Illuminate\Validation\ValidationException;

class InventoryWhatIfConverter extends AbstractDraftConverter
{
    public function __construct(private readonly ValidationFactory $validator) {}

    /**
     * @return array{entity:InventoryWhatIfSnapshot}
     */
    public function convert(AiActionDraft $draft, User $user): array
    {
        $result = $this->extractOutputAndPayload($draft, AiActionDraft::TYPE_INVENTORY_WHATIF);
        $payload = $result['payload'];
        $output = $result['output'];
        $inputs = $this->inputs($draft);

        $validated = $this->validatePayload($payload);

        $warnings = $this->normalizeWarnings($output['warnings'] ?? null);
        $citations = $output['citations'] ?? $draft->citations_json ?? [];

        $snapshot = InventoryWhatIfSnapshot::query()->create([
            'company_id' => $user->company_id,
            'user_id' => $user->id,
            'scenario_name' => $this->determineScenarioName($inputs, $output),
            'part_identifier' => $this->stringValue($inputs['part_identifier'] ?? $inputs['part_number'] ?? $inputs['sku'] ?? $inputs['part_id'] ?? null),
            'input_snapshot' => $this->buildInputSnapshot($draft, $inputs),
            'result_snapshot' => $payload,
            'projected_stockout_risk' => $validated['projected_stockout_risk'],
            'expected_stockout_days' => $validated['expected_stockout_days'],
            'expected_holding_cost_change' => $validated['expected_holding_cost_change'],
            'recommendation' => $validated['recommendation'],
            'assumptions_json' => $validated['assumptions'],
            'confidence' => $output['confidence'] ?? null,
            'needs_human_review' => $output['needs_human_review'] ?? false,
            'warnings_json' => $warnings,
            'citations_json' => $citations,
            'meta' => $this->buildMeta($inputs, $output),
        ]);

        $draft->forceFill([
            'entity_type' => $snapshot->getMorphClass(),
            'entity_id' => $snapshot->id,
        ])->save();

        return ['entity' => $snapshot];
    }

    /**
     * @param array<string, mixed> $payload
     * @return array{
     *     projected_stockout_risk:float,
     *     expected_stockout_days:float,
     *     expected_holding_cost_change:float,
     *     recommendation:string,
     *     assumptions:array<int, string>
     * }
     */
    private function validatePayload(array $payload): array
    {
        $validator = $this->validator->make(
            [
                'projected_stockout_risk' => $payload['projected_stockout_risk'] ?? null,
                'expected_stockout_days' => $payload['expected_stockout_days'] ?? null,
                'expected_holding_cost_change' => $payload['expected_holding_cost_change'] ?? null,
                'recommendation' => $payload['recommendation'] ?? null,
                'assumptions' => $payload['assumptions'] ?? null,
            ],
            [
                'projected_stockout_risk' => ['required', 'numeric', 'between:0,1'],
                'expected_stockout_days' => ['required', 'numeric', 'min:0'],
                'expected_holding_cost_change' => ['required', 'numeric'],
                'recommendation' => ['required', 'string'],
                'assumptions' => ['required', 'array', 'min:1'],
                'assumptions.*' => ['string', 'max:2000'],
            ]
        );

        if ($validator->fails()) {
            throw new ValidationException($validator);
        }

        $data = $validator->validated();

        return [
            'projected_stockout_risk' => (float) $data['projected_stockout_risk'],
            'expected_stockout_days' => (float) $data['expected_stockout_days'],
            'expected_holding_cost_change' => (float) $data['expected_holding_cost_change'],
            'recommendation' => $data['recommendation'],
            'assumptions' => $this->normalizeList($payload['assumptions']),
        ];
    }

    /**
     * @param array<string, mixed> $inputs
     * @param array<string, mixed> $output
     */
    private function determineScenarioName(array $inputs, array $output): string
    {
        $scenario = $this->stringValue($inputs['scenario_name'] ?? null)
            ?? $this->stringValue($output['summary'] ?? null)
            ?? 'Inventory What-if Scenario';

        return $scenario;
    }

    /**
     * @param array<string, mixed> $inputs
     */
    private function buildInputSnapshot(AiActionDraft $draft, array $inputs): array
    {
        $input = $draft->input_json ?? [];
        $snapshot = [
            'query' => $input['query'] ?? null,
            'filters' => $input['filters'] ?? [],
            'user_context' => $input['user_context'] ?? null,
            'inputs' => $inputs,
        ];

        return array_filter($snapshot, fn ($value) => $value !== null);
    }

    /**
     * @param array<string, mixed> $inputs
     * @param array<string, mixed> $output
     */
    private function buildMeta(array $inputs, array $output): ?array
    {
        $meta = array_filter([
            'inputs' => $inputs,
            'summary' => $output['summary'] ?? null,
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
