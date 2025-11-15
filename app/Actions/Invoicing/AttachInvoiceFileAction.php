<?php

namespace App\Actions\Invoicing;

use App\Actions\PurchaseOrder\RecordPurchaseOrderEventAction;
use App\Models\Document;
use App\Models\Invoice;
use App\Models\User;
use App\Support\Audit\AuditLogger;
use App\Support\Documents\DocumentStorer;
use Illuminate\Http\UploadedFile;
use Illuminate\Validation\ValidationException;

class AttachInvoiceFileAction
{
    public function __construct(
        private readonly DocumentStorer $documentStorer,
        private readonly AuditLogger $auditLogger,
        private readonly RecordPurchaseOrderEventAction $recordEvent,
    ) {}

    public function execute(User $user, Invoice $invoice, UploadedFile $file): Document
    {
        if ($user->company_id === null || (int) $invoice->company_id !== (int) $user->company_id) {
            throw ValidationException::withMessages([
                'invoice_id' => ['Invoice not found for this company.'],
            ]);
        }

        $stored = $this->documentStorer->store(
            $user,
            $file,
            'financial',
            (int) $invoice->company_id,
            $invoice->getMorphClass(),
            $invoice->getKey(),
            [
                'kind' => 'invoice_attachment',
                'visibility' => 'company',
                'meta' => [
                    'context' => 'invoice_attachment',
                    'invoice_id' => $invoice->getKey(),
                ],
            ],
        );

        $this->auditLogger->created($stored, [
            'invoice_id' => $invoice->getKey(),
            'user_id' => $user->getKey(),
        ]);

        $purchaseOrder = $invoice->purchaseOrder()->first();

        if ($purchaseOrder !== null) {
            $this->recordEvent->execute(
                $purchaseOrder,
                'invoice_attachment',
                sprintf('Invoice %s attachment uploaded', $invoice->invoice_number),
                null,
                [
                    'invoice_id' => $invoice->getKey(),
                    'document_id' => $stored->getKey(),
                    'filename' => $stored->filename,
                ],
                $user,
                now(),
            );
        }

        return $stored;
    }
}
