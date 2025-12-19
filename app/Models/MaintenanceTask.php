<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class MaintenanceTask extends CompanyScopedModel
{
    use HasFactory;
    use SoftDeletes;

    protected $fillable = [
        'company_id',
        'asset_id',
        'maintenance_procedure_id',
        'created_by',
        'title',
        'status',
        'summary',
        'urgency',
        'environment',
        'asset_reference',
        'safety_notes_json',
        'diagnostic_steps_json',
        'likely_causes_json',
        'recommended_actions_json',
        'escalation_rules_json',
        'citations_json',
        'meta',
        'confidence',
        'needs_human_review',
        'warnings_json',
        'due_at',
    ];

    protected $casts = [
        'safety_notes_json' => 'array',
        'diagnostic_steps_json' => 'array',
        'likely_causes_json' => 'array',
        'recommended_actions_json' => 'array',
        'escalation_rules_json' => 'array',
        'citations_json' => 'array',
        'warnings_json' => 'array',
        'meta' => 'array',
        'confidence' => 'float',
        'needs_human_review' => 'boolean',
        'due_at' => 'datetime',
    ];

    public function asset(): BelongsTo
    {
        return $this->belongsTo(Asset::class);
    }

    public function procedure(): BelongsTo
    {
        return $this->belongsTo(MaintenanceProcedure::class, 'maintenance_procedure_id');
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}
