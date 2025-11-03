<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class RfqClarification extends Model
{
    use HasFactory;

    protected $fillable = [
        'rfq_id',
        'user_id',
        'kind',
        'message',
        'attachment_id',
        'rfq_version',
    ];

    public function rfq(): BelongsTo
    {
        return $this->belongsTo(RFQ::class);
    }

    public function author(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function attachment(): BelongsTo
    {
        return $this->belongsTo(Document::class, 'attachment_id');
    }
}
