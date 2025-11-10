<?php

namespace App\Models;

use App\Models\Document;
use App\Models\QuoteRevision;
use App\Models\Company;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Quote extends Model
{
    use HasFactory;
    use SoftDeletes;

    protected $fillable = [
        'company_id',
        'rfq_id',
        'supplier_id',
        'submitted_by',
        'currency',
        'unit_price',
        'min_order_qty',
        'lead_time_days',
        'note',
        'status',
        'revision_no',
        'withdrawn_at',
        'withdraw_reason',
        'subtotal',
        'tax_amount',
        'total',
        'subtotal_minor',
        'tax_amount_minor',
        'total_minor',
    ];

    protected $casts = [
        'unit_price' => 'decimal:2',
        'min_order_qty' => 'integer',
        'lead_time_days' => 'integer',
        'revision_no' => 'integer',
        'withdrawn_at' => 'datetime',
        'subtotal' => 'decimal:2',
        'tax_amount' => 'decimal:2',
        'total' => 'decimal:2',
        'subtotal_minor' => 'integer',
        'tax_amount_minor' => 'integer',
        'total_minor' => 'integer',
    ];

    public function rfq(): BelongsTo
    {
        return $this->belongsTo(RFQ::class);
    }

    public function supplier(): BelongsTo
    {
        return $this->belongsTo(Supplier::class);
    }

    public function submitter(): BelongsTo
    {
        return $this->belongsTo(User::class, 'submitted_by');
    }

    public function items(): HasMany
    {
        return $this->hasMany(QuoteItem::class);
    }

    public function documents(): MorphMany
    {
        return $this->morphMany(Document::class, 'documentable');
    }

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    public function revisions(): HasMany
    {
        return $this->hasMany(QuoteRevision::class)->orderBy('revision_no');
    }
}
