<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AssetBomItem extends Model
{
    use HasFactory;

    protected $fillable = [
        'asset_id',
        'part_id',
        'quantity',
        'uom',
        'criticality',
        'notes',
    ];

    protected $casts = [
        'quantity' => 'float',
    ];

    public function asset(): BelongsTo
    {
        return $this->belongsTo(Asset::class);
    }

    public function part(): BelongsTo
    {
        return $this->belongsTo(Part::class);
    }
}
