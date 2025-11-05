<?php

namespace App\Models;

use App\Enums\CompanyStatus;
use App\Enums\CompanySupplierStatus;
use App\Models\CompanyDocument;
use App\Models\Plan;
use App\Models\RFQ;
use App\Models\Subscription;
use App\Models\Supplier;
use App\Models\SupplierApplication;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Carbon;

class Company extends Model
{
    use HasFactory;
    use SoftDeletes;

    protected $fillable = [
        'name',
        'slug',
        'status',
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
        'storage_used_mb',
        'stripe_id',
        'plan_code',
        'trial_ends_at',
        'rejection_reason',
        'supplier_status',
        'directory_visibility',
        'supplier_profile_completed_at',
        'is_verified',
        'verified_at',
        'verified_by',
    ];

    protected $casts = [
        'status' => CompanyStatus::class,
        'supplier_status' => CompanySupplierStatus::class,
        'is_verified' => 'boolean',
        'verified_at' => 'datetime',
        'supplier_profile_completed_at' => 'datetime',
        'trial_ends_at' => 'datetime',
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
        return $this->belongsTo(Plan::class, 'plan_code', 'code');
    }

    public function subscriptions(): HasMany
    {
        return $this->hasMany(Subscription::class);
    }

    public function currentSubscription(): ?Subscription
    {
        return $this->subscriptions()
            ->latest('created_at')
            ->first();
    }

    public function billingStatus(): string
    {
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

    public function rfqs(): HasMany
    {
        return $this->hasMany(RFQ::class);
    }

    public function suppliers(): HasMany
    {
        return $this->hasMany(Supplier::class);
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

    public function scopeListedSuppliers(Builder $query): Builder
    {
        return $query
            ->where('supplier_status', CompanySupplierStatus::Approved->value)
            ->where('directory_visibility', 'public')
            ->whereNotNull('supplier_profile_completed_at');
    }
}
