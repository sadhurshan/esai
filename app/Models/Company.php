<?php

namespace App\Models;

use App\Enums\CompanyStatus;
use App\Enums\CompanySupplierStatus;
use App\Models\CompanyDocument;
use App\Models\CompanyProfile;
use App\Models\CopilotPrompt;
use App\Models\Plan;
use App\Models\RFQ;
use App\Models\Subscription;
use App\Models\SupplierRiskScore;
use App\Models\SupplierEsgRecord;
use App\Models\Supplier;
use App\Models\SupplierApplication;
use App\Models\PurchaseRequisition;
use App\Models\User;
use App\Models\ApprovalRule;
use App\Models\Approval;
use App\Models\Delegation;
use App\Models\Rma;
use App\Models\CreditNote;
use App\Models\Warehouse;
use App\Models\Bin;
use App\Models\Inventory;
use App\Models\InventorySetting;
use App\Models\InventoryTxn;
use App\Models\ReorderSuggestion;
use App\Models\ForecastSnapshot;
use App\Models\CompanyLocaleSetting;
use App\Models\CompanyMoneySetting;
use App\Models\TaxCode;
use App\Models\LineTax;
use App\Models\CompanyFeatureFlag;
use App\Models\ApiKey;
use App\Models\RateLimit;
use App\Models\WebhookSubscription;
use App\Models\WebhookDelivery;
use App\Models\DigitalTwin;
use App\Models\UsageSnapshot;
use App\Models\CompanyAiSetting;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasManyThrough;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Carbon;

class Company extends Model
{
    use HasFactory;
    use SoftDeletes;

    /**
     * Fields that must be populated to consider buyer onboarding complete.
     *
     * @var array<int, string>
     */
    public const BUYER_ONBOARDING_REQUIRED_FIELDS = [
        'registration_no',
        'tax_id',
        'country',
        'email_domain',
        'primary_contact_name',
        'primary_contact_email',
        'primary_contact_phone',
    ];

    protected $fillable = [
        'name',
        'slug',
        'status',
        'start_mode',
        'registration_no',
        'tax_id',
        'country',
        'email_domain',
        'primary_contact_name',
        'primary_contact_email',
        'primary_contact_phone',
        'address',
        'phone',
        'website',
        'region',
        'owner_user_id',
        'rfqs_monthly_used',
        'invoices_monthly_used',
        'analytics_usage_months',
        'analytics_last_generated_at',
        'risk_scores_monthly_used',
        'storage_used_mb',
        'stripe_id',
    'plan_id',
        'plan_code',
        'trial_ends_at',
    'notes',
        'rejection_reason',
        'supplier_status',
        'directory_visibility',
        'supplier_profile_completed_at',
        'is_verified',
        'verified_at',
        'verified_by',
        'rma_monthly_used',
        'credit_notes_monthly_used',
    ];

    protected $casts = [
        'status' => CompanyStatus::class,
        'supplier_status' => CompanySupplierStatus::class,
        'is_verified' => 'boolean',
        'verified_at' => 'datetime',
        'supplier_profile_completed_at' => 'datetime',
        'trial_ends_at' => 'datetime',
        'analytics_usage_months' => 'integer',
        'analytics_last_generated_at' => 'datetime',
        'risk_scores_monthly_used' => 'integer',
        'rma_monthly_used' => 'integer',
        'credit_notes_monthly_used' => 'integer',
    ];

    public function isSupplierApproved(): bool
    {
        return $this->supplier_status === CompanySupplierStatus::Approved;
    }

    public function isSupplierListed(): bool
    {
        return $this->isSupplierApproved()
            && $this->directory_visibility === 'public'
            && $this->supplier_profile_completed_at !== null;
    }

    public function owner(): BelongsTo
    {
        return $this->belongsTo(User::class, 'owner_user_id');
    }

    public function users(): HasMany
    {
        return $this->hasMany(User::class);
    }

    public function plan(): BelongsTo
    {
        return $this->belongsTo(Plan::class, 'plan_id');
    }

    public function copilotPrompts(): HasMany
    {
        return $this->hasMany(CopilotPrompt::class);
    }

    public function profile(): HasOne
    {
        return $this->hasOne(CompanyProfile::class);
    }

    public function subscriptions(): HasMany
    {
        return $this->hasMany(Subscription::class);
    }

    public function digitalTwins(): HasMany
    {
        return $this->hasMany(DigitalTwin::class);
    }

    public function currentSubscription(): ?Subscription
    {
        if ($this->relationLoaded('subscriptions')) {
            /** @var \Illuminate\Support\Collection<int, Subscription> $subscriptions */
            $subscriptions = $this->getRelation('subscriptions');

            return $subscriptions
                ->sortByDesc(static fn (Subscription $subscription) => $subscription->created_at)
                ->first();
        }

        return $this->subscriptions()
            ->latest('created_at')
            ->first();
    }

    public function billingStatus(): string
    {
        $plan = $this->plan;

        if ($plan !== null) {
            $price = $plan->price_usd;

            if ($plan->code === 'community' || ($price !== null && (float) $price <= 0)) {
                return 'active';
            }
        }

        if ($this->trial_ends_at instanceof Carbon && $this->trial_ends_at->isFuture()) {
            return 'trialing';
        }

        $subscription = $this->currentSubscription();

        if ($subscription === null) {
            return 'inactive';
        }

        if ($subscription->isActive()) {
            return 'active';
        }

        if ($subscription->isPastDue()) {
            return 'past_due';
        }

        if ($subscription->isCancelled()) {
            return 'cancelled';
        }

        return 'inactive';
    }

