<?php

namespace App\Models;

use App\Enums\SupplierApplicationStatus;
use App\Models\SupplierDocument;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class SupplierApplication extends CompanyScopedModel
{
    use HasFactory;

    protected $fillable = [
        'company_id',
        'submitted_by',
        'status',
        'form_json',
        'reviewed_by',
        'reviewed_at',
        'notes',
    ];

    protected $casts = [
        'status' => SupplierApplicationStatus::class,
        'form_json' => 'array',
        'reviewed_at' => 'datetime',
    ];

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    public function submittedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'submitted_by');
    }

    public function reviewedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reviewed_by');
    }

    public function documents(): BelongsToMany
    {
        return $this->belongsToMany(SupplierDocument::class, 'supplier_application_documents');
    }
}
