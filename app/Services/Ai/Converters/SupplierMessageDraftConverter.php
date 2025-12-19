<?php

namespace App\Services\Ai\Converters;

use App\Models\AiActionDraft;
use App\Models\Supplier;
use App\Models\SupplierMessageDraft;
use App\Models\User;
use Illuminate\Contracts\Validation\Factory as ValidationFactory;
use Illuminate\Validation\ValidationException;

class SupplierMessageDraftConverter extends AbstractDraftConverter
{
    public function __construct(private readonly ValidationFactory $validator) {}

    /**
     * @return array{entity:SupplierMessageDraft}
     */
    public function convert(AiActionDraft $draft, User $user): array
    {
        $result = $this->extractOutputAndPayload($draft, AiActionDraft::TYPE_SUPPLIER_MESSAGE);
        $payload = $result['payload'];
        $output = $result['output'];
        $inputs = $this->inputs($draft);

        $validated = $this->validatePayload($payload);

        $supplierId = $this->resolveSupplierId($inputs['supplier_id'] ?? null, $user->company_id);
        $citations = $output['citations'] ?? $draft->citations_json ?? [];
        $warnings = $this->normalizeWarnings($output['warnings'] ?? null);
        $meta = $this->buildMeta($inputs, $validated);

        $message = SupplierMessageDraft::query()->create([
            'company_id' => $user->company_id,
            'supplier_id' => $supplierId,
            'created_by' => $user->id,
            'supplier_name' => $inputs['supplier_name'] ?? $validated['supplier_name'],
            'goal' => $this->stringValue($inputs['goal'] ?? $validated['goal']),
            'tone' => $this->stringValue($inputs['tone'] ?? $validated['tone']),
            'subject' => $validated['subject'],
            'message_body' => $validated['message_body'],
            'negotiation_points_json' => $validated['negotiation_points'],
            'fallback_options_json' => $validated['fallback_options'],
            'summary' => $output['summary'] ?? null,
            'confidence' => $output['confidence'] ?? null,
            'needs_human_review' => $output['needs_human_review'] ?? false,
            'warnings_json' => $warnings,
            'citations_json' => $citations,
            'meta' => $meta,
        ]);

        $draft->forceFill([
            'entity_type' => $message->getMorphClass(),
            'entity_id' => $message->id,
        ])->save();

        return ['entity' => $message];
    }

    /**
     * @param array<string, mixed> $payload
     * @return array{
     *     subject:string,
     *     message_body:string,
     *     negotiation_points:array<int, string>,
     *     fallback_options:array<int, string>,
     *     goal:?string,
     *     tone:?string,
     *     supplier_name:?string
     * }
     */
    private function validatePayload(array $payload): array
    {
        $validator = $this->validator->make(
            [
                'subject' => $payload['subject'] ?? null,
                'message_body' => $payload['message_body'] ?? null,
                'negotiation_points' => $payload['negotiation_points'] ?? null,
                'fallback_options' => $payload['fallback_options'] ?? null,
                'goal' => $payload['goal'] ?? null,
                'tone' => $payload['tone'] ?? null,
                'supplier_name' => $payload['supplier_name'] ?? null,
            ],
            [
                'subject' => ['required', 'string', 'max:200'],
                'message_body' => ['required', 'string'],
                'negotiation_points' => ['required', 'array', 'min:1'],
                'negotiation_points.*' => ['string', 'max:2000'],
                'fallback_options' => ['required', 'array', 'min:1'],
                'fallback_options.*' => ['string', 'max:2000'],
                'goal' => ['nullable', 'string', 'max:200'],
                'tone' => ['nullable', 'string', 'max:120'],
                'supplier_name' => ['nullable', 'string', 'max:200'],
            ]
        );

        if ($validator->fails()) {
            throw new ValidationException($validator);
        }

        $data = $validator->validated();

        return [
            'subject' => $data['subject'],
            'message_body' => $data['message_body'],
            'negotiation_points' => $this->normalizeList($payload['negotiation_points']),
            'fallback_options' => $this->normalizeList($payload['fallback_options']),
            'goal' => $this->stringValue($data['goal'] ?? null),
            'tone' => $this->stringValue($data['tone'] ?? null),
            'supplier_name' => $this->stringValue($data['supplier_name'] ?? null),
        ];
    }

    private function resolveSupplierId(mixed $value, int $companyId): ?int
    {
        if ($value === null) {
            return null;
        }

        if (! is_numeric($value)) {
            throw $this->validationError('supplier_id', 'Supplier id must be numeric.');
        }

        $supplierId = (int) $value;

        $exists = Supplier::query()
            ->whereKey($supplierId)
            ->where('company_id', $companyId)
            ->exists();

        if (! $exists) {
            throw $this->validationError('supplier_id', 'Supplier not found for this company.');
        }

        return $supplierId;
    }

    /**
     * @param array<string, mixed> $inputs
     * @param array<string, mixed> $payload
     */
    private function buildMeta(array $inputs, array $payload): ?array
    {
        $payloadMeta = array_filter([
            'goal' => $payload['goal'] ?? null,
            'tone' => $payload['tone'] ?? null,
        ], fn ($value) => $value !== null && $value !== '');

        $meta = array_filter([
            'inputs' => $inputs,
            'payload' => $payloadMeta,
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
