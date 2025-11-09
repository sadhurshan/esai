<?php

namespace App\Policies;

use App\Models\Invoice;
use App\Models\User;

class InvoicePolicy
{
    private const ALLOWED_ROLES = ['finance', 'buyer_admin'];

    public function viewAny(User $user): bool
    {
        return $this->hasRole($user) && $user->company_id !== null;
    }

    public function view(User $user, Invoice $invoice): bool
    {
        return $this->hasRole($user) && $this->matchesCompany($user, $invoice);
    }

    public function create(User $user): bool
    {
        return $this->hasRole($user) && $user->company_id !== null;
    }

    public function update(User $user, Invoice $invoice): bool
    {
        return $this->hasRole($user) && $this->matchesCompany($user, $invoice) && $invoice->status === 'pending';
    }

    public function delete(User $user, Invoice $invoice): bool
    {
        return $this->hasRole($user) && $this->matchesCompany($user, $invoice) && $invoice->status === 'pending';
    }

    private function hasRole(User $user): bool
    {
        return in_array($user->role, self::ALLOWED_ROLES, true);
    }

    private function matchesCompany(User $user, Invoice $invoice): bool
    {
        return $user->company_id !== null && (int) $user->company_id === (int) $invoice->company_id;
    }
}
