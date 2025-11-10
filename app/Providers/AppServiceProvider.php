<?php

namespace App\Providers;

use App\Models\Document;
use App\Models\GoodsReceiptNote;
use App\Models\Invoice;
use App\Models\Notification;
use App\Models\NotificationPreference;
use App\Models\Quote;
use App\Models\RFQ;
use App\Models\SupplierApplication;
use App\Policies\DocumentPolicy;
use App\Policies\GoodsReceiptNotePolicy;
use App\Policies\InvoicePolicy;
use App\Policies\NotificationPolicy;
use App\Policies\NotificationPreferencePolicy;
use App\Policies\QuotePolicy;
use App\Policies\RfqClarificationPolicy;
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
        Gate::policy(Document::class, DocumentPolicy::class);
        Gate::policy(Notification::class, NotificationPolicy::class);
        Gate::policy(NotificationPreference::class, NotificationPreferencePolicy::class);
        Gate::policy(RFQ::class, RfqClarificationPolicy::class);
    }
}
