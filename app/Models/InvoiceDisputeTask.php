<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class InvoiceDisputeTask extends CompanyScopedModel
{
    use HasFactory;
    use SoftDeletes;

    protected $fillable = [
        'company_id',
        'invoice_id',
        'purchase_order_id',
        'goods_receipt_note_id',
        'resolution_type',
        'status',
        'summary',
        'owner_role',
        'requires_hold',
        'due_at',
        'actions',
        'impacted_lines',
        'next_steps',
        'notes',
        'reason_codes',
        'created_by',
        'resolved_by',
        'resolved_at',
    ];

    protected $casts = [
        'company_id' => 'integer',
        'invoice_id' => 'integer',
        'purchase_order_id' => 'integer',
        'goods_receipt_note_id' => 'integer',
        'requires_hold' => 'boolean',
        'due_at' => 'datetime',
        'actions' => 'array',
        'impacted_lines' => 'array',
        'next_steps' => 'array',
        'notes' => 'array',
        'reason_codes' => 'array',
        'created_by' => 'integer',
        'resolved_by' => 'integer',
        'resolved_at' => 'datetime',
    ];

    public function invoice(): BelongsTo
    {
        return $this->belongsTo(Invoice::class);
    }

    public function purchaseOrder(): BelongsTo
    {
        return $this->belongsTo(PurchaseOrder::class);
    }

    public function goodsReceiptNote(): BelongsTo
    {
        return $this->belongsTo(GoodsReceiptNote::class);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function resolver(): BelongsTo
    {
        return $this->belongsTo(User::class, 'resolved_by');
    }
}
