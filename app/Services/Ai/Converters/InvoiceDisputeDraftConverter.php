<?php

namespace App\Services\Ai\Converters;

use App\Models\AiActionDraft;
use App\Models\GoodsReceiptNote;
use App\Models\Invoice;
use App\Models\InvoiceDisputeTask;
use App\Models\PurchaseOrder;
use App\Models\User;
use App\Support\Audit\AuditLogger;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;
use Illuminate\Contracts\Validation\Factory as ValidationFactory;
use Illuminate\Validation\ValidationException;

class InvoiceDisputeDraftConverter extends AbstractDraftConverter
{
    public function __construct(
        private readonly ValidationFactory $validator,
        private readonly AuditLogger $auditLogger,
    ) {}

    /**
     * @return array{entity: InvoiceDisputeTask}
     */
    public function convert(AiActionDraft $draft, User $user): array
    {
        $result = $this->extractOutputAndPayload($draft, AiActionDraft::TYPE_INVOICE_DISPUTE_DRAFT);
        $payload = $this->validatePayload($result['payload']);

        $invoice = $this->resolveInvoice(
            $draft,
            $payload['invoice_identifier'],
            $payload['invoice_number'],
            $user->company_id
        );
        $companyId = (int) $invoice->company_id;

        if ($user->company_id !== null && (int) $user->company_id !== $companyId) {
            throw $this->validationError('invoice_id', 'Invoice does not belong to your company.');
        }

        $purchaseOrder = $this->resolvePurchaseOrder(
            $invoice,
            $payload['purchase_order_identifier'],
            $payload['purchase_order_number'],
            $companyId
        );
        $receipt = $this->resolveReceipt(
            $payload['receipt_identifier'],
            $payload['receipt_number'],
            $companyId
        );

        $dueAt = $payload['due_in_days'] !== null ? Carbon::now()->addDays($payload['due_in_days']) : null;

        $task = InvoiceDisputeTask::query()->create([
            'company_id' => $companyId,
            'invoice_id' => $invoice->id,
            'purchase_order_id' => $purchaseOrder?->id ?? $invoice->purchase_order_id,
            'goods_receipt_note_id' => $receipt?->id,
            'resolution_type' => $payload['issue_category'],
            'status' => 'open',
            'summary' => $payload['issue_summary'],
            'owner_role' => $payload['owner_role'],
            'requires_hold' => $payload['requires_hold'],
            'due_at' => $dueAt,
            'actions' => $payload['actions'],
            'impacted_lines' => $payload['impacted_lines'],
            'next_steps' => $payload['next_steps'],
            'notes' => $payload['notes'],
            'reason_codes' => $payload['reason_codes'],
            'created_by' => $user->id,
        ]);

        $draft->forceFill([
            'entity_type' => $task->getMorphClass(),
            'entity_id' => $task->id,
        ])->save();

        $this->auditLogger->custom($task, 'invoice_dispute_created', [
            'invoice_id' => $invoice->id,
            'dispute_task_id' => $task->id,
            'resolution_type' => $task->resolution_type,
            'owner_role' => $task->owner_role,
        ]);

        return ['entity' => $task];
    }

