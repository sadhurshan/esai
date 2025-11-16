<?php

namespace App\Policies\Admin;

use App\Models\PlatformAdmin;
use App\Models\User;
use App\Policies\Concerns\HandlesPlatformAdminAuthorization;

class AnalyticsPolicy
{
    use HandlesPlatformAdminAuthorization;

    public function overview(User $user, ?PlatformAdmin $platformAdmin = null): bool
    {
        return $this->canView($user);
    }
}
