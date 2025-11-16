<?php

namespace App\Policies\Admin;

use App\Models\RoleTemplate;
use App\Models\User;
use App\Policies\Concerns\HandlesPlatformAdminAuthorization;

class RoleTemplatePolicy
{
    use HandlesPlatformAdminAuthorization;

    public function viewAny(User $user): bool
    {
        return $this->canView($user);
    }

    public function update(User $user, RoleTemplate $roleTemplate): bool
    {
        return $this->canModify($user);
    }
}
