<?php

namespace App\Models;

use App\Enums\PrStatus;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class PurchaseRequisition extends CompanyScopedModel
{
    use HasFactory;
    use SoftDeletes;

    protected $fillable = [
        'company_id',
        'pr_number',
        'requested_by',
        'status',
        'currency',
        'needed_by',
        'notes',
        'approved_at',
        'rejected_at',
    ];

    protected $casts = [
        'status' => PrStatus::class,
        'needed_by' => 'date',
        'approved_at' => 'datetime',
        'rejected_at' => 'datetime',
    ];

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    public function requester(): BelongsTo
    {
        return $this->belongsTo(User::class, 'requested_by');
    }

    public function lines(): HasMany
    {
        return $this->hasMany(PurchaseRequisitionLine::class);
    }

    public function totalAmount(): float
    {
        return $this->lines->sum(static function (PurchaseRequisitionLine $line): float {
            $qty = (float) $line->qty;
            $unitPrice = $line->unit_price !== null ? (float) $line->unit_price : 0.0;

            return $qty * $unitPrice;
        });
    }
}
