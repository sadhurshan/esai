<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class DigitalTwinSpec extends Model
{
    /** @use HasFactory<\Database\Factories\DigitalTwinSpecFactory> */
    use HasFactory;
    use SoftDeletes;

    protected $table = 'digital_twin_specs';

    /**
     * @var list<string>
     */
    protected $fillable = [
        'digital_twin_id',
        'name',
        'value',
        'uom',
        'sort_order',
    ];

    /**
     * @var array<string, string>
     */
    protected $casts = [
        'sort_order' => 'integer',
    ];

    public function digitalTwin(): BelongsTo
    {
        return $this->belongsTo(DigitalTwin::class);
    }
}