    /**
     * @param array<string, mixed> $payload
    * @return array{
    *     invoice_identifier: ?string,
    *     invoice_number: ?string,
    *     purchase_order_identifier: ?string,
    *     purchase_order_number: ?string,
    *     receipt_identifier: ?string,
    *     receipt_number: ?string,
     *     issue_summary: string,
     *     issue_category: string,
     *     owner_role: ?string,
     *     requires_hold: bool,
     *     due_in_days: ?int,
     *     actions: list<array{type:string,description:string,owner_role:?string,due_in_days:?int,requires_hold:bool}>,
     *     impacted_lines: list<array{reference:string,issue:string,severity:?string,variance:?float,recommended_action:string}>,
     *     next_steps: list<string>,
     *     notes: list<string>,
     *     reason_codes: list<string>
     * }
     */
    private function validatePayload(array $payload): array
    {
        $reference = $payload['dispute_reference'] ?? [];

        $data = [
            'invoice_id' => $reference['invoice']['id'] ?? $payload['invoice_id'] ?? null,
            'invoice_number' => $reference['invoice']['number'] ?? null,
            'purchase_order_id' => $reference['purchase_order']['id'] ?? $payload['purchase_order_id'] ?? null,
            'purchase_order_number' => $reference['purchase_order']['number'] ?? $payload['purchase_order_number'] ?? null,
            'receipt_id' => $reference['receipt']['id'] ?? $payload['receipt_id'] ?? null,
            'receipt_number' => $reference['receipt']['number'] ?? $payload['receipt_number'] ?? null,
            'issue_summary' => $payload['issue_summary'] ?? null,
            'issue_category' => $payload['issue_category'] ?? null,
            'owner_role' => $payload['owner_role'] ?? null,
            'requires_hold' => $payload['requires_hold'] ?? null,
            'due_in_days' => $payload['due_in_days'] ?? null,
            'actions' => $payload['actions'] ?? null,
            'impacted_lines' => $payload['impacted_lines'] ?? null,
            'next_steps' => $payload['next_steps'] ?? null,
            'notes' => $payload['notes'] ?? null,
            'reason_codes' => $payload['reason_codes'] ?? null,
        ];

        $validator = $this->validator->make($data, [
            'invoice_id' => ['required_without:invoice_number', 'nullable', 'string', 'max:120'],
            'invoice_number' => ['nullable', 'string', 'max:120'],
            'purchase_order_id' => ['nullable', 'string', 'max:120'],
            'receipt_id' => ['nullable', 'string', 'max:120'],
            'issue_summary' => ['required', 'string', 'max:2000'],
            'issue_category' => ['required', 'string', 'max:120'],
            'owner_role' => ['nullable', 'string', 'max:120'],
            'requires_hold' => ['boolean'],
            'due_in_days' => ['nullable', 'integer', 'min:0', 'max:120'],
            'actions' => ['nullable', 'array', 'max:25'],
            'actions.*.type' => ['required', 'string', 'max:120'],
            'actions.*.description' => ['required', 'string', 'max:1000'],
            'actions.*.owner_role' => ['nullable', 'string', 'max:120'],
            'actions.*.due_in_days' => ['nullable', 'integer', 'min:0', 'max:120'],
            'actions.*.requires_hold' => ['nullable', 'boolean'],
            'impacted_lines' => ['nullable', 'array', 'max:50'],
            'impacted_lines.*.reference' => ['required', 'string', 'max:120'],
            'impacted_lines.*.issue' => ['required', 'string', 'max:500'],
            'impacted_lines.*.severity' => ['nullable', 'string', 'in:info,warning,risk'],
            'impacted_lines.*.variance' => ['nullable', 'numeric'],
            'impacted_lines.*.recommended_action' => ['required', 'string', 'max:500'],
            'next_steps' => ['nullable', 'array', 'max:20'],
            'next_steps.*' => ['string', 'max:500'],
            'notes' => ['nullable', 'array', 'max:20'],
            'notes.*' => ['string', 'max:500'],
            'reason_codes' => ['nullable', 'array', 'max:20'],
            'reason_codes.*' => ['string', 'max:120'],
        ]);

        if ($validator->fails()) {
            throw new ValidationException($validator);
        }

        return [
            'invoice_identifier' => $this->stringValue($data['invoice_id']),
            'invoice_number' => $this->stringValue($data['invoice_number']),
            'purchase_order_identifier' => $this->stringValue($data['purchase_order_id']),
            'purchase_order_number' => $this->stringValue($data['purchase_order_number']),
            'receipt_identifier' => $this->stringValue($data['receipt_id']),
            'receipt_number' => $this->stringValue($data['receipt_number']),
            'issue_summary' => (string) $data['issue_summary'],
            'issue_category' => (string) $data['issue_category'],
            'owner_role' => $this->stringValue($data['owner_role']) ?? 'buyer_admin',
            'requires_hold' => $this->boolValue($data['requires_hold'] ?? null, false),
            'due_in_days' => isset($data['due_in_days']) ? max(0, (int) $data['due_in_days']) : null,
            'actions' => $this->normalizeActions($data['actions'] ?? []),
            'impacted_lines' => $this->normalizeImpacts($data['impacted_lines'] ?? []),
            'next_steps' => $this->normalizeList($data['next_steps'] ?? []),
            'notes' => $this->normalizeList($data['notes'] ?? []),
            'reason_codes' => $this->normalizeList($data['reason_codes'] ?? []),
        ];
    }

    /**
     * @param list<array<string, mixed>> $actions
     * @return list<array{type:string,description:string,owner_role:?string,due_in_days:?int,requires_hold:bool}>
     */
    private function normalizeActions(array $actions): array
    {
        $normalized = [];

        foreach ($actions as $action) {
            if (! is_array($action)) {
                continue;
            }

            $type = $this->stringValue($action['type'] ?? null);
            $description = $this->stringValue($action['description'] ?? null);

            if ($type === null || $description === null) {
                continue;
            }

            $normalized[] = [
                'type' => $type,
                'description' => $description,
                'owner_role' => $this->stringValue($action['owner_role'] ?? null),
                'due_in_days' => isset($action['due_in_days']) ? max(0, (int) $action['due_in_days']) : null,
                'requires_hold' => $this->boolValue($action['requires_hold'] ?? null, false),
            ];
        }

        return $normalized;
    }

