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
        'users_max',
        'storage_gb',
        'erp_integrations_max',
    ];

    protected $casts = [
        'price_usd' => 'decimal:2',
    ];

    public function companies(): HasMany
    {
        return $this->hasMany(Company::class, 'plan_code', 'code');
    }
}
