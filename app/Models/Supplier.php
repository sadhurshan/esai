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
        'rating',
        'capabilities',
        'materials',
        'location_region',
        'min_order_qty',
        'avg_response_hours',
        'country',
        'city',
        'email',
        'phone',
        'website',
        'status',
        'rating_avg',
    ];

    protected $casts = [
        'capabilities' => 'array',
        'materials' => 'array',
        'rating_avg' => 'decimal:2',
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
