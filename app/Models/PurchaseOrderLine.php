<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PurchaseOrderLine extends Model
{
    use HasFactory;

    protected $table = 'po_lines';

    protected $fillable = [
        'purchase_order_id',
        'rfq_item_id',
        'line_no',
        'description',
        'quantity',
        'uom',
        'unit_price',
        'delivery_date',
        'received_qty',
        'receiving_status',
    ];

    protected $casts = [
        'line_no' => 'integer',
        'quantity' => 'integer',
        'unit_price' => 'decimal:2',
        'delivery_date' => 'date',
        'received_qty' => 'integer',
    ];

    public function purchaseOrder(): BelongsTo
    {
        return $this->belongsTo(PurchaseOrder::class);
    }

    public function rfqItem(): BelongsTo
    {
        return $this->belongsTo(RfqItem::class);
    }
}
