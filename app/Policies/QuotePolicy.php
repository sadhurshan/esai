<?php

namespace App\Policies;

use App\Enums\CompanySupplierStatus;
use App\Models\Quote;
use App\Models\RFQ;
use App\Models\Supplier;
use App\Models\User;
use App\Support\Permissions\PermissionRegistry;

class QuotePolicy
{
    public function __construct(private readonly PermissionRegistry $permissionRegistry)
    {
    }

    public function submit(User $user, RFQ $rfq, int $supplierId): bool
    {
        if ($user->company_id === null) {
            return false;
        }

        $supplier = Supplier::query()
            ->with('company')
            ->whereKey($supplierId)
            ->where('company_id', $user->company_id)
            ->first();

        if ($supplier === null) {
            return false;
        }

        $company = $supplier->company;

        if ($company === null || $company->supplier_status !== CompanySupplierStatus::Approved) {
            return false;
        }

        if (! $this->userHasCompanyPermission($user, (int) $supplier->company_id, 'rfqs.read')) {
            return false;
        }

        if ($rfq->is_open_bidding) {
            return true;
        }

        return $rfq->invitations()
            ->where('supplier_id', $supplier->id)
            ->exists();
    }

    public function revise(User $user, Quote $quote): bool
    {
        if ($this->supplierHasQuotePermission($user, $quote, 'rfqs.read')) {
            return true;
        }

        return $user->isPlatformAdmin();
    }

    public function withdraw(User $user, Quote $quote): bool
    {
        return $this->revise($user, $quote);
    }

    public function view(User $user, Quote $quote): bool
    {
        if ($this->buyerHasPermission($user, $quote, 'rfqs.read')) {
            return true;
        }

        if ($this->supplierHasQuotePermission($user, $quote, 'rfqs.read')) {
            return true;
        }

        return $user->isPlatformAdmin();
    }

    public function viewRevisions(User $user, Quote $quote): bool
    {
        if ($this->buyerHasPermission($user, $quote, 'rfqs.read')) {
            return true;
        }

        if ($this->supplierHasQuotePermission($user, $quote, 'rfqs.read')) {
            return true;
        }

        return $user->isPlatformAdmin();
    }

    private function belongsToBuyer(User $user, Quote $quote): bool
    {
        if ($user->company_id === null) {
            return false;
        }

        return (int) $quote->company_id === (int) $user->company_id;
    }

    private function buyerHasPermission(User $user, Quote $quote, string $permission): bool
    {
        if (! $this->belongsToBuyer($user, $quote)) {
            return $user->isPlatformAdmin();
        }

        if ($quote->company_id === null) {
            return false;
        }

        return $this->permissionRegistry->userHasAny($user, [$permission], (int) $quote->company_id);
    }

    private function supplierHasQuotePermission(User $user, Quote $quote, string $permission): bool
    {
        $supplier = $quote->relationLoaded('supplier') ? $quote->supplier : $quote->supplier()->withTrashed()->first();

        if ($supplier === null || $supplier->company_id === null) {
            return false;
        }

        if ((int) ($user->company_id ?? 0) !== (int) $supplier->company_id) {
            return false;
        }

        return $this->userHasCompanyPermission($user, (int) $supplier->company_id, $permission);
    }

    private function userHasCompanyPermission(User $user, int $companyId, string $permission): bool
    {
        if ($user->isPlatformAdmin()) {
            return true;
        }

        return $this->permissionRegistry->userHasAny($user, [$permission], $companyId);
    }
}
