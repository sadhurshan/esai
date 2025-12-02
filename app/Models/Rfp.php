<?php

namespace App\Models;

use App\Enums\RfpStatus;
use App\Models\RfpProposal;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * @property int $id
 * @property int $company_id
 * @property string $title
 * @property RfpStatus $status
 */
class Rfp extends CompanyScopedModel
{
    /** @use HasFactory<\Database\Factories\RfpFactory> */
    use HasFactory;
    use SoftDeletes;

    protected $table = 'rfps';

    /**
     * @var list<string>
     */
    protected $fillable = [
        'company_id',
        'created_by',
        'updated_by',
        'title',
        'status',
        'problem_objectives',
        'scope',
        'timeline',
        'evaluation_criteria',
        'proposal_format',
        'ai_assist_enabled',
        'ai_suggestions',
        'published_at',
        'in_review_at',
        'awarded_at',
        'closed_at',
        'meta',
    ];

    /**
     * @var array<string, string>
     */
    protected $casts = [
        'status' => RfpStatus::class,
        'ai_assist_enabled' => 'boolean',
        'ai_suggestions' => 'array',
        'published_at' => 'datetime',
        'in_review_at' => 'datetime',
        'awarded_at' => 'datetime',
        'closed_at' => 'datetime',
        'meta' => 'array',
    ];

    /**
     * @var array<string, mixed>
     */
    protected $attributes = [
        'status' => RfpStatus::Draft->value,
        'ai_assist_enabled' => false,
    ];

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function updater(): BelongsTo
    {
        return $this->belongsTo(User::class, 'updated_by');
    }

    public function proposals(): HasMany
    {
        return $this->hasMany(RfpProposal::class);
    }
}
