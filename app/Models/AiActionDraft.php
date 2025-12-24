<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class AiActionDraft extends CompanyScopedModel
{
    use HasFactory;
    use SoftDeletes;

    public const TYPE_RFQ_DRAFT = 'rfq_draft';
    public const TYPE_SUPPLIER_MESSAGE = 'supplier_message';
    public const TYPE_MAINTENANCE_CHECKLIST = 'maintenance_checklist';
    public const TYPE_INVENTORY_WHATIF = 'inventory_whatif';
    public const TYPE_INVOICE_DRAFT = 'invoice_draft';
    public const TYPE_APPROVE_INVOICE = 'approve_invoice';

    /**
     * @var list<string>
     */
    public const ACTION_TYPES = [
        self::TYPE_RFQ_DRAFT,
        self::TYPE_SUPPLIER_MESSAGE,
        self::TYPE_MAINTENANCE_CHECKLIST,
        self::TYPE_INVENTORY_WHATIF,
        self::TYPE_INVOICE_DRAFT,
        self::TYPE_APPROVE_INVOICE,
    ];

    public const STATUS_DRAFTED = 'drafted';
    public const STATUS_APPROVED = 'approved';
    public const STATUS_REJECTED = 'rejected';
    public const STATUS_EXPIRED = 'expired';

    /**
     * @var list<string>
     */
    public const STATUSES = [
        self::STATUS_DRAFTED,
        self::STATUS_APPROVED,
        self::STATUS_REJECTED,
        self::STATUS_EXPIRED,
    ];

    protected $table = 'ai_action_drafts';

    /**
     * @var list<string>
     */
    protected $fillable = [
        'company_id',
        'user_id',
        'action_type',
        'input_json',
        'output_json',
        'citations_json',
        'status',
        'approved_by',
        'approved_at',
        'rejected_reason',
        'entity_type',
        'entity_id',
    ];

    /**
     * @var array<string, string>
     */
    protected $casts = [
        'input_json' => 'array',
        'output_json' => 'array',
        'citations_json' => 'array',
        'approved_at' => 'datetime',
    ];

    /**
     * @var array<string, mixed>
     */
    protected $attributes = [
        'status' => self::STATUS_DRAFTED,
    ];

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function approver(): BelongsTo
    {
        return $this->belongsTo(User::class, 'approved_by');
    }

    public function isDrafted(): bool
    {
        return $this->status === self::STATUS_DRAFTED;
    }

    public function isApproved(): bool
    {
        return $this->status === self::STATUS_APPROVED;
    }

    public function isRejected(): bool
    {
        return $this->status === self::STATUS_REJECTED;
    }
}