    public function isInBillingGracePeriod(): bool
    {
        return $this->billingGraceEndsAt() !== null;
    }

    public function billingGraceEndsAt(): ?Carbon
    {
        $subscription = $this->currentSubscription();

        if ($subscription === null) {
            return null;
        }

        return $subscription->graceEndsAt();
    }

    public function billingLockDate(): ?Carbon
    {
        $subscription = $this->currentSubscription();

        if ($subscription === null) {
            return null;
        }

        if ($subscription->ends_at instanceof Carbon) {
            return $subscription->ends_at;
        }

        return null;
    }

    public function rfqs(): HasMany
    {
        return $this->hasMany(RFQ::class);
    }

    public function suppliers(): HasMany
    {
        return $this->hasMany(Supplier::class);
    }

    public function supplierRiskScores(): HasMany
    {
        return $this->hasMany(SupplierRiskScore::class);
    }

    public function supplierEsgRecords(): HasMany
    {
        return $this->hasMany(SupplierEsgRecord::class);
    }

    public function approvalRules(): HasMany
    {
        return $this->hasMany(ApprovalRule::class);
    }

    public function approvals(): HasMany
    {
        return $this->hasMany(Approval::class);
    }

    public function delegations(): HasMany
    {
        return $this->hasMany(Delegation::class);
    }

    public function rmas(): HasMany
    {
        return $this->hasMany(Rma::class);
    }

    public function creditNotes(): HasMany
    {
        return $this->hasMany(CreditNote::class);
    }

    public function warehouses(): HasMany
    {
        return $this->hasMany(Warehouse::class);
    }

    public function bins(): HasMany
    {
        return $this->hasMany(Bin::class);
    }

    public function inventories(): HasMany
    {
        return $this->hasMany(Inventory::class);
    }

    public function inventorySettings(): HasMany
    {
        return $this->hasMany(InventorySetting::class);
    }

    public function inventoryTransactions(): HasMany
    {
        return $this->hasMany(InventoryTxn::class);
    }

    public function reorderSuggestions(): HasMany
    {
        return $this->hasMany(ReorderSuggestion::class);
    }

    public function forecastSnapshots(): HasMany
    {
        return $this->hasMany(ForecastSnapshot::class);
    }

    public function usageSnapshots(): HasMany
    {
        return $this->hasMany(UsageSnapshot::class);
    }

    public function moneySetting(): HasOne
    {
        return $this->hasOne(CompanyMoneySetting::class);
    }

    public function localeSetting(): HasOne
    {
        return $this->hasOne(CompanyLocaleSetting::class);
    }

    public function taxCodes(): HasMany
    {
        return $this->hasMany(TaxCode::class);
    }

    public function lineTaxes(): HasMany
    {
        return $this->hasMany(LineTax::class);
    }

    public function featureFlags(): HasMany
    {
        return $this->hasMany(CompanyFeatureFlag::class);
    }

    public function aiSetting(): HasOne
    {
        return $this->hasOne(CompanyAiSetting::class);
    }

    public function apiKeys(): HasMany
    {
        return $this->hasMany(ApiKey::class);
    }

    public function rateLimits(): HasMany
    {
        return $this->hasMany(RateLimit::class);
    }

    public function webhookSubscriptions(): HasMany
    {
        return $this->hasMany(WebhookSubscription::class);
    }

    public function webhookDeliveries(): HasManyThrough
    {
        return $this->hasManyThrough(
            WebhookDelivery::class,
            WebhookSubscription::class,
            'company_id',
            'subscription_id'
        );
    }

    public function purchaseRequisitions(): HasMany
    {
        return $this->hasMany(PurchaseRequisition::class);
    }

    public function supplier(): HasOne
    {
        return $this->hasOne(Supplier::class, 'company_id');
    }

    public function supplierProfile(): HasOne
    {
        return $this->hasOne(Supplier::class, 'company_id');
    }

    public function supplierApplications(): HasMany
    {
        return $this->hasMany(SupplierApplication::class);
    }

    public function documents(): HasMany
    {
        return $this->hasMany(CompanyDocument::class);
    }

    public function hasCompletedBuyerOnboarding(): bool
    {
        foreach (self::BUYER_ONBOARDING_REQUIRED_FIELDS as $attribute) {
            if (blank($this->{$attribute})) {
                return false;
            }
        }

        return true;
    }

    /**
     * @return array<int, string>
     */
    public function buyerOnboardingMissingFields(): array
    {
        $missing = [];

        foreach (self::BUYER_ONBOARDING_REQUIRED_FIELDS as $attribute) {
            if (blank($this->{$attribute})) {
                $missing[] = $attribute;
            }
        }

        return $missing;
    }

    public function scopeListedSuppliers(Builder $query): Builder
    {
        return $query
            ->where('supplier_status', CompanySupplierStatus::Approved->value)
            ->where('directory_visibility', 'public')
            ->whereNotNull('supplier_profile_completed_at');
    }
}
