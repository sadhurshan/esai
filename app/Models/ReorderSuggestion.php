<?php

namespace App\Models;

use App\Enums\ReorderMethod;
use App\Enums\ReorderStatus;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ReorderSuggestion extends Model
{
    /** @use HasFactory<\Database\Factories\ReorderSuggestionFactory> */
    use HasFactory;

    protected $fillable = [
        'company_id',
        'part_id',
        'warehouse_id',
        'suggested_qty',
        'reason',
        'horizon_start',
        'horizon_end',
        'method',
        'generated_at',
        'accepted_at',
        'status',
        'pr_id',
    ];

    protected $casts = [
        'suggested_qty' => 'decimal:3',
        'horizon_start' => 'date',
        'horizon_end' => 'date',
        'generated_at' => 'datetime',
        'accepted_at' => 'datetime',
        'method' => ReorderMethod::class,
        'status' => ReorderStatus::class,
    ];

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    public function part(): BelongsTo
    {
        return $this->belongsTo(Part::class);
    }

    public function warehouse(): BelongsTo
    {
        return $this->belongsTo(Warehouse::class);
    }

    public function purchaseRequisition(): BelongsTo
    {
        return $this->belongsTo(PurchaseRequisition::class, 'pr_id');
    }
}
