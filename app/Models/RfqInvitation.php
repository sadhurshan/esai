<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class RfqInvitation extends Model
{
    use HasFactory;

    protected $fillable = [
        'rfq_id',
        'supplier_id',
        'invited_by',
        'status',
    ];

    public function rfq(): BelongsTo
    {
        return $this->belongsTo(RFQ::class);
    }

    public function supplier(): BelongsTo
    {
        return $this->belongsTo(Supplier::class);
    }

    public function inviter(): BelongsTo
    {
        return $this->belongsTo(User::class, 'invited_by');
    }
}
