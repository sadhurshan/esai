<?php

namespace App\Policies;

use App\Models\Rfp;
use App\Models\User;
use App\Support\Permissions\PermissionRegistry;
use Illuminate\Support\Facades\DB;

class RfpProposalPolicy
{
    public function __construct(private readonly PermissionRegistry $permissionRegistry)
    {
    }

    public function submit(User $user, Rfp $rfp, int $supplierCompanyId): bool
    {
        if ($user->isPlatformAdmin()) {
            return true;
        }

        if ($this->belongsToCompany($user, (int) $rfp->company_id)) {
            return $this->permissionRegistry->userHasAny($user, ['rfps.write'], (int) $rfp->company_id);
        }

        if ($this->belongsToCompany($user, $supplierCompanyId)) {
            return $this->permissionRegistry->userHasAny($user, ['rfps.read'], $supplierCompanyId);
        }

        return false;
    }

    public function viewAny(User $user, Rfp $rfp): bool
    {
        if ($user->isPlatformAdmin()) {
            return true;
        }

        if (! $this->belongsToCompany($user, (int) $rfp->company_id)) {
            return false;
        }

        return $this->permissionRegistry->userHasAny($user, ['rfps.read', 'rfps.write'], (int) $rfp->company_id);
    }

    public function view(User $user, Rfp $rfp): bool
    {
        return $this->viewAny($user, $rfp);
    }

    private function belongsToCompany(User $user, ?int $companyId): bool
    {
        if ($companyId === null) {
            return false;
        }

        if ($user->company_id !== null && (int) $user->company_id === (int) $companyId) {
            return true;
        }

        return DB::table('company_user')
            ->where('user_id', $user->id)
            ->where('company_id', $companyId)
            ->exists();
    }
}
