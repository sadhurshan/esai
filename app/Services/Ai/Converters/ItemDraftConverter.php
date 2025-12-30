<?php

namespace App\Services\Ai\Converters;

use App\Actions\Inventory\StoreInventoryItemAction;
use App\Actions\Inventory\SyncPreferredSuppliersAction;
use App\Actions\Inventory\UpdateInventoryItemAction;
use App\Models\AiActionDraft;
use App\Models\Part;
use App\Models\User;
use Illuminate\Contracts\Validation\Factory as ValidationFactory;
use Illuminate\Validation\ValidationException;

class ItemDraftConverter extends AbstractDraftConverter
{
    public function __construct(
        private readonly StoreInventoryItemAction $storeInventoryItemAction,
        private readonly UpdateInventoryItemAction $updateInventoryItemAction,
        private readonly SyncPreferredSuppliersAction $syncPreferredSuppliers,
        private readonly ValidationFactory $validator,
    ) {}

    /**
     * @return array{entity: Part}
     */
    public function convert(AiActionDraft $draft, User $user): array
    {
        $result = $this->extractOutputAndPayload($draft, AiActionDraft::TYPE_ITEM_DRAFT);
        $payload = $this->validatePayload($result['payload']);

        $companyId = $draft->company_id ?? $user->company_id;

        if ($companyId === null) {
            throw ValidationException::withMessages([
                'company_id' => ['Draft is missing a company context.'],
            ]);
        }

        $part = $this->upsertPart((int) $companyId, $payload);

        $this->syncPreferredSuppliers->execute($part, $payload['preferred_suppliers']);

        $draft->forceFill([
            'entity_type' => $part->getMorphClass(),
            'entity_id' => $part->getKey(),
        ])->save();

        return ['entity' => $part->fresh()];
    }

    /**
     * @param array<string, mixed> $payload
     * @return array{
     *     item_code: string,
     *     name: string,
     *     uom: string,
     *     active: bool,
     *     category: ?string,
     *     description: ?string,
     *     spec: ?string,
     *     attributes: array<string, mixed>|null,
     *     preferred_suppliers: list<array{supplier_id:?int,name:?string,priority:int,notes:?string}>
     * }
     */
    private function validatePayload(array $payload): array
    {
        $validator = $this->validator->make(
            [
                'item_code' => $payload['item_code'] ?? $payload['sku'] ?? null,
                'name' => $payload['name'] ?? null,
                'uom' => $payload['uom'] ?? null,
                'status' => $payload['status'] ?? null,
                'category' => $payload['category'] ?? null,
                'description' => $payload['description'] ?? null,
                'spec' => $payload['spec'] ?? null,
                'attributes' => $payload['attributes'] ?? null,
                'preferred_suppliers' => $payload['preferred_suppliers'] ?? null,
            ],
            [
                'item_code' => ['required', 'string', 'max:128'],
                'name' => ['required', 'string', 'max:191'],
                'uom' => ['required', 'string', 'max:32'],
                'status' => ['nullable', 'string', 'in:active,inactive'],
                'category' => ['nullable', 'string', 'max:191'],
                'description' => ['nullable', 'string', 'max:2000'],
                'spec' => ['nullable', 'string', 'max:4000'],
                'attributes' => ['nullable', 'array'],
                'preferred_suppliers' => ['nullable', 'array', 'max:5'],
                'preferred_suppliers.*.supplier_id' => ['nullable', 'integer', 'min:1'],
                'preferred_suppliers.*.name' => ['nullable', 'string', 'max:191'],
                'preferred_suppliers.*.priority' => ['nullable', 'integer', 'between:1,5'],
                'preferred_suppliers.*.notes' => ['nullable', 'string', 'max:500'],
            ]
        );

        if ($validator->fails()) {
            throw new ValidationException($validator);
        }

        $data = $validator->validated();

        return [
            'item_code' => (string) $data['item_code'],
            'name' => (string) $data['name'],
            'uom' => (string) $data['uom'],
            'active' => ($data['status'] ?? 'active') !== 'inactive',
            'category' => $this->stringValue($data['category'] ?? null),
            'description' => $this->stringValue($data['description'] ?? null),
            'spec' => $this->stringValue($data['spec'] ?? null),
            'attributes' => isset($data['attributes']) && is_array($data['attributes']) ? $data['attributes'] : null,
            'preferred_suppliers' => $this->normalizePreferredSuppliers($data['preferred_suppliers'] ?? []),
        ];
    }

    /**
     * @param array<string, mixed> $payload
     */
    private function upsertPart(int $companyId, array $payload): Part
    {
        $existing = Part::query()
            ->forCompany($companyId)
            ->where('part_number', $payload['item_code'])
            ->first();

        $actionPayload = [
            'sku' => $payload['item_code'],
            'name' => $payload['name'],
            'uom' => $payload['uom'],
            'category' => $payload['category'],
            'description' => $payload['description'],
            'spec' => $payload['spec'],
            'attributes' => $payload['attributes'],
            'active' => $payload['active'],
        ];

        if ($existing instanceof Part) {
            $updated = $this->updateInventoryItemAction->execute($existing, $actionPayload);

            return $updated->fresh() ?? $updated;
        }

        $created = $this->storeInventoryItemAction->execute($companyId, $actionPayload);

        return $created->fresh() ?? $created;
    }

    /**
     * @param array<int, mixed> $candidates
     * @return list<array{supplier_id:?int,name:?string,priority:int,notes:?string}>
     */
    private function normalizePreferredSuppliers(?array $candidates): array
    {
        if ($candidates === null) {
            return [];
        }

        $normalized = [];

        foreach ($candidates as $index => $entry) {
            if (! is_array($entry)) {
                continue;
            }

            $supplierId = $this->coerceId($entry['supplier_id'] ?? null);
            $name = $this->stringValue($entry['name'] ?? null);

            if ($supplierId === null && $name === null) {
                continue;
            }

            $priority = $this->normalizePriority($entry['priority'] ?? null) ?? ($index + 1);
            $notes = $this->stringValue($entry['notes'] ?? null);

            $normalized[] = [
                'supplier_id' => $supplierId,
                'name' => $name,
                'priority' => $priority,
                'notes' => $notes,
            ];
        }

        if ($normalized === []) {
            return [];
        }

        $sorted = collect($normalized)
            ->sortBy([['priority', 'asc']])
            ->values()
            ->take(5)
            ->map(fn (array $entry, int $index): array => [
                'supplier_id' => $entry['supplier_id'],
                'name' => $entry['name'],
                'priority' => $index + 1,
                'notes' => $entry['notes'],
            ]);

        return $sorted->all();
    }

    private function coerceId(null|int|string $value): ?int
    {
        if ($value === null || $value === '') {
            return null;
        }

        if (is_int($value)) {
            return $value > 0 ? $value : null;
        }

        return ctype_digit($value) ? (int) $value : null;
    }

    private function normalizePriority(mixed $value): ?int
    {
        if (is_int($value)) {
            return $this->clampPriority($value);
        }

        if (is_string($value) && ctype_digit($value)) {
            return $this->clampPriority((int) $value);
        }

        return null;
    }

    private function clampPriority(int $value): int
    {
        return max(1, min(5, $value));
    }
}
