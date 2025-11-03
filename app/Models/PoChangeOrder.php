<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class PoChangeOrder extends Model
{
    use HasFactory;
    use SoftDeletes;

    protected $fillable = [
        'purchase_order_id',
        'proposed_by_user_id',
        'changes_json',
        'reason',
        'status',
        'po_revision_no',
    ];

    protected $casts = [
        'changes_json' => 'array',
        'po_revision_no' => 'integer',
    ];

    public function purchaseOrder(): BelongsTo
    {
        return $this->belongsTo(PurchaseOrder::class);
    }

    public function proposedByUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'proposed_by_user_id');
    }
}
