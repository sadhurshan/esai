<?php

namespace App\Policies;

use App\Models\RFQ;
use App\Models\User;
use App\Models\Supplier;
use App\Support\ActivePersonaContext;
use App\Support\CompanyContext;

class RfqClarificationPolicy
{
    private const BUYER_ROLES = ['owner', 'buyer_admin', 'buyer_requester'];
    private const SUPPLIER_ROLES = ['supplier_admin', 'supplier_estimator', 'owner'];
    private const PLATFORM_ROLES = ['platform_super', 'platform_support'];

    public function viewClarifications(User $user, RFQ $rfq): bool
    {
        if ($this->isBuyerContext($user, $rfq)) {
            return true;
        }

        if ($this->isSupplierContext($user) && $this->supplierHasAccess($user, $rfq)) {
            return true;
        }

        return $user->isPlatformAdmin();
    }

    public function postQuestion(User $user, RFQ $rfq): bool
    {
        if (! $this->viewClarifications($user, $rfq)) {
            return false;
        }

        return $this->isBuyerContext($user, $rfq)
            || $this->isSupplierContext($user)
            || $user->isPlatformAdmin();
    }

    public function postAnswer(User $user, RFQ $rfq): bool
    {
        if (! $this->viewClarifications($user, $rfq)) {
            return false;
        }

        if ($this->isBuyerContext($user, $rfq) || $user->isPlatformAdmin()) {
            return true;
        }

        return $this->isSupplierContext($user) && $this->supplierHasAccess($user, $rfq);
    }

    public function postAmendment(User $user, RFQ $rfq): bool
    {
        if (! $this->viewClarifications($user, $rfq)) {
            return false;
        }

        return $this->isBuyerContext($user, $rfq) || $user->isPlatformAdmin();
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
        if (! $this->isSupplierContext($user)) {
            return false;
        }

        if ((bool) $rfq->is_open_bidding) {
            return true;
        }

        $supplierIds = $this->resolveSupplierIds($user);

        if ($supplierIds === []) {
            return false;
        }

        CompanyContext::forCompany($rfq->company_id, static function () use ($rfq): void {
            $rfq->loadMissing('invitations');
        });

        $invitedIds = $rfq->invitations
            ->pluck('supplier_id')
            ->map(static fn ($id) => (int) $id)
            ->all();

        return (bool) array_intersect($supplierIds, $invitedIds);
    }

    private function resolveSupplierIds(User $user): array
    {
        $personaSupplierId = ActivePersonaContext::supplierId();

        if ($personaSupplierId !== null) {
            return [$personaSupplierId];
        }

        if ($user->company_id === null) {
            return [];
        }

        return CompanyContext::forCompany($user->company_id, function () use ($user) {
            return Supplier::query()
                ->where('company_id', $user->company_id)
                ->pluck('id')
                ->map(static fn ($id) => (int) $id)
                ->all();
        });
    }

    private function isBuyerContext(User $user, RFQ $rfq): bool
    {
        if (! $this->belongsToCompany($user, $rfq)) {
            return false;
        }

        if (ActivePersonaContext::isSupplier()) {
            return false;
        }

        return $this->isBuyerRole($user);
    }

    private function isSupplierContext(User $user): bool
    {
        if (ActivePersonaContext::isSupplier()) {
            return true;
        }

        return $this->isSupplierRole($user);
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
