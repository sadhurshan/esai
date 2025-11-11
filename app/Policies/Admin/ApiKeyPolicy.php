<?php

namespace App\Policies\Admin;

use App\Models\ApiKey;
use App\Models\User;
use App\Policies\Concerns\HandlesPlatformAdminAuthorization;

class ApiKeyPolicy
{
    use HandlesPlatformAdminAuthorization;

    public function viewAny(User $user): bool
    {
        return $this->canView($user);
    }

    public function view(User $user, ApiKey $apiKey): bool
    {
        return $this->canView($user);
    }

    public function create(User $user): bool
    {
        return $this->canModify($user);
    }

    public function rotate(User $user, ApiKey $apiKey): bool
    {
        return $this->canModify($user);
    }

    public function toggle(User $user, ApiKey $apiKey): bool
    {
        return $this->canModify($user);
    }

    public function delete(User $user, ApiKey $apiKey): bool
    {
        return $this->canModify($user);
    }
}
