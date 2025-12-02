<?php

namespace App\Models;

use App\Enums\InventoryPolicy;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class InventorySetting extends CompanyScopedModel
{
    /** @use HasFactory<\Database\Factories\InventorySettingFactory> */
    use HasFactory;

    protected $fillable = [
        'company_id',
        'part_id',
        'min_qty',
        'max_qty',
        'safety_stock',
        'reorder_qty',
        'lead_time_days',
        'lot_size',
        'policy',
    ];

    protected $casts = [
        'min_qty' => 'decimal:3',
        'max_qty' => 'decimal:3',
        'safety_stock' => 'decimal:3',
        'reorder_qty' => 'decimal:3',
        'lead_time_days' => 'integer',
        'lot_size' => 'decimal:3',
        'policy' => InventoryPolicy::class,
    ];

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    public function part(): BelongsTo
    {
        return $this->belongsTo(Part::class);
    }
}
