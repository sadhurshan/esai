<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class AiActionFeedback extends CompanyScopedModel
{
    use HasFactory;
    use SoftDeletes;

    protected $table = 'ai_action_feedback';

    /**
     * @var list<string>
     */
    protected $fillable = [
        'company_id',
        'ai_action_draft_id',
        'user_id',
        'rating',
        'comment',
        'metadata',
    ];

    /**
     * @var array<string, string>
     */
    protected $casts = [
        'metadata' => 'array',
    ];

    public function draft(): BelongsTo
    {
        return $this->belongsTo(AiActionDraft::class, 'ai_action_draft_id');
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
