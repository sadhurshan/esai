<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class SupplierMessageDraft extends CompanyScopedModel
{
    use HasFactory;
    use SoftDeletes;

    protected $fillable = [
        'company_id',
        'supplier_id',
        'created_by',
        'supplier_name',
        'goal',
        'tone',
        'subject',
        'message_body',
        'negotiation_points_json',
        'fallback_options_json',
        'status',
        'summary',
        'confidence',
        'needs_human_review',
        'warnings_json',
        'citations_json',
        'meta',
    ];

    protected $casts = [
        'negotiation_points_json' => 'array',
        'fallback_options_json' => 'array',
        'warnings_json' => 'array',
        'citations_json' => 'array',
        'meta' => 'array',
        'confidence' => 'float',
        'needs_human_review' => 'boolean',
    ];

    public function supplier(): BelongsTo
    {
        return $this->belongsTo(Supplier::class);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}
