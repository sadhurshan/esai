<?php

namespace App\Policies\Admin;

use App\Enums\PlatformAdminRole;
use App\Models\DigitalTwin;
use App\Models\User;

class DigitalTwinPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->isPlatformAdmin(PlatformAdminRole::Super);
    }

    public function view(User $user, DigitalTwin $digitalTwin): bool
    {
        return $user->isPlatformAdmin(PlatformAdminRole::Super);
    }

    public function create(User $user): bool
    {
        return $user->isPlatformAdmin(PlatformAdminRole::Super);
    }

    public function update(User $user, DigitalTwin $digitalTwin): bool
    {
        return $user->isPlatformAdmin(PlatformAdminRole::Super);
    }

    public function delete(User $user, DigitalTwin $digitalTwin): bool
    {
        return $user->isPlatformAdmin(PlatformAdminRole::Super);
    }
}