    /**
     * @param list<array<string, mixed>> $impacts
     * @return list<array{reference:string,issue:string,severity:?string,variance:?float,recommended_action:string}>
     */
    private function normalizeImpacts(array $impacts): array
    {
        $normalized = [];

        foreach ($impacts as $impact) {
            if (! is_array($impact)) {
                continue;
            }

            $reference = $this->stringValue($impact['reference'] ?? null);
            $issue = $this->stringValue($impact['issue'] ?? null);
            $recommendation = $this->stringValue($impact['recommended_action'] ?? null);

            if ($reference === null || $issue === null || $recommendation === null) {
                continue;
            }

            $variance = isset($impact['variance']) ? (float) $impact['variance'] : null;

            $normalized[] = [
                'reference' => $reference,
                'issue' => $issue,
                'severity' => $this->stringValue($impact['severity'] ?? null),
                'variance' => $variance,
                'recommended_action' => $recommendation,
            ];
        }

        return $normalized;
    }

    private function resolveInvoice(
        AiActionDraft $draft,
        ?string $identifier,
        ?string $invoiceNumber,
        ?int $companyIdHint
    ): Invoice
    {
        if ($identifier === null && $invoiceNumber === null) {
            throw $this->validationError('invoice_id', 'Invoice identifier is required.');
        }

        $companyScopeId = $companyIdHint ?? $draft->company_id;
        $context = $this->entityContext($draft);

        if ($context['entity_id'] !== null && $this->isInvoiceContext($context['entity_type'])) {
            $invoice = $this->invoiceQuery($companyScopeId)->whereKey($context['entity_id'])->first();
            if ($invoice instanceof Invoice) {
                return $invoice;
            }
        }

        if ($identifier !== null && is_numeric($identifier)) {
            $invoice = $this->invoiceQuery($companyScopeId)->whereKey((int) $identifier)->first();
            if ($invoice instanceof Invoice) {
                return $invoice;
            }
        }

        if ($invoiceNumber !== null) {
            $invoice = $this->invoiceQuery($companyScopeId)
                ->where('invoice_number', $invoiceNumber)
                ->first();
            if ($invoice instanceof Invoice) {
                return $invoice;
            }
        }

        throw $this->validationError('invoice_id', 'Invoice not found for this company.');
    }

    private function resolvePurchaseOrder(
        Invoice $invoice,
        ?string $identifier,
        ?string $poNumber,
        ?int $companyIdHint
    ): ?PurchaseOrder
    {
        if ($invoice->relationLoaded('purchaseOrder') && $invoice->purchaseOrder) {
            return $invoice->purchaseOrder;
        }

        if ($invoice->purchase_order_id) {
            return $invoice->purchaseOrder()->first();
        }

        if ($identifier === null && $poNumber === null) {
            return null;
        }

        $baseQuery = PurchaseOrder::query();

        if ($companyIdHint !== null) {
            $baseQuery->forCompany($companyIdHint);
        }

        if ($identifier !== null && is_numeric($identifier)) {
            $purchaseOrder = (clone $baseQuery)->whereKey((int) $identifier)->first();
            if ($purchaseOrder instanceof PurchaseOrder) {
                return $purchaseOrder;
            }
        }

        if ($poNumber !== null) {
            return (clone $baseQuery)->where('po_number', $poNumber)->first();
        }

        return null;
    }

    private function resolveReceipt(?string $identifier, ?string $receiptNumber, ?int $companyIdHint): ?GoodsReceiptNote
    {
        if ($identifier === null && $receiptNumber === null) {
            return null;
        }

        $baseQuery = GoodsReceiptNote::query();

        if ($companyIdHint !== null) {
            $baseQuery->forCompany($companyIdHint);
        }

        if ($identifier !== null && is_numeric($identifier)) {
            $receipt = (clone $baseQuery)->whereKey((int) $identifier)->first();
            if ($receipt instanceof GoodsReceiptNote) {
                return $receipt;
            }
        }

        if ($receiptNumber !== null) {
            return (clone $baseQuery)->where('number', $receiptNumber)->first();
        }

        return null;
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