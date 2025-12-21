<?php

namespace App\Policies\Admin;

use App\Models\SupplierScrapeJob;
use App\Models\User;
use App\Policies\Concerns\HandlesPlatformAdminAuthorization;

class SupplierScrapeJobPolicy
{
    use HandlesPlatformAdminAuthorization;

    public function viewAny(User $user): bool
    {
        return $this->canView($user);
    }

    public function view(User $user, SupplierScrapeJob $job): bool
    {
        return $this->canView($user);
    }

    public function create(User $user): bool
    {
        return $this->canModify($user);
    }
}
