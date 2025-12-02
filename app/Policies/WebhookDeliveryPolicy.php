<?php

namespace App\Policies;

use App\Enums\PlatformAdminRole;
use App\Models\WebhookDelivery;
use App\Models\User;

class WebhookDeliveryPolicy
{
    public function viewAny(User $user): bool
    {
        if ($user->isPlatformAdmin()) {
            return true;
        }

        return $user->company_id !== null;
    }

    public function view(User $user, WebhookDelivery $delivery): bool
    {
        if ($user->isPlatformAdmin()) {
            return true;
        }

        return (int) ($user->company_id ?? 0) === (int) ($delivery->company_id ?? 0);
    }

    public function retry(User $user, WebhookDelivery $delivery): bool
    {
        if ($user->isPlatformAdmin(PlatformAdminRole::Super)) {
            return true;
        }

        return $this->view($user, $delivery);
    }

    public function replay(User $user): bool
    {
        if ($user->isPlatformAdmin(PlatformAdminRole::Super)) {
            return true;
        }

        return $user->company_id !== null;
    }
}
