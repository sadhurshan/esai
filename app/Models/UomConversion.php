<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class UomConversion extends Model
{
    use HasFactory;
    use SoftDeletes;

    protected $fillable = [
        'from_uom_id',
        'to_uom_id',
        'factor',
        'offset',
    ];

    protected $casts = [
        'factor' => 'decimal:12',
        'offset' => 'decimal:12',
    ];

    public function from(): BelongsTo
    {
        return $this->belongsTo(Uom::class, 'from_uom_id');
    }

    public function to(): BelongsTo
    {
        return $this->belongsTo(Uom::class, 'to_uom_id');
    }
}
