<?php

namespace Database\Seeders;

use App\Models\RoleTemplate;
use App\Support\Permissions\PermissionRegistry;
use App\Support\Permissions\RoleTemplateDefinitions;
use Illuminate\Database\Seeder;
use Illuminate\Support\Arr;

class RoleTemplateSeeder extends Seeder
{
    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        $roles = RoleTemplateDefinitions::all();

        $permissionRegistry = app(PermissionRegistry::class);

        foreach ($roles as $role) {
            RoleTemplate::updateOrCreate(
                ['slug' => $role['slug']],
                Arr::only($role, ['name', 'description', 'permissions', 'is_system'])
            );

            $permissionRegistry->forgetRoleCache($role['slug']);
        }
    }
}
