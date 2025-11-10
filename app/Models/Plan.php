<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Plan extends Model
{
    use HasFactory;

    protected $fillable = [
        'code',
        'name',
        'price_usd',
        'rfqs_per_month',
        'invoices_per_month',
        'users_max',
        'storage_gb',
        'erp_integrations_max',
        'analytics_enabled',
        'analytics_history_months',
        'risk_scores_enabled',
        'risk_history_months',
        'approvals_enabled',
        'approval_levels_limit',
        'rma_enabled',
        'rma_monthly_limit',
        'credit_notes_enabled',
    ];

    protected $casts = [
        'price_usd' => 'decimal:2',
        'analytics_enabled' => 'boolean',
        'analytics_history_months' => 'integer',
        'risk_scores_enabled' => 'boolean',
        'risk_history_months' => 'integer',
        'approvals_enabled' => 'boolean',
        'approval_levels_limit' => 'integer',
        'rma_enabled' => 'boolean',
        'rma_monthly_limit' => 'integer',
        'credit_notes_enabled' => 'boolean',
    ];

    public function companies(): HasMany
    {
        return $this->hasMany(Company::class, 'plan_code', 'code');
    }
}
