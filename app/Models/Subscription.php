<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

class Subscription extends CompanyScopedModel
{
    use HasFactory;

    protected $fillable = [
        'company_id',
        'customer_id',
        'name',
        'stripe_id',
        'stripe_status',
        'stripe_plan',
        'quantity',
        'trial_ends_at',
        'ends_at',
        'checkout_session_id',
        'checkout_status',
        'checkout_url',
        'checkout_started_at',
    ];

    protected $casts = [
        'trial_ends_at' => 'datetime',
        'ends_at' => 'datetime',
        'checkout_started_at' => 'datetime',
    ];

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    public function isActive(): bool
    {
        if ($this->stripe_status === null) {
            return false;
        }

        if (! in_array($this->stripe_status, ['active', 'trialing'], true)) {
            return false;
        }

        if ($this->ends_at instanceof Carbon && $this->ends_at->isPast()) {
            return false;
        }

        return true;
    }

    public function isPastDue(): bool
    {
        return $this->stripe_status === 'past_due';
    }

    public function isCancelled(): bool
    {
        if ($this->stripe_status === null) {
            return false;
        }

        if (in_array($this->stripe_status, ['canceled', 'cancelled', 'unpaid', 'incomplete_expired'], true)) {
            return true;
        }

        if ($this->ends_at instanceof Carbon) {
            return $this->ends_at->isPast();
        }

        return false;
    }

    public function isInGracePeriod(): bool
    {
        return $this->isPastDue()
            && $this->ends_at instanceof Carbon
            && $this->ends_at->isFuture();
    }

    public function graceEndsAt(): ?Carbon
    {
        return $this->isInGracePeriod() ? $this->ends_at : null;
    }
}
