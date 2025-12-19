<?php

namespace App\Policies\Admin;

use App\Models\AiModelMetric;
use App\Models\User;
use App\Policies\Concerns\HandlesPlatformAdminAuthorization;

class AiModelMetricPolicy
{
    use HandlesPlatformAdminAuthorization;

    public function viewAny(User $user): bool
    {
        return $this->canView($user);
    }

    public function view(User $user, AiModelMetric $metric): bool
    {
        return $this->canView($user);
    }
}
