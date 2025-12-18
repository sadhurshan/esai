<?php

namespace App\Policies;

use App\Models\Quote;
use App\Models\RFQ;
use App\Models\Supplier;
use App\Models\User;
use App\Support\ActivePersonaContext;
use App\Support\CompanyContext;
use App\Support\Permissions\PermissionRegistry;

class QuotePolicy
{
    public function __construct(private readonly PermissionRegistry $permissionRegistry)
    {
    }

    public function submit(User $user, RFQ $rfq, int $supplierId): bool
    {
        $companyId = $this->resolveActingCompanyId($user);

        if ($companyId === null) {
            return false;
        }

        $supplier = CompanyContext::bypass(static fn () => Supplier::query()
            ->with('company')
            ->whereKey($supplierId)
            ->first());

        if ($supplier === null) {
            return false;
        }

        if (! $this->supplierMatchesActivePersona($supplier)) {
            return false;
        }

        if (! $this->supplierIsActive($supplier)) {
            return false;
        }

        $permissionCompanyId = ActivePersonaContext::isSupplier()
            ? (int) ($supplier->company_id ?? 0)
            : $companyId;

        if ($permissionCompanyId <= 0) {
            return false;
        }

        if (! $this->userHasCompanyPermission($user, $permissionCompanyId, 'rfqs.read')) {
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

    public function manageShortlist(User $user, Quote $quote): bool
    {
        return $this->buyerHasPermission($user, $quote, 'rfqs.write');
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
        $supplier = $this->resolveQuoteSupplier($quote);

        if ($supplier === null || $supplier->company_id === null) {
            return false;
        }

        if (! $this->supplierActorMatches($supplier, $user)) {
            return false;
        }

        $companyId = (int) $supplier->company_id;

        return $companyId > 0 && $this->userHasCompanyPermission($user, $companyId, $permission);
    }

    private function userHasCompanyPermission(User $user, int $companyId, string $permission): bool
    {
        if ($user->isPlatformAdmin()) {
            return true;
        }

        return $this->permissionRegistry->userHasAny($user, [$permission], $companyId);
    }

    private function supplierMatchesActivePersona(Supplier $supplier): bool
    {
        $personaSupplierId = ActivePersonaContext::supplierId();

        if ($personaSupplierId === null) {
            return true;
        }

        return $personaSupplierId === (int) $supplier->id;
    }

    private function supplierIsActive(Supplier $supplier): bool
    {
        if ($supplier->status === null) {
            return true;
        }

        return ! in_array($supplier->status, ['rejected', 'suspended'], true);
    }

    private function resolveActingCompanyId(User $user): ?int
    {
        $personaCompanyId = ActivePersonaContext::companyId();

        if ($personaCompanyId !== null) {
            return $personaCompanyId;
        }

        if ($user->company_id !== null) {
            return (int) $user->company_id;
        }

        return null;
    }

    private function resolveQuoteSupplier(Quote $quote): ?Supplier
    {
        if ($quote->relationLoaded('supplier')) {
            $supplier = $quote->getRelation('supplier');

            if ($supplier instanceof Supplier) {
                return $supplier;
            }
        }

        return CompanyContext::bypass(static fn () => $quote->supplier()->withTrashed()->first());
    }

    private function supplierActorMatches(Supplier $supplier, User $user): bool
    {
        if (ActivePersonaContext::isSupplier()) {
            $personaSupplierId = ActivePersonaContext::supplierId();

            return $personaSupplierId !== null && $personaSupplierId === (int) $supplier->id;
        }

        if ($supplier->company_id === null || $user->company_id === null) {
            return false;
        }

        return (int) $user->company_id === (int) $supplier->company_id;
    }
}
