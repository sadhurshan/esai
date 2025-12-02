<?php

namespace App\Policies;

use App\Models\Invoice;
use App\Models\User;
use App\Support\Permissions\PermissionRegistry;

class InvoicePolicy
{
    public function __construct(private readonly PermissionRegistry $permissionRegistry)
    {
    }

    public function viewAny(User $user): bool
    {
        return $this->hasBillingPermission($user, 'billing.read');
    }

    public function view(User $user, Invoice $invoice): bool
    {
        if (! $this->matchesCompany($user, $invoice) && ! $user->isPlatformAdmin()) {
            return false;
        }

        return $this->hasBillingPermission($user, 'billing.read', $invoice);
    }

    public function create(User $user): bool
    {
        return $this->hasBillingPermission($user, 'billing.write');
    }

    public function update(User $user, Invoice $invoice): bool
    {
        if ($invoice->status !== 'pending') {
            return false;
        }

        if (! $this->matchesCompany($user, $invoice) && ! $user->isPlatformAdmin()) {
            return false;
        }

        return $this->hasBillingPermission($user, 'billing.write', $invoice);
    }

    public function delete(User $user, Invoice $invoice): bool
    {
        if ($invoice->status !== 'pending') {
            return false;
        }

        if (! $this->matchesCompany($user, $invoice) && ! $user->isPlatformAdmin()) {
            return false;
        }

        return $this->hasBillingPermission($user, 'billing.write', $invoice);
    }

    private function matchesCompany(User $user, Invoice $invoice): bool
    {
        return $user->company_id !== null && (int) $user->company_id === (int) $invoice->company_id;
    }

    private function hasBillingPermission(User $user, string $permission, ?Invoice $invoice = null): bool
    {
        if ($user->isPlatformAdmin()) {
            return true;
        }

        $companyId = $invoice?->company_id ?? $user->company_id;

        if ($companyId === null) {
            return false;
        }

        return $this->permissionRegistry->userHasAny($user, [$permission], (int) $companyId);
    }
}
