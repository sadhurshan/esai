<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class Ncr extends CompanyScopedModel
{
    use HasFactory;
    use SoftDeletes;

    protected $table = 'ncrs';

    protected $fillable = [
        'company_id',
        'goods_receipt_note_id',
        'purchase_order_line_id',
        'raised_by_id',
        'status',
        'disposition',
        'reason',
        'documents_json',
    ];

    protected $casts = [
        'company_id' => 'integer',
        'goods_receipt_note_id' => 'integer',
        'purchase_order_line_id' => 'integer',
        'raised_by_id' => 'integer',
        'documents_json' => 'array',
    ];

    protected $attributes = [
        'documents_json' => '[]',
    ];

    public function goodsReceiptNote(): BelongsTo
    {
        return $this->belongsTo(GoodsReceiptNote::class);
    }

    public function purchaseOrderLine(): BelongsTo
    {
        return $this->belongsTo(PurchaseOrderLine::class);
    }

    public function raisedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'raised_by_id');
    }
}
