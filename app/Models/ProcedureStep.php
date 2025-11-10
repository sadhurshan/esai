<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ProcedureStep extends Model
{
    use HasFactory;

    protected $fillable = [
        'maintenance_procedure_id',
        'step_no',
        'title',
        'instruction_md',
        'estimated_minutes',
        'attachments_json',
    ];

    protected $casts = [
        'estimated_minutes' => 'integer',
        'attachments_json' => 'array',
    ];

    public function procedure(): BelongsTo
    {
        return $this->belongsTo(MaintenanceProcedure::class, 'maintenance_procedure_id');
    }
}
