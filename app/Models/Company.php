<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Carbon;
use App\Models\Plan;
use App\Models\Subscription;

class Company extends Model
{
    use HasFactory;
    use SoftDeletes;

    protected $fillable = [
        'name',
        'slug',
        'status',
        'region',
        'owner_user_id',
        'rfqs_monthly_used',
        'storage_used_mb',
        'stripe_id',
        'plan_code',
        'trial_ends_at',
    ];

    protected $casts = [
        'trial_ends_at' => 'datetime',
    ];

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
}
