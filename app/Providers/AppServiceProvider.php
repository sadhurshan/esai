<?php

namespace App\Providers;

use App\Models\AiEvent;
use App\Models\AiModelMetric;
use App\Models\ApiKey;
use App\Models\AuditLog;
use App\Models\Asset;
use App\Models\Company;
use App\Models\CompanyFeatureFlag;
use App\Models\DigitalTwin;
use App\Models\DigitalTwinCategory;
use App\Models\Document;
use App\Models\EmailTemplate;
use App\Models\GoodsReceiptNote;
use App\Models\Invoice;
use App\Models\Location;
use App\Models\MaintenanceProcedure;
use App\Models\Notification;
use App\Models\NotificationPreference;
use App\Models\Ncr;
use App\Models\Order;
use App\Models\ModelTrainingJob;
use App\Models\Part;
use App\Models\Plan;
use App\Models\PlatformAdmin;
use App\Models\PurchaseOrder;
use App\Models\PurchaseOrderLine;
use App\Models\PurchaseOrderShipment;
use App\Models\PurchaseOrderShipmentLine;
use App\Models\Quote;
use App\Models\RoleTemplate;
use App\Models\ScrapedSupplier;
use App\Models\RateLimit;
use App\Models\RFQ;
use App\Models\Rfp;
use App\Models\RfpProposal;
use App\Models\SupplierScrapeJob;
use App\Models\SupplierApplication;
use App\Models\SupplierDocument;
use App\Models\System;
use App\Models\TaxCode;
use App\Models\WebhookDelivery;
use App\Models\WebhookSubscription;
use App\Policies\Admin\AiEventPolicy as AdminAiEventPolicy;
use App\Policies\Admin\AiModelMetricPolicy as AdminAiModelMetricPolicy;
use App\Policies\Admin\ApiKeyPolicy as AdminApiKeyPolicy;
use App\Policies\Admin\AuditLogPolicy as AdminAuditLogPolicy;
use App\Policies\Admin\CompanyFeatureFlagPolicy as AdminCompanyFeatureFlagPolicy;
use App\Policies\Admin\CompanyPolicy as AdminCompanyPolicy;
use App\Policies\Admin\DigitalTwinCategoryPolicy as AdminDigitalTwinCategoryPolicy;
use App\Policies\Admin\DigitalTwinPolicy as AdminDigitalTwinPolicy;
use App\Policies\Admin\EmailTemplatePolicy as AdminEmailTemplatePolicy;
use App\Policies\Admin\AnalyticsPolicy as AdminAnalyticsPolicy;
use App\Policies\Admin\PlanPolicy as AdminPlanPolicy;
use App\Policies\Admin\RateLimitPolicy as AdminRateLimitPolicy;
use App\Policies\Admin\RoleTemplatePolicy as AdminRoleTemplatePolicy;
use App\Policies\Admin\ModelTrainingJobPolicy as AdminModelTrainingJobPolicy;
use App\Policies\Admin\ScrapedSupplierPolicy as AdminScrapedSupplierPolicy;
use App\Policies\Admin\SupplierScrapeJobPolicy as AdminSupplierScrapeJobPolicy;
use App\Policies\Admin\WebhookSubscriptionPolicy as AdminWebhookSubscriptionPolicy;
use App\Policies\WebhookDeliveryPolicy;
use App\Policies\AssetPolicy;
use App\Policies\DocumentPolicy;
use App\Policies\GoodsReceiptNotePolicy;
use App\Policies\InvoicePolicy;
use App\Policies\LocationPolicy;
use App\Policies\MaintenanceProcedurePolicy;
use App\Policies\NotificationPolicy;
use App\Policies\NotificationPreferencePolicy;
use App\Policies\NcrPolicy;
use App\Policies\OrderPolicy;
use App\Policies\PartPolicy;
use App\Policies\PurchaseOrderPolicy;
use App\Policies\QuotePolicy;
use App\Policies\RfqPolicy;
use App\Policies\RfpPolicy;
use App\Policies\RfpProposalPolicy;
use App\Policies\SupplierApplicationPolicy;
use App\Policies\SupplierDocumentPolicy;
use App\Policies\SystemPolicy;
use App\Policies\TaxCodePolicy;
use App\Observers\PurchaseOrderLineObserver;
use App\Observers\PurchaseOrderObserver;
use App\Observers\PurchaseOrderShipmentLineObserver;
use App\Observers\PurchaseOrderShipmentObserver;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\View;
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
        View::addNamespace('mail', [
            resource_path('views/vendor/mail'),
            base_path('vendor/laravel/framework/src/Illuminate/Mail/resources/views'),
        ]);

        Gate::policy(Plan::class, AdminPlanPolicy::class);
        Gate::policy(PlatformAdmin::class, AdminAnalyticsPolicy::class);
        Gate::policy(Company::class, AdminCompanyPolicy::class);
        Gate::policy(CompanyFeatureFlag::class, AdminCompanyFeatureFlagPolicy::class);
        Gate::policy(EmailTemplate::class, AdminEmailTemplatePolicy::class);
        Gate::policy(DigitalTwinCategory::class, AdminDigitalTwinCategoryPolicy::class);
        Gate::policy(DigitalTwin::class, AdminDigitalTwinPolicy::class);
        Gate::policy(ApiKey::class, AdminApiKeyPolicy::class);
        Gate::policy(RateLimit::class, AdminRateLimitPolicy::class);
        Gate::policy(WebhookSubscription::class, AdminWebhookSubscriptionPolicy::class);
        Gate::policy(WebhookDelivery::class, WebhookDeliveryPolicy::class);
        Gate::policy(RoleTemplate::class, AdminRoleTemplatePolicy::class);
        Gate::policy(AuditLog::class, AdminAuditLogPolicy::class);
        Gate::policy(AiEvent::class, AdminAiEventPolicy::class);
        Gate::policy(AiModelMetric::class, AdminAiModelMetricPolicy::class);
        Gate::policy(ModelTrainingJob::class, AdminModelTrainingJobPolicy::class);
        Gate::policy(SupplierScrapeJob::class, AdminSupplierScrapeJobPolicy::class);
        Gate::policy(ScrapedSupplier::class, AdminScrapedSupplierPolicy::class);

        Gate::policy(Quote::class, QuotePolicy::class);
        Gate::policy(Part::class, PartPolicy::class);
        Gate::policy(SupplierApplication::class, SupplierApplicationPolicy::class);
        Gate::policy(SupplierDocument::class, SupplierDocumentPolicy::class);
        Gate::policy(GoodsReceiptNote::class, GoodsReceiptNotePolicy::class);
        Gate::policy(Ncr::class, NcrPolicy::class);
        Gate::policy(Invoice::class, InvoicePolicy::class);
        Gate::policy(Document::class, DocumentPolicy::class);
        Gate::policy(Notification::class, NotificationPolicy::class);
        Gate::policy(NotificationPreference::class, NotificationPreferencePolicy::class);
        Gate::policy(Order::class, OrderPolicy::class);
        Gate::policy(RFQ::class, RfqPolicy::class);
        Gate::policy(Rfp::class, RfpPolicy::class);
        Gate::policy(RfpProposal::class, RfpProposalPolicy::class);
        Gate::policy(PurchaseOrder::class, PurchaseOrderPolicy::class);
        Gate::policy(Location::class, LocationPolicy::class);
        Gate::policy(System::class, SystemPolicy::class);
        Gate::policy(Asset::class, AssetPolicy::class);
        Gate::policy(MaintenanceProcedure::class, MaintenanceProcedurePolicy::class);
        Gate::policy(TaxCode::class, TaxCodePolicy::class);

        PurchaseOrder::observe(PurchaseOrderObserver::class);
        PurchaseOrderLine::observe(PurchaseOrderLineObserver::class);
        PurchaseOrderShipment::observe(PurchaseOrderShipmentObserver::class);
        PurchaseOrderShipmentLine::observe(PurchaseOrderShipmentLineObserver::class);
    }
}
