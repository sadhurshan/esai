<?php

namespace Database\Seeders;

use App\Models\RoleTemplate;
use Illuminate\Database\Seeder;
use Illuminate\Support\Arr;

class RoleTemplateSeeder extends Seeder
{
    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        $permissionGroups = config('rbac.permission_groups', []);
        $allPermissions = collect($permissionGroups)
            ->flatMap(fn (array $group) => collect($group['permissions'] ?? [])->pluck('key'))
            ->filter()
            ->values()
            ->all();

        $roles = [
            [
                'slug' => 'admin',
                'name' => 'Admin',
                'description' => 'Full access to the admin console and tenant configuration.',
                'permissions' => $allPermissions,
                'is_system' => true,
            ],
            [
                'slug' => 'agent',
                'name' => 'Agent',
                'description' => 'Manage day-to-day sourcing, supplier, and fulfillment workflows.',
                'permissions' => [
                    'rfqs.read',
                    'rfqs.write',
                    'suppliers.read',
                    'suppliers.write',
                    'orders.read',
                    'orders.write',
                    'inventory.read',
                    'inventory.write',
                    'billing.read',
                ],
                'is_system' => true,
            ],
            [
                'slug' => 'viewer',
                'name' => 'Viewer',
                'description' => 'Read-only access to sourcing, suppliers, inventory, and billing.',
                'permissions' => [
                    'rfqs.read',
                    'suppliers.read',
                    'orders.read',
                    'inventory.read',
                    'billing.read',
                    'audit.read',
                ],
                'is_system' => true,
            ],
        ];

        foreach ($roles as $role) {
            RoleTemplate::updateOrCreate(
                ['slug' => $role['slug']],
                Arr::only($role, ['name', 'description', 'permissions', 'is_system'])
            );
        }
    }
}
