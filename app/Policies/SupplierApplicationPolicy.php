<?php

namespace App\Policies;

use App\Enums\SupplierApplicationStatus;
use App\Models\SupplierApplication;
use App\Models\User;

class SupplierApplicationPolicy
{
    public function viewAny(User $user): bool
    {
        return $this->isCompanyOwnerOrBuyerAdmin($user) && $user->company_id !== null;
    }

    public function create(User $user): bool
    {
        return $this->isCompanyOwner($user) && $user->company_id !== null;
    }

    public function view(User $user, SupplierApplication $application): bool
    {
        return $this->matchesCompany($user, $application) && $this->isCompanyOwnerOrBuyerAdmin($user);
    }

    public function delete(User $user, SupplierApplication $application): bool
    {
        return $this->matchesCompany($user, $application)
            && $this->isCompanyOwner($user)
            && $application->status === SupplierApplicationStatus::Pending;
    }

    public function approve(User $user): bool
    {
        return $user->role === 'platform_super';
    }

    public function reject(User $user): bool
    {
        return $user->role === 'platform_super';
    }

    private function isCompanyOwnerOrBuyerAdmin(User $user): bool
    {
        return in_array($user->role, ['owner', 'buyer_admin'], true);
    }

    private function isCompanyOwner(User $user): bool
    {
        return $user->role === 'owner';
    }

    private function matchesCompany(User $user, SupplierApplication $application): bool
    {
        return $user->company_id !== null && (int) $user->company_id === (int) $application->company_id;
    }
}
