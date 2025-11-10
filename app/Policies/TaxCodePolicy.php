<?php

namespace App\Policies;

use App\Models\TaxCode;
use App\Models\User;

class TaxCodePolicy
{
    public function viewAny(User $user): bool
    {
        return $this->canManage($user);
    }

    public function view(User $user, TaxCode $taxCode): bool
    {
        return $this->canManage($user) && $this->ownsTaxCode($user, $taxCode);
    }

    public function create(User $user): bool
    {
        return $this->canManage($user);
    }

    public function update(User $user, TaxCode $taxCode): bool
    {
        return $this->canManage($user) && $this->ownsTaxCode($user, $taxCode);
    }

    public function delete(User $user, TaxCode $taxCode): bool
    {
        return $this->canManage($user) && $this->ownsTaxCode($user, $taxCode);
    }

    private function canManage(User $user): bool
    {
        return in_array($user->role, ['owner', 'buyer_admin'], true) && $user->company_id !== null;
    }

    private function ownsTaxCode(User $user, TaxCode $taxCode): bool
    {
        return (int) $taxCode->company_id === (int) $user->company_id;
    }
}
