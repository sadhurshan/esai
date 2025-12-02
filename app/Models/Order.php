<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;


/**
 * @property int $id
 * @property int $company_id
 * @property int $purchase_order_id
 * @property int|null $supplier_company_id
 * @property string $number
 * @property string $so_number
 * @property string $status
 * @property string $currency
 * @property int $total_minor
 * @property int $ordered_qty
 * @property int $shipped_qty
 * @property array|null $timeline
 * @property array|null $shipping
 * @property array|null $metadata
 * @property \Illuminate\Support\Carbon|null $ordered_at
 * @property \Illuminate\Support\Carbon|null $acknowledged_at
 * @property \Illuminate\Support\Carbon|null $delivered_at
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 */
class Order extends CompanyScopedModel
{
    /** @use HasFactory<\Database\Factories\OrderFactory> */
    use HasFactory;
    use SoftDeletes;

    /**
     * @var list<string>
     */
    protected $fillable = [
        'company_id',
        'purchase_order_id',
        'supplier_company_id',
        'number',
        'so_number',
        'status',
        'currency',
        'total_minor',
        'ordered_qty',
        'shipped_qty',
        'timeline',
        'shipping',
        'metadata',
        'ordered_at',
        'acknowledged_at',
        'delivered_at',
    ];

    /**
     * @var array<string, string>
     */
    protected $casts = [
        'company_id' => 'integer',
        'purchase_order_id' => 'integer',
        'supplier_company_id' => 'integer',
        'total_minor' => 'integer',
        'ordered_qty' => 'integer',
        'shipped_qty' => 'integer',
        'timeline' => 'array',
        'shipping' => 'array',
        'metadata' => 'array',
        'ordered_at' => 'datetime',
        'acknowledged_at' => 'datetime',
        'delivered_at' => 'datetime',
    ];

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    public function supplierCompany(): BelongsTo
    {
        return $this->belongsTo(Company::class, 'supplier_company_id');
    }

    public function purchaseOrder(): BelongsTo
    {
        return $this->belongsTo(PurchaseOrder::class);
    }
}
