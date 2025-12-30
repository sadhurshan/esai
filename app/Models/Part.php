<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Str;

class Part extends CompanyScopedModel
{
    use HasFactory;
    use SoftDeletes;

    protected $fillable = [
        'company_id',
        'part_number',
        'name',
        'description',
        'category',
        'uom',
        'base_uom_id',
        'spec',
        'attributes',
        'default_location_code',
        'active',
        'meta',
    ];

    protected $casts = [
        'meta' => 'array',
        'attributes' => 'array',
        'active' => 'boolean',
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

    public function documents(): MorphMany
    {
        return $this->morphMany(Document::class, 'documentable');
    }

    public function forecastSnapshots(): HasMany
    {
        return $this->hasMany(ForecastSnapshot::class);
    }

    public function reorderSuggestions(): HasMany
    {
        return $this->hasMany(ReorderSuggestion::class);
    }

    public function preferredSuppliers(): HasMany
    {
        return $this->hasMany(PartPreferredSupplier::class);
    }

    public function tags(): HasMany
    {
        return $this->hasMany(PartTag::class);
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

    /**
     * @param array<int, string> $tags
     */
    public function syncTags(array $tags): void
    {
        $normalised = collect($tags)
            ->filter(static fn ($tag): bool => is_string($tag) && trim($tag) !== '')
            ->map(static function (string $tag): array {
                $clean = trim($tag);

                return [
                    'label' => $clean,
                    'normalized' => Str::lower($clean),
                ];
            })
            ->unique('normalized')
            ->values();

        if ($normalised->isEmpty()) {
            $this->tags()->delete();

            return;
        }

        $normalizedValues = $normalised->pluck('normalized')->all();

        $this->tags()
            ->whereNotIn('normalized_tag', $normalizedValues)
            ->delete();

        foreach ($normalised as $tag) {
            $this->tags()->updateOrCreate(
                ['normalized_tag' => $tag['normalized']],
                [
                    'company_id' => $this->company_id,
                    'tag' => $tag['label'],
                ]
            );
        }
    }
}
