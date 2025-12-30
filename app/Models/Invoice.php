<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Invoice extends CompanyScopedModel
{
    use HasFactory;
    use SoftDeletes;

    protected $fillable = [
        'company_id',
        'purchase_order_id',
        'supplier_id',
        'supplier_company_id',
        'invoice_number',
        'invoice_date',
        'due_date',
        'currency',
        'subtotal',
        'tax_amount',
        'total',
        'subtotal_minor',
        'tax_minor',
        'total_minor',
        'status',
        'matched_status',
        'created_by_type',
        'created_by_id',
        'submitted_at',
        'reviewed_at',
        'reviewed_by_id',
        'review_note',
        'payment_reference',
        'document_id',
    ];

    protected $casts = [
        'company_id' => 'integer',
        'purchase_order_id' => 'integer',
        'supplier_id' => 'integer',
        'supplier_company_id' => 'integer',
        'invoice_date' => 'date',
        'due_date' => 'date',
        'subtotal' => 'decimal:2',
        'tax_amount' => 'decimal:2',
        'total' => 'decimal:2',
        'subtotal_minor' => 'integer',
        'tax_minor' => 'integer',
        'total_minor' => 'integer',
        'created_by_id' => 'integer',
        'submitted_at' => 'datetime',
        'reviewed_at' => 'datetime',
        'reviewed_by_id' => 'integer',
    ];

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    public function purchaseOrder(): BelongsTo
    {
        return $this->belongsTo(PurchaseOrder::class);
    }

    public function supplier(): BelongsTo
    {
        return $this->belongsTo(Supplier::class);
    }

    public function supplierCompany(): BelongsTo
    {
        return $this->belongsTo(Company::class, 'supplier_company_id');
    }

    public function document(): BelongsTo
    {
        return $this->belongsTo(Document::class);
    }

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by_id');
    }

    public function reviewedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reviewed_by_id');
    }

    public function attachments(): MorphMany
    {
        return $this->morphMany(Document::class, 'documentable')->latest('created_at');
    }

    public function invoiceAttachments(): HasMany
    {
        return $this->hasMany(InvoiceAttachment::class);
    }

    public function lines(): HasMany
    {
        return $this->hasMany(InvoiceLine::class);
    }

    public function matches(): HasMany
    {
        return $this->hasMany(InvoiceMatch::class);
    }

    public function disputeTasks(): HasMany
    {
        return $this->hasMany(InvoiceDisputeTask::class);
    }

    public function creditNotes(): HasMany
    {
        return $this->hasMany(CreditNote::class);
    }

    public function payments(): HasMany
    {
        return $this->hasMany(InvoicePayment::class)->latest('paid_at')->latest('id');
    }
}
