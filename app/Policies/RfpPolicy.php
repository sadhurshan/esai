<?php

namespace App\Policies;

use App\Models\Rfp;
use App\Models\User;
use App\Support\Permissions\PermissionRegistry;

class RfpPolicy
{
    public function __construct(private readonly PermissionRegistry $permissionRegistry)
    {
    }

    public function create(User $user): bool
    {
        $companyId = $user->company_id;

        if ($companyId === null) {
            return $user->isPlatformAdmin();
        }

        return $this->hasPermission($user, 'rfps.write', (int) $companyId);
    }

    public function update(User $user, Rfp $rfp): bool
    {
        if (! $this->belongsToCompany($user, $rfp)) {
            return $user->isPlatformAdmin();
        }

        return $this->hasPermission($user, 'rfps.write', (int) $rfp->company_id);
    }

    public function view(User $user, Rfp $rfp): bool
    {
        if (! $this->belongsToCompany($user, $rfp)) {
            return $user->isPlatformAdmin();
        }

        return $this->hasPermission($user, 'rfps.read', (int) $rfp->company_id);
    }

    public function delete(User $user, Rfp $rfp): bool
    {
        return $this->update($user, $rfp);
    }

    private function belongsToCompany(User $user, Rfp $rfp): bool
    {
        return $user->company_id !== null && (int) $user->company_id === (int) $rfp->company_id;
    }

    private function hasPermission(User $user, string $permission, int $companyId): bool
    {
        if ($user->isPlatformAdmin()) {
            return true;
        }

        return $this->permissionRegistry->userHasAny($user, [$permission], $companyId);
    }
}
