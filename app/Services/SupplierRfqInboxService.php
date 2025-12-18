<?php

namespace App\Services;

use App\Models\RFQ;
use App\Support\CompanyContext;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;

class SupplierRfqInboxService
{
    public function query(Request $request, int $buyerCompanyId, int $supplierId): Builder
    {
        return CompanyContext::forCompany($buyerCompanyId, function () use ($request, $buyerCompanyId, $supplierId): Builder {
            $builder = RFQ::query()
                ->with(['company', 'invitations' => static function ($invitationQuery) use ($supplierId): void {
                    $invitationQuery->where('rfq_invitations.supplier_id', $supplierId);
                }])
                ->where('company_id', $buyerCompanyId);

            $builder->where(static function (Builder $scope) use ($supplierId): void {
                $scope->whereHas('invitations', static function (Builder $invitationQuery) use ($supplierId): void {
                    $invitationQuery->where('rfq_invitations.supplier_id', $supplierId);
                })->orWhere(static function (Builder $openQuery): void {
                    $openQuery->where('open_bidding', true)
                        ->where('status', RFQ::STATUS_OPEN);
                });
            });

            if ($request->filled('status')) {
                $statuses = collect(explode(',', (string) $request->query('status')))
                    ->map(static fn (string $value): string => strtolower(trim($value)))
                    ->filter(static fn (string $value): bool => in_array($value, RFQ::STATUSES, true))
                    ->unique()
                    ->all();

                if ($statuses !== []) {
                    $builder->whereIn('status', $statuses);
                }
            } else {
                $builder->where('status', '!=', RFQ::STATUS_DRAFT);
            }

            if ($request->filled('search')) {
                $term = trim((string) $request->query('search'));

                $builder->where(static function (Builder $searchQuery) use ($term): void {
                    $searchQuery
                        ->where('title', 'like', "%{$term}%")
                        ->orWhere('number', 'like', "%{$term}%")
                        ->orWhere('method', 'like', "%{$term}%")
                        ->orWhere('material', 'like', "%{$term}%");
                });
            }

            if ($request->filled('due_from')) {
                $builder->whereDate('due_at', '>=', $request->query('due_from'));
            }

            if ($request->filled('due_to')) {
                $builder->whereDate('due_at', '<=', $request->query('due_to'));
            }

            return $builder;
        });
    }
}
