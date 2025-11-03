<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class RFQ extends Model
{
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
        'item_name',
        'type',
        'quantity',
        'material',
        'method',
        'tolerance',
        'finish',
        'tolerance_finish',
        'incoterm',
        'currency',
        'is_open_bidding',
        'open_bidding',
        'publish_at',
        'due_at',
        'close_at',
        'deadline_at',
        'sent_at',
        'status',
        'version',
        'notes',
        'cad_path',
        'client_company',
    ];

    /**
     * @var array<string, string>
     */
    protected $casts = [
        'open_bidding' => 'boolean',
        'is_open_bidding' => 'boolean',
        'publish_at' => 'datetime',
        'due_at' => 'datetime',
        'close_at' => 'datetime',
        'deadline_at' => 'datetime',
        'sent_at' => 'datetime',
        'version' => 'integer',
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

    public function invitations(): HasMany
    {
        return $this->hasMany(RfqInvitation::class, 'rfq_id');
    }

    public function clarifications(): HasMany
    {
        return $this->hasMany(RfqClarification::class, 'rfq_id');
    }

    public function quotes(): HasMany
    {
        return $this->hasMany(Quote::class, 'rfq_id');
    }

    public function purchaseOrders(): HasMany
    {
        return $this->hasMany(PurchaseOrder::class, 'rfq_id');
    }
}
