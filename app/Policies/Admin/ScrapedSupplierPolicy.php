<?php

namespace App\Policies\Admin;

use App\Models\ScrapedSupplier;
use App\Models\User;
use App\Policies\Concerns\HandlesPlatformAdminAuthorization;

class ScrapedSupplierPolicy
{
    use HandlesPlatformAdminAuthorization;

    public function view(User $user, ScrapedSupplier $scraped): bool
    {
        return $this->canView($user);
    }

    public function approve(User $user, ScrapedSupplier $scraped): bool
    {
        return $this->canModify($user);
    }

    public function discard(User $user, ScrapedSupplier $scraped): bool
    {
        return $this->canModify($user);
    }
}
