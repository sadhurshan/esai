<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\SoftDeletes;

class Part extends Model
{
    use HasFactory;
    use SoftDeletes;

    protected $fillable = [
        'company_id',
        'part_number',
        'name',
        'uom',
        'base_uom_id',
        'spec',
        'meta',
    ];

    protected $casts = [
        'meta' => 'array',
    ];

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    public function baseUom(): BelongsTo
    {
        return $this->belongsTo(Uom::class, 'base_uom_id');
    }

    public function bomItems(): HasMany
    {
        return $this->hasMany(AssetBomItem::class);
    }

    public function inventories(): HasMany
    {
        return $this->hasMany(Inventory::class);
    }

    public function inventorySetting(): HasOne
    {
        return $this->hasOne(InventorySetting::class);
    }

    public function forecastSnapshots(): HasMany
    {
        return $this->hasMany(ForecastSnapshot::class);
    }

    public function reorderSuggestions(): HasMany
    {
        return $this->hasMany(ReorderSuggestion::class);
    }

    public function purchaseRequisitionLines(): HasMany
    {
        return $this->hasMany(PurchaseRequisitionLine::class);
    }

    public function getBaseUomCodeAttribute(): ?string
    {
        if ($this->relationLoaded('baseUom') && $this->baseUom !== null) {
            return $this->baseUom->code;
        }

        if ($this->base_uom_id === null) {
            return null;
        }

        return $this->baseUom?->code;
    }
}
