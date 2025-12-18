<?php

namespace App\Http\Requests\Inventory;

use App\Http\Requests\ApiFormRequest;
use Carbon\CarbonImmutable;

class StoreInventoryMovementRequest extends ApiFormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null && $this->user()?->company_id !== null;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'type' => ['required', 'in:receipt,issue,transfer,adjust'],
            'moved_at' => ['required', 'date'],
            'notes' => ['nullable', 'string', 'max:500'],
            'reference' => ['nullable', 'array'],
            'reference.source' => ['nullable', 'in:PO,SO,MANUAL'],
            'reference.id' => ['nullable', 'string', 'max:64'],
            'lines' => ['required', 'array', 'min:1'],
            'lines.*.item_id' => ['required', 'integer', 'min:1'],
            'lines.*.qty' => ['required', 'numeric', 'gt:0'],
            'lines.*.uom' => ['nullable', 'string', 'max:16'],
            'lines.*.from_location_id' => ['nullable', 'integer', 'min:1'],
            'lines.*.to_location_id' => ['nullable', 'integer', 'min:1'],
            'lines.*.reason' => ['nullable', 'string', 'max:191'],
        ];
    }

    protected function prepareForValidation(): void
    {
        $type = strtolower((string) $this->input('type'));
        $lines = $this->normalizeLines($this->input('lines', []));
        $notes = $this->nullIfEmpty($this->input('notes'));

        $this->merge([
            'type' => $type,
            'lines' => $lines,
            'notes' => $notes,
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    public function payload(): array
    {
        $reference = $this->input('reference');

        return [
            'type' => (string) $this->input('type'),
            'moved_at' => CarbonImmutable::parse((string) $this->input('moved_at'))->toIso8601String(),
            'notes' => $this->input('notes'),
            'reference' => [
                'source' => $reference['source'] ?? null,
                'id' => $reference['id'] ?? null,
            ],
            'lines' => array_map(
                fn (array $line): array => [
                    'item_id' => (int) $line['item_id'],
                    'qty' => (float) $line['qty'],
                    'uom' => $line['uom'] ?? null,
                    'from_location_id' => $line['from_location_id'] ?? null,
                    'to_location_id' => $line['to_location_id'] ?? null,
                    'reason' => $line['reason'] ?? null,
                ],
                $this->input('lines', []),
            ),
        ];
    }

    /**
     * @param array<int, mixed> $lines
     * @return array<int, array<string, mixed>>
     */
    private function normalizeLines(array $lines): array
    {
        return array_values(array_map(function ($line): array {
            $payload = is_array($line) ? $line : [];

            return [
                'item_id' => $this->asInt($payload['item_id'] ?? $payload['itemId'] ?? null),
                'qty' => $payload['qty'] ?? null,
                'uom' => $this->nullIfEmpty($payload['uom'] ?? null),
                'from_location_id' => $this->asInt($payload['from_location_id'] ?? $payload['fromLocationId'] ?? null),
                'to_location_id' => $this->asInt($payload['to_location_id'] ?? $payload['toLocationId'] ?? null),
                'reason' => $this->nullIfEmpty($payload['reason'] ?? null),
            ];
        }, $lines));
    }

    private function asInt(mixed $value): ?int
    {
        if ($value === null || $value === '') {
            return null;
        }

        return filter_var($value, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]) ?: null;
    }

    private function nullIfEmpty(?string $value): ?string
    {
        if ($value === null) {
            return null;
        }

        $trimmed = trim($value);

        return $trimmed === '' ? null : $trimmed;
    }
}
