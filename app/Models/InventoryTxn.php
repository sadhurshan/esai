<?php

namespace App\Models;

use App\Enums\InventoryTxnType;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class InventoryTxn extends Model
{
    /** @use HasFactory<\Database\Factories\InventoryTxnFactory> */
    use HasFactory;

    protected $fillable = [
        'company_id',
        'part_id',
        'warehouse_id',
        'bin_id',
        'type',
        'qty',
        'uom',
        'ref_type',
        'ref_id',
        'note',
        'performed_by',
    ];

    protected $casts = [
        'qty' => 'decimal:3',
        'type' => InventoryTxnType::class,
    ];

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    public function part(): BelongsTo
    {
        return $this->belongsTo(Part::class);
    }

    public function warehouse(): BelongsTo
    {
        return $this->belongsTo(Warehouse::class);
    }

    public function bin(): BelongsTo
    {
        return $this->belongsTo(Bin::class);
    }

    public function performer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'performed_by');
    }
}
