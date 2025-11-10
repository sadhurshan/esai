<?php

namespace App\Models;

use App\Enums\ReorderMethod;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ForecastSnapshot extends Model
{
    /** @use HasFactory<\Database\Factories\ForecastSnapshotFactory> */
    use HasFactory;

    protected $fillable = [
        'company_id',
        'part_id',
        'period_start',
        'period_end',
        'demand_qty',
        'method',
        'alpha',
    ];

    protected $casts = [
        'period_start' => 'date',
        'period_end' => 'date',
        'demand_qty' => 'decimal:3',
        'method' => 'string',
        'alpha' => 'decimal:3',
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
