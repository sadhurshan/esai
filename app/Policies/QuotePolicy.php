<?php

namespace App\Policies;

use App\Enums\CompanySupplierStatus;
use App\Models\RFQ;
use App\Models\Supplier;
use App\Models\User;

class QuotePolicy
{
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
}
