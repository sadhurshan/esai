<?php

namespace App\Models;

use App\Enums\WebhookDeliveryStatus;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class WebhookDelivery extends CompanyScopedModel
{
    use HasFactory;

    protected $fillable = [
        'company_id',
        'subscription_id',
        'event',
        'payload',
        'signature',
        'status',
        'attempts',
        'max_attempts',
        'latency_ms',
        'response_code',
        'response_body',
        'last_error',
        'dispatched_at',
        'delivered_at',
        'dead_lettered_at',
    ];

    protected $casts = [
        'company_id' => 'integer',
        'payload' => 'array',
        'status' => WebhookDeliveryStatus::class,
        'attempts' => 'integer',
        'max_attempts' => 'integer',
        'latency_ms' => 'integer',
        'response_code' => 'integer',
        'dispatched_at' => 'datetime',
        'delivered_at' => 'datetime',
        'dead_lettered_at' => 'datetime',
    ];

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    public function subscription(): BelongsTo
    {
        return $this->belongsTo(WebhookSubscription::class, 'subscription_id');
    }
}
