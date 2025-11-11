<?php

namespace App\Policies\Admin;

use App\Models\EmailTemplate;
use App\Models\User;
use App\Policies\Concerns\HandlesPlatformAdminAuthorization;

class EmailTemplatePolicy
{
    use HandlesPlatformAdminAuthorization;

    public function viewAny(User $user): bool
    {
        return $this->canView($user);
    }

    public function view(User $user, EmailTemplate $template): bool
    {
        return $this->canView($user);
    }

    public function create(User $user): bool
    {
        return $this->canModify($user);
    }

    public function update(User $user, EmailTemplate $template): bool
    {
        return $this->canModify($user);
    }

    public function delete(User $user, EmailTemplate $template): bool
    {
        return $this->canModify($user);
    }

    public function preview(User $user, EmailTemplate $template): bool
    {
        return $this->canView($user);
    }
}
