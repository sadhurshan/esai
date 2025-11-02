<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * @property int $id
 * @property string $name
 * @property int $rating
 * @property array<int, string> $capabilities
 * @property array<int, string> $materials
 * @property string $location_region
 * @property int $min_order_qty
 * @property int $avg_response_hours
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @property-read Collection<int, \App\Models\RFQQuote> $rfqQuotes
 */
class Supplier extends Model
{
    /** @use HasFactory<\Database\Factories\SupplierFactory> */
    use HasFactory;

    /**
     * @var list<string>
     */
    protected $fillable = [
        'name',
        'rating',
        'capabilities',
        'materials',
        'location_region',
        'min_order_qty',
        'avg_response_hours',
    ];

    /**
     * @var array<string, string>
     */
    protected $casts = [
        'rating' => 'integer',
        'capabilities' => 'array',
        'materials' => 'array',
        'min_order_qty' => 'integer',
        'avg_response_hours' => 'integer',
    ];

    public function rfqQuotes(): HasMany
    {
        return $this->hasMany(RFQQuote::class);
    }
}
