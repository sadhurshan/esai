<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class InvoiceMatch extends Model
{
    use HasFactory;

    protected $fillable = [
        'invoice_id',
        'purchase_order_id',
        'goods_receipt_note_id',
        'result',
        'details',
    ];

    protected $casts = [
        'invoice_id' => 'integer',
        'purchase_order_id' => 'integer',
        'goods_receipt_note_id' => 'integer',
        'details' => 'array',
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
}
