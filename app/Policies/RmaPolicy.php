<?php

namespace App\Policies;

use App\Models\Rma;
use App\Models\User;

class RmaPolicy
{
    public function view(User $user, Rma $rma): bool
    {
        return (int) $user->company_id === (int) $rma->company_id;
    }

    public function review(User $user, Rma $rma): bool
    {
        if (! $this->view($user, $rma)) {
            return false;
        }

        return in_array($user->role, ['buyer_admin', 'quality', 'finance'], true);
    }
}
