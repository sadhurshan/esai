<?php

namespace App\Policies\Admin;

use App\Models\ModelTrainingJob;
use App\Models\User;
use App\Policies\Concerns\HandlesPlatformAdminAuthorization;

class ModelTrainingJobPolicy
{
    use HandlesPlatformAdminAuthorization;

    public function viewAny(User $user): bool
    {
        return $this->canView($user);
    }

    public function view(User $user, ModelTrainingJob $job): bool
    {
        return $this->canView($user);
    }

    public function create(User $user): bool
    {
        return $this->canModify($user);
    }

    public function update(User $user, ModelTrainingJob $job): bool
    {
        return $this->canModify($user);
    }

    public function refresh(User $user, ModelTrainingJob $job): bool
    {
        return $this->canModify($user);
    }
}
