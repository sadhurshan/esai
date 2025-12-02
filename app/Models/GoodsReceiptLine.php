<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
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
        'ncr_flag',
    ];

    protected $casts = [
        'goods_receipt_note_id' => 'integer',
        'purchase_order_line_id' => 'integer',
        'received_qty' => 'integer',
        'accepted_qty' => 'integer',
        'rejected_qty' => 'integer',
        'attachment_ids' => 'array',
        'ncr_flag' => 'boolean',
    ];

    protected $attributes = [
        'attachment_ids' => '[]',
        'ncr_flag' => false,
    ];

    public function goodsReceiptNote(): BelongsTo
    {
        return $this->belongsTo(GoodsReceiptNote::class);
    }

    public function purchaseOrderLine(): BelongsTo
    {
        return $this->belongsTo(PurchaseOrderLine::class);
    }

    public function ncrs(): HasMany
    {
        return $this->hasMany(Ncr::class, 'purchase_order_line_id', 'purchase_order_line_id')
            ->where('goods_receipt_note_id', $this->goods_receipt_note_id);
    }
}
