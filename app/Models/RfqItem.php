<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class RfqItem extends CompanyScopedModel
{
    use HasFactory;
    use SoftDeletes;

    protected $fillable = [
        'rfq_id',
        'company_id',
        'created_by',
        'updated_by',
        'line_no',
        'part_number',
        'description',
        'part_name',
        'spec',
        'qty',
        'method',
        'material',
        'tolerance',
        'finish',
        'quantity',
        'uom',
        'target_price',
        'currency',
        'target_price_minor',
        'cad_doc_id',
        'specs_json',
        'meta',
    ];

    protected $casts = [
        'quantity' => 'integer',
        'qty' => 'integer',
        'target_price' => 'decimal:2',
        'target_price_minor' => 'integer',
        'specs_json' => 'array',
        'meta' => 'array',
    ];

    protected static function booted(): void
    {
        static::creating(function (self $item): void {
            $item->syncTenantMeta();

            if ($item->updated_by === null && $item->created_by !== null) {
                $item->updated_by = $item->created_by;
            }
        });
    }

    private function syncTenantMeta(): void
    {
        if ($this->company_id !== null && $this->created_by !== null) {
            return;
        }

        $rfq = $this->relationLoaded('rfq')
            ? $this->rfq
            : ($this->rfq_id ? RFQ::query()->select(['id', 'company_id', 'created_by'])->find($this->rfq_id) : null);

        if (! $rfq) {
            return;
        }

        $this->company_id ??= $rfq->company_id;
        $this->created_by ??= $rfq->created_by;
    }

    public function rfq(): BelongsTo
    {
        return $this->belongsTo(RFQ::class);
    }

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function updater(): BelongsTo
    {
        return $this->belongsTo(User::class, 'updated_by');
    }

    public function cadDocument(): BelongsTo
    {
        return $this->belongsTo(Document::class, 'cad_doc_id');
    }

    public function awards(): HasMany
    {
        return $this->hasMany(RfqItemAward::class);
    }

    public function getPartNameAttribute(): ?string
    {
        return $this->part_number ?? $this->attributes['part_name'] ?? null;
    }

    public function setPartNameAttribute(?string $value): void
    {
        $this->attributes['part_number'] = $value;
        $this->attributes['part_name'] = $value;
    }

    public function setPartNumberAttribute(?string $value): void
    {
        $this->attributes['part_number'] = $value;
        $this->attributes['part_name'] = $value;
    }

    public function getSpecAttribute(): ?string
    {
        return $this->description ?? $this->attributes['spec'] ?? null;
    }

    public function setSpecAttribute(?string $value): void
    {
        $this->attributes['description'] = $value;
        $this->attributes['spec'] = $value;
    }

    public function setDescriptionAttribute(?string $value): void
    {
        $this->attributes['description'] = $value;
        $this->attributes['spec'] = $value;
    }

    public function getQuantityAttribute(): ?int
    {
        return $this->qty ?? $this->attributes['quantity'] ?? null;
    }

    public function setQuantityAttribute($value): void
    {
        $this->attributes['qty'] = $value === null ? null : (int) $value;
        $this->attributes['quantity'] = $value === null ? null : (int) $value;
    }

    public function setQtyAttribute($value): void
    {
        $intValue = $value === null ? null : (int) $value;
        $this->attributes['qty'] = $intValue;
        $this->attributes['quantity'] = $intValue;
    }
}
