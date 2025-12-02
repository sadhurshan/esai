<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PurchaseOrderShipmentLine extends Model
{
    use HasFactory;

    protected $fillable = [
        'purchase_order_shipment_id',
        'purchase_order_line_id',
        'qty_shipped',
    ];

    protected $casts = [
        'qty_shipped' => 'decimal:3',
    ];

    public function shipment(): BelongsTo
    {
        return $this->belongsTo(PurchaseOrderShipment::class, 'purchase_order_shipment_id');
    }

    public function purchaseOrderLine(): BelongsTo
    {
        return $this->belongsTo(PurchaseOrderLine::class, 'purchase_order_line_id');
    }
}
