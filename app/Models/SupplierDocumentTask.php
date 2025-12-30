<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class SupplierDocumentTask extends CompanyScopedModel
{
    use HasFactory;
    use SoftDeletes;

    public const STATUS_PENDING = 'pending';
    public const STATUS_FULFILLED = 'fulfilled';
    public const STATUS_WAIVED = 'waived';

    /**
     * @var list<string>
     */
    public const STATUSES = [
        self::STATUS_PENDING,
        self::STATUS_FULFILLED,
        self::STATUS_WAIVED,
    ];

    protected $fillable = [
        'company_id',
        'supplier_id',
        'supplier_document_id',
        'requested_by',
        'completed_by',
        'document_type',
        'status',
        'is_required',
        'priority',
        'due_at',
        'description',
        'notes',
        'completed_at',
    ];

    protected $casts = [
        'is_required' => 'boolean',
        'priority' => 'integer',
        'due_at' => 'datetime',
        'completed_at' => 'datetime',
    ];

    public function supplier(): BelongsTo
    {
        return $this->belongsTo(Supplier::class);
    }

    public function document(): BelongsTo
    {
        return $this->belongsTo(SupplierDocument::class, 'supplier_document_id');
    }

    public function requestedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'requested_by');
    }

    public function completedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'completed_by');
    }
}
