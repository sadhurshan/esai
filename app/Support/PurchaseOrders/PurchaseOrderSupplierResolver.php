<?php

namespace App\Support\PurchaseOrders;

use App\Models\PurchaseOrder;
use App\Models\Supplier;
use App\Support\CompanyContext;

class PurchaseOrderSupplierResolver
{
    public static function resolveSupplierCompanyId(PurchaseOrder $purchaseOrder): ?int
    {
        return CompanyContext::bypass(function () use ($purchaseOrder): ?int {
            $supplier = $purchaseOrder->supplier()
                ->withoutGlobalScope('company_scope')
                ->first();

            if ($supplier?->company_id !== null) {
                return (int) $supplier->company_id;
            }

            $quoteSupplierId = $purchaseOrder->quote?->supplier_id;

            if ($quoteSupplierId !== null) {
                $quoteSupplier = Supplier::query()
                    ->withoutGlobalScope('company_scope')
                    ->find($quoteSupplierId);

                if ($quoteSupplier?->company_id !== null) {
                    return (int) $quoteSupplier->company_id;
                }
            }

            return null;
        });
    }
}
