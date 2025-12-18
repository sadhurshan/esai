<?php

namespace App\Support\Permissions;

use App\Models\RoleTemplate;
use App\Models\User;
use App\Support\ActivePersonaContext;
use Illuminate\Contracts\Cache\Repository as CacheRepository;
use Illuminate\Support\Facades\DB;

class PermissionRegistry
{
    public function __construct(private readonly CacheRepository $cache)
    {
    }

    /**
     * @return list<string>
     */
    public function permissionsForRole(string $role): array
    {
        $role = trim($role);

        if ($role === '') {
            return [];
        }

        $configSignature = md5(json_encode(config('rbac.permission_groups', [])) ?: 'rbac');
        $cacheKey = sprintf('permissions.role.%s.%s', $role, $configSignature);

        return $this->cache->rememberForever($cacheKey, function () use ($role): array {
            $template = RoleTemplate::query()
                ->where('slug', $role)
                ->first();

            $configPermissions = RoleTemplateDefinitions::permissionsForRole($role);

            if ($template !== null && is_array($template->permissions)) {
                return array_values(array_unique(array_merge($template->permissions, $configPermissions)));
            }

            return $configPermissions;
        });
    }

    /**
     * Determine whether the user has at least one of the required permissions.
     *
     * @param  list<string>  $permissions
     */
    public function userHasAny(User $user, array $permissions, ?int $companyId = null): bool
    {
        if ($permissions === []) {
            return true;
        }

        $role = $this->resolveRoleForCompany($user, $companyId);

        if ($role === null) {
            return false;
        }

        $rolePermissions = $this->permissionsForRole($role);

        if ($rolePermissions === []) {
            return false;
        }

        foreach ($permissions as $permission) {
            if (in_array($permission, $rolePermissions, true)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Determine whether the user has all of the required permissions.
     *
     * @param  list<string>  $permissions
     */
    public function userHasAll(User $user, array $permissions, ?int $companyId = null): bool
    {
        if ($permissions === []) {
            return true;
        }

        $role = $this->resolveRoleForCompany($user, $companyId);

        if ($role === null) {
            return false;
        }

        $rolePermissions = $this->permissionsForRole($role);

        if ($rolePermissions === []) {
            return false;
        }

        foreach ($permissions as $permission) {
            if (! in_array($permission, $rolePermissions, true)) {
                return false;
            }
        }

        return true;
    }

    public function forgetRoleCache(string $role): void
    {
        $role = trim($role);

        if ($role === '') {
            return;
        }

        $configSignature = md5(json_encode(config('rbac.permission_groups', [])) ?: 'rbac');

        $this->cache->forget(sprintf('permissions.role.%s.%s', $role, $configSignature));
    }

    private function resolveRoleForCompany(User $user, ?int $companyId): ?string
    {
        $persona = ActivePersonaContext::get();

        if ($persona !== null) {
            if ($persona->isSupplier()) {
                $supplierCompanyId = $persona->supplierCompanyId();

                if ($companyId !== null) {
                    $allowedCompanyIds = array_values(array_filter([
                        $supplierCompanyId,
                        $persona->companyId(),
                    ], static fn ($value) => $value !== null));

                    if ($allowedCompanyIds !== [] && ! in_array($companyId, $allowedCompanyIds, true)) {
                        return null;
                    }
                }

                return $this->resolveSupplierRole($user, $persona->role());
            }

            if ($companyId !== null && $persona->companyId() !== $companyId) {
                return null;
            }

            return $persona->role ?? $user->role;
        }

        if ($companyId === null || (int) $user->company_id === $companyId) {
            return $user->role;
        }

        $membershipRole = DB::table('company_user')
            ->where('company_id', $companyId)
            ->where('user_id', $user->id)
            ->value('role');

        if ($membershipRole === null) {
            return null;
        }

        return (string) $membershipRole;
    }

    private function resolveSupplierRole(User $user, ?string $personaRole): string
    {
        $eligibleRoles = ['owner', 'supplier_admin', 'supplier_estimator'];

        if ($personaRole !== null && in_array($personaRole, $eligibleRoles, true)) {
            return $personaRole;
        }

        $role = $user->role;

        if ($role !== null && in_array($role, $eligibleRoles, true)) {
            return $role;
        }

        return 'supplier_estimator';
    }
}
