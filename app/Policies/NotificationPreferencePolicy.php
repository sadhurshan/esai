<?php

namespace App\Policies;

use App\Models\NotificationPreference;
use App\Models\User;

class NotificationPreferencePolicy
{
    public function view(User $user, NotificationPreference $preference): bool
    {
        return (int) $preference->user_id === (int) $user->id;
    }

    public function update(User $user, NotificationPreference $preference): bool
    {
        return $this->view($user, $preference);
    }
}
