<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
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
    ];

    protected $casts = [
        'unit_price' => 'decimal:2',
        'min_order_qty' => 'integer',
        'lead_time_days' => 'integer',
        'revision_no' => 'integer',
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
}
