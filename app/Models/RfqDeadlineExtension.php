<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class RfqDeadlineExtension extends CompanyScopedModel
{
    use HasFactory;
    use SoftDeletes;

    protected $table = 'rfq_deadline_extensions';

    protected $fillable = [
        'company_id',
        'rfq_id',
        'previous_due_at',
        'new_due_at',
        'reason',
        'extended_by',
    ];

    protected $casts = [
        'previous_due_at' => 'datetime',
        'new_due_at' => 'datetime',
    ];

    public function rfq(): BelongsTo
    {
        return $this->belongsTo(RFQ::class, 'rfq_id');
    }

    public function extendedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'extended_by');
    }
}
