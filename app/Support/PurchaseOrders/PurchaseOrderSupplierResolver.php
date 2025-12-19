<?php

namespace App\Support\PurchaseOrders;

use App\Models\PurchaseOrder;
use App\Models\Quote;
use App\Models\Supplier;
use App\Support\CompanyContext;

class PurchaseOrderSupplierResolver
{
    public static function resolveSupplierCompanyId(PurchaseOrder $purchaseOrder): ?int
    {
        return CompanyContext::bypass(function () use ($purchaseOrder): ?int {
            $supplier = self::resolveSupplierRelation($purchaseOrder);

            if ($supplier?->company_id !== null) {
                return (int) $supplier->company_id;
            }

            if ($purchaseOrder->supplier_id !== null) {
                $directSupplier = self::findSupplier((int) $purchaseOrder->supplier_id);

                if ($directSupplier?->company_id !== null) {
                    return (int) $directSupplier->company_id;
                }
            }

            $quoteSupplier = self::resolveQuoteSupplier($purchaseOrder->getRelationValue('quote'));

            if ($quoteSupplier?->company_id !== null) {
                return (int) $quoteSupplier->company_id;
            }

            $quoteSupplierId = $purchaseOrder->quote?->supplier_id;

            if ($quoteSupplierId !== null) {
                $quoteSupplierRecord = self::findSupplier((int) $quoteSupplierId);

                if ($quoteSupplierRecord?->company_id !== null) {
                    return (int) $quoteSupplierRecord->company_id;
                }
            }

            return null;
        });
    }

    private static function resolveSupplierRelation(PurchaseOrder $purchaseOrder): ?Supplier
    {
        $supplier = $purchaseOrder->getRelationValue('supplier');

        if ($supplier instanceof Supplier) {
            return $supplier;
        }

        return $purchaseOrder->supplier()
            ->withoutGlobalScope('company_scope')
            ->first();
    }

    private static function resolveQuoteSupplier(?Quote $quote): ?Supplier
    {
        if (! $quote instanceof Quote) {
            return null;
        }

        $supplier = $quote->getRelationValue('supplier');

        if ($supplier instanceof Supplier) {
            return $supplier;
        }

        if ($quote->supplier_id === null) {
            return null;
        }

        return self::findSupplier((int) $quote->supplier_id);
    }

    private static function findSupplier(int $supplierId): ?Supplier
    {
        return Supplier::query()
            ->withoutGlobalScope('company_scope')
            ->find($supplierId);
    }
}
