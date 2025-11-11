<?php

namespace App\Policies\Admin;

use App\Models\Plan;
use App\Models\User;
use App\Policies\Concerns\HandlesPlatformAdminAuthorization;

class PlanPolicy
{
    use HandlesPlatformAdminAuthorization;

    public function viewAny(User $user): bool
    {
        return $this->canView($user);
    }

    public function view(User $user, Plan $plan): bool
    {
        return $this->canView($user);
    }

    public function create(User $user): bool
    {
        return $this->canModify($user);
    }

    public function update(User $user, Plan $plan): bool
    {
        return $this->canModify($user);
    }

    public function delete(User $user, Plan $plan): bool
    {
        return $this->canModify($user);
    }
}
