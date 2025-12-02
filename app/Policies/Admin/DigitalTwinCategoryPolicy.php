<?php

namespace App\Policies\Admin;

use App\Enums\PlatformAdminRole;
use App\Models\DigitalTwinCategory;
use App\Models\User;

class DigitalTwinCategoryPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->isPlatformAdmin(PlatformAdminRole::Super);
    }

    public function view(User $user, DigitalTwinCategory $category): bool
    {
        return $user->isPlatformAdmin(PlatformAdminRole::Super);
    }

    public function create(User $user): bool
    {
        return $user->isPlatformAdmin(PlatformAdminRole::Super);
    }

    public function update(User $user, DigitalTwinCategory $category): bool
    {
        return $user->isPlatformAdmin(PlatformAdminRole::Super);
    }

    public function delete(User $user, DigitalTwinCategory $category): bool
    {
        return $user->isPlatformAdmin(PlatformAdminRole::Super);
    }
}
