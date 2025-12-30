<?php

namespace App\Services\Ai\Converters;

use App\Enums\InvoiceStatus;
use App\Models\AiActionDraft;
use App\Models\Invoice;
use App\Models\InvoiceDisputeTask;
use App\Models\User;
use App\Support\Audit\AuditLogger;
use Illuminate\Contracts\Validation\Factory as ValidationFactory;
use Illuminate\Database\DatabaseManager;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class InvoiceMismatchResolutionConverter extends AbstractDraftConverter
{
    public function __construct(
        private readonly DatabaseManager $db,
        private readonly ValidationFactory $validator,
        private readonly AuditLogger $auditLogger,
    ) {}

    /**
     * @return array{entity: Invoice}
     */
    public function convert(AiActionDraft $draft, User $user): array
    {
        $result = $this->extractOutputAndPayload($draft, AiActionDraft::TYPE_INVOICE_MISMATCH_RESOLUTION);
        $payload = $this->validatePayload($result['payload']);

        $invoice = $this->resolveInvoice($draft, $payload['invoice_id'], $user->company_id);
        $companyId = (int) $invoice->company_id;

        if ($companyId <= 0) {
            throw $this->validationError('invoice_id', 'Invoice is missing a company assignment.');
        }

        if ($user->company_id !== null && (int) $user->company_id !== $companyId) {
            throw $this->validationError('invoice_id', 'Invoice does not belong to your company.');
        }

        [$invoice, $disputeTask] = $this->db->transaction(function () use ($invoice, $payload, $user): array {
            $invoice->matched_status = $this->determineMatchedStatus($payload);
            $invoice->review_note = $this->buildReviewNote($payload);
            $invoice->reviewed_by_id = $user->id;
            $invoice->reviewed_at = now();

            if ($invoice->status !== InvoiceStatus::Paid->value) {
                $invoice->status = InvoiceStatus::BuyerReview->value;
            }

            $invoice->save();

            $task = $this->createDisputeTask($invoice, $payload, $user);

            return [$invoice->fresh(['disputeTasks']), $task];
        });

        $this->auditLogger->custom($invoice, 'invoice_mismatch_resolution_applied', [
            'invoice_id' => $invoice->id,
            'resolution_type' => $payload['resolution']['type'],
            'dispute_task_id' => $disputeTask->id,
            'reason_codes' => $payload['resolution']['reason_codes'],
        ]);

        $draft->forceFill([
            'entity_type' => $invoice->getMorphClass(),
            'entity_id' => $invoice->id,
        ])->save();

        return ['entity' => $invoice];
    }

    /**
     * @param array<string, mixed> $payload
     * @return array{
     *     invoice_id: string,
     *     resolution: array{type: string, summary: string, reason_codes: list<string>, confidence: ?float},
     *     actions: list<array{type: string, description: string, owner_role: ?string, due_in_days: ?int, requires_hold: bool}>,
     *     impacted_lines: list<array{line_reference: string, issue: string, severity: ?string, variance: ?float, recommended_action: string}>,
     *     next_steps: list<string>,
     *     notes: list<string>
     * }
     */
    private function validatePayload(array $payload): array
    {
        $validator = $this->validator->make(
            [
                'invoice_id' => $payload['invoice_id'] ?? null,
                'resolution' => $payload['resolution'] ?? null,
                'actions' => $payload['actions'] ?? null,
                'impacted_lines' => $payload['impacted_lines'] ?? null,
                'next_steps' => $payload['next_steps'] ?? null,
                'notes' => $payload['notes'] ?? null,
            ],
            [
                'invoice_id' => ['required', 'string', 'max:120'],
                'resolution' => ['required', 'array'],
                'resolution.type' => ['required', 'string', 'in:hold,partial_approve,request_credit_note,adjust_po'],
                'resolution.summary' => ['required', 'string', 'max:2000'],
                'resolution.reason_codes' => ['nullable', 'array', 'max:25'],
                'resolution.reason_codes.*' => ['string', 'max:120'],
                'resolution.confidence' => ['nullable', 'numeric', 'between:0,1'],
                'actions' => ['required', 'array', 'max:20'],
                'actions.*.type' => ['required', 'string', 'max:120'],
                'actions.*.description' => ['required', 'string', 'max:1000'],
                'actions.*.owner_role' => ['nullable', 'string', 'max:120'],
                'actions.*.due_in_days' => ['nullable', 'integer', 'min:0', 'max:120'],
                'actions.*.requires_hold' => ['nullable', 'boolean'],
                'impacted_lines' => ['required', 'array', 'max:50'],
                'impacted_lines.*.line_reference' => ['required', 'string', 'max:120'],
                'impacted_lines.*.issue' => ['required', 'string', 'max:500'],
                'impacted_lines.*.severity' => ['nullable', 'string', 'in:info,warning,risk'],
                'impacted_lines.*.variance' => ['nullable', 'numeric'],
                'impacted_lines.*.recommended_action' => ['required', 'string', 'max:500'],
                'next_steps' => ['required', 'array', 'max:20'],
                'next_steps.*' => ['string', 'max:500'],
                'notes' => ['nullable', 'array', 'max:20'],
                'notes.*' => ['string', 'max:500'],
            ]
        );

        if ($validator->fails()) {
            throw new ValidationException($validator);
        }

        return [
            'invoice_id' => (string) ($payload['invoice_id'] ?? ''),
            'resolution' => [
                'type' => (string) ($payload['resolution']['type'] ?? 'hold'),
                'summary' => (string) ($payload['resolution']['summary'] ?? ''),
                'reason_codes' => $this->normalizeList($payload['resolution']['reason_codes'] ?? []),
                'confidence' => isset($payload['resolution']['confidence'])
                    ? (float) $payload['resolution']['confidence']
                    : null,
            ],
            'actions' => $this->normalizeActions($payload['actions'] ?? []),
            'impacted_lines' => $this->normalizeImpacts($payload['impacted_lines'] ?? []),
            'next_steps' => $this->normalizeList($payload['next_steps'] ?? []),
            'notes' => $this->normalizeList($payload['notes'] ?? []),
        ];
    }

    /**
     * @param list<array<string, mixed>> $actions
     * @return list<array{type: string, description: string, owner_role: ?string, due_in_days: ?int, requires_hold: bool}>
     */
    private function normalizeActions(array $actions): array
    {
        $normalized = [];

        foreach ($actions as $action) {
            if (! is_array($action)) {
                continue;
            }

            $normalized[] = [
                'type' => (string) ($action['type'] ?? ''),
                'description' => (string) ($action['description'] ?? ''),
                'owner_role' => $this->stringValue($action['owner_role'] ?? null),
                'due_in_days' => isset($action['due_in_days']) ? max(0, (int) $action['due_in_days']) : null,
                'requires_hold' => $this->boolValue($action['requires_hold'] ?? null),
            ];
        }

        return $normalized;
    }

    /**
     * @param list<array<string, mixed>> $impacts
     * @return list<array{line_reference: string, issue: string, severity: ?string, variance: ?float, recommended_action: string}>
     */
    private function normalizeImpacts(array $impacts): array
    {
        $normalized = [];

        foreach ($impacts as $impact) {
            if (! is_array($impact)) {
                continue;
            }

            $normalized[] = [
                'line_reference' => (string) ($impact['line_reference'] ?? ''),
                'issue' => (string) ($impact['issue'] ?? ''),
                'severity' => $this->stringValue($impact['severity'] ?? null),
                'variance' => isset($impact['variance']) ? (float) $impact['variance'] : null,
                'recommended_action' => (string) ($impact['recommended_action'] ?? ''),
            ];
        }

        return $normalized;
    }

    private function determineMatchedStatus(array $payload): string
    {
        $resolutionType = $payload['resolution']['type'];
        $requiresHold = $this->actionsRequireHold($payload['actions']);

        return match ($resolutionType) {
            'partial_approve' => $requiresHold ? 'hold' : 'matched',
            'adjust_po' => 'exception',
            'hold', 'request_credit_note' => 'hold',
            default => $requiresHold ? 'hold' : 'pending',
        };
    }

    /**
     * @param list<array{requires_hold: bool}> $actions
     */
    private function actionsRequireHold(array $actions): bool
    {
        foreach ($actions as $action) {
            if (! empty($action['requires_hold'])) {
                return true;
            }
        }

        return false;
    }

    /**
     * @param array{
     *     resolution: array{type: string, summary: string, reason_codes: list<string>},
     *     actions: list<array{type: string, description: string, owner_role: ?string, due_in_days: ?int}>,
     *     impacted_lines: list<array{line_reference: string, issue: string, severity: ?string, variance: ?float, recommended_action: string}>,
     *     next_steps: list<string>,
     *     notes: list<string>
     * } $payload
     */
    private function buildReviewNote(array $payload): string
    {
        $resolutionType = Str::of($payload['resolution']['type'])
            ->replace(['_', '-'], ' ')
            ->headline()
            ->value();

        $lines = [
            sprintf('%s: %s', $resolutionType, $payload['resolution']['summary']),
        ];

        if ($payload['resolution']['reason_codes'] !== []) {
            $lines[] = 'Reasons: ' . implode(', ', $payload['resolution']['reason_codes']);
        }

        if ($payload['actions'] !== []) {
            $lines[] = 'Actions: ' . implode('; ', array_map(
                static fn (array $action): string => $action['description'],
                $payload['actions']
            ));
        }

        if ($payload['next_steps'] !== []) {
            $lines[] = 'Next steps: ' . implode('; ', $payload['next_steps']);
        }

        if ($payload['impacted_lines'] !== []) {
            $summaries = array_map(
                static fn (array $impact): string => sprintf('%s (%s)', $impact['line_reference'], $impact['issue']),
                $payload['impacted_lines']
            );
            $lines[] = 'Impacts: ' . implode('; ', $summaries);
        }

        if ($payload['notes'] !== []) {
            $lines[] = 'Notes: ' . implode('; ', $payload['notes']);
        }

        return implode(PHP_EOL, array_filter($lines));
    }

    /**
     * @param array<string, mixed> $payload
     */
    private function createDisputeTask(Invoice $invoice, array $payload, User $user): InvoiceDisputeTask
    {
        $firstAction = $payload['actions'][0] ?? null;
        $dueAt = null;

        if (is_array($firstAction) && isset($firstAction['due_in_days'])) {
            $dueAt = Carbon::now()->addDays((int) $firstAction['due_in_days']);
        }

        $ownerRole = $this->stringValue($firstAction['owner_role'] ?? null) ?? 'finance_admin';

        return InvoiceDisputeTask::create([
            'company_id' => $invoice->company_id,
            'invoice_id' => $invoice->id,
            'purchase_order_id' => $invoice->purchase_order_id,
            'resolution_type' => $payload['resolution']['type'],
            'status' => 'open',
            'summary' => $payload['resolution']['summary'],
            'owner_role' => $ownerRole,
            'requires_hold' => $this->actionsRequireHold($payload['actions']),
            'due_at' => $dueAt,
            'actions' => $payload['actions'],
            'impacted_lines' => $payload['impacted_lines'],
            'next_steps' => $payload['next_steps'],
            'notes' => $payload['notes'],
            'reason_codes' => $payload['resolution']['reason_codes'],
            'created_by' => $user->id,
        ]);
    }

    private function resolveInvoice(AiActionDraft $draft, string $identifier, ?int $companyIdHint): Invoice
    {
        $context = $this->entityContext($draft);

        if ($context['entity_id'] !== null && $this->isInvoiceContext($context['entity_type'])) {
            $invoice = $this->invoiceQuery($companyIdHint)
                ->whereKey($context['entity_id'])
                ->first();

            if ($invoice instanceof Invoice) {
                return $invoice;
            }
        }

        if (is_numeric($identifier)) {
            $invoice = $this->invoiceQuery($companyIdHint)
                ->whereKey((int) $identifier)
                ->first();

            if ($invoice instanceof Invoice) {
                return $invoice;
            }
        }

        $invoice = $this->invoiceQuery($companyIdHint)
            ->where('invoice_number', $identifier)
            ->first();

        if (! $invoice instanceof Invoice) {
            throw $this->validationError('invoice_id', 'Invoice not found for this company.');
        }

        return $invoice;
    }

    private function invoiceQuery(?int $companyIdHint)
    {
        $query = Invoice::query();

        if ($companyIdHint !== null) {
            $query->forCompany($companyIdHint);
        }

        return $query;
    }

    private function isInvoiceContext(?string $entityType): bool
    {
        if ($entityType === null) {
            return false;
        }

        return Str::contains(Str::lower($entityType), 'invoice');
    }
}
