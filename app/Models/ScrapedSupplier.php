<?php

namespace App\Models;

use App\Enums\ScrapedSupplierStatus;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class ScrapedSupplier extends CompanyScopedModel
{
    use HasFactory;
    use SoftDeletes;

    protected $fillable = [
        'company_id',
        'scrape_job_id',
        'name',
        'website',
        'description',
        'industry_tags',
        'address',
        'city',
        'state',
        'country',
        'phone',
        'email',
        'contact_person',
        'certifications',
        'product_summary',
        'source_url',
        'confidence',
        'metadata_json',
        'status',
        'approved_supplier_id',
        'reviewed_by',
        'reviewed_at',
        'review_notes',
    ];

    protected $casts = [
        'industry_tags' => 'array',
        'certifications' => 'array',
        'metadata_json' => 'array',
        'confidence' => 'decimal:2',
        'status' => ScrapedSupplierStatus::class,
        'reviewed_at' => 'datetime',
    ];

    public function job(): BelongsTo
    {
        return $this->belongsTo(SupplierScrapeJob::class, 'scrape_job_id');
    }

    public function approvedSupplier(): BelongsTo
    {
        return $this->belongsTo(Supplier::class, 'approved_supplier_id');
    }

    public function reviewer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reviewed_by');
    }
}
