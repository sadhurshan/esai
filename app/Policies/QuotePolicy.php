<?php

namespace App\Policies;

use App\Enums\CompanySupplierStatus;
use App\Models\Quote;
use App\Models\RFQ;
use App\Models\Supplier;
use App\Models\User;

class QuotePolicy
{
    private const BUYER_ROLES = ['owner', 'buyer_admin', 'buyer_requester', 'buyer_viewer'];
    private const SUPPLIER_ROLES = ['supplier_admin', 'supplier_estimator'];

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

        if ($rfq->is_open_bidding) {
            return true;
        }

        return $rfq->invitations()
            ->where('supplier_id', $supplier->id)
            ->exists();
    }

    public function revise(User $user, Quote $quote): bool
    {
        if ($this->belongsToSupplier($user, $quote) && $this->hasSupplierRole($user)) {
            return true;
        }

        return in_array($user->role, ['platform_super', 'platform_support'], true);
    }

    public function withdraw(User $user, Quote $quote): bool
    {
        return $this->revise($user, $quote);
    }

    public function viewRevisions(User $user, Quote $quote): bool
    {
        if ($this->belongsToBuyer($user, $quote) && $this->hasBuyerRole($user)) {
            return true;
        }

        if ($this->belongsToSupplier($user, $quote) && $this->hasSupplierRole($user)) {
            return true;
        }

        return in_array($user->role, ['platform_super', 'platform_support'], true);
    }

    private function hasSupplierRole(User $user): bool
    {
        return in_array($user->role, [...self::SUPPLIER_ROLES, 'platform_super', 'platform_support'], true);
    }

    private function hasBuyerRole(User $user): bool
    {
        return in_array($user->role, [...self::BUYER_ROLES, 'platform_super', 'platform_support'], true);
    }

    private function belongsToSupplier(User $user, Quote $quote): bool
    {
        if ($user->company_id === null) {
            return false;
        }

        $supplier = $quote->relationLoaded('supplier') ? $quote->supplier : $quote->supplier()->withTrashed()->first();

        $supplierCompanyId = $supplier?->company_id;

        return $supplierCompanyId !== null && (int) $supplierCompanyId === (int) $user->company_id;
    }

    private function belongsToBuyer(User $user, Quote $quote): bool
    {
        if ($user->company_id === null) {
            return false;
        }

        return (int) $quote->company_id === (int) $user->company_id;
    }
}
