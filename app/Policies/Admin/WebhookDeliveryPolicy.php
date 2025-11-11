<?php

namespace App\Policies\Admin;

use App\Models\WebhookDelivery;
use App\Models\User;
use App\Policies\Concerns\HandlesPlatformAdminAuthorization;

class WebhookDeliveryPolicy
{
    use HandlesPlatformAdminAuthorization;

    public function viewAny(User $user): bool
    {
        return $this->canView($user);
    }

    public function view(User $user, WebhookDelivery $delivery): bool
    {
        return $this->canView($user);
    }

    public function retry(User $user, WebhookDelivery $delivery): bool
    {
        return $this->canModify($user);
    }
}
