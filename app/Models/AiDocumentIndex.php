<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Carbon;

class AiDocumentIndex extends CompanyScopedModel
{
    use HasFactory;
    use SoftDeletes;

    protected $table = 'ai_document_indexes';

    protected $fillable = [
        'company_id',
        'doc_id',
        'doc_version',
        'source_type',
        'title',
        'mime_type',
        'indexed_at',
        'indexed_chunks',
        'last_error',
        'last_error_at',
    ];

    protected $casts = [
        'indexed_at' => 'datetime',
        'last_error_at' => 'datetime',
    ];

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    public function document(): BelongsTo
    {
        return $this->belongsTo(Document::class, 'doc_id');
    }

    public function markSuccess(int $chunkCount): void
    {
        $this->fill([
            'indexed_at' => Carbon::now(),
            'indexed_chunks' => $chunkCount,
            'last_error' => null,
            'last_error_at' => null,
        ])->save();
    }

    public function markFailure(string $message): void
    {
        $this->fill([
            'last_error' => $message,
            'last_error_at' => Carbon::now(),
        ])->save();
    }
}
