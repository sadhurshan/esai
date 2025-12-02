<?php

namespace App\Models;

use App\Enums\DigitalTwinAssetType;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class DigitalTwinAsset extends Model
{
    /** @use HasFactory<\Database\Factories\DigitalTwinAssetFactory> */
    use HasFactory;
    use SoftDeletes;

    protected $table = 'digital_twin_assets';

    /**
     * @var list<string>
     */
    protected $fillable = [
        'digital_twin_id',
        'type',
        'disk',
        'path',
        'filename',
        'size_bytes',
        'checksum',
        'mime',
        'is_primary',
        'meta',
    ];

    /**
     * @var array<string, string>
     */
    protected $casts = [
        'type' => DigitalTwinAssetType::class,
        'size_bytes' => 'integer',
        'is_primary' => 'boolean',
        'meta' => 'array',
    ];

    public function digitalTwin(): BelongsTo
    {
        return $this->belongsTo(DigitalTwin::class);
    }
}
