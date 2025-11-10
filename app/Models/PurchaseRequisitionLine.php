<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class PurchaseRequisitionLine extends Model
{
    use HasFactory;
    use SoftDeletes;

    protected $fillable = [
        'purchase_requisition_id',
        'part_id',
        'description',
        'uom',
        'qty',
        'unit_price',
        'warehouse_id',
        'bin_id',
        'reason',
        'suggestion_id',
    ];

    protected $casts = [
        'qty' => 'decimal:3',
        'unit_price' => 'decimal:4',
    ];

    public function requisition(): BelongsTo
    {
        return $this->belongsTo(PurchaseRequisition::class, 'purchase_requisition_id');
    }

    public function part(): BelongsTo
    {
        return $this->belongsTo(Part::class);
    }

    public function warehouse(): BelongsTo
    {
        return $this->belongsTo(Warehouse::class);
    }

    public function bin(): BelongsTo
    {
        return $this->belongsTo(Bin::class);
    }

    public function suggestion(): BelongsTo
    {
        return $this->belongsTo(ReorderSuggestion::class, 'suggestion_id');
    }
}
