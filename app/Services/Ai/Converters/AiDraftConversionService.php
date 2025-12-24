<?php

namespace App\Services\Ai\Converters;

use App\Models\AiActionDraft;
use App\Models\User;
use Illuminate\Validation\ValidationException;
use RuntimeException;

class AiDraftConversionService
{
    public function __construct(
        private readonly RfqDraftConverter $rfqConverter,
        private readonly SupplierMessageDraftConverter $supplierMessageConverter,
        private readonly MaintenanceChecklistDraftConverter $maintenanceConverter,
        private readonly InventoryWhatIfConverter $inventoryConverter,
        private readonly InvoiceDraftConverter $invoiceConverter,
        private readonly InvoiceApprovalConverter $invoiceApprovalConverter,
    ) {}

    /**
     * @return array{entity:mixed}
     */
    public function convert(AiActionDraft $draft, User $user): array
    {
        if (! $draft->isApproved()) {
            throw ValidationException::withMessages([
                'status' => ['Copilot drafts must be approved before conversion.'],
            ]);
        }

        return match ($draft->action_type) {
            AiActionDraft::TYPE_RFQ_DRAFT => $this->rfqConverter->convert($draft, $user),
            AiActionDraft::TYPE_SUPPLIER_MESSAGE => $this->supplierMessageConverter->convert($draft, $user),
            AiActionDraft::TYPE_MAINTENANCE_CHECKLIST => $this->maintenanceConverter->convert($draft, $user),
            AiActionDraft::TYPE_INVENTORY_WHATIF => $this->inventoryConverter->convert($draft, $user),
            AiActionDraft::TYPE_INVOICE_DRAFT => $this->invoiceConverter->convert($draft, $user),
            AiActionDraft::TYPE_APPROVE_INVOICE => $this->invoiceApprovalConverter->convert($draft, $user),
            default => throw new RuntimeException('Unsupported Copilot action type: ' . $draft->action_type),
        };
    }
}
