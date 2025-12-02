<?php

namespace App\Policies;

use App\Models\RFQ;
use App\Models\Supplier;
use App\Models\User;

class RfqClarificationPolicy
{
    private const BUYER_ROLES = ['owner', 'buyer_admin', 'buyer_requester'];
    private const SUPPLIER_ROLES = ['supplier_admin', 'supplier_estimator'];
    private const PLATFORM_ROLES = ['platform_super', 'platform_support'];

    public function viewClarifications(User $user, RFQ $rfq): bool
    {
        if ($this->belongsToCompany($user, $rfq) && $this->isBuyerRole($user)) {
            return true;
        }

        if ($this->belongsToCompany($user, $rfq) && $this->isSupplierRole($user)) {
            return true;
        }

        if ($this->isSupplierRole($user) && $this->supplierHasAccess($user, $rfq)) {
            return true;
        }

        return $user->isPlatformAdmin();
    }

    public function postQuestion(User $user, RFQ $rfq): bool
    {
        if (! $this->viewClarifications($user, $rfq)) {
            return false;
        }

        return $this->isBuyerRole($user)
            || $this->isSupplierRole($user)
            || $user->isPlatformAdmin();
    }

    public function postAnswer(User $user, RFQ $rfq): bool
    {
        if (! $this->viewClarifications($user, $rfq)) {
            return false;
        }

        return $this->isBuyerRole($user) || $user->isPlatformAdmin();
    }

    public function postAmendment(User $user, RFQ $rfq): bool
    {
        if (! $this->viewClarifications($user, $rfq)) {
            return false;
        }

        return $this->isBuyerRole($user) || $user->isPlatformAdmin();
    }

    public function awardLines(User $user, RFQ $rfq): bool
    {
        if (! $this->belongsToCompany($user, $rfq)) {
            return false;
        }

        if (in_array($user->role, ['owner', 'buyer_admin', 'buyer_requester'], true)) {
            return true;
        }

        return in_array($user->role, self::PLATFORM_ROLES, true);
    }

    private function belongsToCompany(User $user, RFQ $rfq): bool
    {
        return $user->company_id !== null && (int) $user->company_id === (int) $rfq->company_id;
    }

    private function supplierHasAccess(User $user, RFQ $rfq): bool
    {
        if (! $this->isSupplierRole($user)) {
            return false;
        }

        if ((bool) $rfq->is_open_bidding) {
            return true;
        }

        if ($user->company_id === null) {
            return false;
        }

        $supplierIds = Supplier::query()
            ->where('company_id', $user->company_id)
            ->pluck('id')
            ->map(static fn ($id) => (int) $id)
            ->all();

        if ($supplierIds === []) {
            return false;
        }

        $rfq->loadMissing('invitations');

        return $rfq->invitations
            ->pluck('supplier_id')
            ->map(static fn ($id) => (int) $id)
            ->contains(fn (int $invitedSupplierId): bool => in_array($invitedSupplierId, $supplierIds, true));
    }

    private function isBuyerRole(User $user): bool
    {
        return in_array($user->role, self::BUYER_ROLES, true);
    }

    private function isSupplierRole(User $user): bool
    {
        return in_array($user->role, self::SUPPLIER_ROLES, true);
    }
}
