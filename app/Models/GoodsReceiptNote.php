<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Relations\MorphMany;

class GoodsReceiptNote extends CompanyScopedModel
{
    use HasFactory;
    use SoftDeletes;

    protected $fillable = [
        'company_id',
        'purchase_order_id',
        'number',
        'inspected_by_id',
        'inspected_at',
        'status',
        'reference',
        'notes',
    ];

    protected $casts = [
        'company_id' => 'integer',
        'purchase_order_id' => 'integer',
        'inspected_by_id' => 'integer',
        'inspected_at' => 'datetime',
        'reference' => 'string',
        'notes' => 'string',
    ];

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    public function purchaseOrder(): BelongsTo
    {
        return $this->belongsTo(PurchaseOrder::class);
    }

    public function inspector(): BelongsTo
    {
        return $this->belongsTo(User::class, 'inspected_by_id');
    }

    public function lines(): HasMany
    {
        return $this->hasMany(GoodsReceiptLine::class);
    }

    public function attachments(): MorphMany
    {
        return $this->morphMany(Document::class, 'documentable');
    }

    public function ncrs(): HasMany
    {
        return $this->hasMany(Ncr::class, 'goods_receipt_note_id');
    }
}
