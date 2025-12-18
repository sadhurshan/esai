<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class InventoryMovement extends CompanyScopedModel
{
    use HasFactory;
    use SoftDeletes;

    protected $fillable = [
        'company_id',
        'movement_number',
        'type',
        'status',
        'moved_at',
        'reference_type',
        'reference_id',
        'notes',
        'created_by',
    ];

    protected $casts = [
        'moved_at' => 'datetime',
    ];

    public function lines(): HasMany
    {
        return $this->hasMany(InventoryMovementLine::class, 'movement_id');
    }

    public function transactions(): HasMany
    {
        return $this->hasMany(InventoryTxn::class, 'movement_id');
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}
