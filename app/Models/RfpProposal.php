<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use App\Models\Document;

class RfpProposal extends CompanyScopedModel
{
    /** @use HasFactory<\Database\Factories\RfpProposalFactory> */
    use HasFactory;
    use SoftDeletes;

    protected $fillable = [
        'rfp_id',
        'company_id',
        'supplier_company_id',
        'submitted_by',
        'status',
        'price_total',
        'price_total_minor',
        'currency',
        'lead_time_days',
        'approach_summary',
        'schedule_summary',
        'value_add_summary',
        'attachments_count',
        'meta',
    ];

    protected $casts = [
        'price_total' => 'decimal:2',
        'price_total_minor' => 'integer',
        'lead_time_days' => 'integer',
        'attachments_count' => 'integer',
        'meta' => 'array',
    ];

    protected $attributes = [
        'status' => 'submitted',
        'attachments_count' => 0,
    ];

    public function rfp(): BelongsTo
    {
        return $this->belongsTo(Rfp::class);
    }

    public function buyerCompany(): BelongsTo
    {
        return $this->belongsTo(Company::class, 'company_id');
    }

    public function supplierCompany(): BelongsTo
    {
        return $this->belongsTo(Company::class, 'supplier_company_id');
    }

    public function submitter(): BelongsTo
    {
        return $this->belongsTo(User::class, 'submitted_by');
    }

    public function documents(): MorphMany
    {
        return $this->morphMany(Document::class, 'documentable');
    }
}
