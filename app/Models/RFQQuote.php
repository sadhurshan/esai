<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * @property int $id
 * @property int $rfq_id
 * @property int $supplier_id
 * @property string $via
 * @property string|null $note
 * @property string|null $attachment_path
 * @property string $unit_price_usd
 * @property int $lead_time_days
 * @property \Illuminate\Support\Carbon $submitted_at
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @property-read \App\Models\RFQ $rfq
 * @property-read \App\Models\Supplier $supplier
 */
class RFQQuote extends CompanyScopedModel
{
    /** @use HasFactory<\Database\Factories\RFQQuoteFactory> */
    use HasFactory;
    use SoftDeletes;

    /**
     * @var string
     */
    protected $table = 'rfq_quotes';

    /**
     * @var list<string>
     */
    protected $fillable = [
        'company_id',
        'rfq_id',
        'supplier_id',
        'unit_price_usd',
        'lead_time_days',
        'note',
        'attachment_path',
        'via',
        'submitted_at',
    ];

    /**
     * @var array<string, string>
     */
    protected $casts = [
        'unit_price_usd' => 'decimal:2',
        'lead_time_days' => 'integer',
        'submitted_at' => 'datetime',
    ];

    public function rfq(): BelongsTo
    {
        return $this->belongsTo(RFQ::class);
    }

    public function supplier(): BelongsTo
    {
        return $this->belongsTo(Supplier::class);
    }

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    public function documents(): MorphMany
    {
        return $this->morphMany(Document::class, 'documentable');
    }
}
