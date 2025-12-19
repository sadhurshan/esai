<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class InventoryWhatIfSnapshot extends CompanyScopedModel
{
    use HasFactory;
    use SoftDeletes;

    protected $table = 'inventory_whatif_snapshots';

    protected $fillable = [
        'company_id',
        'user_id',
        'scenario_name',
        'part_identifier',
        'input_snapshot',
        'result_snapshot',
        'projected_stockout_risk',
        'expected_stockout_days',
        'expected_holding_cost_change',
        'recommendation',
        'assumptions_json',
        'confidence',
        'needs_human_review',
        'warnings_json',
        'citations_json',
        'meta',
    ];

    protected $casts = [
        'input_snapshot' => 'array',
        'result_snapshot' => 'array',
        'assumptions_json' => 'array',
        'warnings_json' => 'array',
        'citations_json' => 'array',
        'meta' => 'array',
        'projected_stockout_risk' => 'float',
        'expected_stockout_days' => 'float',
        'expected_holding_cost_change' => 'float',
        'confidence' => 'float',
        'needs_human_review' => 'boolean',
    ];

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
