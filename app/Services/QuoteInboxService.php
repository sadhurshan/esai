<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\Quote;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;

class QuoteInboxService
{
    public function supplierQuery(Request $request, int $companyId, ?int $supplierId = null): Builder
    {
        $query = Quote::query()
            ->forCompany($companyId)
            ->with([
                'supplier',
                'rfq',
                'items.taxes.taxCode',
                'items.rfqItem',
                'documents',
            ]);

        if ($supplierId !== null) {
            $query->where('supplier_id', $supplierId);
        }

        $this->applyCommonFilters($query, $request);

        if ($request->filled('rfq_number')) {
            $rfqNumber = trim((string) $request->query('rfq_number'));
            $query->whereHas('rfq', static function (Builder $builder) use ($rfqNumber): void {
                $builder->where('rfq_number', 'like', "%{$rfqNumber}%");
            });
        }

        return $query;
    }

    public function buyerQuery(Request $request, int $companyId): Builder
    {
        $query = Quote::query()
            ->with([
                'supplier.company',
                'rfq',
                'items.taxes.taxCode',
                'items.rfqItem',
                'documents',
            ])
            ->where('company_id', $companyId);

        $this->applyCommonFilters($query, $request);

        if ($request->filled('supplier_id')) {
            $supplierId = (int) $request->query('supplier_id');
            $query->where('supplier_id', $supplierId);
        }

        if ($request->filled('rfq_number')) {
            $rfqNumber = trim((string) $request->query('rfq_number'));
            $query->whereHas('rfq', static function (Builder $builder) use ($rfqNumber): void {
                $builder->where('rfq_number', 'like', "%{$rfqNumber}%")
                    ->orWhere('title', 'like', "%{$rfqNumber}%");
            });
        }

        if ($request->filled('supplier')) {
            $supplierSearch = trim((string) $request->query('supplier'));
            $query->whereHas('supplier', static function (Builder $builder) use ($supplierSearch): void {
                $builder->where('name', 'like', "%{$supplierSearch}%");
            });
        }

        return $query;
    }

    private function applyCommonFilters(Builder $query, Request $request): void
    {
        if ($request->filled('rfq_id')) {
            $query->where('rfq_id', (int) $request->query('rfq_id'));
        }

        if ($request->filled('status')) {
            $statuses = collect(explode(',', (string) $request->query('status')))
                ->map(static fn (string $value): string => strtolower(trim($value)))
                ->filter()
                ->unique()
                ->all();

            if ($statuses !== []) {
                $query->whereIn('status', $statuses);
            }
        }

        if ($request->filled('search')) {
            $term = trim((string) $request->query('search'));

            $query->where(static function (Builder $builder) use ($term): void {
                $builder->where('notes', 'like', "%{$term}%")
                    ->orWhereHas('supplier', static function (Builder $supplier) use ($term): void {
                        $supplier->where('name', 'like', "%{$term}%");
                    });
            });
        }
    }
}
