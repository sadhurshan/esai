<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CreditNoteLine extends Model
{
    use HasFactory;

    protected $fillable = [
        'credit_note_id',
        'invoice_line_id',
        'qty_to_credit',
        'qty_invoiced',
        'unit_price_minor',
        'line_total_minor',
        'currency',
        'uom',
        'description',
    ];

    protected $casts = [
        'credit_note_id' => 'integer',
        'invoice_line_id' => 'integer',
        'qty_to_credit' => 'decimal:3',
        'qty_invoiced' => 'decimal:3',
        'unit_price_minor' => 'integer',
        'line_total_minor' => 'integer',
    ];

    public function creditNote(): BelongsTo
    {
        return $this->belongsTo(CreditNote::class);
    }

    public function invoiceLine(): BelongsTo
    {
        return $this->belongsTo(InvoiceLine::class);
    }
}
