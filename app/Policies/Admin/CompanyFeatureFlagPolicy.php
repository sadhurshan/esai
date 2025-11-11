<?php

namespace App\Policies\Admin;

use App\Models\CompanyFeatureFlag;
use App\Models\User;
use App\Policies\Concerns\HandlesPlatformAdminAuthorization;

class CompanyFeatureFlagPolicy
{
    use HandlesPlatformAdminAuthorization;

    public function viewAny(User $user): bool
    {
        return $this->canView($user);
    }

    public function view(User $user, CompanyFeatureFlag $flag): bool
    {
        return $this->canView($user);
    }

    public function create(User $user): bool
    {
        return $this->canModify($user);
    }

    public function update(User $user, CompanyFeatureFlag $flag): bool
    {
        return $this->canModify($user);
    }

    public function delete(User $user, CompanyFeatureFlag $flag): bool
    {
        return $this->canModify($user);
    }
}
