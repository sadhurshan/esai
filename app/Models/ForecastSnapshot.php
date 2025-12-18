<?php

namespace App\Models;

use App\Enums\ReorderMethod;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ForecastSnapshot extends CompanyScopedModel
{
    /** @use HasFactory<\Database\Factories\ForecastSnapshotFactory> */
    use HasFactory;

    protected $fillable = [
        'company_id',
        'part_id',
        'period_start',
        'period_end',
        'demand_qty',
        'avg_daily_demand',
        'method',
        'alpha',
        'on_hand_qty',
        'on_order_qty',
        'safety_stock_qty',
        'projected_runout_days',
        'horizon_days',
    ];

    protected $casts = [
        'period_start' => 'date',
        'period_end' => 'date',
        'demand_qty' => 'decimal:3',
        'avg_daily_demand' => 'decimal:3',
        'method' => 'string',
        'alpha' => 'decimal:3',
        'on_hand_qty' => 'decimal:3',
        'on_order_qty' => 'decimal:3',
        'safety_stock_qty' => 'decimal:3',
        'projected_runout_days' => 'decimal:2',
        'horizon_days' => 'integer',
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
