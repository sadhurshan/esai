<?php

namespace App\Providers;

use App\Models\GoodsReceiptNote;
use App\Models\Invoice;
use App\Models\Quote;
use App\Models\SupplierApplication;
use App\Policies\GoodsReceiptNotePolicy;
use App\Policies\InvoicePolicy;
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
        Gate::policy(GoodsReceiptNote::class, GoodsReceiptNotePolicy::class);
        Gate::policy(Invoice::class, InvoicePolicy::class);
    }
}
