<?php

namespace App\Policies\Concerns;

use App\Enums\PlatformAdminRole;
use App\Models\User;

trait HandlesPlatformAdminAuthorization
{
    protected function canView(User $user): bool
    {
        return $user->isPlatformAdmin();
    }

    protected function canModify(User $user): bool
    {
        return $user->isPlatformAdmin(PlatformAdminRole::Super);
    }
}
