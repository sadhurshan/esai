<?php

namespace App\Services\Ai\Converters;

use App\Models\AiActionDraft;
use App\Models\Supplier;
use App\Models\SupplierDocumentTask;
use App\Models\User;
use Illuminate\Contracts\Validation\Factory as ValidationFactory;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class SupplierOnboardDraftConverter extends AbstractDraftConverter
{
    public function __construct(private readonly ValidationFactory $validator) {}

    /**
     * @return array{entity: Supplier, document_tasks: array<int, SupplierDocumentTask|null>}
     */
    public function convert(AiActionDraft $draft, User $user): array
    {
        $result = $this->extractOutputAndPayload($draft, AiActionDraft::TYPE_SUPPLIER_ONBOARD_DRAFT);
        $payload = $this->validatePayload($result['payload']);

        $companyId = $draft->company_id ?? $user->company_id;

        if ($companyId === null) {
            throw ValidationException::withMessages([
                'company_id' => ['Draft is missing a company context.'],
            ]);
        }

        $supplier = $this->upsertSupplier($companyId, $payload);
        $documentTasks = $this->syncDocumentTasks($supplier, $payload['documents_needed'], $user);

        $draft->forceFill([
            'entity_type' => $supplier->getMorphClass(),
            'entity_id' => $supplier->getKey(),
        ])->save();

        return [
            'entity' => $supplier->fresh(),
            'document_tasks' => collect($documentTasks)
                ->map(static fn (SupplierDocumentTask $task) => $task->fresh() ?? $task)
                ->all(),
        ];
    }

    /**
     * @param array<string, mixed> $payload
     * @return array{
     *     legal_name: string,
     *     country: string,
     *     email: string,
     *     phone: string,
     *     payment_terms: string,
     *     tax_id: string,
    *     website: ?string,
    *     address: ?string,
    *     notes: ?string,
    *     capabilities: list<string>,
     *     documents_needed: list<array{
     *         document_type: string,
     *         description: ?string,
     *         is_required: bool,
     *         priority: int,
     *         due_at: ?Carbon,
     *         notes: ?string
     *     }>
     * }
     */
    private function validatePayload(array $payload): array
    {
        $validator = $this->validator->make(
            [
                'legal_name' => $payload['legal_name'] ?? null,
                'country' => $payload['country'] ?? null,
                'email' => $payload['email'] ?? null,
                'phone' => $payload['phone'] ?? null,
                'payment_terms' => $payload['payment_terms'] ?? null,
                'tax_id' => $payload['tax_id'] ?? null,
                'website' => $payload['website'] ?? null,
                'address' => $payload['address'] ?? null,
                'notes' => $payload['notes'] ?? null,
                'documents_needed' => $payload['documents_needed'] ?? null,
                'capabilities' => $payload['capabilities'] ?? null,
            ],
            [
                'legal_name' => ['required', 'string', 'max:191'],
                'country' => ['required', 'string', 'max:64'],
                'email' => ['required', 'email', 'max:191'],
                'phone' => ['required', 'string', 'max:60'],
                'payment_terms' => ['required', 'string', 'max:120'],
                'tax_id' => ['required', 'string', 'max:120'],
                'website' => ['nullable', 'string', 'max:191'],
                'address' => ['nullable', 'string', 'max:191'],
                'notes' => ['nullable', 'string', 'max:1000'],
                'documents_needed' => ['nullable', 'array', 'max:20'],
                'documents_needed.*.type' => ['required', 'string', 'max:120'],
                'documents_needed.*.description' => ['nullable', 'string', 'max:255'],
                'documents_needed.*.required' => ['nullable', 'boolean'],
                'documents_needed.*.priority' => ['nullable', 'integer', 'between:1,5'],
                'documents_needed.*.due_in_days' => ['nullable', 'integer', 'min:1', 'max:365'],
                'documents_needed.*.notes' => ['nullable', 'string', 'max:500'],
                'capabilities' => ['nullable', 'array', 'max:25'],
                'capabilities.*' => ['string', 'max:120'],
            ]
        );

        if ($validator->fails()) {
            throw new ValidationException($validator);
        }

        $data = $validator->validated();

        return [
            'legal_name' => (string) $data['legal_name'],
            'country' => strtoupper((string) $data['country']),
            'email' => (string) $data['email'],
            'phone' => (string) $data['phone'],
            'payment_terms' => (string) $data['payment_terms'],
            'tax_id' => (string) $data['tax_id'],
            'website' => $this->stringValue($data['website'] ?? null),
            'address' => $this->stringValue($data['address'] ?? null),
            'notes' => $this->stringValue($data['notes'] ?? null),
            'capabilities' => $this->normalizeCapabilities($data['capabilities'] ?? []),
            'documents_needed' => $this->normalizeDocumentRequirements($payload['documents_needed'] ?? []),
        ];
    }

    /**
     * @param array<string, mixed> $payload
     */
    private function upsertSupplier(int $companyId, array $payload): Supplier
    {
        $supplier = Supplier::query()
            ->forCompany($companyId)
            ->where(static function ($query) use ($payload): void {
                $query->where('email', $payload['email'])
                    ->orWhere('name', $payload['legal_name']);
            })
            ->orderByDesc('id')
            ->first();

        $capabilities = $payload['capabilities'] ?? ($supplier?->capabilities ?? []);

        $attributes = [
            'company_id' => $companyId,
            'name' => $payload['legal_name'],
            'capabilities' => $capabilities,
            'email' => $payload['email'],
            'phone' => $payload['phone'],
            'country' => $payload['country'],
            'address' => $payload['address'],
            'website' => $payload['website'],
            'payment_terms' => $payload['payment_terms'],
            'tax_id' => $payload['tax_id'],
            'onboarding_notes' => $payload['notes'],
            'status' => 'pending',
        ];

        if ($supplier instanceof Supplier) {
            $supplier->forceFill($attributes)->save();

            return $supplier->fresh() ?? $supplier;
        }

        $created = Supplier::query()->create($attributes);

        return $created->fresh() ?? $created;
    }

    /**
     * @param list<array{document_type:string,description:?string,is_required:bool,priority:int,due_at:?Carbon,notes:?string}> $requirements
     * @return list<SupplierDocumentTask>
     */
    private function syncDocumentTasks(Supplier $supplier, array $requirements, User $user): array
    {
        if ($requirements === []) {
            return [];
        }

        $supplier->loadMissing('documentTasks');

        $existing = $supplier->documentTasks
            ->keyBy(static fn (SupplierDocumentTask $task) => Str::lower($task->document_type));

        $tasks = [];

        foreach ($requirements as $requirement) {
            $key = Str::lower($requirement['document_type']);
            $task = $existing[$key] ?? null;

            if ($task instanceof SupplierDocumentTask) {
                if ($task->status === SupplierDocumentTask::STATUS_PENDING) {
                    $task->fill([
                        'is_required' => $requirement['is_required'],
                        'priority' => $requirement['priority'],
                        'due_at' => $requirement['due_at'],
                        'description' => $requirement['description'],
                        'notes' => $requirement['notes'],
                    ])->save();
                }

                $tasks[] = $task;
                continue;
            }

            $tasks[] = $supplier->documentTasks()->create([
                'company_id' => $supplier->company_id,
                'document_type' => $requirement['document_type'],
                'status' => SupplierDocumentTask::STATUS_PENDING,
                'is_required' => $requirement['is_required'],
                'priority' => $requirement['priority'],
                'due_at' => $requirement['due_at'],
                'description' => $requirement['description'],
                'notes' => $requirement['notes'],
                'requested_by' => $user->id,
            ]);
        }

        return $tasks;
    }

    /**
     * @return list<string>
     */
    private function normalizeCapabilities(mixed $value): array
    {
        if (! is_array($value)) {
            return [];
        }

        $normalized = [];

        foreach ($value as $entry) {
            if (! is_string($entry)) {
                continue;
            }

            $trimmed = trim($entry);

            if ($trimmed === '') {
                continue;
            }

            $normalized[] = Str::limit($trimmed, 120, '');
        }

        $unique = array_values(array_unique($normalized));

        return array_slice($unique, 0, 25);
    }

    /**
     * @return list<array{document_type:string,description:?string,is_required:bool,priority:int,due_at:?Carbon,notes:?string}>
     */
    private function normalizeDocumentRequirements(mixed $value): array
    {
        if (! is_array($value)) {
            return [];
        }

        $normalized = [];

        foreach ($value as $index => $entry) {
            if (! is_array($entry)) {
                continue;
            }

            $type = $this->stringValue($entry['type'] ?? null);

            if ($type === null) {
                continue;
            }

            $normalized[] = [
                'document_type' => Str::limit($type, 120, ''),
                'description' => $this->stringValue($entry['description'] ?? null),
                'is_required' => $this->boolValue($entry['required'] ?? true, true),
                'priority' => $this->normalizePriority($entry['priority'] ?? null, $index + 1),
                'due_at' => $this->normalizeDueDate($entry['due_in_days'] ?? null),
                'notes' => $this->stringValue($entry['notes'] ?? null),
            ];
        }

        return $normalized;
    }

    private function normalizeDueDate(mixed $value): ?Carbon
    {
        if (! is_numeric($value)) {
            return null;
        }

        $days = (int) $value;

        if ($days < 1 || $days > 365) {
            return null;
        }

        return Carbon::now()->addDays($days);
    }

    private function normalizePriority(mixed $value, int $fallback): int
    {
        if (is_int($value)) {
            return $this->clampPriority($value);
        }

        if (is_string($value) && ctype_digit($value)) {
            return $this->clampPriority((int) $value);
        }

        return $this->clampPriority($fallback);
    }

    private function clampPriority(int $value): int
    {
        return max(1, min(5, $value));
    }
}
