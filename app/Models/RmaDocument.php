<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class RmaDocument extends Model
{
    use HasFactory;

    protected $table = 'rma_documents';

    protected $fillable = [
        'rma_id',
        'document_id',
    ];

    public function rma(): BelongsTo
    {
        return $this->belongsTo(Rma::class);
    }

    public function document(): BelongsTo
    {
        return $this->belongsTo(Document::class);
    }
}
