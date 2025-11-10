<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class MaintenanceProcedure extends Model
{
    use HasFactory;
    use SoftDeletes;

    protected $fillable = [
        'company_id',
        'code',
        'title',
        'category',
        'estimated_minutes',
        'instructions_md',
        'tools_json',
        'safety_json',
        'meta',
    ];

    protected $casts = [
        'estimated_minutes' => 'integer',
        'tools_json' => 'array',
        'safety_json' => 'array',
        'meta' => 'array',
    ];

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    public function steps(): HasMany
    {
        return $this->hasMany(ProcedureStep::class)->orderBy('step_no');
    }

    public function assets(): BelongsToMany
    {
        return $this->belongsToMany(Asset::class, 'asset_procedure_links')
            ->withPivot(['frequency_value', 'frequency_unit', 'last_done_at', 'next_due_at', 'meta'])
            ->withTimestamps();
    }
}
