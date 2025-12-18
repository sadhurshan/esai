<?php

namespace App\Models;

use App\Enums\RmaStatus;
use App\Models\CreditNote;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Rma extends CompanyScopedModel
{
    use HasFactory;
    use SoftDeletes;

    protected $fillable = [
        'company_id',
        'purchase_order_id',
        'purchase_order_line_id',
        'grn_id',
        'submitted_by',
        'reason',
        'description',
        'resolution_requested',
        'defect_qty',
        'status',
        'reviewed_by',
        'review_outcome',
        'review_comment',
        'reviewed_at',
        'credit_note_id',
    ];

    protected $casts = [
        'status' => RmaStatus::class,
        'reviewed_at' => 'datetime',
    ];

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    public function purchaseOrder(): BelongsTo
    {
        return $this->belongsTo(PurchaseOrder::class);
    }

    public function purchaseOrderLine(): BelongsTo
    {
        return $this->belongsTo(PurchaseOrderLine::class);
    }

    public function goodsReceiptNote(): BelongsTo
    {
        return $this->belongsTo(GoodsReceiptNote::class, 'grn_id');
    }

    public function submitter(): BelongsTo
    {
        return $this->belongsTo(User::class, 'submitted_by');
    }

    public function reviewer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reviewed_by');
    }

    public function creditNote(): BelongsTo
    {
        return $this->belongsTo(CreditNote::class);
    }

    public function rmaDocuments(): HasMany
    {
        return $this->hasMany(RmaDocument::class);
    }

    public function documents(): BelongsToMany
    {
        return $this->belongsToMany(Document::class, 'rma_documents')
            ->withTimestamps();
    }

    public function isReviewable(): bool
    {
        return in_array($this->status, [RmaStatus::Raised, RmaStatus::UnderReview], true);
    }
}
