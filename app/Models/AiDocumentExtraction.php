<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Carbon;

class AiDocumentExtraction extends CompanyScopedModel
{
    use HasFactory;
    use SoftDeletes;

    protected $table = 'ai_document_extractions';

    protected $fillable = [
        'company_id',
        'document_id',
        'document_version',
        'source_type',
        'filename',
        'mime_type',
        'status',
        'extracted_json',
        'gdt_flags_json',
        'similar_parts_json',
        'extracted_at',
        'last_error',
        'last_error_at',
    ];

    protected $casts = [
        'document_version' => 'integer',
        'extracted_json' => 'array',
        'gdt_flags_json' => 'array',
        'similar_parts_json' => 'array',
        'extracted_at' => 'datetime',
        'last_error_at' => 'datetime',
    ];

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    public function document(): BelongsTo
    {
        return $this->belongsTo(Document::class);
    }

    /**
     * @param array<string, mixed> $extracted
     * @param array<string, mixed> $gdtFlags
     * @param array<int, array<string, mixed>> $similarParts
     */
    public function markCompleted(array $extracted, array $gdtFlags, array $similarParts): void
    {
        $this->fill([
            'status' => 'completed',
            'extracted_json' => $extracted,
            'gdt_flags_json' => $gdtFlags,
            'similar_parts_json' => $similarParts,
            'extracted_at' => Carbon::now(),
            'last_error' => null,
            'last_error_at' => null,
        ])->save();
    }

    public function markFailure(string $message): void
    {
        $this->fill([
            'status' => 'error',
            'last_error' => $message,
            'last_error_at' => Carbon::now(),
        ])->save();
    }
}
