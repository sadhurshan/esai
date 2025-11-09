<?php

namespace App\Models;

use App\Models\Company;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class SupplierDocument extends Model
{
    use HasFactory;
    use SoftDeletes;

    protected $fillable = [
        'supplier_id',
        'company_id',
        'type',
        'path',
        'mime',
        'size_bytes',
        'issued_at',
        'expires_at',
        'status',
    ];

    protected $casts = [
        'size_bytes' => 'integer',
        'issued_at' => 'date',
        'expires_at' => 'date',
    ];

    public function supplier(): BelongsTo
    {
        return $this->belongsTo(Supplier::class);
    }

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }
}
