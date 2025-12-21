<?php

namespace App\Providers;

use App\Enums\PlatformAdminRole;
use App\Models\User;
use Illuminate\Foundation\Support\Providers\AuthServiceProvider as ServiceProvider;
use Illuminate\Support\Facades\Gate;

class AuthServiceProvider extends ServiceProvider
{
    /**
     * Register any authentication / authorization services.
     */
    public function boot(): void
    {
        $this->registerPolicies();

        Gate::define('canTrainAi', static function (User $user): bool {
            return $user->isPlatformAdmin(PlatformAdminRole::Super);
        });
    }
}
