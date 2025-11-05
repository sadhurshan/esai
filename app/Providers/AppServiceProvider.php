<?php

namespace App\Providers;

use App\Models\Quote;
use App\Models\SupplierApplication;
use App\Policies\QuotePolicy;
use App\Policies\SupplierApplicationPolicy;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        Gate::policy(Quote::class, QuotePolicy::class);
        Gate::policy(SupplierApplication::class, SupplierApplicationPolicy::class);
    }
}
