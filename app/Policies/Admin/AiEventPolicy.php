<?php

namespace App\Policies\Admin;

use App\Models\AiEvent;
use App\Models\User;
use App\Policies\Concerns\HandlesPlatformAdminAuthorization;

class AiEventPolicy
{
    use HandlesPlatformAdminAuthorization;

    public function viewAny(User $user): bool
    {
        return $this->canView($user);
    }

    public function view(User $user, AiEvent $event): bool
    {
        return $this->canView($user);
    }
}
