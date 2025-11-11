<?php

namespace App\Policies\Admin;

use App\Models\WebhookSubscription;
use App\Models\User;
use App\Policies\Concerns\HandlesPlatformAdminAuthorization;

class WebhookSubscriptionPolicy
{
    use HandlesPlatformAdminAuthorization;

    public function viewAny(User $user): bool
    {
        return $this->canView($user);
    }

    public function view(User $user, WebhookSubscription $subscription): bool
    {
        return $this->canView($user);
    }

    public function create(User $user): bool
    {
        return $this->canModify($user);
    }

    public function update(User $user, WebhookSubscription $subscription): bool
    {
        return $this->canModify($user);
    }

    public function delete(User $user, WebhookSubscription $subscription): bool
    {
        return $this->canModify($user);
    }
}
