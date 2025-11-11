<?php

namespace App\Models;

use App\Enums\WebhookDeliveryStatus;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class WebhookDelivery extends Model
{
    use HasFactory;

    protected $fillable = [
        'subscription_id',
        'event',
        'payload',
        'signature',
        'status',
        'attempts',
        'last_error',
        'dispatched_at',
        'delivered_at',
    ];

    protected $casts = [
        'payload' => 'array',
        'status' => WebhookDeliveryStatus::class,
        'attempts' => 'integer',
        'dispatched_at' => 'datetime',
        'delivered_at' => 'datetime',
    ];

    public function subscription(): BelongsTo
    {
        return $this->belongsTo(WebhookSubscription::class, 'subscription_id');
    }
}
