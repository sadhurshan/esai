<?php

namespace App\Models;

use App\Models\Document;
use App\Models\QuoteRevision;
use App\Models\Company;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Quote extends CompanyScopedModel
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
        'notes',
        'status',
        'revision_no',
        'withdrawn_at',
        'withdraw_reason',
        'subtotal',
        'tax_amount',
        'total_price',
        'subtotal_minor',
        'tax_amount_minor',
        'total_price_minor',
        'submitted_at',
        'attachments_count',
    ];

    protected $casts = [
        'unit_price' => 'decimal:2',
        'min_order_qty' => 'integer',
        'lead_time_days' => 'integer',
        'revision_no' => 'integer',
        'withdrawn_at' => 'datetime',
        'subtotal' => 'decimal:2',
        'tax_amount' => 'decimal:2',
        'total_price' => 'decimal:2',
        'subtotal_minor' => 'integer',
        'tax_amount_minor' => 'integer',
        'total_price_minor' => 'integer',
        'submitted_at' => 'datetime',
        'attachments_count' => 'integer',
    ];

    protected $attributes = [
        'attachments_count' => 0,
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

    protected function note(): Attribute
    {
        return Attribute::make(
            get: fn ($value, array $attributes): ?string => $attributes['notes'] ?? null,
            set: fn (?string $value): array => ['notes' => $value],
        );
    }

    protected function total(): Attribute
    {
        return Attribute::make(
            get: fn ($value, array $attributes): ?string => $attributes['total_price'] ?? null,
            set: fn ($value): array => ['total_price' => $value],
        );
    }

    protected function totalMinor(): Attribute
    {
        return Attribute::make(
            get: fn ($value, array $attributes): ?int => $attributes['total_price_minor'] ?? null,
            set: fn ($value): array => ['total_price_minor' => $value],
        );
    }
}
