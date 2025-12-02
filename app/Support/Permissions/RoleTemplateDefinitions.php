<?php

namespace App\Support\Permissions;

use Illuminate\Support\Collection;

class RoleTemplateDefinitions
{
    private const OWNER_ONLY_PERMISSIONS = ['suppliers.apply'];

    /**
     * Cached role template definitions derived from the RBAC config.
     *
     * @var array<int, array<string, mixed>>|null
     */
    private static ?array $definitions = null;

    /**
     * @return array<int, array<string, mixed>>
     */
    public static function all(): array
    {
        if (self::$definitions !== null) {
            return self::$definitions;
        }

        $permissionGroups = collect(config('rbac.permission_groups', []));
        $permissionsByDomain = self::permissionsByDomain($permissionGroups);
        $readPermissionsByDomain = self::permissionsByDomain($permissionGroups, 'read');
        $permissionCatalog = self::permissionCatalog($permissionGroups);

        $tenantPermissions = $permissionCatalog
            ->filter(static fn (array $permission): bool => $permission['domain'] !== 'platform')
            ->pluck('key')
            ->values()
            ->all();

        $tenantReadPermissions = $permissionCatalog
            ->filter(static fn (array $permission): bool => $permission['domain'] !== 'platform' && $permission['domain'] !== 'billing' && ($permission['level'] ?? null) === 'read')
            ->pluck('key')
            ->values()
            ->all();

        $sourcingPermissions = $permissionsByDomain->get('sourcing', []);
        $sourcingReadPermissions = $readPermissionsByDomain->get('sourcing', []);
        $suppliersPermissions = $permissionsByDomain->get('suppliers', []);
        $ordersPermissions = $permissionsByDomain->get('orders', []);
        $inventoryPermissions = $permissionsByDomain->get('inventory', []);
        $billingPermissions = $permissionsByDomain->get('billing', []);
        $analyticsPermissions = $permissionsByDomain->get('analytics', []);
        $analyticsReadPermissions = $readPermissionsByDomain->get('analytics', []);
        $workspacePermissions = $permissionsByDomain->get('workspace', []);
        $workspaceReadPermissions = $readPermissionsByDomain->get('workspace', []);
        $platformPermissions = $permissionsByDomain->get('platform', []);

        $ownerPermissions = $tenantPermissions;

        $buyerAdminPermissions = array_values(array_filter(
            $tenantPermissions,
            static fn (string $permission): bool => ! in_array($permission, self::OWNER_ONLY_PERMISSIONS, true)
        ));

        $supplierWorkspacePermissions = array_values(array_filter(
            $workspaceReadPermissions,
            static fn (string $permission): bool => $permission !== 'search.use'
        ));

        return self::$definitions = [
            [
                'slug' => 'platform_admin',
                'name' => 'Platform admin',
                'description' => 'Full access to the platform admin console and tenant configuration.',
                'permissions' => $platformPermissions,
                'is_system' => true,
            ],
            [
                'slug' => 'owner',
                'name' => 'Owner',
                'description' => 'Full buyer and supplier administration for the tenant.',
                'permissions' => $ownerPermissions,
                'is_system' => true,
            ],
            [
                'slug' => 'buyer_admin',
                'name' => 'Buyer admin',
                'description' => 'Manage RFQs, orders, suppliers, and workspace settings.',
                'permissions' => $buyerAdminPermissions,
                'is_system' => true,
            ],
            [
                'slug' => 'buyer_member',
                'name' => 'Buyer member',
                'description' => 'Collaborate on sourcing events with read-only access to critical data.',
                'permissions' => $tenantReadPermissions,
                'is_system' => true,
            ],
            [
                'slug' => 'buyer_requester',
                'name' => 'Buyer requester',
                'description' => 'Create RFQs and review awards without purchasing authority.',
                'permissions' => array_values(array_unique(array_merge($sourcingPermissions, $suppliersPermissions, $workspaceReadPermissions, $analyticsReadPermissions))),
                'is_system' => true,
            ],
            [
                'slug' => 'supplier_admin',
                'name' => 'Supplier admin',
                'description' => 'Manage supplier-side documents, quotes, and receiving updates.',
                'permissions' => array_values(array_unique(array_merge($sourcingReadPermissions, $ordersPermissions, $supplierWorkspacePermissions))),
                'is_system' => true,
            ],
            [
                'slug' => 'supplier_estimator',
                'name' => 'Supplier estimator',
                'description' => 'Prepare and submit quotes without administrative privileges.',
                'permissions' => array_values(array_unique(array_merge($sourcingReadPermissions, $supplierWorkspacePermissions))),
                'is_system' => true,
            ],
            [
                'slug' => 'finance',
                'name' => 'Finance',
                'description' => 'Access invoices, credits, payments, and exception workflows.',
                'permissions' => array_values(array_unique(array_merge($billingPermissions, $ordersPermissions, $workspacePermissions, $analyticsPermissions))),
                'is_system' => true,
            ],
        ];
    }

    /**
     * @return list<string>
     */
    public static function permissionsForRole(string $role): array
    {
        $role = trim($role);

        if ($role === '') {
            return [];
        }

        $definition = collect(self::all())->firstWhere('slug', $role);

        if (! is_array($definition)) {
            return [];
        }

        $permissions = $definition['permissions'] ?? [];

        if (! is_array($permissions)) {
            return [];
        }

        return array_values(array_unique($permissions));
    }

    private static function permissionsByDomain(Collection $permissionGroups, ?string $level = null): Collection
    {
        return $permissionGroups
            ->mapWithKeys(function (array $group) use ($level): array {
                $domain = (string) ($group['id'] ?? '');

                if ($domain === '') {
                    return [];
                }

                $permissions = collect($group['permissions'] ?? [])
                    ->filter(function (array $permission) use ($level): bool {
                        if ($level === null) {
                            return true;
                        }

                        return ($permission['level'] ?? null) === $level;
                    })
                    ->pluck('key')
                    ->filter()
                    ->values()
                    ->all();

                return [$domain => $permissions];
            });
    }

    private static function permissionCatalog(Collection $permissionGroups): Collection
    {
        return $permissionGroups
            ->flatMap(function (array $group): array {
                $domain = (string) ($group['id'] ?? '');

                return collect($group['permissions'] ?? [])
                    ->map(function (array $permission) use ($domain): array {
                        return [
                            'key' => $permission['key'] ?? null,
                            'domain' => $domain,
                            'level' => $permission['level'] ?? null,
                        ];
                    })
                    ->all();
            })
            ->filter(static fn (array $permission): bool => is_string($permission['key']) && $permission['key'] !== '')
            ->values();
    }
}
