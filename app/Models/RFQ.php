<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class RFQ extends CompanyScopedModel
{
    public const STATUS_DRAFT = 'draft';
    public const STATUS_OPEN = 'open';
    public const STATUS_CLOSED = 'closed';
    public const STATUS_AWARDED = 'awarded';
    public const STATUS_CANCELLED = 'cancelled';

    public const STATUSES = [
        self::STATUS_DRAFT,
        self::STATUS_OPEN,
        self::STATUS_CLOSED,
        self::STATUS_AWARDED,
        self::STATUS_CANCELLED,
    ];

    public const METHODS = [
        'cnc',
        'sheet_metal',
        'injection_molding',
        '3d_printing',
        'casting',
        'other',
    ];

    /** @use HasFactory<\Database\Factories\RFQFactory> */
    use HasFactory;
    use SoftDeletes;

    protected $table = 'rfqs';

    /**
     * @var list<string>
     */
    protected $fillable = [
        'company_id',
        'created_by',
        'number',
        'title',
        'method',
        'material',
        'tolerance',
        'finish',
        'quantity_total',
        'delivery_location',
        'incoterm',
        'currency',
        'notes',
        'open_bidding',
        'status',
        'publish_at',
        'due_at',
        'close_at',
        'rfq_version',
        'attachments_count',
        'meta',
        'is_partially_awarded',
        'current_revision_id',
        'cad_document_id',
        'item_name',
        'type',
        'quantity',
        'client_company',
        'deadline_at',
        'sent_at',
        'is_open_bidding',
    ];

    /**
     * @var array<string, string>
     */
    protected $casts = [
        'open_bidding' => 'boolean',
        'publish_at' => 'datetime',
        'due_at' => 'datetime',
        'close_at' => 'datetime',
        'is_partially_awarded' => 'boolean',
        'rfq_version' => 'integer',
        'attachments_count' => 'integer',
        'quantity_total' => 'integer',
        'current_revision_id' => 'integer',
        'cad_document_id' => 'integer',
        'meta' => 'array',
    ];

    /**
     * @var array<string, mixed>
     */
    protected $attributes = [
        'status' => 'draft',
        'rfq_version' => 1,
        'attachments_count' => 0,
    ];

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function items(): HasMany
    {
        return $this->hasMany(RfqItem::class, 'rfq_id');
    }

    public function awards(): HasMany
    {
        return $this->hasMany(RfqItemAward::class, 'rfq_id');
    }

    public function invitations(): HasMany
    {
        return $this->hasMany(RfqInvitation::class, 'rfq_id');
    }

    public function clarifications(): HasMany
    {
        return $this->hasMany(RfqClarification::class, 'rfq_id')->orderBy('created_at');
    }

    public function deadlineExtensions(): HasMany
    {
        return $this->hasMany(RfqDeadlineExtension::class, 'rfq_id');
    }

    public function incrementVersion(?int $revisionId = null): void
    {
        $nextVersion = ($this->rfq_version ?? 1) + 1;

        $this->rfq_version = $nextVersion;
        $this->current_revision_id = $revisionId;
        $this->save();
    }

    public function quotes(): HasMany
    {
        return $this->hasMany(Quote::class, 'rfq_id');
    }

    public function purchaseOrders(): HasMany
    {
        return $this->hasMany(PurchaseOrder::class, 'rfq_id');
    }

    public function cadDocument(): BelongsTo
    {
        return $this->belongsTo(Document::class, 'cad_document_id');
    }

    public function documents(): MorphMany
    {
        return $this->morphMany(Document::class, 'documentable');
    }

    public function isPartiallyAwarded(): bool
    {
        return (bool) ($this->is_partially_awarded ?? false);
    }

    public function getItemNameAttribute(): ?string
    {
        return $this->title;
    }

    public function setItemNameAttribute(?string $value): void
    {
        $this->attributes['title'] = $value;
    }

    public function getQuantityAttribute(): ?int
    {
        return $this->quantity_total;
    }

    public function setQuantityAttribute($value): void
    {
        $this->attributes['quantity_total'] = $value === null ? 0 : (int) $value;
    }

    public function getTypeAttribute(): ?string
    {
        return $this->method;
    }

    public function setTypeAttribute(?string $value): void
    {
        $this->attributes['method'] = $value;
    }

    public function getClientCompanyAttribute(): ?string
    {
        return $this->delivery_location;
    }

    public function setClientCompanyAttribute(?string $value): void
    {
        $this->attributes['delivery_location'] = $value;
    }

    public function getDeadlineAtAttribute()
    {
        return $this->due_at;
    }

    public function setDeadlineAtAttribute($value): void
    {
        $this->due_at = $value;
    }

    public function getSentAtAttribute()
    {
        return $this->publish_at;
    }

    public function setSentAtAttribute($value): void
    {
        $this->publish_at = $value;
    }

    public function getIsOpenBiddingAttribute(): bool
    {
        return (bool) ($this->open_bidding ?? false);
    }

    public function setIsOpenBiddingAttribute($value): void
    {
        $this->attributes['open_bidding'] = (bool) $value;
    }
}
