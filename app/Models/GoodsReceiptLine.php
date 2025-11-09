<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class GoodsReceiptLine extends Model
{
    use HasFactory;
    use SoftDeletes;

    protected $fillable = [
        'goods_receipt_note_id',
        'purchase_order_line_id',
        'received_qty',
        'accepted_qty',
        'rejected_qty',
        'defect_notes',
        'attachment_ids',
    ];

    protected $casts = [
        'goods_receipt_note_id' => 'integer',
        'purchase_order_line_id' => 'integer',
        'received_qty' => 'integer',
        'accepted_qty' => 'integer',
        'rejected_qty' => 'integer',
        'attachment_ids' => 'array',
    ];

    protected $attributes = [
        'attachment_ids' => '[]',
    ];

    public function goodsReceiptNote(): BelongsTo
    {
        return $this->belongsTo(GoodsReceiptNote::class);
    }

    public function purchaseOrderLine(): BelongsTo
    {
        return $this->belongsTo(PurchaseOrderLine::class);
    }
}
