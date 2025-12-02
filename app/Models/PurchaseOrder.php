<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class PurchaseOrder extends CompanyScopedModel
{
    use HasFactory;
    use SoftDeletes;

    protected $fillable = [
        'company_id',
        'rfq_id',
        'quote_id',
        'supplier_id',
        'po_number',
        'currency',
        'incoterm',
        'tax_percent',
        'status',
        'revision_no',
        'ordered_at',
        'expected_at',
        'cancelled_at',
        'pdf_document_id',
        'subtotal',
        'tax_amount',
        'total',
        'subtotal_minor',
        'tax_amount_minor',
        'total_minor',
        'sent_at',
        'ack_status',
        'acknowledged_at',
        'ack_reason',
    ];

    protected $casts = [
        'tax_percent' => 'decimal:2',
        'revision_no' => 'integer',
        'subtotal' => 'decimal:2',
        'tax_amount' => 'decimal:2',
        'total' => 'decimal:2',
        'subtotal_minor' => 'integer',
        'tax_amount_minor' => 'integer',
        'total_minor' => 'integer',
        'cancelled_at' => 'datetime',
        'sent_at' => 'datetime',
        'acknowledged_at' => 'datetime',
    ];

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    public function rfq(): BelongsTo
    {
        return $this->belongsTo(RFQ::class);
    }

    public function quote(): BelongsTo
    {
        return $this->belongsTo(Quote::class);
    }

    public function supplier(): BelongsTo
    {
        return $this->belongsTo(Supplier::class);
    }

    public function pdfDocument(): BelongsTo
    {
        return $this->belongsTo(Document::class, 'pdf_document_id');
    }

    public function lines(): HasMany
    {
        return $this->hasMany(PurchaseOrderLine::class);
    }

    public function changeOrders(): HasMany
    {
        return $this->hasMany(PoChangeOrder::class);
    }

    public function goodsReceiptNotes(): HasMany
    {
        return $this->hasMany(GoodsReceiptNote::class);
    }

    public function invoices(): HasMany
    {
        return $this->hasMany(Invoice::class);
    }

    public function creditNotes(): HasMany
    {
        return $this->hasMany(CreditNote::class);
    }

    public function deliveries(): HasMany
    {
        return $this->hasMany(PurchaseOrderDelivery::class)->latest('created_at');
    }

    public function events(): HasMany
    {
        return $this->hasMany(PurchaseOrderEvent::class)->latest('occurred_at');
    }

    public function shipments(): HasMany
    {
        return $this->hasMany(PurchaseOrderShipment::class);
    }
}
