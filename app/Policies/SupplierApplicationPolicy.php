<?php

namespace App\Policies;

use App\Enums\PlatformAdminRole;
use App\Enums\SupplierApplicationStatus;
use App\Models\SupplierApplication;
use App\Models\User;
use App\Support\Permissions\PermissionRegistry;

class SupplierApplicationPolicy
{
    private const VIEW_PERMISSIONS = ['suppliers.write'];
    private const APPLY_PERMISSIONS = ['suppliers.apply'];

    public function __construct(private readonly PermissionRegistry $permissions)
    {
    }

    public function viewAny(User $user): bool
    {
        $companyId = $this->resolveUserCompanyId($user);

        if ($companyId === null) {
            return false;
        }

        return $this->userHasTenantPermission($user, self::VIEW_PERMISSIONS, $companyId);
    }

    public function create(User $user): bool
    {
        $companyId = $this->resolveUserCompanyId($user);

        if ($companyId === null) {
            return false;
        }

        return $this->userHasTenantPermission($user, self::APPLY_PERMISSIONS, $companyId);
    }

    public function view(User $user, SupplierApplication $application): bool
    {
        if (! $this->matchesCompany($user, $application)) {
            return false;
        }

        return $this->userHasTenantPermission($user, self::VIEW_PERMISSIONS, (int) $application->company_id);
    }

    public function delete(User $user, SupplierApplication $application): bool
    {
        if (! $this->matchesCompany($user, $application)) {
            return false;
        }

        if ($application->status !== SupplierApplicationStatus::Pending) {
            return false;
        }

        return $this->userHasTenantPermission($user, self::APPLY_PERMISSIONS, (int) $application->company_id);
    }

    public function approve(User $user): bool
    {
        return $user->isPlatformAdmin(PlatformAdminRole::Super);
    }

    public function reject(User $user): bool
    {
        return $user->isPlatformAdmin(PlatformAdminRole::Super);
    }

    private function matchesCompany(User $user, SupplierApplication $application): bool
    {
        return $user->company_id !== null && (int) $user->company_id === (int) $application->company_id;
    }

    private function userHasTenantPermission(User $user, array $permissions, ?int $companyId = null): bool
    {
        return $this->permissions->userHasAny($user, $permissions, $companyId);
    }

    private function resolveUserCompanyId(User $user): ?int
    {
        if ($user->company_id === null) {
            return null;
        }

        return (int) $user->company_id;
    }
}
