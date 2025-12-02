<?php

namespace App\Models;

use App\Enums\RiskGrade;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class SupplierRiskScore extends CompanyScopedModel
{
    use HasFactory;
    use SoftDeletes;

    protected $fillable = [
        'company_id',
        'supplier_id',
        'on_time_delivery_rate',
        'defect_rate',
        'price_volatility',
        'lead_time_volatility',
        'responsiveness_rate',
        'overall_score',
        'risk_grade',
        'badges_json',
        'meta',
    ];

    protected $casts = [
        'on_time_delivery_rate' => 'decimal:2',
        'defect_rate' => 'decimal:2',
        'price_volatility' => 'decimal:4',
        'lead_time_volatility' => 'decimal:4',
        'responsiveness_rate' => 'decimal:2',
        'overall_score' => 'decimal:4',
        'risk_grade' => RiskGrade::class,
        'badges_json' => 'array',
        'meta' => 'array',
    ];

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    public function supplier(): BelongsTo
    {
        return $this->belongsTo(Supplier::class);
    }
}
