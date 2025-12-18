<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class AssetProcedureLink extends CompanyScopedModel
{
    use HasFactory;
    use SoftDeletes;

    protected $fillable = [
        'company_id',
        'asset_id',
        'maintenance_procedure_id',
        'frequency_value',
        'frequency_unit',
        'last_done_at',
        'next_due_at',
        'meta',
    ];

    protected $casts = [
        'frequency_value' => 'integer',
        'last_done_at' => 'datetime',
        'next_due_at' => 'datetime',
        'meta' => 'array',
    ];

    public function asset(): BelongsTo
    {
        return $this->belongsTo(Asset::class);
    }

    public function procedure(): BelongsTo
    {
        return $this->belongsTo(MaintenanceProcedure::class, 'maintenance_procedure_id');
    }
}
