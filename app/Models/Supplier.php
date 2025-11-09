<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Supplier extends Model
{
    /** @use HasFactory<\Database\Factories\SupplierFactory> */
    use HasFactory;
    use SoftDeletes;

    protected $fillable = [
        'company_id',
        'name',
        'capabilities',
        'email',
        'phone',
        'website',
        'address',
        'country',
        'city',
        'status',
        'geo_lat',
        'geo_lng',
        'lead_time_days',
        'moq',
        'rating_avg',
        'verified_at',
    ];

    protected $casts = [
        'capabilities' => 'array',
        'geo_lat' => 'float',
        'geo_lng' => 'float',
        'lead_time_days' => 'integer',
        'moq' => 'integer',
        'rating_avg' => 'decimal:2',
        'verified_at' => 'datetime',
    ];

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    public function documents(): HasMany
    {
        return $this->hasMany(SupplierDocument::class);
    }

    public function quotes(): HasMany
    {
        return $this->hasMany(Quote::class);
    }
}
