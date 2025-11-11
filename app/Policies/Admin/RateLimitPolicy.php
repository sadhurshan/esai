<?php

namespace App\Policies\Admin;

use App\Models\RateLimit;
use App\Models\User;
use App\Policies\Concerns\HandlesPlatformAdminAuthorization;

class RateLimitPolicy
{
    use HandlesPlatformAdminAuthorization;

    public function viewAny(User $user): bool
    {
        return $this->canView($user);
    }

    public function view(User $user, RateLimit $rateLimit): bool
    {
        return $this->canView($user);
    }

    public function create(User $user): bool
    {
        return $this->canModify($user);
    }

    public function update(User $user, RateLimit $rateLimit): bool
    {
        return $this->canModify($user);
    }

    public function delete(User $user, RateLimit $rateLimit): bool
    {
        return $this->canModify($user);
    }
}
