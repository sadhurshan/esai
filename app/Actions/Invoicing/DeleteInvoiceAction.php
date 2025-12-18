<?php

namespace App\Actions\Invoicing;

use App\Enums\InvoiceStatus;
use App\Models\Company;
use App\Models\Invoice;
use App\Models\InvoiceMatch;
use App\Models\User;
use App\Support\Audit\AuditLogger;
use Illuminate\Database\DatabaseManager;
use Illuminate\Validation\ValidationException;

class DeleteInvoiceAction
{
    public function __construct(
        private readonly AuditLogger $auditLogger,
        private readonly DatabaseManager $db,
    ) {}

    public function execute(User $user, Invoice $invoice): void
    {
        if ($user->company_id === null || (int) $invoice->company_id !== (int) $user->company_id) {
            throw ValidationException::withMessages([
                'invoice_id' => ['Invoice not found for this company.'],
            ]);
        }

        $deletableStatuses = ['pending', InvoiceStatus::Draft->value];

        if (! in_array($invoice->status, $deletableStatuses, true)) {
            throw ValidationException::withMessages([
                'status' => ['Only draft invoices can be deleted.'],
            ]);
        }

        $before = $invoice->toArray();

        $this->db->transaction(function () use ($invoice, $before, $user): void {
            $invoice->lines()->delete();

            InvoiceMatch::query()
                ->where('invoice_id', $invoice->id)
                ->delete();

            $invoice->delete();

            $company = Company::query()->whereKey($invoice->company_id)->lockForUpdate()->first();

            if ($company !== null) {
                $usage = max(0, (int) $company->invoices_monthly_used - 1);
                $company->invoices_monthly_used = $usage;
                $company->save();
            }

            $this->auditLogger->deleted($invoice, $before);
        });
    }
}
