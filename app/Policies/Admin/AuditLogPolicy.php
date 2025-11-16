<?php

namespace App\Policies\Admin;

use App\Models\AuditLog;
use App\Models\User;
use App\Policies\Concerns\HandlesPlatformAdminAuthorization;

class AuditLogPolicy
{
    use HandlesPlatformAdminAuthorization;

    public function viewAny(User $user): bool
    {
        return $this->canView($user);
    }

    public function view(User $user, AuditLog $log): bool
    {
        return $this->canView($user);
    }
}
