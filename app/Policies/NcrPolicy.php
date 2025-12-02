<?php

namespace App\Policies;

use App\Models\Ncr;
use App\Models\User;

class NcrPolicy
{
    private const ALLOWED_ROLES = ['owner', 'buyer_admin', 'buyer_requester'];

    public function view(User $user, Ncr $ncr): bool
    {
        return $this->hasRole($user) && $this->matchesCompany($user, $ncr);
    }

    public function create(User $user): bool
    {
        return $this->hasRole($user) && $user->company_id !== null;
    }

    public function update(User $user, Ncr $ncr): bool
    {
        return $this->hasRole($user) && $this->matchesCompany($user, $ncr);
    }

    private function hasRole(User $user): bool
    {
        return in_array($user->role, self::ALLOWED_ROLES, true);
    }

    private function matchesCompany(User $user, Ncr $ncr): bool
    {
        return $user->company_id !== null && (int) $user->company_id === (int) $ncr->company_id;
    }
}
