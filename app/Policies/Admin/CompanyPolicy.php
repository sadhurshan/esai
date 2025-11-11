<?php

namespace App\Policies\Admin;

use App\Models\Company;
use App\Models\User;
use App\Policies\Concerns\HandlesPlatformAdminAuthorization;

class CompanyPolicy
{
    use HandlesPlatformAdminAuthorization;

    public function viewAny(User $user): bool
    {
        return $this->canView($user);
    }

    public function view(User $user, Company $company): bool
    {
        return $this->canView($user);
    }

    public function assignPlan(User $user, Company $company): bool
    {
        return $this->canModify($user);
    }

    public function updateStatus(User $user, Company $company): bool
    {
        return $this->canModify($user);
    }

    public function update(User $user, Company $company): bool
    {
        return $this->canModify($user);
    }
}
