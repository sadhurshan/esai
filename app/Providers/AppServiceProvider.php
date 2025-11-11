<?php

namespace App\Providers;

use App\Models\ApiKey;
use App\Models\Asset;
use App\Models\Company;
use App\Models\CompanyFeatureFlag;
use App\Models\Document;
use App\Models\EmailTemplate;
use App\Models\GoodsReceiptNote;
use App\Models\Invoice;
use App\Models\Location;
use App\Models\MaintenanceProcedure;
use App\Models\Notification;
use App\Models\NotificationPreference;
use App\Models\Plan;
use App\Models\Quote;
use App\Models\RateLimit;
use App\Models\RFQ;
use App\Models\SupplierApplication;
use App\Models\System;
use App\Models\TaxCode;
use App\Models\WebhookDelivery;
use App\Models\WebhookSubscription;
use App\Policies\Admin\ApiKeyPolicy as AdminApiKeyPolicy;
use App\Policies\Admin\CompanyFeatureFlagPolicy as AdminCompanyFeatureFlagPolicy;
use App\Policies\Admin\CompanyPolicy as AdminCompanyPolicy;
use App\Policies\Admin\EmailTemplatePolicy as AdminEmailTemplatePolicy;
use App\Policies\Admin\PlanPolicy as AdminPlanPolicy;
use App\Policies\Admin\RateLimitPolicy as AdminRateLimitPolicy;
use App\Policies\Admin\WebhookDeliveryPolicy as AdminWebhookDeliveryPolicy;
use App\Policies\Admin\WebhookSubscriptionPolicy as AdminWebhookSubscriptionPolicy;
use App\Policies\AssetPolicy;
use App\Policies\DocumentPolicy;
use App\Policies\GoodsReceiptNotePolicy;
use App\Policies\InvoicePolicy;
use App\Policies\LocationPolicy;
use App\Policies\MaintenanceProcedurePolicy;
use App\Policies\NotificationPolicy;
use App\Policies\NotificationPreferencePolicy;
use App\Policies\QuotePolicy;
use App\Policies\RfqClarificationPolicy;
use App\Policies\SupplierApplicationPolicy;
use App\Policies\SystemPolicy;
use App\Policies\TaxCodePolicy;
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
        Gate::policy(Plan::class, AdminPlanPolicy::class);
        Gate::policy(Company::class, AdminCompanyPolicy::class);
        Gate::policy(CompanyFeatureFlag::class, AdminCompanyFeatureFlagPolicy::class);
        Gate::policy(EmailTemplate::class, AdminEmailTemplatePolicy::class);
        Gate::policy(ApiKey::class, AdminApiKeyPolicy::class);
        Gate::policy(RateLimit::class, AdminRateLimitPolicy::class);
        Gate::policy(WebhookSubscription::class, AdminWebhookSubscriptionPolicy::class);
        Gate::policy(WebhookDelivery::class, AdminWebhookDeliveryPolicy::class);

        Gate::policy(Quote::class, QuotePolicy::class);
        Gate::policy(SupplierApplication::class, SupplierApplicationPolicy::class);
        Gate::policy(GoodsReceiptNote::class, GoodsReceiptNotePolicy::class);
        Gate::policy(Invoice::class, InvoicePolicy::class);
        Gate::policy(Document::class, DocumentPolicy::class);
        Gate::policy(Notification::class, NotificationPolicy::class);
        Gate::policy(NotificationPreference::class, NotificationPreferencePolicy::class);
        Gate::policy(RFQ::class, RfqClarificationPolicy::class);
        Gate::policy(Location::class, LocationPolicy::class);
        Gate::policy(System::class, SystemPolicy::class);
        Gate::policy(Asset::class, AssetPolicy::class);
        Gate::policy(MaintenanceProcedure::class, MaintenanceProcedurePolicy::class);
        Gate::policy(TaxCode::class, TaxCodePolicy::class);
    }
}
