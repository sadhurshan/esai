<?php

namespace App\Services\Ai\Converters;

use App\Actions\Rfq\CreateRfqAction;
use App\Models\AiActionDraft;
use App\Models\RFQ;
use App\Models\User;
use Illuminate\Contracts\Validation\Factory as ValidationFactory;
use Illuminate\Validation\ValidationException;

class RfqDraftConverter extends AbstractDraftConverter
{
    public function __construct(
        private readonly CreateRfqAction $createRfqAction,
        private readonly ValidationFactory $validator,
    ) {}

    /**
     * @return array{entity:RFQ}
     */
    public function convert(AiActionDraft $draft, User $user): array
    {
        $result = $this->extractOutputAndPayload($draft, AiActionDraft::TYPE_RFQ_DRAFT);
        $output = $result['output'];
        $payload = $result['payload'];

        $validated = $this->validatePayload($payload, $output);

        $rfqData = $this->buildRfqPayload($validated, $this->inputs($draft));

        $rfq = $this->createRfqAction->execute($user, $rfqData);
        $this->applyCopilotMetadata($rfq, $validated, $output, $draft->citations_json ?? []);

        $draft->forceFill([
            'entity_type' => $rfq->getMorphClass(),
            'entity_id' => $rfq->id,
        ])->save();

        return ['entity' => $rfq];
    }

    /**
     * @param array<string, mixed> $payload
     * @param array<string, mixed> $output
     * @return array{
     *     rfq_title:string,
     *     scope_summary:string,
     *     line_items:array<int, array<string, mixed>>,
     *     terms:array<int, string>,
     *     questions:array<int, string>,
     *     rubric:array<int, array<string, mixed>>
     * }
     */
    private function validatePayload(array $payload, array $output): array
    {
        $validator = $this->validator->make(
            [
                'rfq_title' => $payload['rfq_title'] ?? null,
                'scope_summary' => $payload['scope_summary'] ?? null,
                'line_items' => $payload['line_items'] ?? null,
                'terms_and_conditions' => $payload['terms_and_conditions'] ?? null,
                'questions_for_suppliers' => $payload['questions_for_suppliers'] ?? null,
                'evaluation_rubric' => $payload['evaluation_rubric'] ?? null,
            ],
            [
                'rfq_title' => ['required', 'string', 'max:200'],
                'scope_summary' => ['required', 'string', 'max:2000'],
                'line_items' => ['required', 'array', 'min:1'],
                'line_items.*.part_id' => ['required', 'string', 'max:120'],
                'line_items.*.description' => ['required', 'string', 'max:2000'],
                'line_items.*.quantity' => ['required', 'numeric', 'gt:0'],
                'line_items.*.target_date' => ['required', 'date'],
                'terms_and_conditions' => ['required', 'array', 'min:1'],
                'terms_and_conditions.*' => ['string', 'max:2000'],
                'questions_for_suppliers' => ['required', 'array', 'min:1'],
                'questions_for_suppliers.*' => ['string', 'max:2000'],
                'evaluation_rubric' => ['required', 'array', 'min:1'],
                'evaluation_rubric.*.criterion' => ['required', 'string', 'max:200'],
                'evaluation_rubric.*.weight' => ['required', 'numeric', 'between:0,1'],
                'evaluation_rubric.*.guidance' => ['required', 'string', 'max:2000'],
            ]
        );

        if ($validator->fails()) {
            throw new ValidationException($validator);
        }

        $data = $validator->validated();

        return [
            'rfq_title' => $data['rfq_title'],
            'scope_summary' => $data['scope_summary'],
            'line_items' => $payload['line_items'],
            'terms' => $data['terms_and_conditions'],
            'questions' => $data['questions_for_suppliers'],
            'rubric' => $data['evaluation_rubric'] ?? [],
        ];
    }

    /**
     * @param array<string, mixed> $validated
     * @param array<string, mixed> $inputs
     * @return array<string, mixed>
     */
    private function buildRfqPayload(array $validated, array $inputs): array
    {
        $method = $this->normalizeMethod($inputs['method'] ?? $inputs['category'] ?? null);
        $material = $this->stringValue($inputs['material'] ?? null);
        $tolerance = $this->stringValue($inputs['tolerance_finish'] ?? $inputs['tolerance'] ?? null);
        $incoterm = $this->stringValue($inputs['incoterm'] ?? null);
        $currency = strtoupper($this->stringValue($inputs['currency'] ?? 'USD') ?? 'USD');
        $openBidding = $this->boolValue($inputs['open_bidding'] ?? $inputs['is_open_bidding'] ?? false);
        $dueAt = $this->stringValue($inputs['due_at'] ?? null);
        $closeAt = $this->stringValue($inputs['close_at'] ?? null);

        $items = [];
        $targetDates = [];
        foreach ($validated['line_items'] as $line) {
            $items[] = [
                'part_name' => $line['part_id'],
                'quantity' => (int) round((float) $line['quantity']),
                'uom' => $this->stringValue($line['uom'] ?? 'pcs') ?? 'pcs',
                'spec' => $line['description'],
                'target_price' => null,
            ];

            if (! empty($line['target_date'])) {
                $targetDates[] = $line['target_date'];
            }
        }

        if ($dueAt === null && $targetDates !== []) {
            $dueAt = max($targetDates);
        }

        return [
            'title' => $validated['rfq_title'],
            'type' => $method,
            'method' => $method,
            'material' => $material,
            'tolerance_finish' => $tolerance,
            'incoterm' => $incoterm,
            'currency' => $currency,
            'open_bidding' => $openBidding,
            'publish_at' => null,
            'due_at' => $dueAt,
            'close_at' => $closeAt,
            'items' => $items,
        ];
    }

    private function normalizeMethod(?string $value): string
    {
        if ($value === null) {
            return 'other';
        }

        $normalized = strtolower(str_replace(' ', '_', $value));

        return in_array($normalized, RFQ::METHODS, true) ? $normalized : 'other';
    }

    /**
     * @param array<string, mixed> $validated
     * @param array<string, mixed> $output
     * @param array<int, mixed> $citations
     */
    private function applyCopilotMetadata(RFQ $rfq, array $validated, array $output, array $citations): void
    {
        $meta = $rfq->meta ?? [];
        $meta['copilot'] = array_filter([
            'scope_summary' => $validated['scope_summary'],
            'terms' => $validated['terms'],
            'questions' => $validated['questions'],
            'evaluation_rubric' => $validated['rubric'],
            'citations' => $citations,
            'confidence' => $output['confidence'] ?? null,
            'needs_human_review' => $output['needs_human_review'] ?? null,
        ], fn ($value) => $value !== null && $value !== []);

        $rfq->forceFill([
            'notes' => $validated['scope_summary'],
            'meta' => $meta,
        ])->save();
    }
}
