<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PurchaseOrderDelivery extends Model
{
    use HasFactory;

    protected $fillable = [
        'purchase_order_id',
        'created_by_id',
        'channel',
        'recipients_to',
        'recipients_cc',
        'message',
        'status',
        'delivery_reference',
        'sent_at',
        'response_meta',
        'error_reason',
    ];

    protected $casts = [
        'recipients_to' => 'array',
        'recipients_cc' => 'array',
        'response_meta' => 'array',
        'sent_at' => 'datetime',
    ];

    public function purchaseOrder(): BelongsTo
    {
        return $this->belongsTo(PurchaseOrder::class);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by_id');
    }
}
