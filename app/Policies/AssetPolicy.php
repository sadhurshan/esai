<?php

namespace App\Policies;

use App\Models\Asset;
use App\Models\User;

class AssetPolicy
{
    private const VIEW_ROLES = ['owner', 'buyer_admin', 'buyer_requester', 'ops_admin'];
    private const MANAGE_ROLES = ['buyer_admin', 'ops_admin'];

    public function viewAny(User $user): bool
    {
        return $this->canView($user);
    }

    public function view(User $user, Asset $asset): bool
    {
        return $this->canView($user) && $this->matchesCompany($user, $asset->company_id);
    }

    public function create(User $user): bool
    {
        return $this->canManage($user);
    }

    public function update(User $user, Asset $asset): bool
    {
        return $this->canManage($user) && $this->matchesCompany($user, $asset->company_id);
    }

    public function delete(User $user, Asset $asset): bool
    {
        return $this->canManage($user) && $this->matchesCompany($user, $asset->company_id);
    }

    public function setStatus(User $user, Asset $asset): bool
    {
        return $this->canManage($user) && $this->matchesCompany($user, $asset->company_id);
    }

    private function canView(User $user): bool
    {
        return $user->company_id !== null && in_array($user->role, self::VIEW_ROLES, true);
    }

    private function canManage(User $user): bool
    {
        return $user->company_id !== null && in_array($user->role, self::MANAGE_ROLES, true);
    }

    private function matchesCompany(User $user, ?int $companyId): bool
    {
        return $companyId !== null && (int) $user->company_id === (int) $companyId;
    }
}
