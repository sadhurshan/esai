<?php

namespace App\Http\Controllers\Api;

use App\Actions\Invoicing\RecalculateInvoiceTotalsAction;
use App\Http\Resources\InvoiceResource;
use App\Models\Invoice;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class InvoiceTotalsController extends ApiController
{
    public function __construct(private readonly RecalculateInvoiceTotalsAction $recalculateInvoiceTotals)
    {
    }

    public function recalculate(Request $request, Invoice $invoice): JsonResponse
    {
        $user = $this->resolveRequestUser($request);

        if ($user === null) {
            return $this->fail('Authentication required.', 401);
        }

        if ($this->authorizeDenied($user, 'update', $invoice)) {
            return $this->fail('Forbidden.', 403);
        }

        if ($user->company_id === null || (int) $user->company_id !== (int) $invoice->company_id) {
            return $this->fail('Invoice not found for this company.', 404);
        }

        $invoice = $this->recalculateInvoiceTotals->execute($invoice);

        $invoice->loadMissing(['lines.taxes.taxCode', 'document', 'matches', 'purchaseOrder']);

        return $this->ok((new InvoiceResource($invoice))->toArray($request), 'Invoice totals recalculated.');
    }
}
